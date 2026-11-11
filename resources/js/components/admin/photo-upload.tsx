// Ready-made Dice UI registry component; this adapter only connects it to the photo API.
import { useState } from 'react';
import { Upload, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    FileUpload, FileUploadDropzone, FileUploadTrigger, FileUploadList,
    FileUploadItem, FileUploadItemPreview, FileUploadItemMetadata,
    FileUploadItemProgress, FileUploadItemDelete,
} from '@/components/ui/file-upload';

export function PhotoUpload({disabled,onUpload,onError}:{disabled:boolean;onUpload:(file:File,onProgress:(progress:number)=>void)=>Promise<void>;onError:(message:string)=>void}) {
    const [files,setFiles]=useState<File[]>([]);
    return <FileUpload className="h-full min-h-96 min-w-0" value={files} onValueChange={setFiles} disabled={disabled}
        accept="image/jpeg,image/png,image/webp" maxFiles={1} maxSize={10*1024*1024}
        label="Загрузить своё фото"
        onFileValidate={file=>!['image/jpeg','image/png','image/webp'].includes(file.type) ? 'Нужен файл JPG, PNG или WebP.' : file.size>10*1024*1024 ? 'Размер фотографии не должен превышать 10 МБ.' : undefined}
        onFileReject={(file,message)=>onError(message.includes('Maximum') || message.includes('many') ? 'Выберите одну фотографию. Уберите предыдущий файл из загрузчика.' : message)}
        onUpload={async(selected,{onProgress,onSuccess,onError:uploadError})=>{
            const file=selected[0];
            if(!file) return;
            try {
                await onUpload(file,progress=>onProgress(file,progress));
                onSuccess(file);
                setFiles([]);
            } catch(error) {
                uploadError(file,error instanceof Error ? error : new Error('Не удалось загрузить фото.'));
            }
        }}>
        <FileUploadDropzone className="min-h-60 flex-1 rounded-xl">
            <div className="flex size-10 items-center justify-center rounded-full border"><Upload className="size-5 text-muted-foreground" aria-hidden="true"/></div>
            <div className="space-y-1 text-center">
                <p className="text-sm font-medium">Загрузить своё фото</p>
                <p className="text-xs text-muted-foreground">Перетащите фото сюда. JPG, PNG, WebP до 10 МБ.</p>
            </div>
            <FileUploadTrigger asChild><Button variant="outline" disabled={disabled}>Выбрать фото</Button></FileUploadTrigger>
        </FileUploadDropzone>
        <FileUploadList>{files.map(file=><FileUploadItem key={`${file.name}-${file.lastModified}`} value={file} className="flex-wrap">
            <FileUploadItemPreview/>
            <FileUploadItemMetadata title={file.name}/>
            <FileUploadItemDelete asChild><Button variant="ghost" size="icon" disabled={disabled} aria-label="Убрать файл из загрузчика"><X aria-hidden="true"/></Button></FileUploadItemDelete>
            <FileUploadItemProgress className="basis-full"/>
        </FileUploadItem>)}</FileUploadList>
    </FileUpload>;
}
