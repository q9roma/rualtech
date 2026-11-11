import { useEffect, useId, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';

export type PickupPoint = { id: string; city: string; address: string; type: string };
declare global { interface Window { YaDelivery?: { createWidget: (options: unknown) => void } } }
export function YandexPickup({ city, point, onSelect }: { city: string; point: PickupPoint | null; onSelect: (point: PickupPoint) => void }) {
    const id = `yandex-pvz-${useId().replace(/:/g, '')}`;
    const callback = useRef(onSelect);
    callback.current = onSelect;
    const [attempt, setAttempt] = useState(0);
    const [failed, setFailed] = useState(false);
    const initialCity = useRef(city || 'Москва');
    useEffect(() => {
        let started = false;
        setFailed(false);
        const timer = window.setTimeout(() => { if (!started) setFailed(true); }, 15000);
        const start = () => {
            if (started || !window.YaDelivery) return;
            try {
                window.YaDelivery.createWidget({ containerId: id, params: { city: initialCity.current, size: { height: '520px', width: '100%' }, show_select_button: true, filter: { type: ['pickup_point'], payment_methods: ['already_paid'] } } });
                started = true; window.clearTimeout(timer);
            } catch { setFailed(true); }
        };
        const select = (event: Event) => {
            const data = (event as CustomEvent).detail;
            if (!data?.id || !data.address?.full_address || !data.address?.locality) return;
            callback.current({ id: String(data.id), address: data.address.full_address, city: data.address.locality, type: data.type || 'pickup_point' });
        };
        document.addEventListener('YaNddWidgetLoad', start);
        document.addEventListener('YaNddWidgetPointSelected', select);
        let script = document.querySelector<HTMLScriptElement>('script[data-yandex-pvz]');
        if (!script || (!window.YaDelivery && attempt > 0)) {
            script?.remove(); script = document.createElement('script'); script.src = 'https://widget-pvz.dostavka.yandex.net/widget.js?v=2'; script.async = true; script.dataset.yandexPvz = 'true'; document.head.appendChild(script);
        }
        script.addEventListener('error', onError);
        function onError() { setFailed(true); }
        start();
        return () => { window.clearTimeout(timer); document.removeEventListener('YaNddWidgetLoad', start); document.removeEventListener('YaNddWidgetPointSelected', select); script?.removeEventListener('error', onError); document.getElementById(id)?.replaceChildren(); };
    }, [id, attempt]);
    return <div className="pickup-picker">
        <p>Выберите пункт на карте и нажмите «Отправить сюда». Адрес добавится в заказ.</p>
        <div id={id} className="pickup-map" aria-label="Карта пунктов выдачи Яндекс Доставки" />
        {failed && <div role="alert" className="checkout-payment">Карта не загрузилась. Попробуйте снова или выберите другой способ доставки.<Button type="button" variant="outline" onClick={() => setAttempt(n => n + 1)}>Повторить загрузку</Button></div>}
        {point && <div className="pickup-selected" role="status"><strong>Пункт выбран</strong><span>{point.address}</span><small>Код ПВЗ: {point.id}</small></div>}
        <p>Стоимость и возможность отправки оборудования в этот пункт подтвердит менеджер.</p>
    </div>;
}
