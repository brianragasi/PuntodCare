import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { chromium } from 'playwright-core';

const baseUrl = process.env.PUNTOD_BASE_URL || 'http://127.0.0.1/PuntodCare/public/';
const chromePath = process.env.PUNTOD_CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const adminAccount = JSON.parse(readFileSync(path.resolve('storage/admin-credentials.json'), 'utf8'));
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
  await admin.page.locator('.admin-record').filter({ hasText: 'Pilot grave cleaning' }).getByRole('link', { name: /edit offering/i }).click();
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
  await caretaker.page.locator('#work-note').fill('Cleaned and tidied the grave area.');
  await caretaker.page.getByRole('button', { name: /send note for family review/i }).click();
  await caretaker.page.waitForURL(/page=request&id=/);
  await family.page.reload();
  assert.match(await family.page.locator('.request-status-banner').textContent(), /Awaiting family review/);
  assert.match(await family.page.locator('.request-timeline').textContent(), /Cleaned and tidied/);
  await family.page.getByRole('button', { name: 'Approve reported work' }).click();
  await family.page.waitForURL(/page=request&id=/);
  assert.match(await family.page.locator('.request-status-banner').textContent(), /Completed/);
  assert.equal(await family.page.locator('.request-timeline li').count(), 6);
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
    ['submit', { note: 'The grave area was cleaned and tidied.' }],
  ]) {
    const response = await csrfPost(caretaker.page, { action: 'request_transition', decision, request_id: issueId, ...extra });
    assert.equal(response.status(), 200);
  }
  await family.page.goto(`${baseUrl}?page=request&id=${issueId}`);
  await family.page.locator('#issue-note').fill('The surrounding grass still needs trimming.');
  await family.page.getByRole('button', { name: 'Report an issue' }).click();
  await family.page.waitForURL(/page=request&id=/);
  assert.match(await family.page.locator('.request-status-banner').textContent(), /Issue reported/);
  assert.match(await family.page.locator('.request-timeline').textContent(), /grass still needs trimming/);
  assert.deepEqual(errors, []);
  console.log('Request checks passed: creation, price snapshot, assignment, confirmation, progress, approval, decline, cancellation, issue, access, and mobile layout.');
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
