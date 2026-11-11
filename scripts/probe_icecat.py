"""Read-only demo coverage audit. Never attaches media or writes to the database."""
import json
import time
import urllib.request
import urllib.error
import urllib.parse
from pathlib import Path
from concurrent.futures import ThreadPoolExecutor, as_completed
from collections import Counter

ROOT = Path(__file__).resolve().parent.parent / 'storage/app/private/icecat-pilot'

def request(params):
    url = 'https://live.icecat.biz/api?' + urllib.parse.urlencode({'lang':'EN','shopname':'openicecat-live', **params})
    try:
        with urllib.request.urlopen(url, timeout=25) as response:
            return response.status, json.load(response)
    except urllib.error.HTTPError as error:
        try: body = json.loads(error.read())
        except Exception: body = {}
        return error.code, body
    except Exception as error:
        return 0, {'msg':str(error)}

def probe(product):
    path = ROOT / (str(product['id']) + '.json')
    if path.exists():
        cached = json.loads(path.read_text(encoding='utf-8'))
        if cached['status'] != 'error': return cached
    result = {**product, 'attempts':[]}
    lookups = [('mpn', {'Brand':product['brand'], 'ProductCode':product['sku']})]
    if product.get('ean'): lookups.append(('ean', {'GTIN':str(product['ean'])}))
    for method, params in lookups:
        code, body = request(params)
        result['attempts'].append({'method':method, 'status':code, 'message':body.get('msg') or body.get('Message'), 'error_code':body.get('StatusCode')})
        data = body.get('data') or {}
        if code == 200 and data:
            info = data.get('GeneralInfo') or {}
            gtins = [str(v) for v in info.get('GTIN', [])]
            exact_mpn = str(info.get('BrandPartCode', '')).strip().casefold() == product['sku'].strip().casefold()
            exact_ean = bool(product.get('ean')) and str(product['ean']) in gtins
            result.update(method=method, icecat_id=info.get('IcecatId'), returned_brand=info.get('Brand'),
                          returned_mpn=info.get('BrandPartCode'), returned_gtins=gtins, title=info.get('Title'),
                          exact_mpn=exact_mpn, exact_ean=exact_ean, image=data.get('Image'),
                          gallery_count=len(data.get('Gallery') or []), status='candidate' if exact_mpn or exact_ean else 'identity_review')
            image = (data.get('Image') or {}).get('HighPic')
            if image:
                try:
                    # Verify a small portion only; no image file is stored or published.
                    with urllib.request.urlopen(image, timeout=25) as response:
                        sample = response.read(64)
                        result['image_check']={'status':response.status,'mime':response.headers.get('Content-Type'),'nonempty':bool(sample)}
                except Exception as error: result['image_check']={'error':str(error)}
            break
        if code in [429, 0] or code >= 500: break
        time.sleep(0.2)
    if 'status' not in result:
        codes = [a['status'] for a in result['attempts']]
        result['status'] = 'restricted' if 403 in codes else 'not_found' if all(a['status']==404 or (a['status']==400 and a.get('error_code')==16) for a in result['attempts']) else 'error'
    path.write_text(json.dumps(result,ensure_ascii=False,indent=2),encoding='utf-8')
    return result

if __name__ == '__main__':
    ROOT.mkdir(parents=True, exist_ok=True)
    products=json.loads((ROOT.parent/'icecat-pilot-input.json').read_text(encoding='utf-8'))
    results=[]
    with ThreadPoolExecutor(max_workers=2) as pool:
        for future in as_completed([pool.submit(probe,p) for p in products]):
            results.append(future.result())
            if len(results)%10==0: print('Checked',len(results),flush=True)
    results.sort(key=lambda r:r['id'])
    for result in results:
        result['ean_conflict'] = bool(result.get('ean') and result.get('returned_gtins')) and not result.get('exact_ean', False)
    (ROOT/'results.json').write_text(json.dumps(results,ensure_ascii=False,indent=2),encoding='utf-8')
    summary={'total':len(results),'statuses':dict(Counter(r['status'] for r in results)),
             'ean_conflicts':sum(r['status']=='candidate' and r['ean_conflict'] for r in results),
             'ean_fallback_matches':sum(r.get('method')=='ean' for r in results),
             'verified_image_candidates':sum(r['status']=='candidate' and (r.get('image_check') or {}).get('status')==200 and (r.get('image_check') or {}).get('mime','').startswith('image/') for r in results)}
    (ROOT/'summary.json').write_text(json.dumps(summary,indent=2),encoding='utf-8')
    print(json.dumps(summary),flush=True)
