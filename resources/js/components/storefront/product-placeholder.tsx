import { useState } from 'react';
import { brandGradient, brandIdentity } from './brand-identity';
import { categoryIcon } from './category-icon';
import { BrandLogo } from './brand-logo';

export function ProductPlaceholder({ brand, category }: { brand: string | null; category: string | null }) {
    const identity = brandIdentity(brand);
    const [failedLogo, setFailedLogo] = useState<string | null>(null);
    const branded = identity && failedLogo !== identity.logo;
    const Icon = categoryIcon(category);
    return <div className="product-placeholder" data-branded={!!branded} style={{ background: branded ? brandGradient(identity.palette) : undefined }}>
        {branded ? <><div className="product-placeholder-logo"><BrandLogo identity={identity} label={'Логотип ' + brand?.trim()} onError={() => setFailedLogo(identity.logo)} /></div></>
            : <Icon className="product-placeholder-category-icon" size={40} strokeWidth={1.75} aria-hidden="true" />}
    </div>;
}
