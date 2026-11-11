"""Download a limited, private review gallery. No database/public media writes."""
import json
import urllib.request
import urllib.parse
from io import BytesIO
from pathlib import Path
from concurrent.futures import ThreadPoolExecutor
from PIL import Image

ROOT = Path(__file__).resolve().parent.parent / 'storage/app/private/icecat-pilot'

def fetch(url):
    for attempt in range(3):
        try:
            with urllib.request.urlopen(url,timeout=20) as response: return response.read(15_000_001)
        except Exception:
            if attempt == 2: raise

def prepare(row):
    folder = ROOT / 'media' / str(row['id'])
    folder.mkdir(parents=True, exist_ok=True)
    url='https://live.icecat.biz/api?'+urllib.parse.urlencode({'lang':'EN','shopname':'openicecat-live','icecat_id':row['icecat_id']})
    data=json.loads(fetch(url))['data']
    if str(row['ean']) not in [str(v) for v in data['GeneralInfo'].get('GTIN',[])]:
        raise ValueError('Identity changed')
    urls=list(dict.fromkeys([data['Image']['HighPic']]+[g['Pic'] for g in data.get('Gallery',[]) if g.get('Pic') and g.get('Type')=='ProductImage']))[:4]
    images=[]
    for url in urls:
        if urllib.parse.urlparse(url).hostname != 'images.icecat.biz': continue
        raw=fetch(url)
        if len(raw)>15_000_000: continue
        with Image.open(BytesIO(raw)) as image:
            image.verify()
        with Image.open(BytesIO(raw)) as image:
            ext={'JPEG':'jpg','PNG':'png','WEBP':'webp'}.get(image.format)
            if not ext: continue
            width,height=image.size
        filename=f'{len(images)}.{ext}'
        (folder/filename).write_bytes(raw)
        images.append({'file':filename,'source_url':url,'width':width,'height':height})
    print(f"{row['id']}: {len(images)} images",flush=True)
    return {**row,'preview_images':images}

if __name__=='__main__':
    rows=json.loads((ROOT/'results.json').read_text(encoding='utf-8'))
    selected=[r for r in rows if r['status']=='candidate' and r['exact_ean'] and not r['ean_conflict']]
    with ThreadPoolExecutor(max_workers=2) as pool: results=list(pool.map(prepare,selected))
    (ROOT/'preview.json').write_text(json.dumps(results,ensure_ascii=False,indent=2),encoding='utf-8')
    print('Ready:',len(results),sum(len(r['preview_images']) for r in results),flush=True)
