const { spawnSync } = require('node:child_process');
const { mkdirSync } = require('node:fs');
const { join } = require('node:path');

const tmpDir = '.tmp';
mkdirSync(join(tmpDir, 'playwright-transform-cache-0'), { recursive: true });

const command = 'npx';
const result = spawnSync(command, ['playwright', 'test'], {
  stdio: 'inherit',
  shell: true,
  env: {
    ...process.env,
    TMPDIR: tmpDir,
  },
});

if (result.error) {
  console.error(result.error.message);
}

process.exit(result.status ?? 1);
