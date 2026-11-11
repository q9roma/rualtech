import { useState } from 'react';
import { EditorContent, useEditor, useEditorState } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import { TableKit } from '@tiptap/extension-table';
import TextAlign from '@tiptap/extension-text-align';
import Subscript from '@tiptap/extension-subscript';
import Superscript from '@tiptap/extension-superscript';
import axios from 'axios';
import { Bold, Italic, Underline, Strikethrough, Subscript as SubscriptIcon, Superscript as SuperscriptIcon, List, ListOrdered, Quote, Code, Link2, Undo2, Redo2, Paperclip, Table2, AlignLeft, AlignCenter, AlignRight, Minus, Heading2, Heading3, Pilcrow } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog';

export default function RichEditor({ value, onChange, resource, recordId, label }: { value: string; onChange: (html: string) => void; resource: string; recordId?: number; label: string }) {
    const [linkOpen, setLinkOpen] = useState(false);
    const [href, setHref] = useState('');
    const [error, setError] = useState('');
    const [uploading, setUploading] = useState(false);
    const editor = useEditor({ extensions: [StarterKit.configure({ link: { openOnClick: false } }), Image, TableKit, Subscript, Superscript, TextAlign.configure({ types: ['heading', 'paragraph'] })], content: value, immediatelyRender: false,
        editorProps: { attributes: { class: 'admin-rich-editor', role: 'textbox', 'aria-label': label, 'aria-multiline': 'true' } }, onUpdate: ({ editor }) => onChange(editor.getHTML()), });
    useEditorState({ editor, selector: context => ({ bold: context.editor?.isActive('bold'), italic: context.editor?.isActive('italic'), transaction: context.editor?.state }) });
    if (!editor) return <div className="admin-editor-loading">Загрузка редактора…</div>;
    const buttons = [
        { label: 'Абзац', Icon: Pilcrow, active: editor.isActive('paragraph'), run: () => editor.chain().focus().setParagraph().run() },
        { label: 'Заголовок 2', Icon: Heading2, active: editor.isActive('heading', {level: 2}), run: () => editor.chain().focus().toggleHeading({level: 2}).run() },
        { label: 'Заголовок 3', Icon: Heading3, active: editor.isActive('heading', {level: 3}), run: () => editor.chain().focus().toggleHeading({level: 3}).run() },
        { label: 'Жирный', Icon: Bold, active: editor.isActive('bold'), run: () => editor.chain().focus().toggleBold().run() },
        { label: 'Курсив', Icon: Italic, active: editor.isActive('italic'), run: () => editor.chain().focus().toggleItalic().run() },
        { label: 'Подчёркнутый', Icon: Underline, active: editor.isActive('underline'), run: () => editor.chain().focus().toggleUnderline().run() },
        { label: 'Зачёркнутый', Icon: Strikethrough, active: editor.isActive('strike'), run: () => editor.chain().focus().toggleStrike().run() },
        { label: 'Подстрочный', Icon: SubscriptIcon, active: editor.isActive('subscript'), run: () => editor.chain().focus().toggleSubscript().run() },
        { label: 'Надстрочный', Icon: SuperscriptIcon, active: editor.isActive('superscript'), run: () => editor.chain().focus().toggleSuperscript().run() },
        { label: 'Список', Icon: List, active: editor.isActive('bulletList'), run: () => editor.chain().focus().toggleBulletList().run() },
        { label: 'Нумерованный список', Icon: ListOrdered, active: editor.isActive('orderedList'), run: () => editor.chain().focus().toggleOrderedList().run() },
        { label: 'Цитата', Icon: Quote, active: editor.isActive('blockquote'), run: () => editor.chain().focus().toggleBlockquote().run() },
        { label: 'Блок кода', Icon: Code, active: editor.isActive('codeBlock'), run: () => editor.chain().focus().toggleCodeBlock().run() },
        { label: 'По левому краю', Icon: AlignLeft, run: () => editor.chain().focus().setTextAlign('left').run() },
        { label: 'По центру', Icon: AlignCenter, run: () => editor.chain().focus().setTextAlign('center').run() },
        { label: 'По правому краю', Icon: AlignRight, run: () => editor.chain().focus().setTextAlign('right').run() },
        { label: 'Разделитель', Icon: Minus, run: () => editor.chain().focus().setHorizontalRule().run() },
        { label: 'Добавить таблицу', Icon: Table2, run: () => editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run() },
    ];
    return <div className="admin-editor"><div className="admin-editor-toolbar" role="toolbar" aria-label="Форматирование текста">{buttons.map(({label, Icon, active, run}) => <Button type="button" size="icon" variant={active ? 'secondary' : 'ghost'} key={label} title={label} aria-label={label} aria-pressed={active} onClick={run}><Icon size={15}/></Button>)}
        <Button type="button" size="icon" variant="ghost" title="Ссылка" aria-label="Добавить ссылку" onClick={() => { setHref(editor.getAttributes('link').href ?? ''); setLinkOpen(true); }}><Link2/></Button>
        <label className="admin-upload-attachment" title="Прикрепить файл"><Paperclip size={16}/><span className="sr-only">Прикрепить изображение или документ</span><input type="file" disabled={uploading} onChange={async e => { const file = e.target.files?.[0]; if (!file) return; setUploading(true); setError(''); const data = new FormData(); data.append('file', file); if (recordId) data.append('record_id', String(recordId)); try { const {data: result} = await axios.post(`/admin/${resource}/attachments`, data, {headers: {'X-Requested-With':'XMLHttpRequest'}}); if (result.image) editor.chain().focus().setImage({src: result.url, alt: file.name}).run(); else editor.chain().focus().insertContent([{type:'text', text:result.name, marks:[{type:'link',attrs:{href:result.url}}]}]).run(); } catch { setError('Не удалось загрузить файл. Допустимы изображения и документы до 12 МБ.'); } finally {setUploading(false); e.target.value = '';} }}/></label>
        <Button type="button" size="icon" variant="ghost" aria-label="Отменить" disabled={!editor.can().undo()} onClick={() => editor.chain().focus().undo().run()}><Undo2/></Button><Button type="button" size="icon" variant="ghost" aria-label="Повторить" disabled={!editor.can().redo()} onClick={() => editor.chain().focus().redo().run()}><Redo2/></Button>
        {editor.isActive('table') && <><Button type="button" variant="ghost" onClick={() => editor.chain().focus().addRowAfter().run()}>+ Строка</Button><Button type="button" variant="ghost" onClick={() => editor.chain().focus().addColumnAfter().run()}>+ Столбец</Button><Button type="button" variant="ghost" onClick={() => editor.chain().focus().deleteRow().run()}>− Строка</Button><Button type="button" variant="ghost" onClick={() => editor.chain().focus().deleteColumn().run()}>− Столбец</Button><Button type="button" variant="ghost" onClick={() => editor.chain().focus().deleteTable().run()}>Удалить таблицу</Button></>}</div>
        {uploading && <p role="status" className="p-2 text-sm">Загружаем файл…</p>}{error && <p role="alert" className="admin-error p-2">{error}</p>}<EditorContent editor={editor}/>
        <Dialog open={linkOpen} onOpenChange={setLinkOpen}><DialogContent><DialogHeader><DialogTitle>Ссылка</DialogTitle><DialogDescription>Укажите адрес страницы. Пустой адрес удаляет ссылку.</DialogDescription></DialogHeader><Input aria-label="Адрес ссылки" placeholder="https://…" value={href} onChange={e => setHref(e.target.value)}/><DialogFooter><Button type="button" variant="outline" onClick={() => setLinkOpen(false)}>Отмена</Button><Button type="button" onClick={() => { if (!href) editor.chain().focus().extendMarkRange('link').unsetLink().run(); else if (/^(https?:\/\/|mailto:|tel:|\/|#)/i.test(href) && !href.startsWith('//')) editor.chain().focus().extendMarkRange('link').setLink({href}).run(); else { setError('Используйте http, https, mailto, tel или относительный адрес.'); return; } setLinkOpen(false); }}>Применить</Button></DialogFooter></DialogContent></Dialog>
    </div>;
}
