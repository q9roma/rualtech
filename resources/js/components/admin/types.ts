export type Options = Record<string, string>;
export type Field = { key: string; label: string; type: string; required?: boolean; default?: unknown; options?: Options; hint?: string; searchable?: boolean; crop?: boolean; readOnly?: boolean };
export type Column = Field & { visible?: boolean; sortable?: boolean; copyable?: boolean };
export type RecordData = { id: number; canEdit?: boolean; canDelete?: boolean; [key: string]: unknown };
export type Pagination<T> = { data: T[]; current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null; prev_page_url: string | null; next_page_url: string | null; first_page_url: string; last_page_url: string };
export type AdminShared = { admin: { name: string; email: string; unread: number; navigation: { key: string; label: string; url: string }[] }; flash: { success?: string }; [key: string]: unknown };
