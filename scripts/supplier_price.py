"""32-column supplier profile; preserves source identifiers and warehouse detail."""
import re
import json
from pathlib import Path
from collections import Counter

HEADERS = ['Номенклатура', 'EAN', 'Артикул', 'Категория', 'Подкатегория', 'Бренд',
 'С клиент, предоплата, Цена с НДС', 'Предзаказ Предоплата 10%, руб', 'Предзаказ Предоплата 100%, руб',
 'Заказ', 'Ожидается', 'Склад Ворсино, Д-ое к-во', 'Склад Ворсино SRDC, Д-ое к-во',
 'Чашниково ЦД, Д-ое к-во', 'Чашниково ЦД Маркированные товары, Д-ое к-во',
 'Уценка 5% Чашниково ЦД, Д-ое к-во', 'Уценка 10% Чашниково ЦД, Д-ое к-во',
 'Уценка 20% Чашниково ЦД, Д-ое к-во', 'Уценка 50% Чашниково ЦД, Д-ое к-во',
 'Описание', 'ID', 'Гарантия', 'Длина упаковки, см', 'Ширина упаковки, см',
 'Высота упаковки, см', 'Объем упаковки, м3', 'Вес без упаковки, кг', 'Вес в упаковке, кг',
 'Кол-во в упаковке, шт', 'РРЦ, с НДС', 'Страна происхождения', 'Ссылка на товар']
SOURCE = 'supplier-price-32'
VERSION = 'b2b-supplier32-2026-11-09-v3'

def header_order(headers):
    """Map validated header names to canonical positions; reject missing/duplicate names."""
    import catalog_preview as base
    names = [base.clean(v) for v in headers]
    if len(names) != len(HEADERS) or len(set(names)) != len(names) or set(names) != set(HEADERS):
        raise ValueError('Unexpected supplier headers: missing, duplicate or unknown columns')
    return [names.index(name) for name in HEADERS]

def classify(category, sub, name):
    import catalog_preview as base
    if re.search(r'^(?:комплект (?:наволочек|постельного)|махровое полотенце|набор (?:кружек|чайных пар)|одеяло|подушка.*(?:для сна|pragma)|простыня|стакан pragma)', name, re.I):
        return 'exclude', '', '', 'supplier-textile-housewares'
    if category in base.EXCLUDE_CATEGORIES or category == 'Apparel' or sub in ['Apparel', 'Постельные принадлежности', 'Robots', 'Gaming Chairs', 'Smartwatches', 'Watches']:
        return 'exclude', '', '', 'supplier-nonbusiness'
    if category == 'CE' and sub == 'Home Appliances':
        return 'exclude', '', '', 'supplier-household'
    if category == 'Mobile':
        return 'request', '', '', 'supplier-mobile'
    overrides = {
        ('Computer Components', 'HDD Enterprise'): (base.SERVER, 'Накопители для серверов и NAS'),
        ('Computer Components', 'SSD Enterprise'): (base.SERVER, 'Накопители для серверов и NAS'),
        ('Computer Components', 'RAM Enterprise'): (base.SERVER, 'Серверная оперативная память'),
        ('Displays', 'Commercial and Hospitality TV'): (base.PERIPH, 'Профессиональные дисплеи'),
        ('Displays', 'Interactive Solutions'): (base.PERIPH, 'Интерактивные панели'),
        ('Multimedia', 'Digital Signage'): (base.PERIPH, 'Профессиональные дисплеи'),
    }
    if (category, sub) in overrides:
        root, leaf = overrides[(category, sub)]
        return 'include', root, leaf, 'supplier-'+re.sub('[^a-z0-9]+', '-', sub.lower()).strip('-')
    if category in ['Computer Peripherals', 'Lifestyle Audio', 'Audio & video'] and re.search(r'^(?:игровая )?гарнитура|^наушники с микрофоном', name, re.I):
        return 'include', base.PERIPH, 'Гарнитуры', 'supplier-headset'
    if category == 'Computer Peripherals' and re.search(r'^клавиатура\s*\+\s*мышь', name, re.I):
        return 'include', base.PERIPH, 'Комплекты клавиатура и мышь', 'supplier-keyboard-set'
    if category == 'IT' and re.search(r'^аккумуляторная батарея|^аккумулятор (?:wbr|csb)\b', name, re.I):
        return 'include', base.POWER, 'Аккумуляторы для ИБП', 'supplier-ups-battery'
    if not category and sub == 'IT': category = 'IT'
    return base.classify(category, name)

def load_rows(source):
    import openpyxl
    import catalog_preview as base
    approved = {r['supplier_id']: r for r in json.loads((Path(__file__).resolve().parent.parent/'config/catalog-reviewed-supplier.json').read_text(encoding='utf-8'))}
    w = openpyxl.load_workbook(source, read_only=True, data_only=True)
    try:
        if len(w.worksheets) != 1: raise ValueError('Expected one sheet')
        s = w.active
        if s.max_row > 50001 or s.max_column != 32: raise ValueError('Expected 32 columns and up to 50000 rows')
        it = s.iter_rows(values_only=True)
        order = header_order(next(it))
        records, skipped = [], []
        for number, cells in enumerate(it, 2):
            if number > 50001: raise ValueError('Too many rows')
            cells = [cells[i] for i in order]
            v = [base.clean(x) for x in cells]
            if not any(v): skipped.append(number); continue
            errors = []
            status, section, subcategory, rule = classify(v[3], v[4], v[0])
            if v[20] in approved:
                decision = approved[v[20]]
                if base.clean(decision['sku']).casefold() == v[2].casefold() and base.clean(decision['brand']).casefold() == v[5].casefold():
                    status, section, subcategory, rule = 'include', decision['section'], decision['subcategory'], decision['rule']
                else:
                    status = 'review'
                    errors.append('reviewed_identity_changed')
            price = base.price_value(cells[6])
            if not price: errors.append('invalid_price')
            if not v[20]: errors.append('missing_supplier_id')
            if not v[5]: errors.append('missing_brand')
            if not v[2]: errors.append('missing_pn')
            if not v[0]: errors.append('missing_name')
            stock_values = []
            for i in range(10, 19):
                if not re.fullmatch(r'\d+', v[i]):
                    errors.append('invalid_stock'); stock_values.append(None)
                else: stock_values.append(int(v[i]))
            normal = stock_values[1:5]
            available = str(sum(normal)) if all(x is not None for x in normal) else ''
            stock = [available, available, v[10]]
            source_category = ' / '.join(x for x in [v[3], v[4]] if x)
            raw = [x.isoformat() if hasattr(x, 'isoformat') else x for x in cells]
            records.append(dict(row=number, raw=[source_category,v[5],v[2],v[0],*stock,price,'RUB'],
                source_category=source_category,brand=v[5],sku=v[2],name=v[0], stock=stock,price=price,currency='RUB',
                status=status,section=section,subcategory=subcategory,rule=rule,errors=errors,
                key=base.identity(v[5],v[2],v[0]),supplier_id=v[20],source_profile=SOURCE,
                supplier_data=dict(zip(HEADERS,raw))))
        counts = Counter(r['supplier_id'] for r in records)
        for r in records:
            if counts[r['supplier_id']] > 1: r['errors'].append('duplicate_supplier_id')
        return records, skipped, s.title
    finally: w.close()
