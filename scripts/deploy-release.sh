#!/usr/bin/env bash
# PHP snippets deliberately keep dollar variables literal for PHP.
# shellcheck disable=SC2016
set -Eeuo pipefail
environment=${1:?Usage: deploy-release.sh ENVIRONMENT COMMIT_SHA}
sha=${2:?Missing commit SHA}
[[ $sha =~ ^[a-f0-9]{40}$ ]] || exit 1
root="/srv/altech/$environment"
# shellcheck source=scripts/release-common.sh
source "$(dirname "$0")/release-common.sh"
require_root
exec 9>"$root/deploy.lock"
flock -n 9 || fail 'Another deployment or rollback is running'
[[ ! -e $root/current.next && ! -L $root/current.next ]] || fail 'Stale switch marker: inspect before continuing'
[[ ! -e $root/shared/storage/framework/down ]] || fail 'Application already in maintenance mode'
archive="$root/incoming/$sha/release.tar.gz"
[[ -f $archive && -f $archive.sha256 ]] || fail 'Release archive or checksum missing'
(cd "$root/incoming/$sha" && sha256sum --check release.tar.gz.sha256)
# Reject traversal and links before extraction, even when a checksum is valid.
python3 - "$archive" <<'PY'
import sys,tarfile,pathlib
with tarfile.open(sys.argv[1]) as archive:
    for member in archive.getmembers():
        path=pathlib.PurePosixPath(member.name)
        if path.is_absolute() or '..' in path.parts or not (member.isfile() or member.isdir()):
            raise SystemExit('Unsafe archive member')
PY
release="$root/releases/$sha-$(date -u +%Y%m%d%H%M%S)"
mkdir "$release"
tar -xzf "$archive" -C "$release" --no-same-owner
[[ $(php -r '$r=require $argv[1]; echo $r["commit"];' "$release/config/release.php") == "$sha" ]] || fail 'Release identity mismatch'
ln -s "$root/shared/.env" "$release/.env"
ln -s "$root/shared/storage" "$release/storage"
ln -s "$root/shared/storage/app/public" "$release/public/storage"
chmod -R g+rX "$release"
chmod g+w "$release/bootstrap/cache"
cd "$release"
composer check-platform-reqs --no-dev
php artisan package:discover --no-ansi
php artisan config:cache
php artisan deploy:check
catalog_python=$(php -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo config("catalog-import.python");')
"$catalog_python" -c 'import sys; sys.path.insert(0, "scripts"); import catalog_preview, supplier_price, catalog_photo_task, openpyxl, PIL'
url=$(php -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo config("app.url");')
[[ $url =~ ^https://[^/]+$ ]] || fail 'APP_URL must be an HTTPS origin without trailing slash'
previous=''
if [[ -L $root/current ]]; then previous=$(readlink -f "$root/current");
elif [[ -e $root/current ]]; then fail 'current must be a release symlink'; fi
[[ -z $previous || $previous == "$root/releases/"* ]] || fail 'Previous release outside release directory'
maintenance=false
workers_stopped=false
switched=false
recover() {
    code=$?
    trap - ERR
    echo "Release failed; database backup: $root/backups/$(basename "$release").sql.gz" >&2
    # A failed post-switch health check may happen after `up`; close traffic again.
    if [[ $maintenance == true ]]; then
        (cd "$release" && php artisan down --retry=60) || true
    fi
    if [[ $switched == true && -n $previous ]]; then switch_release "$previous" || true; fi
    if [[ -n $previous ]]; then
        cd "$previous"
        if [[ $maintenance == true ]]; then php artisan up || true; fi
        if [[ $workers_stopped == true ]]; then start_workers || true; fi
        echo 'Previous code restored. Database migrations are NOT automatically reversed.' >&2
    else
        echo 'Initial release failed; no previous code exists. Maintenance remains enabled for operator review.' >&2
    fi
    exit "$code"
}
trap recover ERR
php artisan down --retry=60
maintenance=true
workers_stopped=true
stop_workers
# Allow in-flight HTTP requests to drain before the database snapshot.
sleep 10
backup_shared "$(basename "$release")"
php artisan migrate --force --no-interaction
php artisan deploy:check --after-migrate
php artisan route:cache
php artisan view:cache
switch_release "$release"
switched=true
start_workers
php artisan up
health_check "$url" "$sha"
if php -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); exit(config("storefront.inertia") ? 0 : 1);'; then
    curl --fail --silent --show-error --max-time 30 "$url/catalog" | python3 -c 'import sys; html=sys.stdin.read(); sys.exit(not ("<h1" in html and "rel=\"canonical\"" in html))'
fi
printf '%s\n' "$previous" > "$root/previous-release"
trap - ERR
echo "Deployed $sha to $environment"
