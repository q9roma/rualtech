import unittest
from unittest.mock import patch
from types import SimpleNamespace
import supplier_price as supplier
import catalog_preview as base

class SupplierPriceTests(unittest.TestCase):
    def test_nonbusiness_excluded_even_without_categories(self):
        self.assertEqual(supplier.classify('', '', 'Комплект наволочек Pragma')[0], 'exclude')
        self.assertEqual(supplier.classify('Apparel', '', 'Неизвестный предмет')[0], 'exclude')
        self.assertEqual(supplier.classify('Home Appliances', '', 'Монитор для холодильника')[0], 'exclude')

    def test_business_classification(self):
        self.assertEqual(supplier.classify('Computer Components', 'SSD Enterprise', 'Твердотельный накопитель')[1], base.SERVER)
        self.assertEqual(supplier.classify('Displays', 'Commercial and Hospitality TV', 'Телевизор LG')[0], 'include')
        self.assertEqual(supplier.classify('CE', 'TV', 'Телевизор LG')[0], 'request')
        self.assertEqual(supplier.classify('Computer Peripherals', 'Keyboards', 'Клавиатура + мышь Acer')[2], 'Комплекты клавиатура и мышь')

    def test_stock_price_and_complete_source_data(self):
        values = [''] * 32
        for i, v in {0:'Ноутбук HP',1:'0012345678901',2:'HP-1',3:'IT',4:'Laptops',5:'HP',6:1000,8:9999,10:7,11:1,12:2,13:3,14:4,15:100,16:50,17:20,18:10,19:'Описание',20:'0E-1'}.items(): values[i]=v
        sheet = SimpleNamespace(max_row=2, max_column=32, title='Лист_1', iter_rows=lambda **kw: iter([supplier.HEADERS,values]))
        workbook = SimpleNamespace(worksheets=[sheet], active=sheet, close=lambda: None)
        with patch('openpyxl.load_workbook', return_value=workbook): rows, _, _ = supplier.load_rows('unused.xlsx')
        row = rows[0]
        self.assertEqual(row['stock'], ['10','10','7'])
        self.assertEqual(row['price'], '1000.00')
        self.assertEqual(row['supplier_data']['EAN'], '0012345678901')
        self.assertEqual(len(row['supplier_data']), 32)
        self.assertEqual(row['supplier_id'], '0E-1')
        self.assertEqual(row['errors'], [])

    def test_reordered_columns_preserve_source_values_and_stock(self):
        values=['']*32
        for i,v in {0:'Ноутбук HP',2:'HP-1',5:'HP',6:1000,10:7,11:1,12:2,13:3,14:4,15:100,16:50,17:20,18:10,20:'TEST'}.items(): values[i]=v
        order=list(reversed(range(32)))
        headers=[supplier.HEADERS[i] for i in order]
        sheet=SimpleNamespace(max_row=2,max_column=32,title='Test',iter_rows=lambda **kw:iter([headers,[values[i] for i in order]]))
        book=SimpleNamespace(worksheets=[sheet],active=sheet,close=lambda:None)
        with patch('openpyxl.load_workbook',return_value=book): rows,_,_=base.load_rows('unused.xlsx')
        self.assertEqual(rows[0]['stock'],['10','10','7'])
        self.assertEqual(rows[0]['supplier_data'][supplier.HEADERS[15]],100)
        self.assertEqual(rows[0]['supplier_data'][supplier.HEADERS[16]],50)
        self.assertEqual(rows[0]['price'],'1000.00')

    def test_duplicate_or_unknown_headers_are_rejected(self):
        for headers in [supplier.HEADERS[:-1],supplier.HEADERS[:-1]+[supplier.HEADERS[0]],supplier.HEADERS[:-1]+['Unknown']]:
            with self.assertRaises(ValueError): supplier.header_order(headers)

if __name__ == '__main__': unittest.main()
