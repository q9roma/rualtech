import { useState } from 'react';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { PriceFileCard, PriceFileUpload } from './price-file-upload';
import '../../../css/admin.css';

const meta = {
    title: 'Admin/Price file upload',
    component: PriceFileUpload,
    args: { file: null, onChange: () => {}, processing: false, onError: () => {} },
    parameters: { layout: 'centered' },
} satisfies Meta<typeof PriceFileUpload>;
export default meta;
type Story = StoryObj<typeof meta>;

export const Interactive: Story = {
    render: () => {
        const [file, setFile] = useState<File | null>(null);
        const [error, setError] = useState<string>();
        return <div className="w-[368px] max-w-full"><PriceFileUpload file={file} onChange={setFile} error={error} onError={setError} processing={false}/></div>;
    },
};

export const ReferenceStates: Story = {
    render: () => <div className="w-[368px] max-w-full space-y-6">
        <PriceFileCard name="Legal document.pdf" size={28 * 1024 * 1024} state="completed" onRemove={() => {}}/>
        <PriceFileCard name="Legal document.pdf" size={28 * 1024 * 1024} state="in-progress" progress={33} onRemove={() => {}} onCancel={() => {}}/>
        <PriceFileCard name="Legal document.pdf" size={28 * 1024 * 1024} state="error" onRemove={() => {}}/>
    </div>,
};
