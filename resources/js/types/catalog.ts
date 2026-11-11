export type Product = {
    id: number;
    name: string;
    description?: string | null;
    specifications?: {name: string; value: string}[];
    supplierDescription?: string | null;
    sku: string | null;
    brand: string | null;
    category: string | null;
    categoryUrl?: string;
    categoryTone?: 'sky' | 'lavender' | 'teal' | 'green' | 'sand' | 'neutral';
    availability: string | null;
    formattedPrice: string;
    hasPrice?: boolean;
    inStock?: boolean;
    image?: string | null;
    images?: string[];
    imagePreviews?: Record<string, string>;
    imageBackgrounds?: Record<string, string>;
    imageAlt?: string;
    url: string;
    canAddToCart?: boolean;
    addToCartUrl?: string;
};

export type CatalogProps = {
    categoryGuide?: { heading: string; intro: string; checks: string[] } | null;
    navigation: { home: string; catalog: string; services: string; blog: string; logo: string; catalogCategories?: { name: string; count: number; url: string; children?: { name: string; url: string }[] }[]; pages?: { name: string; url: string }[] };
    products: {
        data: Product[];
        total: number;
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: { search: string; category: string; sort: string; price_min: number | null; price_max: number | null; brands: string[]; in_stock: boolean };
    facets: { brands: { name: string; count: number }[]; priceMin: string | number | null; priceMax: string | number | null };
    categories: { category: string; products_count: number; url?: string; children?: { name: string; url: string; count: number }[] }[];
    totalActiveProducts: number;
    canonicalUrl: string;
};

