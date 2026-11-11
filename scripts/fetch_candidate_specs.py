"""Collect evidence for the frozen 71 MPN candidates; no catalog changes."""
import json
import urllib.request
from concurrent.futures import ThreadPoolExecutor, as_completed
from pathlib import Path
import prepare_professional_review as media

BASE = Path(__file__).resolve().parent.parent / 'storage/app/private/catalog-automation'
ROOT = BASE / 'candidate-review'


def fetch(row):
    path = ROOT / (str(row['id']) + '.json')
    if path.exists():
        return json.loads(path.read_text(encoding='utf-8'))
    url = 'https://live.icecat.biz/api?lang=EN&shopname=openicecat-live&icecat_id=' + str(row['icecat_id'])
    with urllib.request.urlopen(url, timeout=25) as response:
        data = json.load(response)['data']
    features = {}
    for group in data.get('FeaturesGroups', []):
        for f in group['Features']:
            features[f['Feature']['Name']['Value']] = {'raw': f.get('RawValue'), 'value': f.get('PresentationValue')}
    result = {'id': row['id'], 'sku': row['sku'], 'brand': row['brand'], 'source': url,
              'general': data['GeneralInfo'], 'features': features, 'image': None}
    try:
        result['image'] = media.download(data['Image']['HighPic'])
    except Exception as error:
        result['image_error'] = str(error)
    path.write_text(json.dumps(result, ensure_ascii=False, indent=2), encoding='utf-8')
    return result


if __name__ == '__main__':
    ROOT.mkdir(exist_ok=True)
    media.MEDIA = BASE / 'icecat-full/media'
    frozen = ROOT / 'targets.json'
    if not frozen.exists():
        report = json.loads((BASE / 'icecat-full/coverage.json').read_text(encoding='utf-8'))
        frozen.write_text(json.dumps(report['counts']['icecat_review_only']['ids']), encoding='utf-8')
    ids = set(json.loads(frozen.read_text(encoding='utf-8')))
    rows = [r for r in json.loads((BASE / 'icecat-full/results.json').read_text(encoding='utf-8')) if r['id'] in ids]
    assert len(rows) == 71
    with ThreadPoolExecutor(max_workers=2) as pool:
        futures = {pool.submit(fetch, r): r for r in rows}
        for n, future in enumerate(as_completed(futures), 1):
            try:
                future.result()
            except Exception as error:
                print('ERROR', futures[future]['id'], repr(error), flush=True)
            if n % 10 == 0:
                print('Collected', n, '/71', flush=True)
