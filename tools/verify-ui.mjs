import assert from 'node:assert/strict';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { chromium } from 'playwright-core';
import { testAdmin } from './test-admin.mjs';

const baseUrl = process.env.PUNTOD_BASE_URL || 'http://127.0.0.1/PuntodCare/public/';
const chromePath = process.env.PUNTOD_CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const { email: adminEmail, password: adminPassword } = testAdmin();
const unique = Date.now().toString(36);
const familyEmail = `family-${unique}@example.test`;
const caretakerEmail = `caretaker-${unique}@example.test`;
const familyPassword = 'Family-test-passphrase-2026';
const caretakerPassword = 'Caretaker-test-passphrase-2026';
const xssPayload = '<img src=x onerror="window.__puntodXss=true">';
const sessionCheck = spawnSync('php', ['-r', `
require 'app/security.php';
$now = 100000;
$_SESSION = ['user_id' => 1, 'authenticated_at' => $now - 100, 'last_activity_at' => $now - 1799];
if (authenticated_session_expired($now)) exit(1);
$_SESSION['last_activity_at'] = $now - 1800;
if (!authenticated_session_expired($now)) exit(2);
$_SESSION = ['user_id' => 1, 'authenticated_at' => $now - 28800, 'last_activity_at' => $now];
if (!authenticated_session_expired($now)) exit(3);
$_SESSION = ['user_id' => 1];
if (!authenticated_session_expired($now)) exit(4);
`], { encoding: 'utf8' });
assert.equal(sessionCheck.status, 0, `session expiry checks failed: ${sessionCheck.stderr}`);
const browser = await chromium.launch({ executablePath: chromePath, headless: true });
const errors = [];

async function newPage(viewport) {
  const context = await browser.newContext({ viewport, isMobile: viewport.width < 600 });
  const page = await context.newPage();
  page.on('pageerror', (error) => errors.push(error.message));
  return { context, page };
}

async function signIn(page, email, password) {
  await page.goto(`${baseUrl}?page=login`);
  await page.locator('#email').fill(email);
  await page.locator('#password').fill(password);
  await page.getByRole('button', { name: 'Sign in' }).click();
  await page.waitForURL(/page=overview/);
}

async function signOut(page) {
  await page.getByRole('button', { name: 'Sign out' }).click();
  await page.waitForURL(/page=login/);
}

try {
  const guest = await newPage({ width: 1440, height: 900 });
  const loginResponse = await guest.page.goto(baseUrl);
  await guest.page.waitForURL(/page=login/);
  assert.equal(await guest.page.locator('h1').textContent(), 'Welcome back.');
  const securityHeaders = loginResponse.headers();
  assert.match(securityHeaders['content-security-policy'], /script-src 'self'/);
  assert.equal(securityHeaders['x-frame-options'], 'DENY');
  assert.equal(securityHeaders['x-content-type-options'], 'nosniff');
  assert.equal(securityHeaders['referrer-policy'], 'strict-origin-when-cross-origin');
  for (const privatePath of ['config/local.php', 'storage/admin-credentials.json', 'storage/demo-credentials.json', '.git/config']) {
    const response = await guest.page.request.get(new URL(`../${privatePath}`, baseUrl).href);
    assert.equal(response.status(), 403, `${privatePath} must be private`);
  }
  await guest.page.screenshot({ path: path.resolve('storage/login-desktop.png'), fullPage: true });

  await guest.page.goto(`${baseUrl}?page=register`);
  await guest.page.locator('#full_name').fill(`Test Family ${xssPayload}`);
  await guest.page.locator('#email').fill(familyEmail);
  await guest.page.locator('#password').fill(familyPassword);
  await guest.page.locator('#password_confirmation').fill(familyPassword);
  await guest.page.evaluate(() => {
    const forgedRole = document.createElement('input');
    forgedRole.type = 'hidden';
    forgedRole.name = 'role';
    forgedRole.value = 'admin';
    document.querySelector('.auth-form').append(forgedRole);
  });
  await guest.page.getByRole('button', { name: /create family account/i }).click();
  await guest.page.waitForURL(/page=overview/);
  assert.equal(await guest.page.locator('h1').textContent(), 'Always close in care.');
  assert.match(await guest.page.locator('.workspace-card').textContent(), /Test Family/);
  assert.equal(await guest.page.locator('.workspace-card img').count(), 0, 'family name is rendered as text');
  assert.equal(await guest.page.evaluate(() => window.__puntodXss), undefined);
  assert.equal(await guest.page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true);
  await guest.page.goto(`${baseUrl}?role=admin&page=overview`);
  assert.equal(await guest.page.locator('h1').textContent(), 'Always close in care.', 'URL role does not change authority');
  const forbidden = await guest.page.goto(`${baseUrl}?page=caretakers`);
  assert.equal(forbidden?.status(), 403, 'family cannot read caretaker review');
  await guest.page.goto(`${baseUrl}?page=overview`);
  const csrf = await guest.page.locator('input[name="csrf_token"]').first().getAttribute('value');
  const forbiddenPost = await guest.page.request.post(`${baseUrl}?page=caretakers`, { form: { csrf_token: csrf, action: 'review_caretaker', subject_id: '1', decision: 'verify', note: '' } });
  assert.equal(forbiddenPost.status(), 403, 'family cannot submit caretaker review');
  const noCsrf = await guest.page.request.post(`${baseUrl}?page=overview`, { form: { action: 'logout' } });
  assert.equal(noCsrf.status(), 403, 'state changes require CSRF token');
  assert.match(await noCsrf.text(), /Your session expired/);
  await guest.page.goto(`${baseUrl}?page=overview`);
  await signOut(guest.page);

  await guest.page.locator('#email').fill(familyEmail);
  await guest.page.locator('#password').fill('wrong-password');
  await guest.page.getByRole('button', { name: 'Sign in' }).click();
  assert.match(await guest.page.locator('.auth-error').textContent(), /could not sign you in/i);
  await signIn(guest.page, familyEmail, familyPassword);
  const newFamilyPassword = 'Reset-family-passphrase-2026';
  const reset = spawnSync('php', ['tools/reset-password.php', familyEmail], {
    encoding: 'utf8',
    env: { ...process.env, PUNTOD_NEW_PASSWORD: newFamilyPassword }
  });
  assert.equal(reset.status, 0, 'CLI password reset succeeds');
  await guest.page.goto(`${baseUrl}?page=account`);
  await guest.page.waitForURL(/page=login/);
  await signIn(guest.page, familyEmail, newFamilyPassword);
  await guest.context.close();

  const caretaker = await newPage({ width: 390, height: 844 });
  await caretaker.page.goto(`${baseUrl}?page=enroll`);
  await caretaker.page.locator('#full_name').fill('Test Caretaker');
  await caretaker.page.locator('#email').fill(caretakerEmail);
  await caretaker.page.locator('#phone').fill('09171234567');
  await caretaker.page.locator('#service_area').fill('Cagayan de Oro City');
  await caretaker.page.locator('#experience').fill(`Grave cleaning and photo documentation. ${xssPayload}`);
  await caretaker.page.locator('#password').fill(caretakerPassword);
  await caretaker.page.locator('#password_confirmation').fill(caretakerPassword);
  await caretaker.page.getByRole('button', { name: /submit application/i }).click();
  await caretaker.page.waitForURL(/page=overview/);
  assert.match(await caretaker.page.locator('.caretaker-status-note').textContent(), /pending review/i);
  assert.equal(await caretaker.page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true);
  await caretaker.page.screenshot({ path: path.resolve('storage/caretaker-mobile.png'), fullPage: true });
  await caretaker.page.getByRole('button', { name: 'Open navigation' }).click();
  assert.equal(await caretaker.page.getByRole('button', { name: 'Open navigation' }).getAttribute('aria-expanded'), 'true');
  await caretaker.page.keyboard.press('Escape');
  assert.equal(await caretaker.page.getByRole('button', { name: 'Open navigation' }).getAttribute('aria-expanded'), 'false');

  const admin = await newPage({ width: 1440, height: 900 });
  await signIn(admin.page, adminEmail, adminPassword);
  assert.equal(await admin.page.locator('h1').textContent(), 'Good day, administrator.');
  await admin.page.screenshot({ path: path.resolve('storage/admin-desktop.png'), fullPage: true });
  await admin.page.goto(`${baseUrl}?page=caretakers`);
  assert.match(await admin.page.locator('.review-card').first().textContent(), /Test Caretaker/);
  assert.equal(await admin.page.locator('.review-card img').count(), 0, 'caretaker note is rendered as text');
  assert.equal(await admin.page.evaluate(() => window.__puntodXss), undefined);
  const card = admin.page.locator('.review-card').filter({ hasText: caretakerEmail });
  await card.getByRole('button', { name: 'Verify caretaker' }).click();
  await admin.page.waitForURL(/page=caretakers/);
  assert.match(await card.textContent(), /Verified/);
  await caretaker.page.reload();
  assert.equal(await caretaker.page.locator('.caretaker-status-note').count(), 0, 'verified caretaker sees updated status');
  await caretaker.page.goto(`${baseUrl}?page=account`);
  assert.match(await caretaker.page.locator('main').textContent(), /Verified/);

  await card.locator('textarea[name="note"]').fill('Pilot suspension test');
  await card.getByRole('button', { name: 'Suspend caretaker' }).click();
  await admin.page.waitForURL(/page=caretakers/);
  await caretaker.page.goto(`${baseUrl}?page=account`);
  await caretaker.page.waitForURL(/page=login/);
  await card.getByRole('button', { name: 'Restore verification' }).click();
  await admin.page.waitForURL(/page=caretakers/);
  await signIn(caretaker.page, caretakerEmail, caretakerPassword);
  await caretaker.context.close();

  await admin.page.goto(`${baseUrl}?page=components`);
  await admin.page.getByRole('button', { name: /check example form/i }).click();
  assert.equal(await admin.page.locator('#sample-name-error').textContent(), 'Enter a name with at least 2 characters.');
  await admin.page.locator('#sample-name').fill('Maria Dela Cruz');
  await admin.page.locator('#sample-email').fill('maria@example.com');
  await admin.page.locator('#sample-service').selectOption('inspection');
  await admin.page.getByRole('button', { name: /check example form/i }).click();
  assert.match(await admin.page.locator('#form-result').textContent(), /does not save information/i);
  await admin.context.close();

  assert.deepEqual(errors, [], 'no browser script errors');
  console.log('UI and security checks passed: accounts, permissions, CSRF, XSS payloads, response headers, session expiry, and mobile layout.');
} finally {
  await browser.close();
  const cleanup = spawnSync('php', ['tools/cleanup-test-users.php', familyEmail, caretakerEmail], { encoding: 'utf8' });
  if (cleanup.status !== 0) {
    console.error(cleanup.stderr || 'Test account cleanup failed.');
    process.exitCode = 1;
  }
}
