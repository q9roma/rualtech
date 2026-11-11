import type { Meta, StoryObj } from '@storybook/react-vite';

function Palette() {
    const tokens = ['card', 'foreground', 'primary', 'secondary', 'muted', 'muted-foreground', 'border', 'destructive'];
    return <div><h1 style={{ fontSize: 24, marginBottom: 16 }}>Поверхности и цвета Алтех</h1><p style={{ marginBottom: 24 }}>Фон страницы — #fafafa. Белые карточки располагаются поверх него. Цвета ниже читаются из переменных storefront.css.</p><div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: 16 }}>{tokens.map(token => <div key={token} style={{ border: '1px solid var(--border)', borderRadius: 12, overflow: 'hidden' }}><div style={{ height: 80, background: `var(--${token})` }} /><p style={{ padding: 12, background: 'var(--card)' }}>--{token}</p></div>)}</div><p style={{ marginTop: 24 }}>--background сейчас задаёт белую поверхность для базовых контролов shadcn. Серый фон страницы задан отдельно в body. Здесь показана текущая реализация проекта.</p></div>;
}
const meta = { title: 'Основа/Палитра', component: Palette } satisfies Meta<typeof Palette>;
export default meta;
export const Default: StoryObj<typeof meta> = { name: 'Цвета проекта' };
