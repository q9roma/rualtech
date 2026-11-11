import { useEffect, useState, useRef } from 'react';
import { ArrowLeft, ArrowRight, Plus, Trash2, ImageIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
export type Specification = { name: string; value: string };
export type Photo = File | string;

export function Specifications({ value, onChange, readOnly }: {value: Specification[]; onChange: (value: Specification[]) => void; readOnly: boolean}) {
    return <div className="space-y-4">{value.map((item, index) => <div className="admin-specification-row" key={index}>
        <Input aria-label={`Характеристика ${index + 1}`} placeholder="Название, например Интерфейс" value={item.name} readOnly={readOnly} onChange={e => onChange(value.map((row, i) => i === index ? {...row, name:e.target.value} : row))}/>
        <Input aria-label={`Значение ${index + 1}`} placeholder="Значение, например USB" value={item.value} readOnly={readOnly} onChange={e => onChange(value.map((row, i) => i === index ? {...row, value:e.target.value} : row))}/>
        {!readOnly && <Button type="button" variant="ghost" size="icon" aria-label={`Удалить характеристику ${index + 1}`} onClick={() => onChange(value.filter((_, i) => i !== index))}><Trash2/></Button>}
    </div>)}{!value.length && <p className="text-sm text-muted-foreground">Характеристики пока не добавлены.</p>}{!readOnly && <Button type="button" variant="outline" disabled={value.length >= 100} onClick={() => onChange([...value, {name:'', value:''}])}><Plus/>Добавить характеристику</Button>}</div>;
}
function PhotoPreview({photo, url}: {photo: Photo; url?: string}) {
    const [src, setSrc] = useState(url);
    useEffect(() => {if (photo instanceof File) {const objectUrl=URL.createObjectURL(photo);setSrc(objectUrl);return()=>URL.revokeObjectURL(objectUrl);}setSrc(url);},[photo,url]);
    return src ? <img src={src} alt="Фото товара"/> : <ImageIcon/>;
}
export function ProductPhotos({value, originals, urls, cover, onChange, readOnly}: {value: Photo[] | null; originals: string[]; urls: string[]; cover?: string; onChange: (value: Photo[]) => void; readOnly: boolean}) {
    const [error,setError]=useState(''); const photoInput=useRef<HTMLInputElement>(null); const photos=value ?? [];
    function move(index:number, direction:number) {const next=[...photos];[next[index],next[index+direction]]=[next[index+direction],next[index]];onChange(next);}
    return <div className="space-y-4">
        {value === null && cover && <div className="space-y-2"><p className="text-sm text-muted-foreground">Сейчас используется фото из каталога. Загруженные фотографии заменят автоматическую галерею.</p><img className="h-32 w-40 object-contain" src={cover} alt="Текущая обложка"/>{!readOnly && <Button type="button" variant="outline" onClick={()=>onChange([])}>Убрать фотографии из карточки</Button>}</div>}
        <div className="admin-photo-grid">{photos.map((photo,index)=><div className="admin-photo-item" key={index}><PhotoPreview photo={photo} url={typeof photo==='string' ? urls[originals.indexOf(photo)] : undefined}/><span className="text-xs text-muted-foreground">{index===0 ? 'Обложка' : `Фото ${index+1}`}</span>{!readOnly && <div className="flex gap-1"><Button type="button" size="icon" variant="outline" disabled={index===0} aria-label={`Фото ${index+1}: влево`} onClick={()=>move(index,-1)}><ArrowLeft/></Button><Button type="button" size="icon" variant="outline" disabled={index===photos.length-1} aria-label={`Фото ${index+1}: вправо`} onClick={()=>move(index,1)}><ArrowRight/></Button><Button type="button" size="icon" variant="ghost" aria-label={`Удалить фото ${index+1}`} onClick={()=>onChange(photos.filter((_,i)=>i!==index))}><Trash2/></Button></div>}</div>)}</div>
        {!photos.length && !cover && <p className="text-sm text-muted-foreground">Фотографий пока нет.</p>}
        {!readOnly && <><Button type="button" variant="outline" onClick={()=>photoInput.current?.click()}><Plus/>Добавить фотографии</Button><Input ref={photoInput} className="hidden" type="file" multiple accept="image/jpeg,image/png,image/webp" aria-label="Добавить фотографии" onChange={e=>{const files=Array.from(e.target.files ?? []);e.target.value='';if(photos.length+files.length>12 || files.some(f=>!['image/jpeg','image/png','image/webp'].includes(f.type)||f.size>10*1024*1024)){setError('Можно загрузить до 12 фото JPEG, PNG или WebP, каждое до 10 МБ.');return;}setError('');onChange([...photos,...files]);}}/></>}
        {error && <p role="alert" className="admin-error">{error}</p>}
    </div>;
}
