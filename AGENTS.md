# Storefront & Catalog Invariants

## 1. Catalog & Navigation Fidelity
- Always align navigation groups, category titles, and subcategory links with the exact directory structure of the product catalog (from `/home/arslan/Downloads/Products-cleaned` / supplier exports).
- Never invent placeholder column titles such as "MORE" or merge disparate product types (e.g. Race vs Race Suits).
- When a category contains dozens of items (e.g. 34 Film Jackets or 26 Movie Jackets), showcase the leading styles and provide an explicit "Shop All {Category} ({Total})" archive link rather than silently dropping items.

## 2. Product Detail Page Standards (LeatherCollection Benchmark)
- **Feature Bar**: Use a compact horizontal 4-column feature grid (`Made To Order`, `Free Worldwide Shipping`, `Delivery & Returns`, `Size Guide`) directly underneath product actions rather than tall stacked vertical blocks.
- **Custom Sizing & Options**:
  - Outerwear and suits must offer `Custom Size (+ $95.00)` alongside demographic size groupings (`Men`, `Women`).
  - Outerwear must support `Lining Options` (`Regular Viscose Lining`, `No Lining`, `Sublimated Print Lining`).
  - Product purchase forms must provide `Order Instructions` (`special_request`) for customizations.
- **Interactive Size Guides**:
  - Size charts must support both `CENTIMETER` and `INCHES` with instant unit toggling.
  - Multi-table categories (Men vs Women charts) must provide clear tabbed switching.
  - Sizing must be accessible via both an in-page tab and a direct popup modal.
- **Specifications & Information Tabs**:
  - Present comprehensive category-specific specs (Material, Construction, Armor/Protection, Closure, Pockets).
  - Include dedicated `Overview`, `Specifications`, `Details`, `Size Guide`, `FAQs`, and `Reviews` tabs.

