import unittest
import tempfile
from pathlib import Path
from unittest.mock import patch
from catalog_automation import offers, match, allowed_url, download, digest, write, SafeRedirect


class AutomationTest(unittest.TestCase):
    def test_identity_requires_brand_sku_and_ean(self):
        index = offers(b'<yml_catalog><shop><offers><offer id="123"><vendor>HP</vendor><vendorCode>ABC</vendorCode><picture>https://images.vvpgroup.com/a.jpg</picture></offer></offers></shop></yml_catalog>')
        product = {'brand': 'hp', 'sku': 'abc', 'ean': '123'}
        self.assertEqual(match(product, index)[0], 'matched')
        self.assertEqual(match({**product, 'ean': '456'}, index), ('ean_conflict_or_missing', []))
        self.assertEqual(match({**product, 'sku': 'other'}, index), ('missing', []))
        index[('hp', 'abc')] *= 2
        self.assertEqual(match(product, index), ('ambiguous', []))

    def test_rejects_unsafe_feeds(self):
        for xml in [b'<!DOCTYPE foo><yml_catalog/>', b'<wrong/>', b'<yml_catalog/>',
                    b'<yml_catalog><shop><offers><offer id="1"/><offer id="1"/></offers></shop></yml_catalog>']:
            with self.assertRaises(ValueError):
                offers(xml)

    def test_image_host_is_explicit(self):
        self.assertTrue(allowed_url('https://images.vvpgroup.com/image.jpg'))
        for url in ['http://images.vvpgroup.com/x', 'https://images.vvpgroup.com.evil.com/x',
                    'https://user@images.vvpgroup.com/x', 'https://127.0.0.1/x', 'file:///secret']:
            self.assertFalse(allowed_url(url))

    def test_redirect_cannot_escape_image_host(self):
        with self.assertRaises(ValueError):
            SafeRedirect().redirect_request(None, None, 302, '', {}, 'https://127.0.0.1/private')

    def test_verified_cache_is_reused_but_changed_bytes_are_rechecked(self):
        with tempfile.TemporaryDirectory() as directory:
            folder = Path(directory)
            url = 'https://images.vvpgroup.com/test.jpg'
            (folder / 'cover.jpg').write_bytes(b'previously-verified')
            record = {'status': 'verified', 'file': 'cover.jpg', 'sha256': digest(b'previously-verified')}
            write(folder / (digest(url.encode()) + '.json'), record)
            with patch('urllib.request.build_opener', side_effect=RuntimeError('network attempted')) as network:
                self.assertEqual(download(url, folder), record)
                network.assert_not_called()
                (folder / 'cover.jpg').write_bytes(b'changed')
                self.assertEqual(download(url, folder)['status'], 'failed')
                network.assert_called_once()


if __name__ == '__main__':
    unittest.main()
