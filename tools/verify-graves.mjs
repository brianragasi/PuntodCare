import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { chromium } from 'playwright-core';

const baseUrl = process.env.PUNTOD_BASE_URL || 'http://127.0.0.1/PuntodCare/public/';
const chromePath = process.env.PUNTOD_CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const admin = JSON.parse(readFileSync(path.resolve('storage/admin-credentials.json'), 'utf8'));
const suffix = Date.now().toString(36);
const cemeteryName = `Puntod Test ${suffix}`;
const ownerEmail = `grave-owner-${suffix}@example.test`;
const otherEmail = `grave-other-${suffix}@example.test`;
const password = 'Grave-test-passphrase-2026';
const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/9WQAAAAASUVORK5CYII=', 'base64');
const browser = await chromium.launch({ executablePath: chromePath, headless: true });
const errors = [];

async function pageFor(width = 1440) {
  const context = await browser.newContext({ viewport: { width, height: 900 }, isMobile: width < 600 });
  const page = await context.newPage();
  page.on('pageerror', (error) => errors.push(error.message));
  return { context, page };
}

async function register(page, email, fullName) {
  await page.goto(`${baseUrl}?page=register`);
  await page.locator('#full_name').fill(fullName);
  await page.locator('#email').fill(email);
  await page.locator('#password').fill(password);
  await page.locator('#password_confirmation').fill(password);
  await page.getByRole('button', { name: /create family account/i }).click();
  await page.waitForURL(/page=overview/);
}

try {
  const manager = await pageFor();
  await manager.page.goto(`${baseUrl}?page=login`);
  await manager.page.locator('#email').fill(process.env.PUNTOD_ADMIN_EMAIL || admin.email);
  await manager.page.locator('#password').fill(process.env.PUNTOD_ADMIN_PASSWORD || admin.password);
  await manager.page.getByRole('button', { name: 'Sign in' }).click();
  await manager.page.waitForURL(/page=overview/);
  await manager.page.goto(`${baseUrl}?page=cemeteries`);
  await manager.page.locator('#cemetery-name').fill(cemeteryName);
  await manager.page.locator('#cemetery-city').fill('Cagayan de Oro City');
  await manager.page.locator('#cemetery-province').fill('Misamis Oriental');
  await manager.page.getByRole('button', { name: /add cemetery/i }).click();
  await manager.page.waitForURL(/page=cemeteries/);
  assert.equal(await manager.page.locator('.admin-record').filter({ hasText: cemeteryName }).count(), 1);
  await manager.context.close();

  const owner = await pageFor(390);
  await register(owner.page, ownerEmail, 'Grave Test Owner');
  assert.match(await owner.page.locator('.grave-home-empty').textContent(), /Start with one loved one/);
  await owner.page.goto(`${baseUrl}?page=grave-form`);
  await owner.page.setViewportSize({ width: 1440, height: 900 });
  await owner.page.screenshot({ path: path.resolve('storage/grave-form-desktop.png'), fullPage: true });
  await owner.page.setViewportSize({ width: 390, height: 900 });
  const cemeteryId = await owner.page.locator('#grave-cemetery option').filter({ hasText: cemeteryName }).getAttribute('value');
  assert.ok(cemeteryId);
  await owner.page.locator('#grave-cemetery').selectOption(cemeteryId);
  await owner.page.locator('#grave-name').fill('Maria Test Family');
  await owner.page.locator('#grave-headstone').fill('Maria T. Family');
  await owner.page.locator('#grave-birth').fill('1948-05-09');
  await owner.page.locator('#grave-death').fill('1947-08-12');
  await owner.page.locator('#grave-section').fill('Garden A');
  await owner.page.locator('#grave-lot').fill('Lot 12');
  await owner.page.locator('#grave-note').fill('Beside the east path');
  await owner.page.locator('#grave-latitude').fill('8.4822');
  await owner.page.locator('#grave-longitude').fill('124.6470');
  await owner.page.getByRole('button', { name: /register grave/i }).click();
  assert.match(await owner.page.locator('#grave-death + .field-error').textContent(), /after the birth date/);
  await owner.page.locator('#grave-death').fill('2022-08-12');
  await owner.page.getByRole('button', { name: /register grave/i }).click();
  await owner.page.waitForURL(/page=grave&id=/);
  const graveId = new URL(owner.page.url()).searchParams.get('id');
  assert.ok(graveId);
  assert.match(await owner.page.locator('.grave-details').textContent(), /Maria T\. Family/);
  assert.match(await owner.page.locator('.grave-details').textContent(), /8\.4822000/);
  assert.match(await owner.page.locator('.grave-timeline').textContent(), /Grave profile registered/);
  assert.match(await owner.page.locator('.grave-care-pending').textContent(), /No care reports yet/);
  assert.equal(await owner.page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true);
  await owner.page.screenshot({ path: path.resolve('storage/grave-mobile.png'), fullPage: true });

  await owner.page.getByRole('link', { name: /edit details/i }).click();
  await owner.page.locator('#grave-lot').fill('Lot 13');
  await owner.page.getByRole('button', { name: /save grave details/i }).click();
  await owner.page.waitForURL(/page=grave&id=/);
  assert.match(await owner.page.locator('.grave-details').textContent(), /Lot 13/);
  assert.match(await owner.page.locator('.grave-timeline').textContent(), /Grave details updated/);

  await owner.page.locator('#grave-photo-file').setInputFiles({ name: 'not-photo.txt', mimeType: 'text/plain', buffer: Buffer.from('not an image') });
  await owner.page.getByRole('button', { name: /upload photo/i }).click();
  await owner.page.waitForURL(/page=grave&id=/);
  assert.match(await owner.page.locator('.account-flash').textContent(), /valid JPEG, PNG, or WebP/);
  await owner.page.locator('#grave-photo-file').setInputFiles({ name: 'headstone.php.png', mimeType: 'image/png', buffer: png });
  await owner.page.locator('#grave-photo-caption').fill('Headstone from east path');
  await owner.page.getByRole('button', { name: /upload photo/i }).click();
  await owner.page.waitForURL(/page=grave&id=/);
  assert.equal(await owner.page.locator('.grave-photo img').count(), 1);
  assert.equal(await owner.page.locator('.grave-photo img').evaluate((image) => image.complete && image.naturalWidth > 0), true);
  assert.match(await owner.page.locator('.grave-timeline').textContent(), /Reference photo added/);
  const photoUrl = await owner.page.locator('.grave-photo img').getAttribute('src');
  const photoId = new URL(photoUrl, baseUrl).searchParams.get('id');
  const ownerPhoto = await owner.page.request.get(new URL(photoUrl, baseUrl).href);
  assert.equal(ownerPhoto.status(), 200);
  assert.equal(ownerPhoto.headers()['content-type'], 'image/png');
  const lookup = spawnSync('php', ['-r', "require 'app/database.php'; $q = db()->prepare('SELECT storage_name FROM grave_photos WHERE id = ?'); $q->execute([$argv[1]]); echo $q->fetchColumn();", '--', photoId], { encoding: 'utf8' });
  assert.equal(lookup.status, 0);
  assert.match(lookup.stdout, /^[a-f0-9]{48}$/);
  const directFile = await owner.page.request.get(new URL(`../storage/grave-photos/${lookup.stdout}`, baseUrl).href);
  assert.equal(directFile.status(), 403, 'photo storage cannot be opened directly');
  await owner.page.setViewportSize({ width: 1440, height: 900 });
  await owner.page.screenshot({ path: path.resolve('storage/grave-desktop.png'), fullPage: true });
  await owner.page.setViewportSize({ width: 390, height: 900 });

  const other = await pageFor(390);
  await register(other.page, otherEmail, 'Another Test Family');
  assert.equal((await other.page.goto(`${baseUrl}?page=grave&id=${graveId}`))?.status(), 404, 'other family cannot view grave');
  assert.equal((await other.page.request.get(new URL(photoUrl, baseUrl).href)).status(), 404, 'other family cannot view photo');
  await other.page.goto(`${baseUrl}?page=grave-form`);
  const csrf = await other.page.locator('input[name="csrf_token"]').first().getAttribute('value');
  const forgedEdit = await other.page.request.post(`${baseUrl}?page=grave-form`, { form: { csrf_token: csrf, action: 'save_grave', id: graveId, cemetery_id: cemeteryId, deceased_name: 'Forged', headstone_name: 'Forged', section_code: 'Garden A', lot_code: 'Lot 1' } });
  assert.equal(forgedEdit.status(), 404, 'other family cannot edit grave');
  const forgedRemove = await other.page.request.post(`${baseUrl}?page=grave`, { form: { csrf_token: csrf, action: 'remove_grave_photo', photo_id: photoId } });
  assert.equal(forgedRemove.status(), 404, 'other family cannot remove photo');
  await other.context.close();

  await owner.page.goto(`${baseUrl}?page=grave&id=${graveId}`);
  owner.page.once('dialog', (dialog) => dialog.accept());
  await owner.page.getByRole('button', { name: 'Remove photo' }).click();
  await owner.page.waitForURL(/page=grave&id=/);
  assert.equal(await owner.page.locator('.grave-photo img').count(), 0);
  assert.equal((await owner.page.request.get(new URL(photoUrl, baseUrl).href)).status(), 404);
  assert.match(await owner.page.locator('.grave-timeline').textContent(), /Reference photo removed/);
  await owner.page.goto(`${baseUrl}?page=graves`);
  assert.match(await owner.page.locator('.grave-card').textContent(), /Maria Test Family/);
  assert.deepEqual(errors, []);
  await owner.context.close();
  console.log('Grave checks passed: registration, editing, dates and pin, private photos, history, mobile layout, and cross-family access.');
} finally {
  await browser.close();
  for (const [script, args] of [
    ['tools/cleanup-test-graves.php', [cemeteryName]],
    ['tools/cleanup-test-catalog.php', [cemeteryName]],
    ['tools/cleanup-test-users.php', [ownerEmail, otherEmail]],
  ]) {
    const cleanup = spawnSync('php', [script, ...args], { encoding: 'utf8' });
    if (cleanup.status !== 0) {
      console.error(cleanup.stderr || `${script} cleanup failed.`);
      process.exitCode = 1;
    }
  }
}
