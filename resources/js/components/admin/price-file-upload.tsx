import { useId, useRef, useState } from 'react';
import { CircleAlert, CircleCheck, FileText, FileSpreadsheet, Loader, Trash2, X } from 'lucide-react';
import { cn } from '@/lib/utils';

type UploadState = 'selected' | 'in-progress' | 'completed' | 'error';
const sizeLabel = (bytes: number) => `${(bytes / 1024 / 1024).toLocaleString('ru-RU', { maximumFractionDigits: 2 })} МБ`;

// Geometry follows shadcncraft File Upload Card 1, preset b1YmpVjjk.
export function PriceFileCard({ name, size, state, progress = 0, onRemove, onCancel }: {
    name: string; size: number; state: UploadState; progress?: number;
    onRemove: () => void; onCancel?: () => void;
}) {
    const uploading = state === 'in-progress';
    const failed = state === 'error';
    const percent = Math.round(Math.min(100, Math.max(0, progress)));
    const awaitingResponse = uploading && percent === 100;
    return <div data-slot="file-upload-card" data-state={state} className={cn('flex w-full flex-wrap items-center gap-2.5 rounded-[14px] border border-border p-4 text-sm leading-5 transition-colors', uploading && 'border-primary/30 bg-primary/5', failed && 'border-destructive')}>
        <div className="flex size-12 shrink-0 items-center justify-center rounded-[10px] border border-border bg-secondary text-primary"><FileText className="size-[22px]" aria-hidden="true"/></div>
        <div className="flex min-w-0 flex-1 flex-col gap-1">
            <div className="line-clamp-1 break-all text-sm font-medium leading-[1.375]" title={name}>{name}</div>
            <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                <p className="line-clamp-1 text-sm leading-5 text-muted-foreground tabular-nums">{uploading ? `${sizeLabel(size * percent / 100)} / ` : ''}{sizeLabel(size)}</p>
                <div role="status" className="flex shrink-0 items-center gap-1 text-sm leading-5">
                    {failed ? <CircleAlert className="size-4 text-destructive"/> : uploading ? <Loader className="size-4 animate-spin text-primary motion-reduce:animate-none"/> : <CircleCheck className={cn('size-4', state === 'completed' ? 'text-[#16a34a] dark:text-[#76e0a7]' : 'text-muted-foreground')}/>}
                    {failed ? 'Ошибка' : uploading ? (awaitingResponse ? 'Ожидаем ответ сервера…' : 'Файл загружается…') : state === 'completed' ? 'Загружен' : 'Выбран'}
                </div>
            </div>
        </div>
        <button data-slot="file-upload-action" type="button" className={cn('inline-flex size-8 shrink-0 self-start items-center justify-center rounded-[10px] transition-colors hover:bg-accent focus-visible:outline-2 focus-visible:outline-ring disabled:pointer-events-none disabled:opacity-50', failed && 'text-destructive')} disabled={uploading && !onCancel} aria-label={uploading ? 'Отменить загрузку' : 'Убрать выбранный файл'} onClick={uploading ? onCancel : onRemove}>
            {uploading ? <X className="size-4"/> : <Trash2 className="size-4"/>}
        </button>
        {uploading && <div className="basis-full space-y-2"><div className="flex items-center justify-between gap-3 text-xs text-muted-foreground"><span>{awaitingResponse ? 'Файл передан. Не закрывайте страницу.' : 'Передаём файл на сервер'}</span><span className="shrink-0 font-medium tabular-nums text-foreground">{percent}%</span></div><div role="progressbar" aria-label="Загрузка прайса" aria-valuemin={0} aria-valuemax={100} aria-valuenow={percent} className="h-1.5 w-full overflow-hidden rounded-full bg-secondary"><div className="h-full w-full bg-primary transition-transform" style={{ transform: `translateX(-${100 - percent}%)` }}/></div></div>}
    </div>;
}

export function PriceFileUpload({ file, onChange, processing, progress, error, onError, onCancel, accept = ['.xlsx'], formatLabel = 'XLSX' }: {
    accept?: string[]; formatLabel?: string;
    file: File | null; onChange: (file: File | null) => void; processing: boolean;
    progress?: number; error?: string; onError: (error?: string) => void; onCancel?: () => void;
}) {
    const id = useId();
    const input = useRef<HTMLInputElement>(null);
    const [dragging, setDragging] = useState(false);
    function select(files: File[]) {
        if (processing || !files.length) return;
        const selected = files[0];
        const message = files.length > 1 ? 'Выберите один файл прайса.'
            : !accept.some(extension => selected.name.toLowerCase().endsWith(extension)) ? `Нужен файл в формате ${formatLabel}.`
            : selected.size > 20 * 1024 * 1024 ? 'Размер файла не должен превышать 20 МБ.' : undefined;
        onError(message);
        onChange(message ? null : selected);
    }
    const browse = () => { if (!processing) input.current?.click(); };
    return <div className="w-full space-y-4">
        <input ref={input} id={id} className="sr-only" type="file" accept={accept.join(',')} tabIndex={-1} aria-label="Файл прайса" aria-describedby={`${id}-help`} disabled={processing}
            onChange={event => { select(Array.from(event.target.files ?? [])); event.target.value = ''; }}/>
        <div role="button" tabIndex={processing ? -1 : 0} aria-disabled={processing} aria-describedby={`${id}-help`} data-slot="file-upload-area"
            className={cn('group flex cursor-pointer flex-col items-center gap-3 rounded-[10px] border border-dashed border-input p-4 outline-none transition-[background-color,box-shadow] hover:bg-primary-foreground focus-visible:border-solid focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-disabled:cursor-not-allowed aria-disabled:bg-secondary', dragging && 'bg-primary-foreground border-ring', error && 'border-destructive')}
            onClick={browse} onKeyDown={event => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); browse(); } }}
            onDragOver={event => { event.preventDefault(); if (!processing) setDragging(true); }}
            onDragLeave={event => { if (!event.currentTarget.contains(event.relatedTarget as Node)) setDragging(false); }}
            onDrop={event => { event.preventDefault(); setDragging(false); select(Array.from(event.dataTransfer.files)); }}>
            <div className="flex size-10 items-center justify-center rounded-full border border-input/80 text-muted-foreground group-aria-disabled:text-gray-400"><FileSpreadsheet className="size-[18px]" aria-hidden="true"/></div>
            <div className="flex flex-col items-center gap-1">
                <div className="text-center text-sm font-medium leading-5 text-balance group-aria-disabled:text-gray-400">Перетащите файл или <span className="underline underline-offset-2 group-aria-disabled:no-underline">выберите</span></div>
                <p id={`${id}-help`} className="text-center text-xs leading-4 text-balance text-muted-foreground group-aria-disabled:text-gray-400">{formatLabel} (макс. 20 МБ)</p>
            </div>
        </div>
        {file && <PriceFileCard name={file.name} size={file.size} state={error ? 'error' : processing ? 'in-progress' : 'selected'} progress={progress} onCancel={onCancel} onRemove={() => { onChange(null); onError(); }}/>} 
        {error && <p role="alert" className="text-sm text-destructive">{error}</p>}
    </div>;
}
