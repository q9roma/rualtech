#!/usr/bin/env bash
set -Eeuo pipefail
umask 027

sha=${1:?Usage: build-release.sh COMMIT_SHA}
[[ $sha =~ ^[a-f0-9]{40}$ ]] || { echo 'Invalid commit SHA' >&2; exit 1; }
[[ -f vendor/autoload.php && -f public/build/manifest.json && -f bootstrap/ssr/ssr.js ]]
[[ ! -e public/hot ]] || { echo 'Refusing a development asset marker' >&2; exit 1; }
mkdir -p dist
stage=$(mktemp -d)
[[ $stage == /* && -d $stage && ! -L $stage && $(basename "$stage") == tmp.* ]] || { echo 'Unsafe staging directory' >&2; exit 1; }
trap 'rm -rf -- "$stage"' EXIT
# Explicit allowlist: no root utilities, secrets, .git, local databases or uploaded files.
tar --exclude='bootstrap/cache/*.php' --exclude='public/storage' --exclude='public/hot' \
    --exclude='.git' --exclude='.bin' --exclude='.env' --exclude='.env.*' \
    -cf - app bootstrap config database public resources routes vendor node_modules artisan composer.json composer.lock package.json package-lock.json \
    | tar -xf - -C "$stage"
printf "<?php\nreturn ['commit' => '%s'];\n" "$sha" > "$stage/config/release.php"
mkdir -p "$stage/scripts" "$stage/bootstrap/cache"
cp scripts/deploy-release.sh scripts/rollback-release.sh scripts/release-common.sh "$stage/scripts/"
cp scripts/catalog_preview.py scripts/supplier_price.py scripts/catalog_automation.py scripts/catalog_photo_task.py \
    scripts/audit_catalog_photos.py scripts/probe_icecat.py scripts/review_icecat_candidates.py "$stage/scripts/"
cp deploy/catalog-requirements.txt "$stage/scripts/"
tar -czf dist/release.tar.gz -C "$stage" .
(cd dist && sha256sum release.tar.gz > release.tar.gz.sha256)
echo "Release packaged for $sha"
