import { Page, type PageProps } from '@/components/storefront/content';
import { ContactDialog } from '@/components/storefront/contact-dialog';
import { BrandRibbon } from '@/components/storefront/brand-ribbon';
import { DeliveryPopover } from '@/components/storefront/delivery-popover';
import { deliveryContent } from '@/lib/delivery-content';
import '../../css/delivery.css';

const carriers = ['СДЭК', 'Деловые Линии', 'ПЭК', 'Почта России', 'DPD', 'Более 5 компаний'];

export default function Delivery(props: PageProps) {
    return <Page {...props} hideSubheader>
        <section className="delivery-overview" aria-labelledby="delivery-heading">
            <div className="delivery-overview-main">
                <div className="delivery-overview-copy">
                    <h1 id="delivery-heading">Доставка<br />оборудования</h1>
                    <p className="delivery-overview-region">{deliveryContent.local}</p>
                    <BrandRibbon items={carriers} label="Транспортные компании" className="shop-carrier-ribbon delivery-overview-carriers" />
                </div>
                <div className="delivery-overview-vehicle" aria-hidden="true"><img src="/images/figma-delivery-van-full.png" alt="" fetchPriority="high" /></div>
            </div>
            <div className="delivery-overview-contact"><p>{deliveryContent.regions}</p><div className="delivery-overview-actions"><DeliveryPopover className="delivery-overview-details" /><ContactDialog service="Организация доставки" label="Обсудить доставку" className="delivery-overview-button" /></div></div>
        </section>
    </Page>;
}
