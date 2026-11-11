"""Isolated 161-product Icecat demo audit; never writes catalog or pilot manifest."""
import json
from collections import Counter, defaultdict
from concurrent.futures import ThreadPoolExecutor, as_completed
from pathlib import Path
import probe_icecat as icecat

BASE = Path(__file__).resolve().parent.parent / 'storage/app/private/catalog-automation'
OUT = BASE / 'icecat-professional'


def main():
    OUT.mkdir(exist_ok=True)
    icecat.ROOT = OUT
    targets = json.loads((BASE / 'professional-media-targets.json').read_text(encoding='utf-8-sig'))
    selected = [r for r in targets if r['brand'] in ('Samsung', 'LG', 'Philips')]
    assert len(selected) == 161 and len({r['id'] for r in selected}) == 161
    source = json.loads((BASE / 'current-coverage-input.json').read_text(encoding='utf-8'))
    eans = defaultdict(set)
    for link in source['links']:
        ean = json.loads(link['supplier_data'] or '{}').get('EAN')
        if ean:
            eans[link['product_id']].add(str(ean))
    products = [{**r, 'ean': next(iter(eans[r['id']])) if len(eans[r['id']]) == 1 else '',
                 'vvp_status': r['status']} for r in selected]
    for product in products:
        product.pop('status', None)
    (OUT / 'input.json').write_text(json.dumps(products, ensure_ascii=False, indent=2), encoding='utf-8')
    results = []
    with ThreadPoolExecutor(max_workers=2) as pool:
        for future in as_completed([pool.submit(icecat.probe, p) for p in products]):
            r = future.result()
            # Old audit inputs carried VVP's status: never treat it as Icecat's result.
            if r.get('status') in ('missing', 'no_photo'):
                attempts = r['attempts']
                codes = [a['status'] for a in attempts]
                r['status'] = ('restricted' if 403 in codes else 'not_found'
                               if attempts and all(a['status'] == 404 or (a['status'] == 400 and a.get('error_code') == 16) for a in attempts)
                               else 'error')
            r['ean_conflict'] = bool(r.get('ean') and r.get('returned_gtins')) and not r.get('exact_ean', False)
            returned = r.get('returned_brand') or ''
            if isinstance(returned, dict):
                returned = returned.get('Name', '')
            r['exact_brand'] = str(returned).strip().casefold() == r['brand'].strip().casefold()
            check = r.get('image_check') or {}
            r['usable_candidate'] = bool(r['status'] == 'candidate' and r['exact_brand'] and r.get('exact_ean')
                                         and not r['ean_conflict'] and check.get('status') == 200
                                         and check.get('mime', '').startswith('image/') and check.get('nonempty'))
            r['auto_assign'] = False
            (OUT / (str(r['id']) + '.json')).write_text(json.dumps(r, ensure_ascii=False, indent=2), encoding='utf-8')
            results.append(r)
            if len(results) % 10 == 0:
                print(f'Checked {len(results)}/161', flush=True)
    results.sort(key=lambda r: r['id'])
    def summary(rows):
        return {'total': len(rows), 'statuses': dict(Counter(r['status'] for r in rows)),
                'exact_ean_image_candidates': sum(r['usable_candidate'] for r in rows),
                'ean_conflicts': sum(r['ean_conflict'] for r in rows),
                'vvp_statuses': dict(Counter(r['vvp_status'] for r in rows))}
    report = {'mode': 'demo-read-only', 'published': 0, **summary(results),
              'brands': {b: summary([r for r in results if r['brand'] == b]) for b in ('Samsung', 'LG', 'Philips')}}
    (OUT / 'results.json').write_text(json.dumps(results, ensure_ascii=False, indent=2), encoding='utf-8')
    (OUT / 'summary.json').write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding='utf-8')
    print(json.dumps(report, ensure_ascii=False, indent=2), flush=True)


if __name__ == '__main__':
    main()
