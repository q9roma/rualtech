import { Server, HardDrive, Cpu } from 'lucide-react';
import { ContactDialog } from '@/components/storefront/contact-dialog';

const scenarios = [
    { icon: Server, title: 'Собрать новый сервер', text: 'Для 1С, баз данных и виртуальных машин. Подберём платформу, память и накопители с учётом нагрузки и планов расширения.' },
    { icon: HardDrive, title: 'Организовать хранение', text: 'Для общих файлов и резервных копий. Определим нужную ёмкость, скорость доступа и состав накопителей для СХД или NAS.' },
    { icon: Cpu, title: 'Обновить инфраструктуру', text: 'Когда системе не хватает памяти, места или производительности. Проверим текущую конфигурацию и подберём совместимые компоненты.' },
];

export function ServerServiceIntro({ service }: { service: string }) {
    return <>
        <header className="service-hero">
            <div className="service-hero-copy">
                <p className="service-eyebrow"><Server size={16} strokeWidth={1.75} aria-hidden="true" />Серверы и хранение данных</p>
                <h1>Подбор серверов <br />и систем хранения</h1>
                <p className="service-hero-description">Поможем выбрать оборудование для новой системы или обновить существующую. Учтём рабочие приложения, нагрузку, совместимость компонентов и бюджет.</p>
                <div className="service-hero-actions"><ContactDialog service={service} label="Обсудить задачу" /></div>
            </div>
            <div className="service-hero-art"><img src="/images/server-service-banner.webp" alt="Серверные платформы и сетевое хранилище — иллюстрация оборудования" width="1536" height="1024" fetchPriority="high" /></div>
        </header>
        <section className="service-scenarios" aria-labelledby="service-scenarios-title">
            <h2 id="service-scenarios-title">С чем поможем</h2>
            <div className="service-scenarios-grid">{scenarios.map(({ icon: Icon, ...scenario }) => <article className="service-scenario" key={scenario.title}>
                <div className="service-scenario-heading"><Icon size={18} strokeWidth={1.8} aria-hidden="true" /><h3>{scenario.title}</h3></div>
                <p>{scenario.text}</p>
            </article>)}</div>
        </section>
        <section className="service-brief" aria-labelledby="service-brief-title">
            <div className="service-brief-copy"><h2 id="service-brief-title">Что написать в заявке</h2><p>Укажите программы, число пользователей и что нужно изменить. Если уже есть сервер, добавьте его модель или конфигурацию.</p><ContactDialog service={service} label="Обсудить задачу" /></div>
            <div className="service-brief-example"><p className="service-brief-caption">Пример запроса</p><blockquote>Работаем в 1С, команда растёт. Нужен сервер и место для общих файлов. Хотим понять, что выбрать и сколько это будет стоить.</blockquote><p className="service-brief-response">В ответ уточним нагрузку, объём данных и бюджет.</p></div>
        </section>
    </>;
}
