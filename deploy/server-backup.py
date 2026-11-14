#!/usr/bin/env python3
"""Root-only backup of the existing site; does not change application data."""
import datetime
import fcntl
import gzip
import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess

BASE = Path('/var/backups/rualtech')
SITE = Path('/var/www/www-root/data/www/rualtech.ru')


def main():
    os.umask(0o077)
    BASE.mkdir(mode=0o700, parents=True, exist_ok=True)
    with (BASE / 'backup.lock').open('w') as lock:
        fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        stamp = datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')
        work = BASE / (stamp + '.partial')
        work.mkdir()
        with (work / 'database.sql.gz').open('wb') as output:
            dump = subprocess.Popen(['mysqldump', '--defaults-extra-file=/root/.my.cnf', '--single-transaction', '--routines', '--triggers',
                '--events', '--no-tablespaces', '--set-gtid-purged=OFF', 'rualtech'], stdout=subprocess.PIPE)
            with gzip.GzipFile(fileobj=output, mode='wb') as compressed:
                shutil.copyfileobj(dump.stdout, compressed)
            dump.stdout.close()
            if dump.wait() != 0:
                raise RuntimeError('mysqldump failed; incomplete backup retained for inspection')
        subprocess.run(['gzip', '-t', str(work / 'database.sql.gz')], check=True)
        subprocess.run(['tar', '-czf', str(work / 'site.tar.gz'), '-C', str(SITE), '.'], check=True)
        subprocess.run(['tar', '-tzf', str(work / 'site.tar.gz')], stdout=subprocess.DEVNULL, check=True)
        configs = ['/etc/nginx', '/etc/php', '/opt/php84/etc',
                   '/etc/systemd/system', '/etc/sudoers.d/altech',
                   '/usr/local/sbin/rualtech-backup', '/usr/local/sbin/rualtech-health',
                   '/root/.acme.sh', '/root/rualtech-php84-backup',
                   '/var/www/httpd-cert/www-root']
        subprocess.run(['tar', '-czf', str(work / 'config.tar.gz'), *configs], check=True)
        files = {}
        for file in work.iterdir():
            with file.open('rb') as stream:
                files[file.name] = {'bytes': file.stat().st_size,
                                    'sha256': hashlib.file_digest(stream, 'sha256').hexdigest()}
        (work / 'manifest.json').write_text(json.dumps({'created_at': stamp, 'database': 'rualtech',
            'source': str(SITE), 'files': files, 'restore_verified': False}, indent=2))
        final = BASE / stamp
        work.rename(final)
        temp = BASE / 'latest.next'
        if temp.is_symlink(): temp.unlink()
        temp.symlink_to(final.name)
        temp.replace(BASE / 'latest')
        # Delete only complete snapshots made by this script, never unrelated backups.
        complete = sorted(p for p in BASE.iterdir() if p.is_dir() and not p.is_symlink()
                          and (p / 'manifest.json').is_file() and len(p.name) == 16
                          and p.name.endswith('Z') and p.name[:8].isdigit())
        for old in complete[:-7]:
            assert old.resolve().parent == BASE.resolve()
            shutil.rmtree(old)
        print(json.dumps({'status': 'ok', 'snapshot': final.name, 'files': files}))


if __name__ == '__main__':
    main()
