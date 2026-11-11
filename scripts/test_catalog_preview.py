from io import BytesIO
import unittest
from pathlib import Path
import catalog_preview as preview


class CatalogPreviewTests(unittest.TestCase):
    def test_led_extensions_are_not_power_strips(self):
        import supplier_price
        for name in ['Удлинитель светодиодной ленты Sber SBDV-00038', 'Удлинитель для умной светодиодной ленты Яндекса, 1м YNDX-00547']:
            self.assertEqual(preview.classify('Audio & video', name)[0], 'request')
            self.assertEqual(supplier_price.classify('Audio & video', 'Smart Devices', name)[0], 'request')
        self.assertEqual(preview.classify('IT', 'Удлинитель силовой')[2], 'Сетевые фильтры и удлинители')

    def test_unknown_category_is_not_published_even_with_known_title(self):
        self.assertEqual(preview.classify('Новая группа', 'Ноутбук HP')[0], 'review')

    def test_source_category_does_not_override_actual_product_type(self):
        result=preview.classify('Network Equipment', 'Концентратор USB Type-C Cudy UH405')
        self.assertEqual(result[1:3], (preview.PERIPH, 'Док-станции и USB-хабы'))

    def test_professional_displays_and_consumer_tv_differ(self):
        self.assertEqual(preview.classify('Displays', 'Профессиональный дисплей Philips')[0], 'include')
        self.assertEqual(preview.classify('CE', 'Телевизор Samsung')[0], 'request')
        self.assertEqual(preview.classify('ТЕЛЕВИЗОРЫ', 'Кронштейн для ТВ')[0], 'review')

    def test_variant_identity_ignores_tax_text_but_preserves_delivery(self):
        a=preview.identity('Microsoft','PN','Windows формат поставки - BOX (вкл. НДС 20%)')
        b=preview.identity('Microsoft','PN','Windows формат поставки - BOX (вкл. НДС 22%)')
        c=preview.identity('Microsoft','PN','Windows формат поставки - наклейка')
        self.assertEqual(a,b)
        self.assertNotEqual(a,c)

    def row(self, name='Ноутбук HP'):
        return dict(key=preview.identity('HP','PN',name), name=name, raw=['HP','PN',name], errors=[], status='include', price='10.00',stock=['>50','','0'])

    def test_duplicate_source_rows_are_all_held(self):
        rows=[self.row(),self.row()]
        preview.analyse(rows,[])
        self.assertEqual([r['operation'] for r in rows],['hold','hold'])

    def test_same_pn_different_titles_never_silently_merge(self):
        rows=[self.row(),self.row('Ноутбук HP другой цвет')]
        preview.analyse(rows,[])
        self.assertTrue(all('ambiguous_source_identity' in r['errors'] for r in rows))

    def test_existing_price_difference_is_reported_without_mutation(self):
        existing=[dict(id=1,brand='HP',sku='PN',name='Ноутбук HP',price='12.00',availability='>50')]
        rows=[self.row()]
        preview.analyse(rows,existing)
        self.assertEqual(rows[0]['changes'],{'price':{'before':'12.00','after':'10.00'}})
        self.assertEqual(existing[0]['price'],'12.00')

    def test_bad_price_is_not_silently_converted(self):
        for value in ['abc','0','-1','NaN','Infinity','12x34']:
            self.assertIsNone(preview.price_value(value))
        self.assertEqual(preview.price_value('2 190,00'), '2190.00')

    def test_reordered_headers_are_rejected(self):
        import openpyxl
        p=BytesIO();w=openpyxl.Workbook()
        w.active.append(list(reversed(preview.HEADERS)));w.save(p);w.close();p.seek(0)
        with self.assertRaises(ValueError): preview.load_rows(p)

    def test_stock_is_preserved_and_missing_pn_is_flagged(self):
        import openpyxl
        p=BytesIO();w=openpyxl.Workbook();w.active.append(preview.HEADERS)
        w.active.append(['НОУТБУКИ','HP',None,'Ноутбук HP','>50',None,0,10,'RUB']);w.save(p);w.close();p.seek(0)
        rows,_,_=preview.load_rows(p)
        preview.analyse(rows,[])
        self.assertEqual(rows[0]['stock'],['>50','','0'])
        self.assertEqual(rows[0]['status'],'review')

if __name__=='__main__': unittest.main()
