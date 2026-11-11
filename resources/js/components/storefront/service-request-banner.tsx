import { useId, useState } from 'react';
import { Card, CardHeader, CardContent, CardFooter } from '@/components/ui/card';
import { ContactDialog } from '@/components/storefront/contact-dialog';
import { Textarea } from '@/components/ui/textarea';

export function ServiceRequestBanner({ service }: { service: string }) {
    const messageId = useId();
    const [message, setMessage] = useState('');
    return <Card className="service-chat" aria-labelledby={`${messageId}-title`}>
        <CardHeader className="service-chat-header"><div><h2 id={`${messageId}-title`}>Что нужно для работы?</h2><p>Просто поставьте задачу</p></div></CardHeader>
        <CardContent className="service-chat-messages" aria-label="Пример диалога">
            <div className="service-chat-message service-chat-customer"><span className="sr-only">Клиент: </span><p>Работаем в 1С, сервер тормозит. Хотим понять, менять его или можно обновить.</p></div>
            <div className="service-chat-message service-chat-company"><span className="sr-only">Алтех: </span><p>Давайте посмотрим, что у вас сейчас. Проверим, что можно улучшить, и сравним с новым сервером.</p></div>
        </CardContent>
        <CardFooter className="service-chat-footer"><label className="sr-only" htmlFor={messageId}>Ваше сообщение</label><Textarea id={messageId} value={message} onChange={event => setMessage(event.target.value)} maxLength={1000} rows={1} placeholder="Что хотите сделать?" /><ContactDialog service={service} label="Добавить контакты" className="service-chat-send" initialMessage={message} onMessageChange={setMessage} /></CardFooter>
    </Card>;
}
