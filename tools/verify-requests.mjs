import assert from 'node:assert/strict';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { chromium } from 'playwright-core';
import { testAdmin } from './test-admin.mjs';

const baseUrl = process.env.PUNTOD_BASE_URL || 'http://127.0.0.1/PuntodCare/public/';
const chromePath = process.env.PUNTOD_CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const adminAccount = testAdmin();
const suffix = Date.now().toString(36);
const cemeteryName = `Puntod Test ${suffix}`;
const familyEmail = `request-family-${suffix}@example.test`;
const otherEmail = `request-other-${suffix}@example.test`;
const caretakerEmail = `request-caretaker-${suffix}@example.test`;
const password = 'Request-pilot-test-2026';
const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/9WQAAAAASUVORK5CYII=', 'base64');
const browser = await chromium.launch({ executablePath: chromePath, headless: true });
const errors = [];

async function makePage(width = 1440) {
  const context = await browser.newContext({ viewport: { width, height: 900 }, isMobile: width < 600 });
  const page = await context.newPage();
  page.on('pageerror', (error) => errors.push(error.message));
  return { context, page };
}

async function register(page, type, email, name) {
  await page.goto(`${baseUrl}?page=${type}`);
  for (const [field, value] of Object.entries({ full_name: name, email, password, password_confirmation: password })) await page.locator(`#${field}`).fill(value);
  if (type === 'enroll') {
    await page.locator('#phone').fill('09171234567');
    await page.locator('#service_area').fill('Cagayan de Oro City');
  }
  await page.getByRole('button', { name: type === 'enroll' ? /submit application/i : /create family account/i }).click();
  await page.waitForURL(/page=overview/);
}

async function csrfPost(page, form) {
  const csrf = await page.locator('input[name="csrf_token"]').first().getAttribute('value');
  return page.request.post(`${baseUrl}?page=request`, { form: { csrf_token: csrf, ...form } });
}

async function evidencePost(page, requestId, stage, file = png) {
  const csrf = await page.locator('input[name="csrf_token"]').first().getAttribute('value');
  return page.request.post(`${baseUrl}?page=request&id=${requestId}`, { multipart: {
    csrf_token: csrf, action: 'upload_request_evidence', request_id: requestId, stage,
    caption: `${stage} photo from test`, photo: { name: `${stage}.png`, mimeType: 'image/png', buffer: file },
  } });
}

try {
  const admin = await makePage();
  await admin.page.goto(`${baseUrl}?page=login`);
  await admin.page.locator('#email').fill(process.env.PUNTOD_ADMIN_EMAIL || adminAccount.email);
  await admin.page.locator('#password').fill(process.env.PUNTOD_ADMIN_PASSWORD || adminAccount.password);
  await admin.page.getByRole('button', { name: 'Sign in' }).click();
  await admin.page.waitForURL(/page=overview/);
  await admin.page.goto(`${baseUrl}?page=cemeteries`);
  await admin.page.locator('#cemetery-name').fill(cemeteryName);
  await admin.page.locator('#cemetery-city').fill('Cagayan de Oro City');
  await admin.page.locator('#cemetery-province').fill('Misamis Oriental');
  await admin.page.getByRole('button', { name: /add cemetery/i }).click();
  await admin.page.waitForURL(/page=cemeteries/);
  await admin.page.goto(`${baseUrl}?page=services`);
  const cemeteryId = await admin.page.locator('#service-cemetery option').filter({ hasText: cemeteryName }).getAttribute('value');
  await admin.page.locator('#service-cemetery').selectOption(cemeteryId);
  await admin.page.locator('#service-name').fill('Pilot grave cleaning');
  await admin.page.locator('#service-description').fill('Clean and tidy the grave area.');
  await admin.page.locator('#service-price').fill('500.00');
  await admin.page.getByRole('button', { name: /add service/i }).click();
  await admin.page.waitForURL(/page=services/);

  const caretaker = await makePage(390);
  await register(caretaker.page, 'enroll', caretakerEmail, 'Request Test Caretaker');
  const family = await makePage(390);
  await register(family.page, 'register', familyEmail, 'Request Test Family');
  const other = await makePage();
  await register(other.page, 'register', otherEmail, 'Another Request Family');

  await family.page.goto(`${baseUrl}?page=grave-form`);
  await family.page.locator('#grave-cemetery').selectOption(cemeteryId);
  await family.page.locator('#grave-name').fill('Maria Request Test');
  await family.page.locator('#grave-headstone').fill('Maria R. Test');
  await family.page.locator('#grave-section').fill('Garden A');
  await family.page.locator('#grave-lot').fill('Lot 12');
  await family.page.getByRole('button', { name: /register grave/i }).click();
  await family.page.waitForURL(/page=grave&id=/);
  const graveId = new URL(family.page.url()).searchParams.get('id');
  await family.page.locator('#grave-photo-file').setInputFiles({ name: 'headstone.png', mimeType: 'image/png', buffer: png });
  await family.page.getByRole('button', { name: /upload photo/i }).click();
  await family.page.waitForURL(/page=grave&id=/);
  const photoUrl = new URL(await family.page.locator('.grave-photo img').getAttribute('src'), baseUrl).href;
  assert.equal((await caretaker.page.request.get(photoUrl)).status(), 404);
  assert.equal((await admin.page.request.get(photoUrl)).status(), 404);
  await family.page.getByRole('link', { name: /request care/i }).click();
  assert.match(await family.page.locator('[data-request-price]').textContent(), /Choose a service/);
  let serviceId = await family.page.locator('#request-service option').filter({ hasText: 'Pilot grave cleaning' }).getAttribute('value');
  await family.page.locator('#request-service').selectOption(serviceId);
  assert.match(await family.page.locator('[data-request-price]').textContent(), /₱500\.00/);
  await family.page.screenshot({ path: path.resolve('storage/request-form-mobile.png'), fullPage: true });
  const tomorrow = new Date(Date.now() + 86400000).toISOString().slice(0, 10);
  await family.page.locator('#request-date').fill('2020-01-01');
  await family.page.getByRole('button', { name: /submit care request/i }).click();
  assert.match(await family.page.locator('.field-error').allTextContents().then((parts) => parts.join(' ')), /date from today/);
  await family.page.locator('#request-date').fill(tomorrow);
  await family.page.locator('#request-instructions').fill('Please enter through the east gate. <img src=x onerror="window.__requestXss=true">');
  await family.page.getByRole('button', { name: /submit care request/i }).click();
  assert.match(await family.page.locator('.field-error').allTextContents().then((parts) => parts.join(' ')), /Review the pilot estimate/);
  await family.page.locator('input[name="price_ack"]').check();
  const submissionFeedback = await family.page.evaluate(() => {
    const demoForm = document.createElement('form');
    demoForm.method = 'post';
    const demoButton = document.createElement('button');
    demoButton.type = 'submit';
    demoButton.textContent = 'Save';
    demoForm.append(demoButton);
    document.body.append(demoForm);
    let seenSubmissions = 0;
    let repeatedBlocked = false;
    const stopNavigation = (event) => { seenSubmissions += 1; if (seenSubmissions === 2) repeatedBlocked = event.defaultPrevented; event.preventDefault(); };
    window.addEventListener('submit', stopNavigation);
    demoForm.dispatchEvent(new SubmitEvent('submit', { bubbles: true, cancelable: true, submitter: demoButton }));
    const repeated = new SubmitEvent('submit', { bubbles: true, cancelable: true, submitter: demoButton });
    demoForm.dispatchEvent(repeated);
    const result = { busy: demoButton.getAttribute('aria-busy'), feedback: demoForm.querySelector('.submit-feedback')?.textContent, repeatedBlocked };
    window.removeEventListener('submit', stopNavigation);
    demoForm.remove();
    return result;
  });
  assert.deepEqual(submissionFeedback, { busy: 'true', feedback: 'Submitting…', repeatedBlocked: true });
  await family.page.getByRole('button', { name: /submit care request/i }).click();
  await family.page.waitForURL(/page=request&id=/);
  const requestId = new URL(family.page.url()).searchParams.get('id');
  assert.ok(requestId);
  assert.match(await family.page.locator('.request-status-banner').textContent(), /Requested/);
  assert.match(await family.page.locator('.grave-details').textContent(), /₱500\.00/);
  const duplicateToken = await family.page.locator('input[name="csrf_token"]').first().getAttribute('value');
  const duplicateRequest = await family.page.request.post(`${baseUrl}?page=request-new`, { form: { csrf_token: duplicateToken, action: 'create_request', grave_id: graveId, service_id: serviceId, preferred_date: tomorrow, price_ack: '1' } });
  assert.match(await duplicateRequest.text(), /An open request already exists/);
  assert.equal(await family.page.locator('.grave-details img').count(), 0);
  assert.equal(await family.page.evaluate(() => window.__requestXss), undefined);
  assert.equal(await family.page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true);
  await family.page.screenshot({ path: path.resolve('storage/request-family-mobile.png'), fullPage: true });
  assert.equal((await other.page.goto(`${baseUrl}?page=request&id=${requestId}`))?.status(), 404);
  assert.equal((await other.page.request.get(photoUrl)).status(), 404);
  await caretaker.page.goto(`${baseUrl}?page=requests`);
  assert.equal((await caretaker.page.goto(`${baseUrl}?page=request&id=${requestId}`))?.status(), 404);

  await admin.page.goto(`${baseUrl}?page=services`);
  await admin.page.locator('.admin-record').filter({ hasText: 'Pilot grave cleaning' }).filter({ hasText: cemeteryName }).getByRole('link', { name: /edit offering/i }).click();
  await admin.page.locator('#service-price').fill('650.00');
  await admin.page.getByRole('button', { name: /save changes/i }).click();
  await admin.page.waitForURL(/page=services/);
  await family.page.reload();
  assert.match(await family.page.locator('.grave-details').textContent(), /₱500\.00/);
  const staleToken = await family.page.locator('input[name="csrf_token"]').first().getAttribute('value');
  const staleQuote = await family.page.request.post(`${baseUrl}?page=request-new`, { form: { csrf_token: staleToken, action: 'create_request', grave_id: graveId, service_id: serviceId, preferred_date: tomorrow, price_ack: '1' } });
  assert.match(await staleQuote.text(), /pilot price changed/i);
  serviceId = `${serviceId.split(':')[0]}:65000`;

  await admin.page.goto(`${baseUrl}?page=caretakers`);
  await admin.page.locator('.review-card').filter({ hasText: caretakerEmail }).getByRole('button', { name: 'Verify caretaker' }).click();
  await admin.page.waitForURL(/page=caretakers/);
  const grant = admin.page.locator('.authorization-row').filter({ hasText: 'Request Test Caretaker' });
  await grant.locator('select[name="cemetery_id"]').selectOption(cemeteryId);
  await grant.getByRole('button', { name: 'Authorize' }).click();
  await admin.page.waitForURL(/page=caretakers/);
  await admin.page.goto(`${baseUrl}?page=request&id=${requestId}`);
  const caretakerId = await admin.page.locator('#assign-caretaker option').filter({ hasText: 'Request Test Caretaker' }).getAttribute('value');
  await admin.page.locator('#assign-caretaker').selectOption(caretakerId);
  await admin.page.getByRole('button', { name: 'Assign caretaker' }).click();
  await admin.page.waitForURL(/page=request&id=/);
  assert.match(await admin.page.locator('.request-status-banner').textContent(), /Assigned/);
  await admin.page.screenshot({ path: path.resolve('storage/request-admin-desktop.png'), fullPage: true });
  await admin.page.goto(`${baseUrl}?page=caretakers`);
  const assignedGrant = admin.page.locator('.authorization-row').filter({ hasText: 'Request Test Caretaker' });
  await assignedGrant.getByRole('button', { name: /remove request test caretaker/i }).click();
  await admin.page.waitForURL(/page=caretakers/);
  assert.equal((await caretaker.page.request.get(`${baseUrl}?page=request&id=${requestId}`)).status(), 404);
  assert.equal((await caretaker.page.request.get(photoUrl)).status(), 404);
  await assignedGrant.locator('select[name="cemetery_id"]').selectOption(cemeteryId);
  await assignedGrant.getByRole('button', { name: 'Authorize' }).click();
  await admin.page.waitForURL(/page=caretakers/);
  await admin.page.goto(`${baseUrl}?page=request&id=${requestId}`);
  const duplicate = await csrfPost(admin.page, { action: 'request_transition', decision: 'assign', request_id: requestId, caretaker_id: caretakerId });
  assert.match(await duplicate.text(), /not available for the request/);
  await caretaker.page.goto(`${baseUrl}?page=updates`);
  assert.match(await caretaker.page.locator('.update-list').textContent(), /new care assignment/i);
  await caretaker.page.locator('.update-card').filter({ hasText: `#${requestId}` }).getByRole('button', { name: /open request/i }).click();
  await caretaker.page.waitForURL(new RegExp(`page=request&id=${requestId}`));
  await caretaker.page.goto(`${baseUrl}?page=updates`);
  assert.equal(await caretaker.page.locator('.update-card').filter({ hasText: `#${requestId}` }).filter({ has: caretaker.page.locator('.is-unread') }).count(), 0);
  await caretaker.page.goto(`${baseUrl}?page=request&id=${requestId}`);
  assert.equal((await caretaker.page.goto(`${baseUrl}?page=request&id=${requestId}`))?.status(), 200);
  assert.equal((await caretaker.page.request.get(photoUrl)).status(), 200);
  assert.equal(await caretaker.page.locator('#request-photos-title').count(), 1);
  await caretaker.page.getByRole('button', { name: 'Accept assignment' }).click();
  await caretaker.page.waitForURL(/page=request&id=/);
  await caretaker.page.screenshot({ path: path.resolve('storage/request-caretaker-mobile.png'), fullPage: true });
  await caretaker.page.locator('#confirm-headstone').fill('Wrong headstone');
  await caretaker.page.locator('#confirm-section').fill('Garden A');
  await caretaker.page.locator('#confirm-lot').fill('Lot 12');
  await caretaker.page.locator('input[name="confirmed"]').check();
  await caretaker.page.getByRole('button', { name: /confirm grave and start/i }).click();
  await caretaker.page.waitForURL(/page=request&id=/);
  assert.match(await caretaker.page.locator('.account-flash').textContent(), /Confirm the recorded headstone/);
  await caretaker.page.locator('#confirm-headstone').fill('Maria R. Test');
  await caretaker.page.locator('#confirm-section').fill('Garden A');
  await caretaker.page.locator('#confirm-lot').fill('Lot 12');
  await caretaker.page.locator('input[name="confirmed"]').check();
  await caretaker.page.getByRole('button', { name: /confirm grave and start/i }).click();
  await caretaker.page.waitForURL(/page=request&id=/);
  assert.match(await caretaker.page.locator('.request-status-banner').textContent(), /In progress/);
  const missingEvidence = await csrfPost(caretaker.page, { action: 'request_transition', decision: 'submit', request_id: requestId, note: 'Cleaned and tidied the grave area.' });
  assert.match(await missingEvidence.text(), /Add at least one before and one after photo/);
  const invalidEvidence = await evidencePost(caretaker.page, requestId, 'before', Buffer.from('not an image'));
  assert.match(await invalidEvidence.text(), /valid JPEG, PNG, or WebP/);
  const prematureAfter = await evidencePost(caretaker.page, requestId, 'after');
  assert.match(await prematureAfter.text(), /Add a before photo/);
  const beforeUpload = await evidencePost(caretaker.page, requestId, 'before');
  assert.match(await beforeUpload.text(), /Evidence photo added/);
  await caretaker.page.reload();
  await caretaker.page.locator('#evidence-after-file').setInputFiles({ name: 'after.png', mimeType: 'image/png', buffer: png });
  await caretaker.page.getByRole('button', { name: 'Upload after photo' }).click();
  await caretaker.page.waitForURL(/page=request&id=/);
  assert.equal(await caretaker.page.locator('.evidence-photo img').count(), 2);
  caretaker.page.once('dialog', (dialog) => dialog.accept());
  await caretaker.page.locator('.evidence-stage').filter({ hasText: 'After' }).getByRole('button', { name: 'Remove photo' }).click();
  await caretaker.page.waitForURL(/page=request&id=/);
  assert.equal(await caretaker.page.locator('.evidence-photo img').count(), 1);
  const missingAfterAgain = await csrfPost(caretaker.page, { action: 'request_transition', decision: 'submit', request_id: requestId, note: 'Cleaned and tidied the grave area.' });
  assert.match(await missingAfterAgain.text(), /Add at least one before and one after photo/);
  await caretaker.page.locator('#evidence-after-file').setInputFiles({ name: 'after.png', mimeType: 'image/png', buffer: png });
  await caretaker.page.getByRole('button', { name: 'Upload after photo' }).click();
  await caretaker.page.waitForURL(/page=request&id=/);
  assert.equal(await caretaker.page.locator('.evidence-photo img').count(), 2);
  const evidenceUrl = new URL(await caretaker.page.locator('.evidence-photo img').first().getAttribute('src'), baseUrl).href;
  assert.equal((await other.page.request.get(evidenceUrl)).status(), 404);
  await other.page.goto(`${baseUrl}?page=overview`);
  const forgedEvidenceRemove = await csrfPost(other.page, { action: 'remove_request_evidence', evidence_id: new URL(evidenceUrl).searchParams.get('id'), request_id: requestId });
  assert.equal(forgedEvidenceRemove.status(), 403);
  assert.equal((await admin.page.request.get(evidenceUrl)).status(), 200);
  const evidenceId = new URL(evidenceUrl).searchParams.get('id');
  const evidenceLookup = spawnSync('php', ['-r', "require 'app/database.php'; $q = db()->prepare('SELECT storage_name FROM request_evidence WHERE id = ?'); $q->execute([$argv[1]]); echo $q->fetchColumn();", '--', evidenceId], { encoding: 'utf8' });
  assert.equal(evidenceLookup.status, 0);
  assert.match(evidenceLookup.stdout, /^[a-f0-9]{48}$/);
  assert.equal((await caretaker.page.request.get(new URL(`../storage/request-evidence/${evidenceLookup.stdout}`, baseUrl).href)).status(), 403);
  await caretaker.page.locator('#work-note').fill('Cleaned and tidied the grave area.');
  await caretaker.page.getByRole('button', { name: /send evidence for family review/i }).click();
  await caretaker.page.waitForURL(/page=request&id=/);
  const lockedEvidence = await csrfPost(caretaker.page, { action: 'remove_request_evidence', evidence_id: evidenceId, request_id: requestId });
  assert.match(await lockedEvidence.text(), /can no longer be removed/);
  await family.page.reload();
  assert.match(await family.page.locator('.request-status-banner').textContent(), /Awaiting family review/);
  assert.match(await family.page.locator('.request-timeline').textContent(), /Cleaned and tidied/);
  assert.equal(await family.page.locator('.evidence-photo img').count(), 2);
  await family.page.locator('.evidence-photo img').first().scrollIntoViewIfNeeded();
  await family.page.waitForFunction(() => { const image = document.querySelector('.evidence-photo img'); return image?.complete && image.naturalWidth > 0; });
  assert.equal(await family.page.locator('.evidence-photo img').first().evaluate((image) => image.complete && image.naturalWidth > 0), true);
  for (const width of [320, 390, 768, 1280]) {
    await family.page.setViewportSize({ width, height: 900 });
    assert.equal(await family.page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true, `review overflows at ${width}px`);
    assert.equal(await family.page.getByRole('button', { name: 'Approve reported work' }).isVisible(), true);
  }
  await family.page.setViewportSize({ width: 390, height: 900 });
  await family.page.waitForTimeout(300);
  assert.equal(await family.page.locator('#site-sidebar').evaluate((sidebar) => sidebar.getBoundingClientRect().right <= 0), true);
  await family.page.screenshot({ path: path.resolve('storage/evidence-family-mobile.png'), fullPage: true });
  await family.page.goto(`${baseUrl}?page=updates`);
  assert.match(await family.page.locator('.update-list').textContent(), /before-and-after evidence/i);
  await family.page.locator('.update-card').filter({ hasText: /before-and-after evidence/i }).getByRole('button', { name: /open request/i }).click();
  await family.page.waitForURL(new RegExp(`page=request&id=${requestId}`));
  await family.page.getByRole('button', { name: 'Approve reported work' }).click();
  await family.page.waitForURL(/page=request&id=/);
  assert.match(await family.page.locator('.request-status-banner').textContent(), /Completed/);
  assert.equal(await family.page.locator('.request-timeline li').count(), 10);
  await other.page.goto(`${baseUrl}?page=overview`);
  const forgedCancel = await csrfPost(other.page, { action: 'request_transition', decision: 'cancel', request_id: requestId });
  assert.equal(forgedCancel.status(), 404);

  async function createAnotherRequest() {
    const token = await family.page.locator('input[name="csrf_token"]').first().getAttribute('value');
    const response = await family.page.request.post(`${baseUrl}?page=request-new`, { form: { csrf_token: token, action: 'create_request', grave_id: graveId, service_id: serviceId, preferred_date: tomorrow, instructions: '', price_ack: '1' } });
    assert.equal(response.status(), 200);
    const id = new URL(response.url()).searchParams.get('id');
    assert.ok(id);
    return id;
  }

  const declinedId = await createAnotherRequest();
  await admin.page.goto(`${baseUrl}?page=request&id=${declinedId}`);
  await admin.page.locator('#assign-caretaker').selectOption(caretakerId);
  await admin.page.getByRole('button', { name: 'Assign caretaker' }).click();
  await admin.page.waitForURL(/page=request&id=/);
  await caretaker.page.goto(`${baseUrl}?page=request&id=${declinedId}`);
  await caretaker.page.locator('#decline-note').fill('Unavailable on the preferred date.');
  await caretaker.page.getByRole('button', { name: 'Decline assignment' }).click();
  await caretaker.page.waitForURL(/page=requests/);
  assert.equal(await caretaker.page.locator('.request-card').filter({ hasText: `REQUEST #${declinedId}` }).count(), 0);
  assert.equal((await caretaker.page.request.get(photoUrl)).status(), 404);
  await family.page.goto(`${baseUrl}?page=request&id=${declinedId}`);
  assert.match(await family.page.locator('.request-status-banner').textContent(), /Requested/);
  assert.match(await family.page.locator('.request-timeline').textContent(), /Unavailable on the preferred date/);
  await family.page.getByRole('button', { name: 'Cancel request' }).click();
  await family.page.waitForURL(/page=request&id=/);
  assert.match(await family.page.locator('.request-status-banner').textContent(), /Cancelled/);

  const issueId = await createAnotherRequest();
  await admin.page.goto(`${baseUrl}?page=request&id=${issueId}`);
  await admin.page.locator('#assign-caretaker').selectOption(caretakerId);
  await admin.page.getByRole('button', { name: 'Assign caretaker' }).click();
  await admin.page.waitForURL(/page=request&id=/);
  await admin.page.locator('#requeue-note').fill('Caretaker schedule needs another review.');
  await admin.page.getByRole('button', { name: 'Return for reassignment' }).click();
  await admin.page.waitForURL(/page=request&id=/);
  assert.match(await admin.page.locator('.request-status-banner').textContent(), /Requested/);
  assert.equal((await caretaker.page.request.get(`${baseUrl}?page=request&id=${issueId}`)).status(), 404);
  await admin.page.locator('#assign-caretaker').selectOption(caretakerId);
  await admin.page.getByRole('button', { name: 'Assign caretaker' }).click();
  await admin.page.waitForURL(/page=request&id=/);
  await caretaker.page.goto(`${baseUrl}?page=request&id=${issueId}`);
  for (const [decision, extra] of [
    ['accept', {}],
    ['start', { confirmed: '1', headstone_name: 'Maria R. Test', section_code: 'Garden A', lot_code: 'Lot 12' }],
  ]) {
    const response = await csrfPost(caretaker.page, { action: 'request_transition', decision, request_id: issueId, ...extra });
    assert.equal(response.status(), 200);
  }
  assert.match(await (await evidencePost(caretaker.page, issueId, 'before')).text(), /Evidence photo added/);
  assert.match(await (await evidencePost(caretaker.page, issueId, 'after')).text(), /Evidence photo added/);
  const firstSubmit = await csrfPost(caretaker.page, { action: 'request_transition', decision: 'submit', request_id: issueId, note: 'The grave area was cleaned and tidied.' });
  assert.equal(firstSubmit.status(), 200);
  await family.page.goto(`${baseUrl}?page=request&id=${issueId}`);
  await family.page.locator('#issue-note').fill('The surrounding grass still needs trimming.');
  await family.page.getByRole('button', { name: 'Report an issue' }).click();
  await family.page.waitForURL(/page=request&id=/);
  assert.match(await family.page.locator('.request-status-banner').textContent(), /Issue reported/);
  assert.match(await family.page.locator('.request-timeline').textContent(), /grass still needs trimming/);
  await admin.page.goto(`${baseUrl}?page=updates`);
  assert.match(await admin.page.locator('.update-list').textContent(), /needs review/);
  await admin.page.goto(`${baseUrl}?page=request&id=${issueId}`);
  await admin.page.setViewportSize({ width: 390, height: 900 });
  assert.equal(await admin.page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true, 'admin issue review overflows on phone');
  await admin.page.locator('#resolution-note').fill('I reviewed the images and asked the caretaker to check the grass.');
  await admin.page.getByRole('button', { name: 'Send response for family review' }).click();
  await admin.page.waitForURL(/page=request&id=/);
  await family.page.reload();
  assert.match(await family.page.locator('.request-status-banner').textContent(), /Awaiting family review/);
  assert.match(await family.page.locator('.request-timeline').textContent(), /Administrator response to the issue/);
  await family.page.locator('#issue-note').fill('The grass remains untrimmed and needs another visit.');
  await family.page.getByRole('button', { name: 'Report an issue' }).click();
  await family.page.waitForURL(/page=request&id=/);
  await admin.page.reload();
  await admin.page.locator('#rework-note').fill('Please return and trim the surrounding grass.');
  await admin.page.getByRole('button', { name: 'Request new work round' }).click();
  await admin.page.waitForURL(/page=request&id=/);
  assert.match(await admin.page.locator('.request-status-banner').textContent(), /Requested/);
  assert.match(await admin.page.locator('#evidence-title').textContent(), /Before and after/);
  await admin.page.locator('#assign-caretaker').selectOption(caretakerId);
  await admin.page.getByRole('button', { name: 'Assign caretaker' }).click();
  await admin.page.waitForURL(/page=request&id=/);
  await caretaker.page.goto(`${baseUrl}?page=request&id=${issueId}`);
  for (const [decision, extra] of [
    ['accept', {}],
    ['start', { confirmed: '1', headstone_name: 'Maria R. Test', section_code: 'Garden A', lot_code: 'Lot 12' }],
  ]) {
    const response = await csrfPost(caretaker.page, { action: 'request_transition', decision, request_id: issueId, ...extra });
    assert.equal(response.status(), 200);
  }
  const staleEvidence = await csrfPost(caretaker.page, { action: 'request_transition', decision: 'submit', request_id: issueId, note: 'Trimming was completed around the grave.' });
  assert.match(await staleEvidence.text(), /Add at least one before and one after photo/);
  assert.match(await (await evidencePost(caretaker.page, issueId, 'before')).text(), /Evidence photo added/);
  assert.match(await (await evidencePost(caretaker.page, issueId, 'after')).text(), /Evidence photo added/);
  assert.equal((await csrfPost(caretaker.page, { action: 'request_transition', decision: 'submit', request_id: issueId, note: 'Trimming was completed around the grave.' })).status(), 200);
  await family.page.reload();
  assert.match(await family.page.locator('.request-status-banner').textContent(), /Awaiting family review/);
  assert.equal(await family.page.locator('.evidence-round').count(), 2);
  await family.page.getByRole('button', { name: 'Approve reported work' }).click();
  await family.page.waitForURL(/page=request&id=/);
  assert.match(await family.page.locator('.request-status-banner').textContent(), /Completed/);
  assert.deepEqual(errors, []);
  console.log('Workflow checks passed: prices, assignments, before-and-after evidence, private access, family review, issue resolution, rework, updates, and mobile layout.');
  await Promise.all([admin.context.close(), caretaker.context.close(), family.context.close(), other.context.close()]);
} finally {
  await browser.close();
  for (const [script, args] of [
    ['tools/cleanup-test-requests.php', [cemeteryName]],
    ['tools/cleanup-test-graves.php', [cemeteryName]],
    ['tools/cleanup-test-catalog.php', [cemeteryName]],
    ['tools/cleanup-test-users.php', [familyEmail, otherEmail, caretakerEmail]],
  ]) {
    const cleanup = spawnSync('php', [script, ...args], { encoding: 'utf8' });
    if (cleanup.status !== 0) {
      console.error(cleanup.stderr || `${script} cleanup failed.`);
      process.exitCode = 1;
    }
  }
}
