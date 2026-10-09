import { spawnSync } from 'node:child_process';

for (const suite of ['ui', 'admin', 'graves', 'requests']) {
  process.stdout.write(`\nChecking ${suite}...\n`);
  const result = spawnSync(process.execPath, [`tools/verify-${suite}.mjs`], { stdio: 'inherit' });
  if (result.status !== 0) {
    process.stderr.write(`Pilot verification stopped: ${suite} failed.\n`);
    process.exit(result.status || 1);
  }
}

process.stdout.write('\nPilot verification passed across accounts, catalog, graves, requests, evidence, review, permissions, and mobile views.\n');
