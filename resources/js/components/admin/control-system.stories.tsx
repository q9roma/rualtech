import type { Meta, StoryObj } from '@storybook/react-vite';
import { Search, Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { PriceDatePicker } from './price-date-picker';
import { PriceFileCard } from './price-file-upload';
import '../../../css/admin.css';

const meta = { title: 'Admin/Control system', parameters: { layout: 'fullscreen' } } satisfies Meta;
export default meta;
type Story = StoryObj<typeof meta>;

export const Sections: Story = {
    render: () => <div className="admin-app-shell" style={{ minHeight: '100vh' }}>
        <main className="admin-content">
            <div className="admin-page-heading"><h1>Обновить прайс</h1><Button><Plus/>Новая загрузка</Button></div>
            <section className="admin-surface mb-6">
                <div className="admin-section-heading"><h2>Параметры загрузки</h2></div>
                <div className="space-y-4">
                    <PriceDatePicker value="2026-11-10" today="2026-11-10" onChange={() => {}}/>
                    <PriceFileCard name="Прайс поставщика.xlsx" size={1048576} state="selected" onRemove={() => {}}/>
                    <div className="flex flex-wrap gap-2"><Button>Проверить прайс</Button><Button variant="outline">Другой файл</Button><Button variant="ghost">Отменить</Button><Button disabled>Обработка</Button></div>
                </div>
            </section>
            <section className="admin-surface">
                <div className="admin-section-heading"><h2>Позиции каталога</h2></div>
                <div className="space-y-4">
                    <Tabs defaultValue="all"><TabsList><TabsTrigger value="all">Все товары</TabsTrigger><TabsTrigger value="draft">Черновики</TabsTrigger></TabsList></Tabs>
                    <div className="flex flex-wrap items-center gap-2"><Input className="w-64" placeholder="Название или артикул"/><Button variant="outline"><Search/>Найти</Button><Button variant="outline">Фильтры</Button><Button variant="ghost" size="icon" aria-label="Удалить"><Trash2/></Button></div>
                    <div className="flex flex-wrap gap-2"><Button>Сохранить</Button><Button variant="outline">Отмена</Button><Button variant="destructive">Удалить</Button></div>
                </div>
            </section>
        </main>
    </div>,
};
