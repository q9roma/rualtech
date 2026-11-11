"""Offline, read-only XLSX import rehearsal. Never connects to or writes a database.

Requires openpyxl. Existing products are supplied as a read-only JSON snapshot.
Rules are ordered: exclusion/on-request, specific type, category-scoped fallback.
Unknown types and ambiguous identities always require review.
"""
import argparse
import csv
import hashlib
import json
import re
from collections import Counter, defaultdict
from datetime import datetime, timezone
from decimal import Decimal, InvalidOperation
from pathlib import Path

VERSION = 'b2b-2026-11-09-v2'
HEADERS = ['Вид оборудования', 'Бренд', 'PN', 'Наименование', 'Есть', 'Свободно', 'Транзит', 'Цена', 'Валюта']
EXCLUDE_CATEGORIES = {'Home Appliances', 'Cleaning equipment', 'Garden Tools', 'Power tools', 'КЛИМАТИЧЕСКОЕ ОБОРУДОВАНИЕ', 'Entertainment', 'Pro Audio'}
KNOWN_CATEGORIES = EXCLUDE_CATEGORIES | {
    '', 'ПО Microsoft (Windows, Office, Windows Server)', 'Audio & video', 'IT',
    'Computer Components', 'Computer Peripherals', 'Network Equipment', 'Cable Equipment',
    'Enterprise', 'Mobile', 'Mobile phone accessories', 'CE', 'Multimedia', 'Cables and Adapters',
    'Displays', 'Interactive Solutions', 'LED Displays', 'Projectors', 'ЗАПЧАСТИ',
    'ИБП, АКБ, СЕТЕВЫЕ ФИЛЬТРЫ', 'КОМПЛЕКТУЮЩИЕ ПК', 'КОМПЬЮТЕРНАЯ КАБЕЛЬНАЯ ПРОДУКЦИЯ',
    'КОМПЬЮТЕРНАЯ ПЕРИФЕРИЯ, КЛАВИАТУРЫ, МЫШКИ', 'МОНИТОРЫ', 'МОНОБЛОКИ, БРЕНДОВЫЕ ПК',
    'НОСИТЕЛИ ИНФОРМАЦИИ', 'НОУТБУКИ', 'ОФИСНАЯ ТЕЛЕФОНИЯ', 'ОФИСНОЕ И ТОРГОВОЕ ОБОРУДОВАНИЕ',
    'ПЕЧАТНАЯ ТЕХНИКА, СКАНЕРЫ, КОПИРЫ', 'ПЛАНШЕТЫ, ЭЛЕКТРОННЫЕ КНИГИ',
    'ПРОГРАММНОЕ ОБЕСПЕЧЕНИЕ', 'РАСХОДНЫЕ МАТЕРИАЛЫ И ЧИСТЯЩИЕ СРЕДСТВА',
    'СЕРВЕРЫ И СХД', 'СЕТЕВОЕ ОБОРУДОВАНИЕ', 'СКС', 'СМАРТФОНЫ, МОБИЛЬНЫЕ ТЕЛЕФОНЫ',
    'ТЕЛЕВИЗОРЫ', 'УМНЫЕ КОЛОНКИ, АККУСТИЧЕСКИЕ СИСТЕМЫ',
}
PC = 'Компьютеры и ноутбуки'
SERVER = 'Серверы и хранение данных'
NETWORK = 'Сетевое оборудование'
PARTS = 'Комплектующие'
PERIPH = 'Мониторы и периферия'
PRINT = 'Печать и расходные материалы'
POWER = 'Электропитание'
SOFT = 'Программное обеспечение'

def clean(value):
    if value is None:
        return ''
    if isinstance(value, float) and value.is_integer():
        return str(int(value))
    return re.sub(r'\s+', ' ', str(value)).strip()

# Each rule has a stable ID recorded in every output row. Patterns use product
# type, not the mere presence of a keyword in a compatibility description.
RULES = [
    ('furniture-auto', 'exclude', '', '', r'^(?:[кk]ресло|стол компьютерный|зарядное устройство автомобильное|датчик присутствия.*яндекс|климатический модуль яндекса|кейпад)', None),
    ('mobile-charger', 'request', '', '', r'^адаптер сетевой', ['Mobile phone accessories']),
    ('cleaning-peripherals', 'include', PERIPH, 'Средства ухода за техникой', r'^распылитель пневматический|^салфетки для экранов', ['Mobile phone accessories']),
    ('ups-accessories', 'include', POWER, 'Мониторинг и монтаж ИБП', r'^датчик.*cyberpower|^сетевая карта (?:cyberpower|сайбер электро)|^комплект креплений (?:cyberpower|импульс)', ['IT']),
    ('ups-battery-models', 'include', POWER, 'Аккумуляторы для ИБП', r'^аккумулятор (?:wbr|csb)\b', ['ИБП, АКБ, СЕТЕВЫЕ ФИЛЬТРЫ']),
    ('printing-accessories', 'include', PRINT, 'Запчасти и аксессуары для печати', r'^комплект перезаправки|^выходной лоток|^контроллер печати|^крышка konica minolta оригинала', ['IT']),
    ('server-ram', 'include', SERVER, 'Серверная оперативная память', r'^модуль памяти synology', ['Computer Components']),
    ('windows-other', 'include', SOFT, 'Операционные системы', r'^по microsoft windows', ['ПРОГРАММНОЕ ОБЕСПЕЧЕНИЕ']),
    ('nonbusiness', 'exclude', '', '', r'^(?:геймпад|игровая приставка|смарт[ -]?часы|фитнес|часы |видеорегистратор автомобильный|автомобильная |гуманоид|сервисный робот|кресло|3d)', None),
    ('household', 'exclude', '', '', r'^(?:фен|выпрямитель|стайлер|триммер|электробритва|электрическая зуб|портативная |пати |умн(?:ая|ый|ое) |саундбар|колонка|акустическая|[сc]истема акустическая)', None),
    ('mobile-request', 'request', '', '', r'^(?:смартфон|планшет|мобильный |электронная книга|защитное стекло|чехол|стилус|сетевое зарядное|адаптер питания|внешний аккумулятор|беспроводное зарядное)', None),
    ('tv-request', 'request', '', '', r'^телевизор', None),
    ('display-mount-review', 'review', PERIPH, 'Крепления и стойки', r'^(?:кронштейн|стойка)', ['ТЕЛЕВИЗОРЫ']),
    ('webcam', 'include', PERIPH, 'Веб-камеры', r'^(?:камера-web|веб-камера)', None),
    ('speakerphone', 'include', PERIPH, 'Спикерфоны', r'^спикерфон', None),
    ('headset', 'include', PERIPH, 'Гарнитуры', r'^(?:гарнитура|наушники с микрофоном)', None),
    ('audio-review', 'review', PERIPH, 'Оборудование переговорных', r'^(?:наушники|беспроводные|микрофон|звуковая)', None),
    ('interactive', 'include', PERIPH, 'Интерактивные панели', r'.', ['Interactive Solutions']),
    ('led', 'include', PERIPH, 'Светодиодные экраны и модули', r'.', ['LED Displays']),
    ('professional-display', 'include', PERIPH, 'Профессиональные дисплеи', r'.', ['Displays']),
    ('projector', 'include', PERIPH, 'Проекторы', r'^проектор', None),
    ('phone', 'include', PERIPH, 'Офисная телефония', r'.', ['ОФИСНАЯ ТЕЛЕФОНИЯ']),
    ('office-software', 'include', SOFT, 'Офисные приложения', r'office|visio|project', ['ПО Microsoft (Windows, Office, Windows Server)']),
    ('server-software', 'include', SOFT, 'Серверное ПО и лицензии доступа', r'server|\bcal\b', ['ПО Microsoft (Windows, Office, Windows Server)']),
    ('windows', 'include', SOFT, 'Операционные системы', r'windows', ['ПО Microsoft (Windows, Office, Windows Server)']),
    ('antivirus', 'include', SOFT, 'Антивирусы и защита', r'kaspersky', ['ПРОГРАММНОЕ ОБЕСПЕЧЕНИЕ']),
    ('nas', 'include', SERVER, 'Системы хранения данных', r'^(?:схд|сетевое хранилище|сетевой накопитель)', ['Enterprise', 'СЕРВЕРЫ И СХД']),
    ('server-drive', 'include', SERVER, 'Накопители для серверов и NAS', r'^(?:жесткий|жёсткий|накопитель)', ['СЕРВЕРЫ И СХД']),
    ('enterprise-wifi', 'include', NETWORK, 'Точки доступа и Wi-Fi-системы', r'^точка доступа', ['Enterprise']),
    ('server-parts', 'include', SERVER, 'Серверные комплектующие', r'.', ['Enterprise', 'СЕРВЕРЫ И СХД']),
    ('laptop', 'include', PC, 'Ноутбуки', r'^ноутбук', None),
    ('aio', 'include', PC, 'Моноблоки', r'^моноблок', None),
    ('mini-pc', 'include', PC, 'Мини-ПК', r'^неттоп|^мини-пк', None),
    ('desktop', 'include', PC, 'Настольные компьютеры', r'^пк |^компьютер|^рабочая станция', None),
    ('monitor', 'include', PERIPH, 'Мониторы', r'^монитор\b', None),
    ('monitor-mount', 'include', PERIPH, 'Крепления и стойки', r'^кронштейн', ['МОНИТОРЫ']),
    ('bags', 'include', PERIPH, 'Сумки и рюкзаки для ноутбуков', r'^(?:сумка|рюкзак).*ноутбук', None),
    ('laptop-stand', 'include', PERIPH, 'Подставки для ноутбуков', r'^подставка для ноутбука', None),
    ('docks', 'include', PERIPH, 'Док-станции и USB-хабы', r'^(?:док-станция|usb[ -]?хаб|usb хаб|концентратор usb)', None),
    ('keyboard-set', 'include', PERIPH, 'Комплекты клавиатура и мышь', r'^(?:клавиатура\+мышь|комплект)', ['Computer Peripherals', 'КОМПЬЮТЕРНАЯ ПЕРИФЕРИЯ, КЛАВИАТУРЫ, МЫШКИ']),
    ('keyboard', 'include', PERIPH, 'Клавиатуры', r'^клавиатура', None),
    ('mouse', 'include', PERIPH, 'Мыши и трекпады', r'^мышь|^трекпад', None),
    ('mousepad', 'include', PERIPH, 'Коврики для мыши', r'^коврик', None),
    ('switch', 'include', NETWORK, 'Коммутаторы', r'^коммутатор|^управляемый.*коммутатор|^l2/l3 коммутатор', None),
    ('router', 'include', NETWORK, 'Маршрутизаторы', r'^маршрутизатор|^роутер', None),
    ('wifi', 'include', NETWORK, 'Точки доступа и Wi-Fi-системы', r'^(?:уличная )?точка доступа|^система.*(?:wi-fi|mesh)|^беспроводной.*(?:маршрутизатор|точка)', ['Network Equipment', 'СЕТЕВОЕ ОБОРУДОВАНИЕ']),
    ('antenna', 'include', NETWORK, 'Антенны', r'^антенна', ['Network Equipment']),
    ('cellular-modem', 'include', NETWORK, 'Сетевые адаптеры и модемы', r'^5g модем', ['Network Equipment']),
    ('network-adapter', 'include', NETWORK, 'Сетевые адаптеры и модемы', r'^(?:сетевой|адаптер|модем|беспроводной)', ['Network Equipment', 'СЕТЕВОЕ ОБОРУДОВАНИЕ']),
    ('structured-cabling', 'include', NETWORK, 'СКС и монтажные принадлежности', r'.', ['СКС']),
    ('ethernet', 'include', NETWORK, 'Сетевые кабели', r'^(?:ethernet|кабель.*(?:rj45|витая пара)|патч-корд)', None),
    ('cable', 'include', PERIPH, 'Кабели и переходники', r'^кабель|^переходник|^usb ', ['Cable Equipment', 'Cables and Adapters', 'КОМПЬЮТЕРНАЯ КАБЕЛЬНАЯ ПРОДУКЦИЯ', 'Network Equipment', 'СМАРТФОНЫ, МОБИЛЬНЫЕ ТЕЛЕФОНЫ', 'Mobile phone accessories']),
    ('ups', 'include', POWER, 'Источники бесперебойного питания', r'^ибп', None),
    ('ups-battery', 'include', POWER, 'Аккумуляторы для ИБП', r'^аккумуляторная батарея', ['IT', 'ИБП, АКБ, СЕТЕВЫЕ ФИЛЬТРЫ']),
    ('power-distribution', 'include', POWER, 'Сетевые фильтры и удлинители', r'^сетевой фильтр|^удлинитель', ['IT', 'ИБП, АКБ, СЕТЕВЫЕ ФИЛЬТРЫ', 'Audio & video']),
    ('mfp', 'include', PRINT, 'Многофункциональные устройства', r'^мфу', None),
    ('printer', 'include', PRINT, 'Принтеры', r'^принтер', None),
    ('cartridge', 'include', PRINT, 'Картриджи и тонеры', r'^картридж|^тонер', None),
    ('barcode', 'include', PRINT, 'Сканеры штрихкодов', r'^сканер штрих', None),
    ('shredder', 'include', PRINT, 'Уничтожители документов', r'^шредер', None),
    ('print-parts', 'include', PRINT, 'Запчасти и аксессуары для печати', r'^фьюзер|^бункер|^блок|^лоток|^тумба.*(?:pantum|cpc-230)', ['ЗАПЧАСТИ', 'РАСХОДНЫЕ МАТЕРИАЛЫ И ЧИСТЯЩИЕ СРЕДСТВА', 'IT', '']),
]
for rid, sub, pattern in [
    ('cpu','Процессоры',r'^процессор'),
    ('board','Материнские платы',r'^материнская'),
    ('ram','Оперативная память',r'^оперативная|^память'),
    ('gpu','Видеокарты',r'^видеокарта|^nvidia'),
    ('ssd','SSD',r'^твердотельн|^накопитель .*ssd'),
    ('hdd','Жёсткие диски',r'^жесткий|^жёсткий'),
    ('external','Внешние накопители',r'^внешний'),
    ('flash','Флеш-накопители и карты памяти',r'^флеш|^карта памяти'),
    ('psu','Блоки питания',r'^блок питания'),
    ('case','Корпуса',r'^корпус'),
    ('cooling','Охлаждение',r'^вентилятор|^кулер|^сжо|^термопаста'),
    ('mounting','Принадлежности для установки накопителей',r'^салазки'),
]:
    RULES.append((rid,'include',PARTS,sub,pattern,['Computer Components','КОМПЛЕКТУЮЩИЕ ПК','НОСИТЕЛИ ИНФОРМАЦИИ']))

def classify(category, name):
    if category not in KNOWN_CATEGORIES:
        return 'review', '', '', 'unknown-category'
    if re.search(r'^удлинитель.*светодиодной ленты', name, re.I):
        return 'request', '', '', 'lighting-accessory-on-request'
    if category in EXCLUDE_CATEGORIES:
        return 'exclude', '', '', 'excluded-category'
    for rid, status, section, sub, pattern, categories in RULES:
        if (categories is None or category in categories) and re.search(pattern,name,re.I):
            return status, section, sub, rid
    return 'review', '', '', 'unknown-type'

def identity(brand, sku, name):
    variant = re.search(r'формат поставки\s*[-—:]?\s*(.*?)(?:\s*\(вкл\.|$)', name, re.I)
    return (clean(brand).casefold(), clean(sku).casefold(), clean(variant.group(1)).casefold() if variant else '')

def price_value(value):
    s=clean(value).replace(' ', '').replace('₽','')
    if ',' in s:
        s=s.replace('.', '').replace(',', '.')
    try:
        d=Decimal(s)
        return str(d.quantize(Decimal('.01'))) if d.is_finite() and d>0 and d<Decimal('10000000000') else None
    except InvalidOperation:
        return None

def load_rows(source):
    import openpyxl
    workbook=openpyxl.load_workbook(source,read_only=True,data_only=True)
    if len(workbook.worksheets)!=1:
        workbook.close()
        raise ValueError('Expected one sheet; select and review a multi-sheet file explicitly')
    sheet=workbook.worksheets[0]
    from supplier_price import HEADERS as SUPPLIER_HEADERS, load_rows as supplier_rows
    if set([clean(v) for v in next(sheet.iter_rows(values_only=True))]) == set(SUPPLIER_HEADERS):
        workbook.close()
        return supplier_rows(source)
    if sheet.max_row > 50001 or sheet.max_column > 9:
        workbook.close()
        raise ValueError('Maximum 50000 data rows and 9 columns')
    iterator=sheet.iter_rows(values_only=True)
    if [clean(v) for v in next(iterator)] != HEADERS:
        workbook.close()
        raise ValueError('Unexpected headers: refusing positional import')
    records=[]; skipped=[]
    for number, cells in enumerate(iterator,2):
        if number > 50001:
            workbook.close()
            raise ValueError('Maximum 50000 data rows')
        raw=[v.isoformat() if hasattr(v,'isoformat') else v for v in cells]
        v=[clean(x) for x in cells]
        if not v[3]:
            # Real stock/identity rows without a title must not disappear silently.
            meaningful=any(v[i] for i in [0,1,2]) or any(v[i] not in ('','0','RUB') for i in [4,5,6,7,8])
            if not meaningful:
                skipped.append(number); continue
        status,section,sub,rule=classify(v[0],v[3])
        errors=[]
        if not v[3]: errors.append('missing_name')
        if not v[1]: errors.append('missing_brand')
        if not v[2]: errors.append('missing_pn')
        if len(v[3])>2000: errors.append('name_too_long')
        if any(len(v[i])>255 for i in [0,1,2,4,5,6]): errors.append('field_too_long')
        price=price_value(cells[7])
        if price is None: errors.append('invalid_price')
        if v[8]!='RUB': errors.append('currency_needs_review')
        for index in [4,5,6]:
            if v[index] and not re.fullmatch(r'(?:>\s*)?\d+',v[index]): errors.append('stock_text_'+str(index))
        records.append(dict(row=number,raw=raw,source_category=v[0],brand=v[1],sku=v[2],name=v[3],
            stock=v[4:7],price=price,currency=v[8],status=status,section=section,subcategory=sub,rule=rule,
            errors=errors,key=identity(v[1],v[2],v[3])))
    workbook.close()
    return records,skipped,sheet.title

def analyse(records, existing):
    groups=defaultdict(list)
    for r in records: groups[r['key']].append(r)
    by_key=defaultdict(list)
    for p in existing: by_key[identity(p.get('brand'),p.get('sku'),p.get('name',''))].append(p)
    for r in records:
        peers=groups[r['key']]
        if len(peers)>1:
            exact=all(p['name']==r['name'] and p['raw']==r['raw'] for p in peers)
            r['errors'].append('duplicate_row' if exact else 'ambiguous_source_identity')
        matches=by_key[r['key']]
        r['existing_ids']=[p['id'] for p in matches]
        if len(matches)>1: r['errors'].append('ambiguous_database_identity')
        # Never silently merge a changed title; suggested identity still needs adoption.
        if len(matches)==1 and clean(matches[0]['name'])!=r['name']:
            r['errors'].append('title_changed_verify_identity')
        if r['errors'] and r['status']=='include': r['status']='review'
        r['operation']='skip'
        r['changes']={}
        if r['status']=='include':
            r['operation']='new' if not matches else 'update'
            if matches:
                p=matches[0]
                for key,value in [('price',r['price']),('availability',r['stock'][0])]:
                    old=price_value(p.get(key)) if key=='price' else clean(p.get(key))
                    if old!=value: r['changes'][key]={'before':old,'after':value}
                if not r['changes']: r['operation']='unchanged'
        elif r['status']=='review': r['operation']='hold'
    source_keys=set(groups)
    absent=[p['id'] for p in existing if identity(p.get('brand'),p.get('sku'),p.get('name','')) not in source_keys]
    return absent

def safe_cell(value):
    s=str(value) if value is not None else ''
    return "'"+s if s.startswith(('=','+','-','@','\t','\r')) else s

def main():
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('source',type=Path)
    parser.add_argument('--existing',type=Path,required=True,help='Read-only product JSON snapshot')
    parser.add_argument('--output',type=Path,required=True)
    args=parser.parse_args()
    records,skipped,sheet=load_rows(args.source)
    existing=json.loads(args.existing.read_text(encoding='utf-8-sig'))
    absent=analyse(records,existing)
    args.output.mkdir(parents=True,exist_ok=True)
    profile=records[0].get('source_profile','altech-price') if records else 'altech-price'
    from supplier_price import VERSION as SUPPLIER_VERSION
    summary={'mode':'dry-run-no-database-writes','source_profile':profile,'rules_version':SUPPLIER_VERSION if profile=='supplier-price-32' else VERSION,'created_at':datetime.now(timezone.utc).isoformat(),
        'source_name':args.source.name,'source_sha256':hashlib.sha256(args.source.read_bytes()).hexdigest(),
        'snapshot_sha256':hashlib.sha256(args.existing.read_bytes()).hexdigest(),'sheet':sheet,
        'rows':len(records),'skipped_service_rows':len(skipped),'existing_products':len(existing),
        'status':dict(Counter(r['status'] for r in records)),'operations':dict(Counter(r['operation'] for r in records)),
        'errors':dict(Counter(e for r in records for e in r['errors'])),
        'sections':dict(Counter(r['section'] for r in records if r['status']=='include')),
        'absent_existing_ids':absent,'published':0}
    (args.output/'summary.json').write_text(json.dumps(summary,ensure_ascii=False,indent=2),encoding='utf-8')
    (args.output/'staged-products.json').write_text(json.dumps(records,ensure_ascii=False,indent=2),encoding='utf-8')
    labels={'include':'Включить','exclude':'Исключить','request':'Только под запрос','review':'Проверить'}
    columns=['Строка Excel','Решение','Раздел','Подкатегория','Правило','Проверки','Операция','ID в базе',*HEADERS]
    for filename,subset in [('classified.csv',records),('review.csv',[r for r in records if r['status']=='review'])]:
        with (args.output/filename).open('w',encoding='utf-8-sig',newline='') as f:
            writer=csv.writer(f,delimiter=';');writer.writerow(columns)
            for r in subset:
                writer.writerow([safe_cell(v) for v in [r['row'],labels[r['status']],r['section'],r['subcategory'],r['rule'],','.join(r['errors']),r['operation'],','.join(map(str,r['existing_ids'])),*r['raw']]])
    counts=Counter((r['section'],r['subcategory']) for r in records if r['status']=='include')
    with (args.output/'subcategories.csv').open('w',encoding='utf-8-sig',newline='') as f:
        writer=csv.writer(f,delimiter=';');writer.writerow(['Раздел','Подкатегория','Строк к включению'])
        writer.writerows((*key,n) for key,n in sorted(counts.items()))
    assert sum(summary['status'].values())==len(records)
    assert all(r['section'] and r['subcategory'] and not r['errors'] for r in records if r['status']=='include')
    print(json.dumps(summary,ensure_ascii=False,indent=2))

if __name__=='__main__': main()
