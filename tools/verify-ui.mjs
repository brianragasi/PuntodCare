import assert from 'node:assert/strict';
import path from 'node:path';
import { chromium } from 'playwright-core';

const baseUrl = process.env.PUNTOD_BASE_URL || 'http://127.0.0.1:8000/';
const chromePath = process.env.PUNTOD_CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const browser = await chromium.launch({ executablePath: chromePath, headless: true });
const desktop = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await desktop.newPage();
const errors = [];
page.on('pageerror', (error) => errors.push(error.message));

try {
  for (const [role, heading] of [
    ['admin', 'Good day, administrator.'],
    ['family', 'Always close in care.'],
    ['caretaker', 'Care begins with you.']
  ]) {
    const response = await page.goto(`${baseUrl}?role=${role}`);
    assert.equal(response?.status(), 200, `${role} page returns 200`);
    assert.equal(await page.locator('h1').textContent(), heading);
    assert.equal(await page.locator('.preview-banner').count(), 1);
    assert.equal(await page.locator('link[rel="stylesheet"]').count(), 1);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true, `${role} desktop has no horizontal overflow`);
  }

  await page.goto(`${baseUrl}?role=admin`);
  await page.screenshot({ path: path.resolve('storage/admin-desktop.png'), fullPage: true });
  await page.goto(`${baseUrl}?role=admin&page=components`);
  await page.getByRole('button', { name: /check example form/i }).click();
  assert.equal(await page.locator('#sample-name-error').textContent(), 'Enter a name with at least 2 characters.');
  await page.locator('#sample-name').fill('Maria Dela Cruz');
  await page.locator('#sample-email').fill('maria@example.com');
  await page.locator('#sample-service').selectOption('inspection');
  await page.getByRole('button', { name: /check example form/i }).click();
  assert.match(await page.locator('#form-result').textContent(), /does not save information/i);
  await page.locator('[data-role-select]').selectOption('family');
  await page.waitForURL(/role=family/);
  assert.equal(await page.locator('h1').textContent(), 'Interface kit.');

  const mobile = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, deviceScaleFactor: 1 });
  const phonePage = await mobile.newPage();
  phonePage.on('pageerror', (error) => errors.push(error.message));
  for (const role of ['admin', 'family', 'caretaker']) {
    await phonePage.goto(`${baseUrl}?role=${role}`);
    assert.equal(await phonePage.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true, `${role} mobile has no horizontal overflow`);
  }
  await phonePage.screenshot({ path: path.resolve('storage/caretaker-mobile.png'), fullPage: true });
  await phonePage.getByRole('button', { name: 'Open navigation' }).click();
  assert.equal(await phonePage.getByRole('button', { name: 'Open navigation' }).getAttribute('aria-expanded'), 'true');
  await phonePage.keyboard.press('Escape');
  assert.equal(await phonePage.getByRole('button', { name: 'Open navigation' }).getAttribute('aria-expanded'), 'false');
  await phonePage.goto(`${baseUrl}?role=family&page=components`);
  await phonePage.screenshot({ path: path.resolve('storage/components-mobile.png'), fullPage: true });
  await mobile.close();

  assert.deepEqual(errors, [], 'no browser script errors');
  console.log('UI checks passed: three roles, desktop/mobile width, sample form, role switching, mobile navigation.');
} finally {
  await desktop.close();
  await browser.close();
}
