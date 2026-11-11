# Admin component references

Verified against live examples on 2026-11-10. Keep the Russian domain content and existing Inertia actions; do not replace business rules with demo behavior.

| Admin surface | Reference |
| --- | --- |
| Shared floating sidebar, navigation density, account menu, header | https://shadcncraft.com/blocks/pro-application/app-shells/app-shell-2 |
| Dashboard and price application metrics | https://shadcncraft.com/components/pro-application/metric-card (example 1, Nova preset) |
| Upload area and selected file, including CSV import | https://shadcncraft.com/components/pro-application/file-upload-area and https://shadcncraft.com/components/pro-application/file-upload-card |
| Price workflow steps | https://shadcncraft.com/components/pro-application/progress-item (example 1) |
| Comparison, history and resource tables | https://shadcncraft.com/components/official-shadcn/table |
| Product/photo containers | https://shadcncraft.com/components/official-shadcn/card |
| Photo details and blocked publication items | https://shadcncraft.com/components/official-shadcn/accordion |
| Empty comparison/photo/notification lists | https://shadcncraft.com/components/official-shadcn/empty |
| Buttons, checkboxes, badges and forms | Official shadcn components linked from the shadcncraft catalog |

The official-shadcn entries explicitly link to ui.shadcn.com for React implementation. Pro examples were inspected via their public rendered previews; this is not an import of licensed Pro source code.

App Shell 2 adaptation: floating sidebar with an 8px outer inset and 12px corners; white sidebar on a gray (#f4f4f5) workspace in light mode. The header and workspace span the viewport height; the price workspace retains its independent scrolling and pinned footer. Card/input background tokens remain separate from the shell background.

Measured Nova geometry: navigation row 32px, account row 48px, sidebar padding 8px, header 56px; metric padding 16px, radius 14px, heading 16/24px, value 24/32px, description 14/20px.

Browser checks: all sidebar destination pages; ready-upload comparison/application; completed-upload photos/result; account dropdown; accordion expansion; collapsed sidebar; page creation form; mobile form and navigation. No prices applied, products published or form records created during UI verification.
