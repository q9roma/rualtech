import { useEffect, useRef } from 'react';
import { Card } from '@/components/ui/card';
import { ContactDialog } from './contact-dialog';
import homeContent from '@/lib/home-content.json';

const benefitIcons = ['handshake', 'lightning', 'money', 'truck', 'document', 'vendors'];

const facts = [
    { value: '50+', title: <>довольных<br />клиентов</>, description: 'Успешно завершенные проекты' },
    { value: '8 лет', title: 'в айти', description: 'Профессиональный опыт' },
    { value: '100%', title: <>подход<br />к качеству</>, description: 'Внимание к деталям' },
];

export function WhyAltech() {
    const benefitsRef = useRef<HTMLElement>(null);

    useEffect(() => {
        const section = benefitsRef.current;
        if (!section) return;
        const motion = window.matchMedia('(min-width: 901px) and (prefers-reduced-motion: no-preference)');
        let frame = 0;
        const ease = (value: number) => {
            const t = Math.max(0, Math.min(1, value));
            return t * t * (3 - 2 * t);
        };
        const update = () => {
            frame = 0;
            if (!motion.matches) {
                section.style.removeProperty('--van-left-offset');
                section.style.removeProperty('--van-right-offset');
                return;
            }
            const top = section.getBoundingClientRect().top;
            const height = window.innerHeight;
            // Keep the drive visible while the benefits enter the viewport, and reverse on scroll up.
            const progress = ease((height * 0.95 - top) / (height * 0.8));
            const list = section.querySelector<HTMLElement>('.storefront-benefits-list');
            if (!list) return;
            // Travel all the way beyond the section edge, including on wide screens.
            const distance = Math.max(0, (section.clientWidth - list.offsetWidth) / 2 - 36);
            section.style.setProperty('--van-right-offset', `${progress * distance}px`);
            section.style.setProperty('--van-left-offset', `${(1 - progress) * distance}px`);
        };
        const schedule = () => { if (!frame) frame = requestAnimationFrame(update); };
        const observer = new ResizeObserver(schedule);
        observer.observe(document.body);
        window.addEventListener('scroll', schedule, { passive: true });
        window.addEventListener('resize', schedule);
        motion.addEventListener('change', schedule);
        update();
        return () => {
            cancelAnimationFrame(frame);
            observer.disconnect();
            window.removeEventListener('scroll', schedule);
            window.removeEventListener('resize', schedule);
            motion.removeEventListener('change', schedule);
        };
    }, []);

    return <><section id="about" className="storefront-why" aria-labelledby="why-heading">
        <img className="storefront-why-glow storefront-why-green" src="/images/figma-why-green.svg" alt="" />
        <img className="storefront-why-glow storefront-why-purple" src="/images/figma-why-purple.svg" alt="" />
        <img className="storefront-why-glow storefront-why-blue" src="/images/figma-why-blue.svg" alt="" />
        <img className="storefront-why-glow storefront-why-amber" src="/images/figma-why-amber.svg" alt="" />
        <div className="storefront-why-heading"><h2 id="why-heading">почему <span>алтех <img src="/images/figma-heading-pointer.svg" width="12" height="16" alt="" /></span></h2><p>Мы используем современные подходы, быстро реагируем на потребности клиентов и обеспечиваем персональный подход к каждому проекту</p></div>
        <div className="storefront-why-facts">{facts.map(fact => <Card className="storefront-why-fact" key={fact.value}><div><p className="storefront-why-value">{fact.value}</p><h3>{fact.title}</h3></div><p>{fact.description}</p></Card>)}</div>
    </section>
        <div className="storefront-benefits-divider"><img src="/images/figma-why-dot.svg" alt="" /></div>
        <section ref={benefitsRef} className="storefront-benefits-section" aria-label="Преимущества сотрудничества">
            <div className="storefront-delivery-van storefront-delivery-van-left"><img src="/images/figma-delivery-van-full.png" alt="" /></div>
            <div className="storefront-delivery-van storefront-delivery-van-right"><img src="/images/figma-delivery-van-full.png" alt="" /></div>
            <div className="storefront-benefits-list">{[1, 2, 0, 3, 4, 5].map(index => { const benefit = homeContent.benefits[index]; return <article className="storefront-benefit" key={benefit.title}><h3><span className="storefront-benefit-icon"><img src={`/images/figma-benefit-${benefitIcons[index]}.svg`} alt="" /></span>{benefit.title}</h3><p>{benefit.text.replace(/\.$/, '')}</p></article>; })}
                <ContactDialog label="Присоединяйтесь!" service="Сотрудничество с Алтех" className="storefront-benefit-join" icon={<img src="/images/figma-benefit-wave.svg" alt="" />} />
            </div>
        </section>
    </>;
}
