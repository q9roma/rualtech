import unittest
from unittest.mock import patch
from catalog_photo_task import identity, allowed, icecat, run
from pathlib import Path
from tempfile import TemporaryDirectory

class PhotoTaskTest(unittest.TestCase):
    def test_mpn_candidate_is_visible_but_requires_manual_review(self):
        product = {'id':99999999,'brand':'LG','sku':'M1','ean':''}
        row = {'status':'candidate','returned_brand':'LG','returned_mpn':'M1','returned_gtins':[],
               'image':{'HighPic':'https://images.icecat.biz/test.jpg'}}
        with TemporaryDirectory() as directory, patch('catalog_photo_task.BASE', Path(directory)), \
             patch('catalog_photo_task.MEDIA', Path(directory)/'media'), \
             patch('catalog_photo_task.icecat', return_value=row), \
             patch('catalog_photo_task.image', return_value={'flags':[]}) as download:
            result = run(product)
            download.assert_called_once()
            self.assertEqual('review', result['status'])
            self.assertEqual('review', result['images'][0]['identity'])
            product['ean'] = '123'
            row['returned_gtins'] = ['456']
            download.reset_mock()
            result = run(product)
            download.assert_not_called()
            self.assertEqual([], result['images'])
            row['returned_gtins'] = ['123']
            result = run(product)
            self.assertEqual('ready', result['status'])

    def test_shared_image_is_a_warning_not_a_quality_block(self):
        product = {'id':99999999,'brand':'LG','sku':'M1','ean':'123'}
        row = {'status':'candidate','returned_brand':'LG','returned_mpn':'M1','returned_gtins':['123'],
               'image':{'HighPic':'https://images.icecat.biz/test.jpg'}}
        with TemporaryDirectory() as directory, patch('catalog_photo_task.BASE', Path(directory)), \
             patch('catalog_photo_task.MEDIA', Path(directory)/'media'), \
             patch('catalog_photo_task.icecat', return_value=row), \
             patch('catalog_photo_task.image', return_value={'flags':['shared_across_models']}):
            self.assertEqual('ready', run(product)['status'])
    def test_full_mpn_does_not_override_conflicting_ean(self):
        p={'id':99999999,'brand':'LG','sku':'M1','ean':'123'}
        r={'returned_brand':'LG','returned_mpn':'M1','returned_gtins':['456']}
        self.assertEqual('conflict',identity(p,r))
        r['returned_gtins']=[]; self.assertEqual('review',identity(p,r))
        r['returned_gtins']=['123']; self.assertEqual('confirmed',identity(p,r))

    @patch('catalog_photo_task.request',return_value=(429,{}))
    def test_rate_limit_is_retryable_and_does_not_try_other_queries(self,request):
        self.assertEqual('retry',icecat({'id':99999999,'brand':'LG','sku':'M1','ean':'123'})['status'])
        self.assertEqual(1,request.call_count)

    def test_image_hosts_are_restricted(self):
        self.assertTrue(allowed('https://images.icecat.biz/a.jpg'))
        self.assertFalse(allowed('https://images.icecat.biz.evil.test/a.jpg'))
        self.assertFalse(allowed('http://127.0.0.1/private'))

if __name__ == '__main__': unittest.main()
