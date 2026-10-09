import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { chromium } from 'playwright-core';

const baseUrl = process.env.PUNTOD_BASE_URL || 'http://127.0.0.1/PuntodCare/public/';
const chromePath = process.env.PUNTOD_CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const savedAdmin = JSON.parse(readFileSync(path.resolve('storage/admin-credentials.json'), 'utf8'));
const adminEmail = process.env.PUNTOD_ADMIN_EMAIL || savedAdmin.email;
const adminPassword = process.env.PUNTOD_ADMIN_PASSWORD || savedAdmin.password;
const suffix = Date.now().toString(36);
const cemeteryName = `Puntod Test ${suffix}`;
const familyEmail = `catalog-family-${suffix}@example.test`;
const caretakerEmail = `catalog-caretaker-${suffix}@example.test`;
const password = 'Pilot-catalog-test-pass-2026';
const browser = await chromium.launch({ executablePath: chromePath, headless: true });
const errors = [];

async function contextWithPage(viewport = { width: 1440, height: 900 }) {
  const context = await browser.newContext({ viewport, isMobile: viewport.width < 600 });
  const page = await context.newPage();
  page.on('pageerror', (error) => errors.push(error.message));
  return { context, page };
}

async function register(page, url, values, buttonName) {
  await page.goto(`${baseUrl}?page=${url}`);
  for (const [key, value] of Object.entries(values)) await page.locator(`#${key}`).fill(value);
  await page.getByRole('button', { name: buttonName }).click();
  await page.waitForURL(/page=overview/);
}

try {
  const admin = await contextWithPage();
  await admin.page.goto(`${baseUrl}?page=login`);
  await admin.page.locator('#email').fill(adminEmail);
  await admin.page.locator('#password').fill(adminPassword);
  await admin.page.getByRole('button', { name: 'Sign in' }).click();
  await admin.page.waitForURL(/page=overview/);

  await admin.page.goto(`${baseUrl}?page=cemeteries`);
  await admin.page.locator('#cemetery-name').fill(cemeteryName);
  await admin.page.locator('#cemetery-city').fill('Cagayan de Oro City');
  await admin.page.locator('#cemetery-province').fill('Misamis Oriental');
  await admin.page.locator('#cemetery-address').fill('Pilot gate, north entrance');
  await admin.page.getByRole('button', { name: /add cemetery/i }).click();
  await admin.page.waitForURL(/page=cemeteries/);
  let cemeteryCard = admin.page.locator('.admin-record').filter({ hasText: cemeteryName });
  assert.equal(await cemeteryCard.count(), 1);
  await admin.page.screenshot({ path: path.resolve('storage/cemeteries-desktop.png'), fullPage: true });

  await cemeteryCard.getByRole('link', { name: /edit details/i }).click();
  await admin.page.locator('#cemetery-address').fill('<img src=x onerror="window.__puntodXss=true">');
  await admin.page.getByRole('button', { name: /save changes/i }).click();
  await admin.page.waitForURL(/page=cemeteries/);
  cemeteryCard = admin.page.locator('.admin-record').filter({ hasText: cemeteryName });
  assert.equal(await cemeteryCard.locator('img').count(), 0, 'cemetery address is rendered as text');
  assert.equal(await admin.page.evaluate(() => window.__puntodXss), undefined);

  await admin.page.locator('#cemetery-name').fill(cemeteryName);
  await admin.page.locator('#cemetery-city').fill('Cagayan de Oro City');
  await admin.page.locator('#cemetery-province').fill('Misamis Oriental');
  await admin.page.getByRole('button', { name: /add cemetery/i }).click();
  assert.match(await admin.page.locator('#cemetery-name + .field-error').textContent(), /already exists/i, 'duplicate cemetery rejected');
  await admin.page.goto(`${baseUrl}?page=cemeteries`);

  await admin.page.goto(`${baseUrl}?page=plots`);
  const cemeteryId = await admin.page.locator('#plot-cemetery option').filter({ hasText: cemeteryName }).getAttribute('value');
  assert.ok(cemeteryId);
  await admin.page.locator('#plot-cemetery').selectOption(cemeteryId);
  await admin.page.locator('#plot-section').fill('Garden A');
  await admin.page.locator('#plot-block').fill('Block 2');
  await admin.page.locator('#plot-row').fill('Row 3');
  await admin.page.locator('#plot-lot').fill('Lot 12');
  await admin.page.locator('#plot-landmark').fill('Near the east gate');
  await admin.page.getByRole('button', { name: /add plot reference/i }).click();
  await admin.page.waitForURL(/page=plots/);
  let plotCard = admin.page.locator('.admin-record').filter({ hasText: 'Section Garden A · Lot Lot 12' });
  assert.equal(await plotCard.count(), 1);
  await admin.page.locator('#plot-cemetery').selectOption(cemeteryId);
  await admin.page.locator('#plot-section').fill('Garden A');
  await admin.page.locator('#plot-block').fill('Block 2');
  await admin.page.locator('#plot-row').fill('Row 3');
  await admin.page.locator('#plot-lot').fill('Lot 12');
  await admin.page.getByRole('button', { name: /add plot reference/i }).click();
  assert.match(await admin.page.locator('#plot-lot + .field-error').textContent(), /already has/i, 'duplicate plot rejected');
  await admin.page.goto(`${baseUrl}?page=plots&q=Garden+A`);
  assert.equal(await admin.page.locator('.admin-record').count(), 1, 'plot search works');

  await admin.page.goto(`${baseUrl}?page=services`);
  await admin.page.locator('#service-cemetery').selectOption(cemeteryId);
  await admin.page.locator('#service-name').fill('Grave cleaning');
  await admin.page.locator('#service-description').fill('Cleaning and before-and-after photos.');
  await admin.page.locator('#service-price').fill('0');
  await admin.page.getByRole('button', { name: /add service/i }).click();
  assert.match(await admin.page.locator('#service-price + .field-error').textContent(), /price in pesos/i, 'invalid price rejected');
  await admin.page.locator('#service-price').fill('500.00');
  await admin.page.getByRole('button', { name: /add service/i }).click();
  await admin.page.waitForURL(/page=services/);
  let serviceCard = admin.page.locator('.admin-record').filter({ hasText: 'Grave cleaning' });
  assert.match(await serviceCard.textContent(), /₱500\.00/);
  assert.match(await serviceCard.textContent(), /Pilot estimate/);
  await serviceCard.getByRole('link', { name: /edit offering/i }).click();
  await admin.page.locator('#service-price').fill('550.00');
  await admin.page.getByRole('button', { name: /save changes/i }).click();
  await admin.page.waitForURL(/page=services/);
  serviceCard = admin.page.locator('.admin-record').filter({ hasText: 'Grave cleaning' });
  assert.match(await serviceCard.textContent(), /₱550\.00/);
  await serviceCard.getByRole('link', { name: /edit offering/i }).click();
  assert.match(await admin.page.locator('.price-history').textContent(), /₱500\.00/);
  assert.match(await admin.page.locator('.price-history').textContent(), /₱550\.00/);

  const family = await contextWithPage({ width: 390, height: 844 });
  await register(family.page, 'register', { full_name: 'Catalog Test Family', email: familyEmail, password, password_confirmation: password }, /create family account/i);
  assert.equal((await family.page.goto(`${baseUrl}?page=cemeteries`))?.status(), 403);
  await family.page.goto(`${baseUrl}?page=overview`);
  const csrf = await family.page.locator('input[name="csrf_token"]').first().getAttribute('value');
  const forbidden = await family.page.request.post(`${baseUrl}?page=cemeteries`, { form: { csrf_token: csrf, action: 'save_cemetery', name: 'Forged', city: 'City', province: 'Province', status: 'active' } });
  assert.equal(forbidden.status(), 403, 'family cannot create a cemetery');
  await family.context.close();

  const caretaker = await contextWithPage({ width: 390, height: 844 });
  await register(caretaker.page, 'enroll', { full_name: 'Catalog Test Caretaker', email: caretakerEmail, phone: '09171234567', service_area: 'Cagayan de Oro City', password, password_confirmation: password }, /submit application/i);
  await caretaker.context.close();

  await admin.page.goto(`${baseUrl}?page=families&q=${encodeURIComponent(familyEmail)}`);
  assert.match(await admin.page.locator('.admin-record').textContent(), /Catalog Test Family/);
  await admin.page.goto(`${baseUrl}?page=caretakers`);
  let caretakerCard = admin.page.locator('.review-card').filter({ hasText: caretakerEmail });
  await caretakerCard.getByRole('button', { name: 'Verify caretaker' }).click();
  await admin.page.waitForURL(/page=caretakers/);
  const grant = admin.page.locator('.authorization-row').filter({ hasText: 'Catalog Test Caretaker' });
  await grant.locator('select[name="cemetery_id"]').selectOption(cemeteryId);
  await grant.getByRole('button', { name: 'Authorize' }).click();
  await admin.page.waitForURL(/page=caretakers/);
  assert.match(await grant.textContent(), new RegExp(cemeteryName));
  await grant.getByRole('button', { name: /remove catalog test caretaker/i }).click();
  await admin.page.waitForURL(/page=caretakers/);
  assert.match(await grant.textContent(), /No cemetery authorization/);

  await admin.page.goto(`${baseUrl}?page=overview`);
  assert.match(await admin.page.locator('.catalog-activity').textContent(), /Grave cleaning|Garden A/);

  const mobileAdmin = await contextWithPage({ width: 390, height: 844 });
  await mobileAdmin.page.goto(`${baseUrl}?page=login`);
  await mobileAdmin.page.locator('#email').fill(adminEmail);
  await mobileAdmin.page.locator('#password').fill(adminPassword);
  await mobileAdmin.page.getByRole('button', { name: 'Sign in' }).click();
  await mobileAdmin.page.waitForURL(/page=overview/);
  for (const pageName of ['cemeteries', 'plots', 'services', 'caretakers', 'families']) {
    await mobileAdmin.page.goto(`${baseUrl}?page=${pageName}`);
    const dimensions = await mobileAdmin.page.evaluate(() => ({ page: document.documentElement.scrollWidth, viewport: window.innerWidth }));
    assert.ok(dimensions.page <= dimensions.viewport + 1, `${pageName} overflows a phone viewport: ${JSON.stringify(dimensions)}`);
  }
  await mobileAdmin.page.goto(`${baseUrl}?page=services`);
  await mobileAdmin.page.screenshot({ path: path.resolve('storage/services-mobile.png'), fullPage: true });
  await mobileAdmin.context.close();

  assert.deepEqual(errors, []);
  await admin.context.close();
  console.log('Admin checks passed: cemetery, plot, service, price history, caretaker access, family search, and role restrictions.');
} finally {
  await browser.close();
  const catalogCleanup = spawnSync('php', ['tools/cleanup-test-catalog.php', cemeteryName], { encoding: 'utf8' });
  const userCleanup = spawnSync('php', ['tools/cleanup-test-users.php', familyEmail, caretakerEmail], { encoding: 'utf8' });
  if (catalogCleanup.status !== 0) {
    console.error(catalogCleanup.stderr || 'Catalog cleanup failed.');
    process.exitCode = 1;
  }
  if (userCleanup.status !== 0) {
    console.error(userCleanup.stderr || 'User cleanup failed.');
    process.exitCode = 1;
  }
}
