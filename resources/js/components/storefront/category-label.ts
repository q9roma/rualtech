/** Display names stay independent of category keys used by filters and URLs. */
export function categoryLabel(name: string): string {
    return name === 'Печать и расходные материалы' ? 'Печать и расходники' : name;
}
