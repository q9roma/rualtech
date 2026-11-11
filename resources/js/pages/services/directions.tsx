import { Page, type PageProps } from '@/components/storefront/content';
import { ServicesOverview, type Direction } from '@/components/storefront/services-overview';
import { ContactDialog } from '@/components/storefront/contact-dialog';

export default function Directions(props: PageProps & { directions: Direction[] }) {
    return <Page {...props}>
        <ServicesOverview directions={props.directions} title="Подберём решение под вашу задачу" />
        <div className="storefront-direction-request"><div><h2>Нужна помощь с выбором?</h2><p>Расскажите о задаче, количестве оборудования и бюджете. Поможем составить подборку.</p></div><ContactDialog service="Подбор оборудования и ПО" label="Запросить подбор" /></div>
    </Page>;
}
