import registry from './brand-assets.json';

type BrandAsset = {
    file: string;
    color: string;
    palette: string[];
    monochrome?: boolean;
    bounds: { width: number; height: number; x: number; y: number; w: number; h: number };
    size: number;
};
const assets: Record<string, BrandAsset> = registry;
const aliases: Record<string, string> = {
    'tp-link': 'tplink', 'tp link': 'tplink', 'kingston technology': 'kingston',
    'a4 tech': 'a4tech', 'micro-star international': 'msi', 'wd': 'western digital',
    'g&g': 'gg', 'accordpc': 'accord pc', 'formula v-line': 'formula v line', 'formula': 'formula v line',
    'konica minolta': 'konica minolta, inc.',
};

/** Only verified local artwork; unknown brands fall back to the category icon. */
export function brandIdentity(brand: string | null | undefined) {
    const normalized = brand?.trim().toLowerCase().replace(/\s+/g, ' ');
    if (!normalized) return null;
    const key = Object.hasOwn(aliases, normalized) ? aliases[normalized] : normalized;
    if (!Object.hasOwn(assets, key)) return null;
    const asset = assets[key];
    return { logo: '/images/partners/' + asset.file, palette: asset.palette,
        monochrome: asset.monochrome ?? false, bounds: asset.bounds, size: asset.size };
}

export function brandGradient(palette: string[]) {
    const colors = palette.slice(0, 4);
    const glows = colors.map((color, index) => {
        const channels = color.slice(1).match(/../g)!.map(channel => parseInt(channel, 16));
        const neutral = Math.max(...channels) - Math.min(...channels) < 16;
        const x = colors.length === 1 ? 50 : 15 + index * 70 / (colors.length - 1);
        return `radial-gradient(ellipse ${colors.length === 1 ? 90 : 65}% 85% at ${x}% 110%, ${color}${neutral ? '18' : '38'} 0%, ${color}00 78%)`;
    });
    return `${glows.join(', ')}, #ffffff`;
}
