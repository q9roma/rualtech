#!/usr/bin/env python3
"""Read-only checks and change notifications. No automatic service restarts."""
import datetime
import json
from pathlib import Path
import shutil
import subprocess
import ssl
import urllib.request

STATE = Path('/var/lib/rualtech-monitor')
RECIPIENT = 'roma.moscow.home@gmail.com'


def main():
    STATE.mkdir(mode=0o700, parents=True, exist_ok=True)
    issues = []
    for unit in ['nginx', 'php-fpm84', 'mysql', 'laravel-queue-rualtech']:
        if subprocess.run(['systemctl', 'is-active', '--quiet', unit]).returncode:
            issues.append('Service not active: ' + unit)
    if subprocess.run(['systemctl', 'is-failed', '--quiet', 'rualtech-backup']).returncode == 0:
        issues.append('Last backup job failed')
    if subprocess.run(['systemctl', 'is-failed', '--quiet', 'rualtech-certificate']).returncode == 0:
        issues.append('Certificate renewal job failed')
    try:
        failed = subprocess.check_output(['mysql', '--defaults-extra-file=/root/.my.cnf', '-NBe',
            'SELECT COUNT(*) FROM rualtech.failed_jobs WHERE failed_at > NOW() - INTERVAL 1 DAY'], text=True)
        if int(failed.strip()) > 0: issues.append('Queue has failed jobs in the last 24 hours')
    except (subprocess.CalledProcessError, ValueError):
        issues.append('Cannot inspect queue failure count')
    offsite = STATE / 'offsite-ok'
    if not offsite.exists() or datetime.datetime.now().timestamp() - offsite.stat().st_mtime > 72 * 3600:
        issues.append('No confirmed copy on the workstation in 72 hours')
    for path in ['/', '/catalog', '/admin/login']:
        try:
            with urllib.request.urlopen('https://rualtech.ru' + path, timeout=20) as response:
                if response.status != 200: issues.append('HTTP check failed: ' + path)
        except Exception as error:
            issues.append('HTTP check failed: ' + path + ' (' + type(error).__name__ + ')')
    disk = shutil.disk_usage('/')
    if disk.free / disk.total < .15: issues.append('Disk free space below 15%')
    try:
        manifest = Path('/var/backups/rualtech/latest/manifest.json')
        age = datetime.datetime.now().timestamp() - manifest.stat().st_mtime
        if age > 30 * 3600: issues.append('No successful backup in 30 hours')
    except OSError:
        issues.append('Backup manifest is missing')
    try:
        cert = ssl._ssl._test_decode_cert('/var/www/httpd-cert/www-root/www.rualtech.ru_custom_1.crtca')
        if ssl.cert_time_to_seconds(cert['notAfter']) - datetime.datetime.now().timestamp() < 21 * 86400:
            issues.append('TLS certificate expires within 21 days')
    except Exception:
        issues.append('Cannot check TLS certificate expiry')
    previous_file = STATE / 'status.json'
    previous = json.loads(previous_file.read_text()) if previous_file.exists() else {}
    result = {'checked_at': datetime.datetime.now(datetime.timezone.utc).isoformat(), 'issues': issues,
              'disk_free_bytes': disk.free}
    if issues != previous.get('issues', []) or (issues and previous.get('notification_failed')):
        subject = '[Rualtech] ' + ('Server needs attention' if issues else 'Server recovered')
        message = 'To: ' + RECIPIENT + '\nFrom: monitoring@rualtech.ru\nSubject: ' + subject + '\n\n'
        message += '\n'.join(issues) if issues else 'All configured checks are passing.'
        sent = subprocess.run(['/usr/sbin/sendmail', '-f', 'monitoring@rualtech.ru', '-t'], input=message, text=True)
        result['notification_failed'] = sent.returncode != 0
    previous_file.write_text(json.dumps(result, indent=2))
    print(json.dumps(result))


if __name__ == '__main__':
    main()
