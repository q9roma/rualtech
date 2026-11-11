"""Package an explicit local source snapshot without credentials or dependencies."""
import hashlib
import json
from pathlib import Path
import tarfile

root = Path(__file__).resolve().parents[1]
paths = ['app', 'bootstrap', 'config', 'database', 'public', 'resources', 'routes',
         'scripts', 'deploy', 'tests', 'artisan', 'composer.json', 'composer.lock',
         'package.json', 'package-lock.json', 'phpunit.xml', 'vite.config.js',
         'tsconfig.json']
files = []
for name in paths:
    source = root / name
    if not source.exists():
        continue
    for path in ([source] if source.is_file() else source.rglob('*')):
        rel = path.relative_to(root)
        posix = rel.as_posix()
        if path.is_symlink() or not path.is_file():
            continue
        if any(part in {'.git', '__pycache__', 'node_modules'} for part in rel.parts):
            continue
        if path.name.startswith('.env') or path.suffix in {'.sqlite', '.sqlite3', '.pyc'}:
            continue
        if any(posix.startswith(prefix) for prefix in ['bootstrap/cache/', 'bootstrap/ssr/', 'public/build/', 'public/storage/']):
            continue
        if posix == 'public/hot':
            continue
        files.append((posix, path))
digest = hashlib.sha256()
for name, path in sorted(files):
    digest.update(name.encode() + b'\0' + hashlib.sha256(path.read_bytes()).digest())
identity = digest.hexdigest()[:40]
destination = root / 'dist' / 'staging-source.tar.gz'
destination.parent.mkdir(exist_ok=True)
with tarfile.open(destination, 'w:gz') as archive:
    for name, path in sorted(files):
        archive.add(path, arcname=name, recursive=False)
manifest = {'snapshot': identity, 'kind': 'local-source-snapshot-not-git-commit',
            'files': len(files), 'sha256': hashlib.sha256(destination.read_bytes()).hexdigest()}
(destination.parent / 'staging-source.json').write_text(json.dumps(manifest, indent=2))
print(json.dumps(manifest))
