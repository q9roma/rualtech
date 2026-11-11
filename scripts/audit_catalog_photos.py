"""Private, resumable photo quality audit. Does not access or mutate the database."""
import hashlib
import json
import sys
import time
import urllib.parse
import urllib.request
import warnings
from collections import Counter, defaultdict
from concurrent.futures import ThreadPoolExecutor, as_completed
from datetime import datetime, timezone
from io import BytesIO
from pathlib import Path
from PIL import Image, ImageStat
from review_icecat_candidates import accepted

BASE = Path(__file__).resolve().parent.parent / 'storage/app/private/catalog-automation'
ROOT = BASE / 'photo-quality'
MEDIA = BASE.parent / 'catalog-media-queue/media'

def media_path(file):
    path = MEDIA / file
    return path if path.exists() else ROOT / 'media' / file

HOST = 'images.vvpgroup.com'

def read(path):
    return json.loads(path.read_text(encoding='utf-8-sig'))

def write(path, data):
    temp = path.with_suffix('.tmp')
    temp.write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding='utf-8')
    temp.replace(path)

def valid_url(url):
    p = urllib.parse.urlsplit(url)
    return p.scheme == 'https' and p.hostname == HOST and not p.username and not p.password and p.port in (None, 443)

class SafeRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, url):
        if not valid_url(url):
            raise ValueError('Redirect outside approved HTTPS image host')
        return super().redirect_request(req, fp, code, msg, headers, url)

def inspect(raw):
    if len(raw) > 15_000_000:
        raise ValueError('File exceeds 15 MB')
    with warnings.catch_warnings():
        warnings.simplefilter('error', Image.DecompressionBombWarning)
        with Image.open(BytesIO(raw)) as im:
            im.load()
            ext = {'JPEG': 'jpg', 'PNG': 'png', 'WEBP': 'webp'}.get(im.format)
            if not ext:
                raise ValueError('Unsupported raster format')
            w, h = im.size
            sample = im.convert('RGBA')
            bg = Image.new('RGBA', sample.size, 'white')
            bg.alpha_composite(sample)
            sample = bg.convert('RGB'); sample.thumbnail((128, 128))
            variance = max(ImageStat.Stat(sample).stddev)
    flags = []
    if max(w, h) < 600 or min(w, h) < 200:
        flags.append('small')
    if variance < 3:
        flags.append('blank')
    return {'file': hashlib.sha256(raw).hexdigest()+'.'+ext, 'width': w, 'height': h,
            'bytes': len(raw), 'flags': flags}

def fetch(url):
    cache = ROOT / 'requests' / (hashlib.sha256(url.encode()).hexdigest()+'.json')
    if cache.exists():
        result = read(cache)
        if result.get('file'):
            path = media_path(result['file'])
            if path.is_file() and hashlib.sha256(path.read_bytes()).hexdigest() == path.stem:
                return result
        if '--cached' in sys.argv and result['status'] == 'error':
            return result
    if '--cached' in sys.argv:
        raise ValueError('Incomplete cache: '+url)
    result = {'url': url, 'source': 'VVP'}
    for attempt in range(2):
        try:
            if not valid_url(url):
                raise ValueError('Unapproved URL')
            req = urllib.request.Request(url, headers={'User-Agent': 'Altech-Catalog-Photo-Audit/1.0'})
            with urllib.request.build_opener(SafeRedirect).open(req, timeout=25) as response:
                mime = response.headers.get_content_type()
                raw = response.read(15_000_001)
            if mime not in ('image/jpeg', 'image/png', 'image/webp', 'application/octet-stream'):
                raise ValueError('Unexpected MIME: '+mime)
            result.update(inspect(raw))
            (MEDIA/result['file']).write_bytes(raw)
            result['status'] = 'decoded'
            result.pop('error', None)
            break
        except Exception as error:
            result.update(status='error', error=str(error))
            if attempt == 0:
                time.sleep(1)
    result['checked_at'] = datetime.now(timezone.utc).isoformat()
    write(cache, result)
    return result

def main():
    for directory in (ROOT, ROOT/'requests', MEDIA):
        directory.mkdir(parents=True,exist_ok=True)
    products = read(BASE/'icecat-full/input.json')
    icecat = {r['id']:r for r in read(BASE/'icecat-full/results.json')}
    decisions = {r['id']:r for r in read(BASE/'candidate-review/decisions.json')}
    confirmed = set(read(BASE/'icecat-full/coverage.json')['counts']['icecat_confirmed']['ids'])
    urls = sorted({u for p in products if p['vvp_status']=='matched' for u in p['vvp_images']})
    checked = {}
    with ThreadPoolExecutor(max_workers=32) as pool:
        futures = {pool.submit(fetch, u):u for u in urls}
        for future in as_completed(futures):
            url = futures[future]
            checked[url] = future.result()
            if len(checked)%100 == 0 or len(checked)==len(urls):
                progress = {'checked':len(checked), 'total':len(urls), 'errors':sum(r['status']=='error' for r in checked.values())}
                write(ROOT/'progress.json',progress)
                print(json.dumps(progress),flush=True)
    rows = []
    for p in products:
        images = [{**checked[u], 'primary': i == 0} for i,u in enumerate(p['vvp_images'])] if p['vvp_status']=='matched' else []
        r = icecat[p['id']]
        if p['id'] in confirmed:
            d = decisions.get(p['id'])
            image = r.get('downloaded_image') if r.get('strict_match') else d['image'] if accepted(r,d) else None
            if image:
                try:
                    raw = (BASE/'icecat-full/media'/image['file']).read_bytes()
                    meta = inspect(raw)
                    (MEDIA/meta['file']).write_bytes(raw)
                    images.append({**meta,'source':'Icecat','url':image['url'],'status':'decoded','primary':True})
                except Exception as e:
                    images.append({'source':'Icecat','status':'error','error':str(e)})
        rows.append({**p,'images':images})
    owners = defaultdict(set)
    for r in rows:
        for im in r['images']:
            if im.get('file'):
                owners[im['file']].add((r['brand'].casefold(),r['sku'].casefold()))
    for r in rows:
        for im in r['images']:
            if im.get('file') and len(owners[im['file']])>=3:
                im['flags'].append('shared_across_models')
        usable = [im for im in r['images'] if im['status']=='decoded' and not im['flags']]
        usable.sort(key=lambda im: (im.get('primary',False), im['width']*im['height']),reverse=True)
        unique = {}
        for im in usable:
            unique.setdefault(im['file'], im)
        r['gallery'] = list(unique.values())
        r['cover'] = r['gallery'][0] if r['gallery'] else None
    ids = {r['id'] for r in rows}
    sets = {source:{r['id'] for r in rows if any(im['source']==source and im['status']=='decoded' and not im['flags'] for im in r['images'])} for source in ('VVP','Icecat')}
    sets['combined'] = sets['VVP']|sets['Icecat']
    sets['uncovered'] = ids-sets['combined']
    sets['intersection'] = sets['VVP']&sets['Icecat']
    sections = []
    for section in sorted({r['section'] for r in rows}):
        subset = {r['id'] for r in rows if r['section']==section}
        sections.append({'name':section,'total':len(subset),'covered':len(subset&sets['combined']), 'percent':round(100*len(subset&sets['combined'])/len(subset),2)})
    report = {'denominator':len(ids),'generated_at':datetime.now(timezone.utc).isoformat(),
        'source_hashes': {name: hashlib.sha256((BASE/name).read_bytes()).hexdigest() for name in ('icecat-full/input.json','icecat-full/results.json','candidate-review/decisions.json')},
        'counts':{k:{'count':len(v),'percent':round(100*len(v)/len(ids),2)} for k,v in sets.items()},
        'vvp_urls':len(urls),'vvp_errors':sum(r['status']=='error' for r in checked.values()),
        'flags':dict(Counter(flag for r in rows for im in r['images'] for flag in im.get('flags',[]))),
        'sections':sections,'published':0,
        'method':'Technical suitability only: decoded raster <=15MB, long edge >=600px, short edge >=200px; reject blank and exact files shared by >=3 models. Identity from previous audit. Semantic placeholders, composition, kit and revision require visual review.'}
    write(ROOT/'review.json',rows)
    write(ROOT/'approved-media.json', {im['file']: True for r in rows for im in r['gallery']})
    write(ROOT/'coverage.json',report)
    print(json.dumps(report,ensure_ascii=False),flush=True)

if __name__ == '__main__':
    main()
