"""One bounded private photo task. No database writes or storefront manifest changes."""
import hashlib
import json
import sys
import urllib.parse
import urllib.request
from datetime import datetime, timezone
from pathlib import Path
from audit_catalog_photos import inspect
from catalog_automation import offers, match
from probe_icecat import request

BASE = Path(__file__).resolve().parent.parent / 'storage/app/private'
MEDIA = BASE / 'catalog-media-queue/media'
HOSTS = {'images.vvpgroup.com', 'images.icecat.biz'}

def read(path):
    return json.loads(path.read_text(encoding='utf-8-sig'))

def allowed(url):
    p = urllib.parse.urlsplit(url)
    return p.scheme == 'https' and p.hostname in HOSTS and not p.username and not p.password and p.port in (None,443)

class Redirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, url):
        if not allowed(url):
            raise ValueError('Unapproved image redirect')
        return super().redirect_request(req, fp, code, msg, headers, url)

def image(url):
    if not allowed(url):
        raise ValueError('Unapproved image URL')
    # Reuse a verified downloaded file; never reuse a cached download failure forever.
    cache = BASE / 'catalog-automation/photo-quality/requests' / (hashlib.sha256(url.encode()).hexdigest()+'.json')
    raw = None
    if cache.exists():
        meta = read(cache)
        if meta.get('status') == 'decoded' and meta.get('file'):
            path = MEDIA / meta['file']
            if not path.exists(): path = BASE / 'catalog-automation/photo-quality/media' / meta['file']
            if path.is_file():
                candidate = path.read_bytes()
                if hashlib.sha256(candidate).hexdigest() == path.stem: raw = candidate
    own = MEDIA / (hashlib.sha256(url.encode()).hexdigest()+'.json')
    if raw is None and own.exists():
        meta = read(own); path = MEDIA/meta['file']
        if path.is_file():
            candidate = path.read_bytes()
            if hashlib.sha256(candidate).hexdigest() == path.stem: raw = candidate
    if raw is None:
        with urllib.request.build_opener(Redirect).open(url,timeout=15) as response:
            if response.headers.get_content_type() not in ('image/jpeg','image/png','image/webp','application/octet-stream'):
                raise ValueError('Unexpected image MIME')
            raw = response.read(15_000_001)
    meta = {**inspect(raw), 'url':url}
    # Identical images shared by different models were held in the full audit.
    rejected = BASE / 'catalog-automation/photo-quality/review.json'
    if rejected.exists():
        if any(im.get('file') == meta['file'] and 'shared_across_models' in im.get('flags',[]) for r in read(rejected) for im in r['images']):
            meta['flags'].append('shared_across_models')
    (MEDIA/meta['file']).write_bytes(raw)
    own.write_text(json.dumps(meta),encoding='utf-8')
    return meta

def icecat(product):
    cached = BASE / 'catalog-automation/icecat-full' / (str(product['id'])+'.json')
    if cached.exists():
        row = read(cached)
        age = (datetime.now(timezone.utc)-datetime.fromisoformat(row.get('checked_at','2000-01-01T00:00:00+00:00'))).total_seconds()
        if age < 7*86400 and all(row.get(k,'') == product.get(k,'') for k in ('brand','sku','ean')) and row.get('status') != 'error':
            return row
    attempts = []
    queries = [{'Brand':product['brand'],'ProductCode':product['sku']}]
    if product['ean']: queries.append({'GTIN':product['ean']})
    for params in queries:
        code, body = request(params)
        attempts.append(code)
        if code == 0 or code == 429 or code >= 500:
            return {'status':'retry','error':'Icecat temporarily unavailable: '+str(code)}
        if code == 200 and body.get('data'):
            data = body['data']; info = data.get('GeneralInfo') or {}
            return {'status':'candidate','returned_brand':info.get('Brand'), 'returned_mpn':info.get('BrandPartCode'),
                'returned_gtins':list(map(str,info.get('GTIN') or [])),'icecat_id':info.get('IcecatId'), 'image':data.get('Image') or {}}
        if code not in (400,403,404): return {'status':'retry','error':'Unexpected Icecat response '+str(code)}
    return {'status':'restricted' if 403 in attempts else 'not_found'}

def identity(product, row):
    brand = row.get('returned_brand') or ''
    if isinstance(brand,dict): brand = brand.get('Name','')
    brand_match = str(brand).strip().casefold() == product['brand'].strip().casefold()
    mpn = str(row.get('returned_mpn') or '').strip().casefold() == product['sku'].strip().casefold()
    gtins = row.get('returned_gtins') or []
    conflict = bool(product['ean'] and gtins and product['ean'] not in gtins)
    if conflict or not brand_match: return 'conflict'
    if product['ean'] and product['ean'] in gtins: return 'confirmed'
    return 'review' if mpn else 'conflict'

def run(product):
    MEDIA.mkdir(parents=True,exist_ok=True)
    result = {'status':'not_found','product':product,'images':[],'sources':[], 'errors':[], 'auto_assign':False,'published':0}
    if product.get('ean_conflict') or not product['brand'] or not product['sku']:
        return {**result,'status':'review','error':'Missing identity or conflicting supplier EANs'}
    settings = BASE/'catalog-automation/sources.json'
    feeds = read(settings).get('media_feeds',[]) if settings.exists() else []
    for filename in feeds[:1]:
        try:
            path = Path(filename)
            if path.stat().st_size > 20_000_000: raise ValueError('Feed exceeds 20 MB')
            raw = path.read_bytes()
            status, urls = match(product,offers(raw))
            result['sources'].append({'source':'VVP','status':status,'feed_sha256':hashlib.sha256(raw).hexdigest(),'feed_mode':'local-file'})
            if status == 'matched':
                for url in urls[:2]:
                    try: result['images'].append({**image(url),'source':'VVP','identity':'confirmed'})
                    except Exception as e: result['errors'].append('VVP image: '+str(e))
                    if any(not [flag for flag in im['flags'] if flag not in ('shared_across_models', 'perceptual_match')] for im in result['images']): break
        except Exception as e:
            result['errors'].append('VVP feed: '+str(e))
    row = icecat(product)
    result['sources'].append({'source':'Icecat','status':row['status'],'icecat_id':row.get('icecat_id')})
    verdict = identity(product,row) if row['status'] in ('candidate','identity_review') else None
    if verdict:
        result['identity_evidence'] = {'verdict':verdict, 'brand':row.get('returned_brand'),
            'mpn':row.get('returned_mpn'), 'gtins':row.get('returned_gtins') or [], 'icecat_id':row.get('icecat_id')}
    if verdict in ('confirmed', 'review') and (row.get('image') or {}).get('HighPic'):
        try: result['images'].append({**image(row['image']['HighPic']),'source':'Icecat','identity':verdict})
        except Exception as e: result['errors'].append('Icecat image: '+str(e))
    usable = [im for im in result['images'] if not [flag for flag in im['flags'] if flag not in ('shared_across_models', 'perceptual_match')] and im['identity'] == 'confirmed']
    if usable and len(usable) == len(result['images']): result['status']='ready'
    elif verdict in ('review','conflict') or result['images']:
        result['status']='review'
        result['review_reason'] = ('Brand/MPN matched but EAN not confirmed' if verdict == 'review' else
            'Identity conflict' if verdict == 'conflict' else 'Images failed quality checks')
    elif row['status'] == 'retry' or result['errors']: result['status']='retry'
    elif row['status'] == 'restricted': result['status']='restricted'
    if row.get('error'): result['errors'].append(row['error'])
    if result['errors']: result['error']='; '.join(result['errors'])
    result['checked_at']=datetime.now(timezone.utc).isoformat()
    return result

if __name__ == '__main__':
    directory=Path(sys.argv[1])
    output=run(read(directory/'input.json'))
    temporary=directory/'result.tmp'
    temporary.write_text(json.dumps(output,ensure_ascii=False,indent=2),encoding='utf-8')
    temporary.replace(directory/'result.json')
    print(output['status'])
