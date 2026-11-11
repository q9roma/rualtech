import type { brandIdentity } from './brand-identity';

type Identity = NonNullable<ReturnType<typeof brandIdentity>>;

/** Crop only transparent padding; preserve proportions and center visible artwork. */
export function BrandLogo({ identity, label, onError }: { identity: Identity; label: string; onError?: () => void }) {
    const bounds = identity.bounds;
    const style = { width: `${identity.size}%`, filter: identity.monochrome ? 'brightness(0)' : undefined };
    if (!bounds) return <img src={identity.logo} alt={label} style={style} onError={onError} />;
    return <svg className="product-brand-art" viewBox={`${bounds.x} ${bounds.y} ${bounds.w} ${bounds.h}`} role="img" aria-label={label} style={style}>
        <image href={identity.logo} width={bounds.width} height={bounds.height} onError={onError} />
    </svg>;
}
