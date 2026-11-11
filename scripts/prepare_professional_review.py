"""Download private review media, recheck identities, and report source coverage."""
import hashlib
import json
import re
import urllib.parse
import urllib.request
import warnings
from collections import Counter
from concurrent.futures import ThreadPoolExecutor, as_completed
from io import BytesIO
from pathlib import Path
from PIL import Image

BASE = Path(__file__).resolve().parent.parent / 'storage/app/private'
ROOT = BASE / 'catalog-automation/icecat-professional'
MEDIA = ROOT / 'media'


class SafeRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, url):
        if urllib.parse.urlsplit(url).hostname != 'images.icecat.biz' or not url.startswith('https://'):
            raise ValueError('Image redirect outside approved host')
        return super().redirect_request(req, fp, code, msg, headers, url)


def download(url):
    parsed = urllib.parse.urlsplit(url)
    if parsed.scheme != 'https' or parsed.hostname != 'images.icecat.biz' or parsed.username or parsed.port not in (None, 443):
        raise ValueError('Unapproved image URL')
    with urllib.request.build_opener(SafeRedirect).open(url, timeout=25) as response:
        mime = response.headers.get_content_type()
        raw = response.read(15_000_001)
    if len(raw) > 15_000_000 or mime not in ('image/jpeg', 'image/png', 'image/webp'):
        raise ValueError('Invalid size or MIME')
    with warnings.catch_warnings():
        warnings.simplefilter('error', Image.DecompressionBombWarning)
        with Image.open(BytesIO(raw)) as image:
            image.load()
            width, height = image.size
            ext = {'JPEG': 'jpg', 'PNG': 'png', 'WEBP': 'webp'}.get(image.format)
    if not ext:
        raise ValueError('Unsupported image format')
    filename = hashlib.sha256(raw).hexdigest() + '.' + ext
    (MEDIA / filename).write_bytes(raw)
    return {'file': filename, 'width': width, 'height': height, 'url': url,
            'small': min(width, height) < 400}


def prepare(row):
    result = {**row, 'review_images': [], 'review_flags': [], 'review_errors': [], 'auto_assign': False}
    try:
        query = urllib.parse.urlencode({'lang': 'EN', 'shopname': 'openicecat-live', 'icecat_id': row['icecat_id']})
        with urllib.request.urlopen('https://live.icecat.biz/api?' + query, timeout=25) as response:
            data = json.load(response)['data']
        info = data['GeneralInfo']
        brand = info.get('Brand', '')
        if isinstance(brand, dict):
            brand = brand.get('Name', '')
        if str(brand).casefold() != row['brand'].casefold() or str(info.get('BrandPartCode', '')).strip().casefold() != row['sku'].strip().casefold():
            raise ValueError('Brand or MPN changed')
        gtins = list(map(str, info.get('GTIN') or []))
        if row['usable_candidate'] and row['ean'] not in gtins:
            raise ValueError('EAN no longer matches')
        result['fresh_gtins'] = gtins
        result['fresh_title'] = info.get('Title', '')
        result['identity_rechecked'] = True
        if not row['usable_candidate']:
            result['review_flags'].append('EAN не подтверждён')
        if row['sku'] == 'LWSMB.AL':
            result['review_flags'].append('Лицензия ПО, не физический дисплей')
        if row['sku'] == 'LSCB018-GK':
            result['review_flags'].append('Проверить комплект: в прайсе экран 163″, модель производителя относится к LED-кабинету')
        title = result['fresh_title']
        if re.search(r'\bUHD\b|\b4K\b', row['name'], re.I) and 'Full HD' in title and '4K' not in title:
            result['review_flags'].append('Расхождение разрешения: прайс UHD/4K, заголовок Icecat Full HD')
        urls = list(dict.fromkeys([data.get('Image', {}).get('HighPic')] + [g.get('Pic') for g in data.get('Gallery', []) if g.get('Type') == 'ProductImage']))
        urls = [u for u in urls if u][:4 if row['usable_candidate'] else 1]
        for url in urls:
            try:
                image = download(url)
                result['review_images'].append(image)
                if image['small']:
                    result['review_flags'].append('У изображения сторона меньше 400 px')
            except Exception as error:
                result['review_errors'].append(str(error))
        if not result['review_images']:
            result['review_errors'].append('Нет загруженного изображения')
    except Exception as error:
        result['review_errors'].append(str(error))
    result['review_flags'] = list(dict.fromkeys(result['review_flags']))
    return result


def main():
    MEDIA.mkdir(exist_ok=True)
    rows = json.loads((ROOT / 'results.json').read_text(encoding='utf-8'))
    selected = [r for r in rows if r['brand'] in ('LG', 'Philips') and r['status'] == 'candidate']
    assert len(selected) == 67
    results = []
    with ThreadPoolExecutor(max_workers=2) as pool:
        for future in as_completed([pool.submit(prepare, r) for r in selected]):
            results.append(future.result())
            if len(results) % 10 == 0:
                print(f'Prepared {len(results)}/67', flush=True)
    results.sort(key=lambda r: (not r['usable_candidate'], r['brand'], r['sku']))
    (ROOT / 'review.json').write_text(json.dumps(results, ensure_ascii=False, indent=2), encoding='utf-8')
    current = json.loads((BASE / 'catalog-automation/current-coverage.json').read_text(encoding='utf-8'))
    ids = {r['id'] for r in current['products']}
    vvp = {r['id'] for r in current['products'] if r['status'] == 'matched'}
    old = json.loads((BASE / 'icecat-pilot/preview.json').read_text(encoding='utf-8'))
    old_ids = {r['id'] for r in old if r.get('exact_ean') and not r.get('ean_conflict') and r.get('preview_images')} & ids
    new_ids = {r['id'] for r in results if r['usable_candidate'] and r.get('identity_rechecked') and r['review_images']}
    icecat = old_ids | new_ids
    candidates = {r['id'] for r in results if not r['usable_candidate'] and r.get('identity_rechecked') and r['review_images']}
    tested = {r['id'] for r in json.loads((BASE / 'icecat-pilot/results.json').read_text(encoding='utf-8'))} | {r['id'] for r in rows}
    sets = {'vvp': vvp, 'icecat_confirmed': icecat, 'intersection': vvp & icecat,
            'icecat_adds': icecat - vvp, 'combined': vvp | icecat, 'uncovered': ids - (vvp | icecat),
            'icecat_review_only': candidates, 'potential_after_review': vvp | icecat | candidates,
            'icecat_tested': tested & ids}
    report = {'denominator': len(ids), 'scope': 'current imported catalog, published and drafts',
              'method': 'VVP: exact identifiers and image URLs; Icecat: exact EAN and downloaded pilot images. Candidates excluded from confirmed coverage.',
              'counts': {k: {'count': len(v), 'percent': round(100 * len(v) / len(ids), 2), 'ids': sorted(v)} for k, v in sets.items()},
              'review_products': len(results), 'downloaded_products': sum(bool(r['review_images']) for r in results),
              'downloaded_images': sum(len(r['review_images']) for r in results),
              'products_with_errors': sum(bool(r['review_errors']) for r in results),
              'flags': dict(Counter(flag for r in results for flag in r['review_flags']))}
    (ROOT / 'coverage.json').write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding='utf-8')
    print(json.dumps({**report, 'counts': {k: {a: b for a, b in v.items() if a != 'ids'} for k, v in report['counts'].items()}}, ensure_ascii=False, indent=2))


if __name__ == '__main__':
    main()
