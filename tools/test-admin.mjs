import { existsSync, readFileSync } from 'node:fs';
import path from 'node:path';

export function testAdmin() {
  if (process.env.PUNTOD_ADMIN_EMAIL && process.env.PUNTOD_ADMIN_PASSWORD) {
    return { email: process.env.PUNTOD_ADMIN_EMAIL, password: process.env.PUNTOD_ADMIN_PASSWORD };
  }
  for (const file of ['storage/admin-credentials.json', 'storage/demo-credentials.json']) {
    const location = path.resolve(file);
    if (!existsSync(location)) continue;
    const saved = JSON.parse(readFileSync(location, 'utf8'));
    const account = saved.accounts?.admin || saved;
    if (account.email && account.password) return account;
  }
  throw new Error('Set PUNTOD_ADMIN_EMAIL and PUNTOD_ADMIN_PASSWORD, or create an administrator account first.');
}
