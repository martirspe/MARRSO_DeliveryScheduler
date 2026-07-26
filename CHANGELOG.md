# Changelog

All notable changes to the MARRSO Magento 2 extension suite are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-05-23

### Added

#### MARRSO_Base (new module)
- Shared vendor module (`MARRSO_Base`) for MARRSO-branded admin experience
- Amasty-style sidebar menu: **MARRSO** brand logo + flyout with grouped columns
- Menu groups: **Extensions**, module management columns, **Settings & Configuration**
- Brand dashboard at `Admin → MARRSO → Dashboard` (`/marrso/dashboard/index`)
- `ExtensionPool` with `marrso_base_collect_extensions` event for registering extensions
- Shared admin CSS (`marrso-admin.css`) and logo assets
- ACL resource `MARRSO_Base::menu`
- Data patch `GrantBaseMenuToExistingRoles` — grants Base menu to roles that already had Delivery Scheduler access
- Spanish translations for Base admin UI (`i18n/es_ES.csv`)

#### MARRSO_DeliveryScheduler
- Observer `RegisterBaseExtension` — registers Delivery Scheduler in the Base extension pool
- Pickup Slots admin CRUD (controllers, UI components, grid collection, data providers)
- Admin dashboard with stats, quick-access cards and configuration shortcuts
- Express checkout page (`/delivery/checkout/express`)
- Express delivery modalities in checkout: 180 min, 24 h, scheduled delivery
- Leaflet map for pickup point selection
- Configurable express prices: `express_180_price`, `express_24_price`, `default_delivery_price`
- Seed patches: extended demo data, pickup slots manual control, express slots, Lima demo data
- `auto_generate_slots` flag on pickup locations — cron skips manually managed locations
- Spanish translations for checkout and admin (`i18n/es_ES.csv`)

### Changed
- **Architecture**: brand/menu assets moved from `MARRSO_DeliveryScheduler` to `MARRSO_Base`
- Delivery Scheduler menu items now hang from `MARRSO_Base::marrso`, `::extensions` and `::settings`
- Delivery Scheduler ACL nested under `MARRSO_Base::menu`
- `composer.json` requires `marrso/module-base`; autoload registers both modules
- Checkout method cards: **GRATIS** only for pickup; delivery methods show real or fallback prices
- `ExpressSlotGenerator` uses config prices instead of hardcoded constants
- Admin listing columns reordered: selectors first, actions last; ID column renamed
- Dashboard **Configuration shortcuts** block spacing fixed

### Fixed
- Invalid admin menu parent `Magento_Backend::menu` (top-level items must omit `parent`)
- Menu icon size and default Magento glyph override (custom MARRSO logo at level-0)
- Submenu square glyph before Dashboard (conflict with core `item-dashboard` CSS class)
- Admin CRUD routes: `pickuplocation`, `deliveryslot` (lowercase, no underscores)
- Admin UI grids: grid collections, `updateUrl`, mass actions, form `dataScope`
- Database schema: foreign keys, nullable fields, quote address extension columns

### Migration notes (1.0.x → 1.1.0)
1. Deploy **both** modules: `app/code/MARRSO/Base` and `app/code/MARRSO/DeliveryScheduler`
2. Run:
   ```bash
   bin/magento module:enable MARRSO_Base MARRSO_DeliveryScheduler
   bin/magento setup:upgrade
   bin/magento cache:flush
   bin/magento setup:static-content:deploy -f es_ES en_US --area adminhtml
   ```
3. Verify admin role has **MARRSO** permission (patch applies automatically on upgrade)
4. Configuration path unchanged: `Stores → Configuration → MARRSO → Delivery Scheduler`
5. Admin navigation is now under the **MARRSO** sidebar menu (not Content or standalone Delivery Scheduler root)

---

## [1.0.0] - 2026-05-01

### Added
- Initial release of **MARRSO_DeliveryScheduler**
- Pickup points with geolocation and distance calculation (Haversine)
- Home delivery slots by district with dynamic pricing and capacity
- Falabella-style checkout UI (KnockoutJS)
- REST API: pickup locations, delivery slots, save selection, holidays
- Admin CRUD: Pickup Locations, Delivery Slots, Holidays
- Automatic slot generation and cleanup cron jobs
- MSI-compatible architecture
- System configuration: general, pickup, delivery, slot generation, advanced
- Event observers: quote submit, availability validation, slot generation
- Plugins: shipping information save, order attribute copy

[1.1.0]: https://github.com/marrso/magento-delivery-scheduler/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/marrso/magento-delivery-scheduler/releases/tag/v1.0.0
