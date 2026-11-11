export type CartLine = { id: number; image: string | null; imagePreview: string | null; imageBackground: string | null; url: string | null; brand: string | null; category: string | null; name: string; sku: string | null; quantity: number; unitPrice: string; total: string };
export type Cart = { lines: CartLine[]; total: string; fingerprint: string; issues: string[] };
