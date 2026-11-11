import unittest
from io import BytesIO
from PIL import Image, ImageDraw
from audit_catalog_photos import inspect, valid_url

class PhotoQualityTest(unittest.TestCase):
    def image(self, size, blank=False):
        im = Image.new('RGB', size, 'white')
        if not blank:
            ImageDraw.Draw(im).rectangle((10, 10, size[0]//2, size[1]//2), fill='black')
        out = BytesIO(); im.save(out, format='PNG')
        return out.getvalue()

    def test_corrupt_and_blank_are_not_usable(self):
        with self.assertRaises(Exception):
            inspect(b'<html>not an image</html>')
        self.assertIn('blank', inspect(self.image((800, 800), True))['flags'])

    def test_portrait_and_panorama_thresholds(self):
        self.assertEqual([], inspect(self.image((200, 800)))['flags'])
        self.assertIn('small', inspect(self.image((199, 800)))['flags'])
        self.assertIn('small', inspect(self.image((599, 599)))['flags'])

    def test_only_supplier_https_host_is_allowed(self):
        self.assertTrue(valid_url('https://images.vvpgroup.com/test.jpg'))
        for url in ['http://images.vvpgroup.com/a', 'https://images.vvpgroup.com.evil.test/a', 'https://user@images.vvpgroup.com/a', 'https://127.0.0.1/a']:
            self.assertFalse(valid_url(url))

if __name__ == '__main__':
    unittest.main()
