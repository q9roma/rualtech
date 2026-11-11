"""Read-only sample probes of explicit manufacturer pages; no catalog writes."""
import concurrent.futures
import json
import re
import urllib.parse
import urllib.request
from html.parser import HTMLParser
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent / 'storage/app/private/catalog-automation'
SAMPLES = [
    ('Samsung', 'LH65WMBWBGCXCI', 'https://www.samsung.com/ru/business/smart-signage/interactive-display/flip-pro-wm65b-interactive-display-lh65wmbwbgcxci/', 'exact'),
    ('Samsung', 'LH43BECHLGUXCI', 'https://www.samsung.com/ru/business/smart-signage/uhd-4k-signage/bec-h-biztv-uhd-4k-biztv-lh43bechlguxci/', 'exact'),
    ('LG', 'LSBF015-GD', 'https://www.lg.com/ae/business/led-signage/lg-lsbf015-gd', 'exact_model_check_bundle'),
    ('LG', 'LSCB018-GK', 'https://www.lg.com/uk/business/commercial-display/led-signage/indoor-led/lscb018-gk/', 'exact_model_check_bundle'),
    ('Philips', '55HFL4518U/12', 'https://www.usa.philips.com/p-p/55HFL4518U_12/professional-tv/overview', 'exact'),
    ('Hisense', '55DM66GE', 'https://www.hisense-b2b.com/us/43-86-4k-uhd-digital-signage-display', 'multi_model_page'),
    ('AET', 'NX COB', 'https://aetdisplays.com/product/nx-cob-anode/', 'series_only'),
    ('AET', 'Koala', 'https://aetledindia.com/product/koala-240x540/', 'series_only'),
    ('Novastar', 'VX600', 'https://www.novastar.tech/product/detail.html?catid=3&id=35', 'multi_model_page'),
    ('LIGA Group', 'IK 32', 'https://liga-smart.ru/page70141215.html', 'different_model_not_match'),
    ('EWIN', 'DM55A', 'https://global.ewin-tech.com/', 'homepage_not_match'),
]


class Images(HTMLParser):
    def __init__(self):
        super().__init__()
        self.images = []
        self.text = []

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == 'meta' and (a.get('property') or a.get('name')) in ('og:image', 'twitter:image'):
            self.images.append({'kind': 'meta', 'url': a.get('content', ''), 'alt': ''})
        if tag == 'img':
            for key in ('data-desktop-src', 'data-src', 'src'):
                if a.get(key) and not a[key].startswith('data:'):
                    self.images.append({'kind': 'img', 'url': a[key], 'alt': a.get('alt', '')})
                    break

    def handle_data(self, data):
        self.text.append(data)


def probe(sample):
    brand, sku, url, identity = sample
    result = dict(brand=brand, sku=sku, page=url, identity=identity, auto_assign=False)
    try:
        with urllib.request.urlopen(url, timeout=25) as response:
            raw = response.read(5_000_001)
            if len(raw) > 5_000_000:
                raise ValueError('Page too large')
            result.update(http=response.status, final_url=response.url)
        parser = Images()
        parser.feed(raw.decode('utf-8', errors='replace'))
        result['model_in_page_text'] = sku.casefold() in ' '.join(parser.text).casefold()
        images = []
        seen = set()
        for img in parser.images:
            full = urllib.parse.urljoin(result['final_url'], img['url'])
            if urllib.parse.urlsplit(full).scheme not in ('http', 'https') or full in seen:
                continue
            seen.add(full)
            images.append({**img, 'url': full, 'model_in_alt_or_url': sku.casefold() in (img['alt'] + full).casefold()})
        result['images'] = images
        result['image_count'] = len(images)
        result['model_image_count'] = sum(i['model_in_alt_or_url'] for i in images)
        result['status'] = 'page_read'
    except Exception as error:
        result.update(status='failed', error=str(error))
    return result


if __name__ == '__main__':
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
        results = list(pool.map(probe, SAMPLES))
    (ROOT / 'manufacturer-source-probes.json').write_text(json.dumps(results, ensure_ascii=False, indent=2), encoding='utf-8')
    print(json.dumps([{k: v for k, v in r.items() if k != 'images'} for r in results], ensure_ascii=False, indent=2))
