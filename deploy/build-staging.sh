#!/usr/bin/env bash
# Build a local source snapshot on Linux. Never targets production.
set -Eeuo pipefail
umask 027
sha=${1:?Source snapshot identifier required}
checksum=${2:?Source archive SHA256 required}
[[ $sha =~ ^[a-f0-9]{40}$ && $checksum =~ ^[a-f0-9]{64}$ ]]
root=/srv/altech/staging
archive="$root/incoming/$sha/source.tar.gz"
printf '%s  %s\n' "$checksum" "$archive" | sha256sum --check
build="$root/builds/$sha"
[[ ! -e $build ]] || { echo 'Build already exists; inspect before retrying' >&2; exit 1; }
python3 - "$archive" <<'PY'
import pathlib, sys, tarfile
with tarfile.open(sys.argv[1]) as archive:
    for member in archive:
        path = pathlib.PurePosixPath(member.name)
        if path.is_absolute() or '..' in path.parts or not (member.isfile() or member.isdir()):
            raise SystemExit('Unsafe source archive')
PY
mkdir -p "$build"
tar -xzf "$archive" -C "$build" --no-same-owner
cd "$build"
mkdir -p bootstrap/cache storage/framework/{cache/data,sessions,views} storage/logs storage/app/public
# A real test env file avoids Laravel's missing-file warnings. No live credentials.
printf 'APP_ENV=testing\nAPP_URL=http://localhost\nDB_CONNECTION=sqlite\nDB_DATABASE=:memory:\nMAIL_MAILER=array\n' > .env
export APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: MAIL_MAILER=array
export APP_KEY
APP_KEY=$(php -r 'echo "base64:".base64_encode(random_bytes(32));')
composer install --prefer-dist --no-interaction --no-progress --no-scripts
php artisan package:discover --no-ansi
php artisan filament:assets --no-ansi
npm ci --no-fund --no-progress
npm run typecheck
npm run build:ssr
vendor/bin/phpunit --fail-on-warning
composer audit --locked
npm audit
composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --optimize-autoloader
npm prune --omit=dev --no-fund
bash scripts/build-release.sh "$sha"
cp dist/release.tar.gz* scripts/deploy-release.sh scripts/release-common.sh "$root/incoming/$sha/"
echo "STAGING_ARTIFACT_READY=$sha"
