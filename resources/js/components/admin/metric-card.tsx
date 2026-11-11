import type { ReactNode } from 'react';

// shadcncraft Metric Card 1, Nova preset. Domain labels replace demo data.
export function MetricCard({ title, value, description, children }: { title: string; value: ReactNode; description?: ReactNode; children?: ReactNode }) {
    return <section data-slot="metric-card"><h3>{title}</h3><div data-slot="metric-card-value">{value}</div>{description && <div data-slot="metric-card-description">{description}</div>}{children}</section>;
}
