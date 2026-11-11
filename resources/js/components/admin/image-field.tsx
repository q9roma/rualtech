import { useEffect, useRef, useState } from 'react';
import { Crop, RotateCw, Upload, X } from 'lucide-react';
import { Slider } from '@/components/ui/slider';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog';

export default function ImageField({ value, currentUrl, onChange, label, crop = false }: { value: File | string | null; currentUrl?: string; onChange: (file: File | null) => void; label: string; crop?: boolean }) {
    const [preview, setPreview] = useState(currentUrl ?? '');
    const [open, setOpen] = useState(false);
    const [zoom, setZoom] = useState(1);
    const [x, setX] = useState(50);
    const [y, setY] = useState(50);
    const [rotation, setRotation] = useState(0);
    const [error, setError] = useState('');
    const canvas = useRef<HTMLCanvasElement>(null); const fileInput = useRef<HTMLInputElement>(null);
    useEffect(() => { if (value instanceof File) { const url = URL.createObjectURL(value); setPreview(url); return () => URL.revokeObjectURL(url); } setPreview(value ? currentUrl ?? '' : ''); }, [value, currentUrl]);
    useEffect(() => {
        if (!open || !preview) return;
        let active = true;
        const img = new window.Image(); img.crossOrigin = 'anonymous'; img.onload = () => {
            if (!active || !canvas.current) return;
            const context = canvas.current.getContext('2d'); if (!context) return;
            const rotated = document.createElement('canvas'); rotated.width = rotation % 180 ? img.height : img.width; rotated.height = rotation % 180 ? img.width : img.height;
            const ctx = rotated.getContext('2d')!; ctx.translate(rotated.width/2, rotated.height/2); ctx.rotate(rotation*Math.PI/180); ctx.drawImage(img,-img.width/2,-img.height/2);
            const scale = Math.max(800/rotated.width, 450/rotated.height)*zoom; const width = rotated.width*scale, height = rotated.height*scale;
            context.clearRect(0,0,800,450); context.drawImage(rotated, -(width-800)*x/100, -(height-450)*y/100, width,height);
        }; img.onerror = () => setError('Не удалось открыть изображение для редактирования. Загрузите файл заново.'); img.src = preview;
        return () => { active = false; };
    }, [open, preview, zoom, x, y, rotation]);
    function choose(file?: File) { if (!file) return; if (!['image/jpeg','image/png','image/webp','image/gif'].includes(file.type) || file.size > 10*1024*1024) {setError('Выберите JPEG, PNG, WebP или GIF до 10 МБ.');return;} setError(''); onChange(file);setZoom(1);setX(50);setY(50);setRotation(0); }
    return <div className="admin-image-field" onDragOver={e=>e.preventDefault()} onDrop={e=>{e.preventDefault();choose(e.dataTransfer.files[0]);}}>
        {preview ? <img src={preview} alt={label}/> : <button type="button" className="admin-image-placeholder" aria-label={label+': выбрать изображение'} onClick={()=>fileInput.current?.click()}><Upload/><span>Выберите изображение или перетащите файл</span></button>}
        <input ref={fileInput} className="hidden" aria-label={label} type="file" accept="image/jpeg,image/png,image/webp,image/gif" onChange={e => choose(e.target.files?.[0])}/>
        {error && <p role="alert" className="admin-error mt-2">{error}</p>}
        {preview && <div className="flex flex-wrap gap-2 mt-4"><Button type="button" variant="outline" onClick={()=>fileInput.current?.click()}><Upload/>Заменить</Button>{crop && <Button variant="outline" type="button" onClick={()=>setOpen(true)}><Crop/>Кадрировать</Button>}<Button variant="ghost" type="button" onClick={()=>onChange(null)}><X/>Убрать</Button></div>}
        <Dialog open={open} onOpenChange={setOpen}><DialogContent className="sm:max-w-2xl"><DialogHeader><DialogTitle>Обложка категории</DialogTitle><DialogDescription>Кадрирование 16:9, итоговый размер 800 × 450 пикселей.</DialogDescription></DialogHeader><canvas className="w-full rounded-lg border" ref={canvas} width={800} height={450}/>{error && <p role="alert" className="admin-error">{error}</p>}<div className="space-y-2"><span className="text-sm font-medium">Масштаб</span><Slider aria-label="Масштаб" min={1} max={3} step={0.01} value={[zoom]} onValueChange={v => setZoom(v[0])}/></div><div className="space-y-2"><span className="text-sm font-medium">По горизонтали</span><Slider aria-label="По горизонтали" value={[x]} onValueChange={v => setX(v[0])}/></div><div className="space-y-2"><span className="text-sm font-medium">По вертикали</span><Slider aria-label="По вертикали" value={[y]} onValueChange={v => setY(v[0])}/></div><DialogFooter><Button variant="outline" type="button" onClick={() => setOpen(false)}>Отмена</Button><Button variant="outline" type="button" onClick={() => setRotation(r => (r+90)%360)}><RotateCw/>Повернуть</Button><Button type="button" onClick={() => {try {canvas.current?.toBlob(blob => {if(blob) {onChange(new File([blob], 'cover.jpg', {type:'image/jpeg'}));setOpen(false);setZoom(1);setRotation(0);setX(50);setY(50);}}, 'image/jpeg',.9);} catch {setError('Не удалось обработать изображение. Загрузите файл заново.');}}}>Применить</Button></DialogFooter></DialogContent></Dialog>
    </div>;
}
