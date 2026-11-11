"""Frozen manual model review. Writes private evidence only; never assigns photos."""
import hashlib
import json
from pathlib import Path

BASE = Path(__file__).resolve().parent.parent / 'storage/app/private/catalog-automation'
HELD = {
    1321: 'В прайсе готовый экран 163 дюйма; модель Icecat и производителя — отдельный LED-кабинет. Состав комплекта не подтверждён.',
    2958: 'Это лицензия LG, а не физическое оборудование. Иллюстрацию нельзя учитывать как фотографию товара.',
    3022: 'В прайсе яркость 300, у Icecat и производителя 330 кд/м². Требуется уточнение данных поставщика.',
    3064: 'В прайсе яркость 390, в Icecat 400 кд/м². Различие не объяснено.',
    3065: 'В прайсе яркость 390, в Icecat 400 кд/м². Различие не объяснено.',
    3066: 'В прайсе яркость 390, в Icecat 400 кд/м². Различие не объяснено.',
    3117: 'В прайсе XGA, у Icecat и производителя 1280×800. Требуется уточнение описания поставщика.',
    3181: 'Региональная версия приставки не определена; разрешение Icecat расходится с официальным описанием. Разъёмы и версия требуют проверки.',
}
RESOLVED = {
    2967: ('Яркость 300 типовая / 260 минимальная: различие объяснено производителем.', 'https://www.lg.com/global/business/commercial-display/digital-signage/professional-tv/50pk640s-sca/'),
    2971: ('Официальная версия EU/CIS подтверждает 330 кд/м² из прайса.', 'https://www.lg.com/global/business/commercial-display/digital-signage-after/professional-tv/86un640s-eucis/'),
    3020: ('Официальная спецификация подтверждает 330 кд/м² из прайса; значение Icecat не переносим.', 'https://www.lg.com/ru/business/professional-display/lg-75ul3j-e'),
    3029: ('Full HD в заголовке Icecat ошибочно: подробные характеристики и производитель подтверждают 3840×2160.', 'https://www.usa.philips.com/p-p/43BDL3650Q_00/signage-solutions-q-line-display'),
    3031: ('Официальная спецификация /00 подтверждает 400 кд/м² из прайса.', 'https://www.documents.philips.com/assets/20230613/af18b07810ac45b9a3b8b020012422cb.pdf'),
    3120: ('380 кд/м² после защитного стекла подтверждены производителем.', 'https://www.usa.philips.com/p-p/75BDL3052E_00/signage-solutions-e-line-display'),
    3122: ('Официальная спецификация /00 подтверждает 400 кд/м² после стекла.', 'https://www.documents.philips.com/assets/20230530/173487a9b04a4074b9d2b01200609461.pdf'),
}

def fingerprint(row):
    keys = ('id', 'brand', 'sku', 'name', 'ean', 'icecat_id', 'returned_brand', 'returned_mpn', 'returned_gtins', 'image')
    return hashlib.sha256(json.dumps({k: row.get(k) for k in keys}, sort_keys=True, ensure_ascii=False).encode()).hexdigest()

def accepted(row, decision):
    if not decision or decision['outcome'] != 'confirmed' or decision['fingerprint'] != fingerprint(row):
        return False
    if not row.get('mpn_review') or row.get('ean_conflict'):
        return False
    file = decision.get('image', {}).get('file', '')
    if Path(file).name != file or not file:
        return False
    path = BASE / 'icecat-full/media' / file
    return path.is_file() and hashlib.sha256(path.read_bytes()).hexdigest() == decision.get('image_sha256')

def main():
    root = BASE / 'candidate-review'
    ids = set(json.loads((root / 'targets.json').read_text()))
    results = json.loads((BASE / 'icecat-full/results.json').read_text(encoding='utf-8'))
    existing = root / 'decisions.json'
    if existing.exists():
        frozen = {d['id']: d for d in json.loads(existing.read_text(encoding='utf-8'))}
        assert set(frozen) == ids
        assert all(frozen[r['id']]['fingerprint'] == fingerprint(r) for r in results if r['id'] in ids), 'Evidence changed: a new manual review is required; refusing to replace decisions.'
        print('Existing manual decisions retained.')
        return
    decisions = []
    for row in results:
        if row['id'] not in ids:
            continue
        evidence = json.loads((root / f"{row['id']}.json").read_text(encoding='utf-8'))
        assert row['mpn_review'] and not row['ean_conflict']
        assert str(evidence['general']['IcecatId']) == str(row['icecat_id'])
        image = evidence.get('image')
        assert image
        reason = HELD.get(row['id'], 'Подтверждено соответствие модели: точные бренд и полный артикул; характеристики сопоставлены с описанием прайса. EAN не подтверждён. Комплектация и аппаратная ревизия отдельно не подтверждены.')
        sources = [evidence['source']]
        if row['id'] in RESOLVED:
            note, url = RESOLVED[row['id']]
            reason += ' ' + note
            sources.append(url)
        decisions.append({**{k: row[k] for k in ('id', 'brand', 'sku', 'name')},
            'outcome': 'held' if row['id'] in HELD else 'confirmed', 'reason': reason,
            'fingerprint': fingerprint(row), 'sources': sources, 'features': evidence['features'],
            'icecat_title': evidence['general']['Title'], 'image': image,
            'image_sha256': hashlib.sha256((BASE / 'icecat-full/media' / image['file']).read_bytes()).hexdigest()})
    assert len(decisions) == 71
    (root / 'decisions.json').write_text(json.dumps(decisions, ensure_ascii=False, indent=2), encoding='utf-8')
    print(json.dumps({'reviewed': len(decisions), 'confirmed': sum(d['outcome'] == 'confirmed' for d in decisions), 'held': len(HELD)}))

if __name__ == '__main__':
    main()
