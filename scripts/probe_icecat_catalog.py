"""Resumable read-only audit of the complete current catalog; no storefront writes."""
import json
import time
from collections import Counter, defaultdict
from concurrent.futures import ThreadPoolExecutor, as_completed
from datetime import datetime, timezone
from pathlib import Path
import probe_icecat as api
import prepare_professional_review as media
from review_icecat_candidates import accepted
from catalog_automation import offers, match

PRIVATE = Path(__file__).resolve().parent.parent / 'storage/app/private'
BASE = PRIVATE / 'catalog-automation'
OUT = BASE / 'icecat-full'


def read(path):
    return json.loads(path.read_text(encoding='utf-8-sig'))


def write(path, data):
    temporary = path.with_suffix('.tmp')
    for attempt in range(4):
        try:
            temporary.write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding='utf-8')
            temporary.replace(path)
            return
        except PermissionError:
            if attempt == 3:
                raise
            time.sleep(0.25 * (attempt + 1))


def check(product):
    cached = OUT / (str(product['id']) + '.json')
    if cached.exists():
        old = read(cached)
        if all(old.get(k) == product.get(k) for k in ('brand', 'sku', 'ean')) and old.get('full_checked') and old['status'] != 'error':
            if not old.get('strict_match') or old.get('downloaded_image') or old.get('image_absent'):
                return {**old, 'category': product['category'], 'section': product['section'], 'vvp_status': product['vvp_status']}
    seed = None
    for directory in (OUT, BASE / 'icecat-professional', PRIVATE / 'icecat-pilot'):
        path = directory / (str(product['id']) + '.json')
        if path.exists():
            candidate = read(path)
            if all(candidate.get(k, '') == product.get(k, '') for k in ('brand', 'sku', 'ean')) and candidate.get('status') in ('candidate', 'identity_review', 'restricted', 'not_found'):
                seed = candidate
                break
    if seed is None:
        # probe() has its own cache: remove stale data only by overwriting it with a retryable record.
        if cached.exists():
            write(cached, {**product, 'status': 'error'})
        result = api.probe(product)
        if result['status'] == 'error':
            throttled = any(a['status'] == 429 for a in result['attempts'])
            time.sleep(60 if throttled else 2)
            result = api.probe(product)
    else:
        result = {**seed, **product}
    brand = result.get('returned_brand') or ''
    if isinstance(brand, dict):
        brand = brand.get('Name', '')
    result['exact_brand'] = str(brand).strip().casefold() == product['brand'].strip().casefold()
    result['exact_ean'] = bool(product['ean']) and product['ean'] in (result.get('returned_gtins') or [])
    result['ean_conflict'] = bool(product['ean'] and result.get('returned_gtins')) and not result['exact_ean']
    result['strict_match'] = bool(result['status'] == 'candidate' and result['exact_brand'] and result['exact_ean'] and not result['ean_conflict'])
    result['mpn_review'] = bool(result['status'] == 'candidate' and result['exact_brand'] and result.get('exact_mpn') and not result['ean_conflict'] and not result['strict_match'])
    if result['strict_match'] and not (result.get('image') or {}).get('HighPic'):
        result['image_absent'] = True
        result.pop('download_error', None)
    elif result['strict_match'] and not result.get('downloaded_image'):
        try:
            result['downloaded_image'] = media.download((result.get('image') or {}).get('HighPic', ''))
            result.pop('download_error', None)
        except Exception as error:
            result['download_error'] = str(error)
    result.update(full_checked=True, auto_assign=False, checked_at=datetime.now(timezone.utc).isoformat())
    write(cached, result)
    return result


def main():
    OUT.mkdir(exist_ok=True)
    api.ROOT = OUT
    media.MEDIA = OUT / 'media'
    media.MEDIA.mkdir(exist_ok=True)
    source = read(BASE / 'current-coverage-input.json')
    settings = read(BASE / 'sources.json')
    index = offers(Path(settings['media_feeds'][0]).read_bytes())
    cats = {c['id']: c for c in source['categories']}
    codes = defaultdict(set)
    for link in source['links']:
        value = json.loads(link['supplier_data'] or '{}').get('EAN')
        if value:
            codes[link['product_id']].add(str(value))
    products = []
    for p in source['products']:
        category = cats.get(p['catalog_category_id'], {})
        parent = cats.get(category.get('parent_id'), category)
        row = {k: p[k] for k in ('id', 'brand', 'sku', 'name')}
        row.update(ean=next(iter(codes[p['id']])) if len(codes[p['id']]) == 1 else '',
                   category=category.get('name', 'Без категории'), section=parent.get('name', 'Без раздела'))
        row['vvp_status'], row['vvp_images'] = match(row, index)
        products.append(row)
    write(OUT / 'input.json', products)
    results = []
    with ThreadPoolExecutor(max_workers=2) as pool:
        futures = {pool.submit(check, p): p for p in products}
        for future in as_completed(futures):
            try:
                results.append(future.result())
            except Exception as error:
                product = futures[future]
                print('ERROR '+str(product['id'])+': '+repr(error), flush=True)
                failed = {**product, 'status': 'error', 'error': repr(error), 'strict_match': False,
                          'mpn_review': False, 'ean_conflict': False, 'full_checked': True, 'auto_assign': False}
                write(OUT / (str(product['id']) + '.json'), failed)
                results.append(failed)
            if len(results) % 25 == 0 or len(results) == len(products):
                progress = {'checked': len(results), 'total': len(products), 'statuses': dict(Counter(r['status'] for r in results))}
                write(OUT / 'progress.json', progress)
                print(json.dumps(progress), flush=True)
    results.sort(key=lambda r: r['id'])
    assert len(results) == len({r['id'] for r in results}) == len(products)
    write(OUT / 'results.json', results)
    ids = {r['id'] for r in results}
    vvp = {r['id'] for r in results if r['vvp_status'] == 'matched'}
    icecat = {r['id'] for r in results if r['strict_match'] and r.get('downloaded_image')}
    ean_confirmed = set(icecat)
    decision_file = BASE / 'candidate-review/decisions.json'
    decisions = {d['id']: d for d in read(decision_file)} if decision_file.exists() else {}
    mpn_confirmed = {r['id'] for r in results if accepted(r, decisions.get(r['id']))}
    icecat |= mpn_confirmed
    candidates = {r['id'] for r in results if r['mpn_review'] and (r.get('image_check') or {}).get('status') == 200}
    candidates -= mpn_confirmed
    sets = {'vvp': vvp, 'icecat_confirmed': icecat, 'intersection': vvp & icecat, 'icecat_adds': icecat - vvp,
            'combined': vvp | icecat, 'uncovered': ids - (vvp | icecat), 'icecat_review_only': candidates,
            'potential_after_review': vvp | icecat | candidates, 'icecat_tested': ids}
    sets.update(icecat_ean_confirmed=ean_confirmed, icecat_mpn_confirmed=mpn_confirmed)
    sections = []
    for name in sorted({r['section'] for r in results}):
        subset = {r['id'] for r in results if r['section'] == name}
        sections.append({'name': name, 'total': len(subset), 'vvp': len(subset & vvp), 'icecat': len(subset & icecat),
                         'combined': len(subset & (vvp | icecat)), 'percent': round(100 * len(subset & (vvp | icecat)) / len(subset), 2)})
    report = {'denominator': len(ids), 'generated_at': datetime.now(timezone.utc).isoformat(), 'scope': 'full-current-catalog',
              'method': 'VVP: exact identity and image URL in XML. Icecat: brand/EAN or separately reviewed full MPN/specifications; downloaded image. Model identity does not verify kit/revision. No assignment or publication.',
              'counts': {k: {'count': len(v), 'percent': round(100 * len(v) / len(ids), 2), 'ids': sorted(v)} for k, v in sets.items()},
              'statuses': dict(Counter(r['status'] for r in results)), 'sections': sections,
              'ean_conflicts': sum(r['ean_conflict'] for r in results),
              'strict_matches_without_image': sum(bool(r.get('image_absent')) for r in results),
              'download_errors': sum(bool(r.get('download_error')) for r in results), 'published': 0}
    write(OUT / 'coverage.json', report)
    print(json.dumps({**report, 'counts': {k: {a: b for a, b in v.items() if a != 'ids'} for k, v in report['counts'].items()}}, ensure_ascii=False), flush=True)


if __name__ == '__main__':
    main()
