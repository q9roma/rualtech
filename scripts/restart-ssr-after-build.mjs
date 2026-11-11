import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

// Linux releases restart their systemd SSR service in release-common.sh.
if (process.platform === 'win32') {
    const result = spawnSync('powershell.exe', [
        '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass',
        '-File', fileURLToPath(new URL('./start-storefront-ssr.ps1', import.meta.url)),
        '-Restart',
    ], { stdio: 'inherit', windowsHide: true });
    if (result.error) console.error(result.error.message);
    process.exitCode = result.status ?? 1;
}
