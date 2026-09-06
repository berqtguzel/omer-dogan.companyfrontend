# Corporate design system

Entry: `design-system.css`, loaded once by `app.jsx`.

- `tokens.css`: design values and responsive thresholds.
- `base.css`: opt-in corporate typography and accessibility foundations.
- `layout.css`: containers, sections and responsive grids.
- `utilities.css`: small reusable composition helpers.
- `components/`: one stylesheet per shared component, imported by that component.
- `pages/`: page-specific composition only, imported by the page.

New layouts use `corporate-site`. Apply `corporateTheme(settings.colors)` to
that boundary so absent panel colors retain corporate defaults. Do not read
panel color keys in individual components. Dynamic inline styles are reserved
for this theme boundary and data-dependent values.

Each selector has one owner. When migrating a legacy component, remove its old
stylesheet imports and rules once its remaining consumers migrate. Do not add
corporate rules to `redesign.css` or `cards-modern.css`. The legacy styles remain
active until their pages migrate; this foundation does not restyle those pages.

Fonts use Manrope / Inter with system fallbacks. Font declarations alone do not
download assets; local licensed font files must be supplied before enabling
font-face/preload. Avoid adding another font family or remote font dependency.

Responsive layout is mobile first at 40rem, 60rem and 80rem. Query literals must
match this convention because custom properties cannot serve as media queries.
Use duration tokens and respect reduced motion. Grids use minmax(0, 1fr) to
prevent content-driven horizontal overflow.
