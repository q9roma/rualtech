"""Read-only review of an exported DB snapshot. No database access or mutations."""
import argparse, csv, hashlib, html, json
from collections import Counter, defaultdict
from pathlib import Path
from catalog_preview import identity, clean, safe_cell

def proposal(p):
    n=p['row']; c=p['source_category']; name=p['name']
    if p['import_issues']:
        return 'Сверить варианты', '', 'Совпадает бренд/артикул, но разные ID поставщика; сравнить EAN, цену и комплектацию. Не объединять автоматически.'
    exact={1539:'Комплекты клавиатура и мышь',1540:'Комплекты клавиатура и мышь',3910:'Интерактивные панели',3911:'Светодиодные экраны и модули',3915:'Крепления и стойки',3916:'Крепления и стойки',3918:'Проекторы',3945:'Светодиодные экраны и модули',3946:'Светодиодные экраны и модули',3947:'Светодиодные экраны и модули',4219:'Крепления и стойки',4220:'Крепления и стойки'}
    if n in exact:
        reason='Профильное оборудование по названию и исходной группе; кандидат для черновика.'
        if n in (1539,1540): reason='Описание поставщика прямо подтверждает комплект клавиатуры и мыши для офиса.'
        return 'Кандидат в каталог', exact[n], reason
    if n in [360,361] or 3919<=n<=3926:
        return 'Нужна подкатегория', 'Видеонаблюдение', 'Профильное направление B2B, но в принятом дереве нет отдельной подкатегории. Аналоговые и IP-камеры различать.'
    if 3928<=n<=3932:
        return 'Нужна подкатегория', 'Оборудование видеоконференций', 'Терминалы и опции переговорных, назначение подтверждается названием/описанием поставщика.'
    if n in [3933,3934]: return 'Нужна подкатегория', 'ПО для видеоконференций', 'Отдельные лицензии: не смешивать с аппаратными терминалами или офисными приложениями.'
    if n==3917: return 'Нужна подкатегория', 'Проекционные экраны', 'Экран для проектора, а не сам проектор.'
    if n in [3912,3913]: return 'Нужна подкатегория', 'Системы гостиничного телевидения', 'Специализированная платформа и приставка; не относить к бытовым ТВ-приставкам.'
    if c in ['Audio & video / Partybox','Audio & video / Smart Devices','Audio & video / Smart Speaker']:
        return 'Исключить из ассортимента', '', 'Бытовые развлечения и аксессуары к ним, не стартовый B2B-ассортимент. Это рекомендация, не удаление.'
    if n in [2648,3914]: return 'Уточнить назначение', '', 'В описании недостаточно данных: проверить применение к технике или совместимость аксессуара.'
    return 'Только под запрос', '', 'Не включать в основной каталог без подтверждённого рабочего сценария: персональное/студийное аудио либо мелкие мобильные аксессуары.'

def main():
    parser=argparse.ArgumentParser();parser.add_argument('snapshot',type=Path);parser.add_argument('output',type=Path);a=parser.parse_args()
    s=json.loads(a.snapshot.read_text(encoding='utf-8'));out=a.output;out.mkdir(parents=True,exist_ok=True)
    # Decisions below reference reviewed source row numbers, not general import rules.
    if s['batch']['file_hash'] != 'f9c19b7fcf4d69678595130ed2b50719c3f62283df6d53d007538e90d52bada7':
        raise ValueError('This review is bound to the inspected 06-11-2026 workbook; review another file independently')
    rows=[dict(json.loads(r['payload']),operation=r['operation']) for r in s['rows']]
    held=[]
    for p in rows:
        if p['operation']!='held': continue
        decision,category,reason=proposal(p)
        held.append({'row':p['row'],'supplier_id':p.get('supplier_id',''),'sku':p['sku'],'name':p['name'],'source_category':p['source_category'],'recommendation':decision,'category':category,'reason':reason,'issues':', '.join(p['import_issues']),'ean':p.get('supplier_data',{}).get('EAN'),'price':p['price'],'stock':p['stock'][0]})
    linked={r['product_id'] for r in s['links'] if r['source']=='supplier-price-32'}
    identities={identity(p['brand'],p['sku'],p['name']) for p in rows}
    skus={clean(p['sku']).casefold() for p in rows}
    old=[]
    for p in s['products']:
        if p['id'] in linked: continue
        same_identity=identity(p['brand'],p['sku'],p['name']) in identities
        same_sku=clean(p['sku']).casefold() in skus
        old.append({'id':p['id'],'sku':p['sku'],'brand':p['brand'],'name':p['name'],'category':p['category'],'published':bool(p['is_active']),'price':p['price'],'availability':p['availability'],'same_identity':same_identity,'same_sku':same_sku,'recommendation':'Уточнить актуальные цену и наличие; решение по текущей публикации отдельно' if p['is_active'] else 'Сохранить черновик; не публиковать до подтверждения поставки','url':f"http://127.0.0.1:8001/admin/products/{p['id']}"})
    summary={'batch':s['batch']['id'],'held':len(held),'recommendations':dict(Counter(p['recommendation'] for p in held)),'unlinked_old':len(old),'truly_absent_even_by_sku':sum(not p['same_sku'] for p in old),'old_published':sum(p['published'] for p in old),'old_drafts':sum(not p['published'] for p in old),'old_categories':dict(Counter(p['category'] for p in old).most_common()),'products':len(s['products']),'source_snapshot_sha256':hashlib.sha256(a.snapshot.read_bytes()).hexdigest(),'mode':'read-only-proposals-not-applied'}
    for filename,data in [('held-review',held),('old-only',old)]:
        (out/(filename+'.json')).write_text(json.dumps(data,ensure_ascii=False,indent=2),encoding='utf-8')
        with (out/(filename+'.csv')).open('w',encoding='utf-8-sig',newline='') as f:
            labels={'row':'Строка Excel','supplier_id':'ID поставщика','sku':'Артикул','name':'Товар','source_category':'Категория поставщика','recommendation':'Рекомендация','category':'Категория сайта / предложение','reason':'Основание','issues':'Проверки импорта','ean':'EAN','price':'Цена в снимке','stock':'Наличие в прайсе','id':'ID товара','brand':'Бренд','published':'Опубликован','availability':'Старое наличие','same_identity':'Совпадение в новом прайсе','same_sku':'Артикул есть в новом прайсе','url':'Карточка в админке'}
            writer=csv.writer(f,delimiter=';');writer.writerow(labels.get(k,k) for k in data[0])
            for p in data:writer.writerow(safe_cell(v) for v in p.values())
    (out/'summary.json').write_text(json.dumps(summary,ensure_ascii=False,indent=2),encoding='utf-8')
    e=lambda x:html.escape(str(x) if x is not None else '—',quote=True)
    tables=[]
    for title,data,columns in [('Спорные строки',held,{'row':'Строка','sku':'Артикул','name':'Товар','recommendation':'Решение','category':'Категория','reason':'Почему','supplier_id':'ID поставщика','ean':'EAN','price':'Цена','stock':'Наличие'}),('Только в старом прайсе',old,{'id':'ID','sku':'Артикул','name':'Товар','category':'Категория','published':'Опубликован','price':'Старая цена','availability':'Старое наличие','recommendation':'Рекомендация'})]:
        body=''.join('<tr>'+''.join('<td>'+e(('Да' if p[k] else 'Нет') if k=='published' else p[k])+'</td>' for k in columns)+'</tr>' for p in data)
        tables.append(f'<section><h2>{title} · {len(data)}</h2><input aria-label="Поиск в разделе {title}" placeholder="Поиск по товару, артикулу, категории или решению" oninput="filterRows(this)"><p class="count"></p><div class="scroll"><table><thead><tr>'+''.join('<th>'+e(v)+'</th>' for v in columns.values())+'</tr></thead><tbody>'+body+'</tbody></table></div></section>')
    page='''<!doctype html><html lang="ru"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Разбор каталога — Алтех</title><style>body{font:15px/1.5 system-ui;background:#f4f4f5;color:#18181b;margin:24px}main{max-width:1500px;margin:auto}section{background:white;border:1px solid #ddd;border-radius:12px;padding:20px;margin:24px 0}h1{font-size:28px}input{font:inherit;padding:10px;width:min(90%,680px);border:1px solid #bbb;border-radius:6px}.scroll{overflow:auto;max-height:700px}table{border-collapse:collapse;width:100%}td,th{text-align:left;padding:10px;vertical-align:top;border-bottom:1px solid #ddd;min-width:100px}th{position:sticky;top:0;background:#eee}td:nth-child(3){min-width:320px}a{color:#1558ac}.note{color:#52525b}</style><main><h1>Разбор каталога · 9 ноября 2026</h1><p>Рекомендации для проверки. Товары, категории, цены и статусы публикации не изменялись.</p>'''
    page+=f'<p><b>{len(held)}</b> спорных строк · <b>{len(old)}</b> товаров отсутствуют в новом прайсе даже по артикулу · <b>{summary["old_published"]}</b> из них сейчас опубликованы.</p><ul>'+''.join(f'<li>{e(k)}: <b>{v}</b></li>' for k,v in summary['recommendations'].items())+'</ul>'
    page+='<p class="note">Старая цена и старое наличие приведены для сверки, актуальность новым прайсом не подтверждена. Совпадения по артикулу проверены по всем 4 223 строкам, включая исключённые и спорные.</p><p><a href="held-review.csv">Скачать спорные строки (CSV UTF-8)</a> · <a href="old-only.csv">Скачать старые товары (CSV UTF-8)</a></p>'+''.join(tables)
    page+='''</main><script>function filterRows(input){let q=input.value.toLocaleLowerCase(),n=0;input.closest('section').querySelectorAll('tbody tr').forEach(r=>{r.hidden=!r.textContent.toLocaleLowerCase().includes(q);if(!r.hidden)n++});input.nextElementSibling.textContent='Показано: '+n}</script></html>'''
    (out/'review.html').write_text(page,encoding='utf-8')
    assert len(held)==sum(summary['recommendations'].values())
    assert len(old)+len(linked)==len(s['products'])
    print(json.dumps(summary,ensure_ascii=False,indent=2))

if __name__=='__main__':main()
