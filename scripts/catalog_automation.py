"""Private supplier checks. Never modifies sources, database, or storefront manifests."""
import hashlib
import json
import shutil
import subprocess
import sys
import time
import urllib.parse
import urllib.request
import warnings
import zipfile
from collections import Counter, defaultdict
from io import BytesIO
from pathlib import Path
from xml.etree import ElementTree as ET

from PIL import Image

VERSION = '1'
DOWNLOAD_VERSION = 2
PROJECT = Path(__file__).resolve().parent.parent


def digest(raw):
    return hashlib.sha256(raw).hexdigest()


def write(path, value):
    tmp = path.with_suffix('.tmp')
    tmp.write_text(json.dumps(value, ensure_ascii=False, indent=2), encoding='utf-8')
    tmp.replace(path)


def read(path):
    return json.loads(path.read_text(encoding='utf-8-sig'))


def source(path, suffix):
    path = Path(path)
    if path.suffix.lower() != suffix or not path.is_file():
        raise ValueError('Source missing or wrong extension')
    if time.time() - path.stat().st_mtime < 60:
        raise ValueError('Source is still being written; retry next cycle')
    if path.stat().st_size > 20_000_000:
        raise ValueError('Source exceeds 20 MB')
    return path.read_bytes()


def offers(raw):
    if b'\x00' in raw or b'<!DOCTYPE' in raw.upper() or b'<!ENTITY' in raw.upper():
        raise ValueError('DTD/entities are not supported')
    root = ET.fromstring(raw)
    if root.tag != 'yml_catalog':
        raise ValueError('Expected yml_catalog')
    result = defaultdict(list)
    ids = set()
    for offer in root.findall('./shop/offers/offer'):
        identity = offer.get('id', '').strip()
        if not identity or identity in ids:
            raise ValueError('Missing or duplicate offer ID')
        ids.add(identity)
        key = tuple((offer.findtext(k) or '').strip().casefold() for k in ('vendor', 'vendorCode'))
        if all(key):
            result[key].append({'ean': identity, 'pictures': list(dict.fromkeys(p.text.strip() for p in offer.findall('picture') if p.text))})
    if not ids:
        raise ValueError('Empty feed')
    return result


def match(product, index):
    candidates = index.get(tuple(str(product.get(k) or '').strip().casefold() for k in ('brand', 'sku')), [])
    if not candidates:
        return 'missing', []
    if len(candidates) != 1:
        return 'ambiguous', []
    candidate = candidates[0]
    if not product.get('ean') or product['ean'] != candidate['ean']:
        return 'ean_conflict_or_missing', []
    return ('matched' if candidate['pictures'] else 'no_photo'), candidate['pictures']


def allowed_url(url):
    parsed = urllib.parse.urlsplit(url)
    return (parsed.scheme == 'https' and parsed.hostname == 'images.vvpgroup.com'
            and parsed.port in (None, 443) and not parsed.username and not parsed.password)


class SafeRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        if not allowed_url(newurl):
            raise ValueError('Redirect outside approved image host')
        return super().redirect_request(req, fp, code, msg, headers, newurl)


def download(url, folder):
    if not allowed_url(url):
        return {'status': 'rejected_url'}
    key = digest(url.encode())
    meta = folder / (key + '.json')
    if meta.exists():
        cached = read(meta)
        if cached['status'] == 'verified':
            image = folder / cached['file']
            if image.exists() and digest(image.read_bytes()) == cached['sha256']:
                return cached
        elif cached.get('version') == DOWNLOAD_VERSION and time.time() - cached['checked_at'] < 3600:
            return cached
    try:
        with urllib.request.build_opener(SafeRedirect).open(url, timeout=15) as response:
            if response.headers.get_content_type() not in ('image/jpeg', 'image/png', 'image/webp'):
                raise ValueError('Unexpected content type')
            raw = response.read(15_000_001)
        if len(raw) > 15_000_000:
            raise ValueError('Image exceeds 15 MB')
        with warnings.catch_warnings():
            warnings.simplefilter('error', Image.DecompressionBombWarning)
            with Image.open(BytesIO(raw)) as image:
                width, height = image.size
                ext = {'JPEG': 'jpg', 'PNG': 'png', 'WEBP': 'webp'}.get(image.format)
                image.verify()
            with Image.open(BytesIO(raw)) as image:
                image.load()
        if not ext or min(width, height) < 200:
            raise ValueError('Unsupported format or image smaller than 200 px')
        filename = digest(raw) + '.' + ext
        (folder / filename).write_bytes(raw)
        result = {'status': 'verified', 'file': filename, 'sha256': digest(raw), 'width': width, 'height': height}
    except Exception as error:
        result = {'status': 'failed', 'error': str(error)}
    result.update(checked_at=time.time(), source_url=url, version=DOWNLOAD_VERSION)
    write(meta, result)
    return result


def run(folder):
    settings = read(folder / 'sources.json')
    report = {'mode': 'dry-run', 'at': time.time(), 'prices': [], 'feeds': [], 'published': 0}
    snapshot = (folder / 'products.json').read_bytes() + (folder / 'context.json').read_bytes()
    rules = b''.join((PROJECT / p).read_bytes() for p in (
        'scripts/catalog_preview.py', 'scripts/supplier_price.py', 'config/catalog-taxonomy.json',
        'config/catalog-reviewed-supplier.json', 'app/Admin/CatalogDraftImport.php',
        'app/Admin/CatalogPriceWorkflow.php', 'config/catalog-import.php'))
    for filename in settings.get('prices', []):
        try:
            raw = source(filename, '.xlsx')
            key = digest(raw + snapshot + rules + VERSION.encode())
            target = folder / 'prices' / key
            target.mkdir(parents=True, exist_ok=True)
            if not (target / 'summary.json').exists():
                with zipfile.ZipFile(BytesIO(raw)) as z:
                    if len(z.infolist()) > 10000 or sum(i.file_size for i in z.infolist()) > 200_000_000:
                        raise ValueError('Expanded XLSX exceeds limits')
                (target / 'source.xlsx').write_bytes(raw)
                shutil.copyfile(folder / 'products.json', target / 'snapshot.json')
                subprocess.run([sys.executable, str(PROJECT / 'scripts/catalog_preview.py'), str(target / 'source.xlsx'),
                                '--existing', str(target / 'snapshot.json'), '--output', str(target)],
                               check=True, timeout=180, capture_output=True)
            report['prices'].append({'source': filename, 'status': 'prepared', 'directory': str(target)})
        except Exception as error:
            report['prices'].append({'source': filename, 'status': 'failed', 'error': str(error)})
    products = read(folder / 'media-products.json')
    cache = folder / 'images'
    cache.mkdir(exist_ok=True)
    budget = min(60, max(0, int(settings.get('image_budget', 20))))
    for filename in settings.get('media_feeds', []):
        try:
            raw = source(filename, '.xml')
            index = offers(raw)
            rows = []
            for product in sorted(products, key=lambda p: (not p['active'], p['id'])):
                status, urls = match(product, index)
                row = {**product, 'status': status, 'picture_urls': urls, 'images': []}
                # Published sample first; private candidates only, one cover per product in this pilot.
                if status == 'matched' and product['active'] and urls:
                    url = urls[0]
                    cached = cache / (digest(url.encode()) + '.json')
                    ready = cached.exists() and read(cached)['status'] == 'verified'
                    cooling = cached.exists() and read(cached).get('version') == DOWNLOAD_VERSION and time.time() - read(cached)['checked_at'] < 3600
                    if ready or cooling or budget > 0:
                        row['images'] = [download(url, cache)]
                        if not ready and not cooling:
                            budget -= 1
                rows.append(row)
            feed = {'source': filename, 'status': 'checked', 'sha256': digest(raw),
                    'coverage': dict(Counter(r['status'] for r in rows)),
                    'verified_covers': sum(any(i['status'] == 'verified' for i in r['images']) for r in rows),
                    'products': rows}
            write(folder / ('media-' + digest(raw) + '.json'), feed)
            report['feeds'].append({k: v for k, v in feed.items() if k != 'products'})
        except Exception as error:
            report['feeds'].append({'source': filename, 'status': 'failed', 'error': str(error)})
    write(folder / 'latest.json', report)
    print(json.dumps(report, ensure_ascii=False))


if __name__ == '__main__':
    run(Path(sys.argv[1]).resolve())
