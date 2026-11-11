#!/usr/bin/env bash
# Package an isolated fixture, never this checkout's credentials or database.
set -Eeuo pipefail
source_root=$(cd "$(dirname "$0")/.." && pwd)
fixture=$(mktemp -d)
[[ $fixture == /* && -d $fixture && ! -L $fixture && $(basename "$fixture") == tmp.* ]]
trap 'rm -rf -- "$fixture"' EXIT
cd "$fixture"
mkdir -p app bootstrap/ssr bootstrap/cache config database public/build public/storage resources routes vendor node_modules scripts deploy
touch artisan vendor/autoload.php composer.json composer.lock package.json package-lock.json
printf '{}' > public/build/manifest.json
printf 'fixture' > bootstrap/ssr/ssr.js
printf 'private' > bootstrap/cache/config.php
printf 'private' > public/storage/upload.txt
printf 'private' > .env
printf 'private' > app/.env.secret
cp "$source_root"/scripts/*.py scripts/
cp "$source_root"/scripts/{build-release,deploy-release,rollback-release,release-common}.sh scripts/
cp "$source_root/deploy/catalog-requirements.txt" deploy/
sha=0123456789012345678901234567890123456789
bash scripts/build-release.sh "$sha"
(cd dist && sha256sum --check release.tar.gz.sha256)
python3 - <<'PY'
import tarfile
with tarfile.open('dist/release.tar.gz') as archive:
    names = {member.name.removeprefix('./') for member in archive}
    for required in ['scripts/catalog_preview.py', 'scripts/supplier_price.py',
                     'scripts/catalog_photo_task.py', 'scripts/catalog_automation.py',
                     'scripts/audit_catalog_photos.py', 'scripts/probe_icecat.py',
                     'scripts/review_icecat_candidates.py', 'scripts/catalog-requirements.txt',
                     'scripts/deploy-release.sh', 'bootstrap/ssr/ssr.js', 'public/build/manifest.json']:
        assert required in names, required
    for forbidden in ['.env', 'app/.env.secret', 'bootstrap/cache/config.php',
                      'public/storage/upload.txt', 'scripts/test_catalog_preview.py']:
        assert forbidden not in names, forbidden
    assert b'0123456789012345678901234567890123456789' in archive.extractfile('./config/release.php').read()
print('Release package content and secret exclusions passed.')
PY
