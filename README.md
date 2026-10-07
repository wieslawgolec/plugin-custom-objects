# CustomObjectsBundle

**Custom Objects (Custom Tables) Engine for Mautic 7.2+**

Unlocks infinite One-to-Many relational database entities inside Mautic by dynamically creating tables via Doctrine SchemaManager and wiring them into Campaign Decisions — comparable to HubSpot Custom Objects.

**Current version: 1.0.0**

## Features

- **Dynamic schema** — create custom object tables from a UI/API without static Doctrine entities
- **Contact linkage** — every custom row has a `contact_id` FK to Mautic’s `leads` table (`ON DELETE CASCADE`)
- **Typed fields** — string, integer, text, boolean, datetime, float, bigint
- **Campaign Decision node** — “Check Custom Object Property” evaluates values at runtime
- **Idempotent create / safe drop / list** helpers
- **REST-style SchemaController** (create / list / drop) ready for a Twig admin UI
- **PHPUnit 11.x suite** + GitHub Actions matrix (PHP 8.2 / 8.5 / 8.6)

## Architecture

```
CustomObjectsBundle/
├── Config/config.php              # Routes + DI services
├── Controller/SchemaController.php
├── EventSubscriber/CampaignSubscriber.php
├── Service/DynamicSchemaManager.php   # Core engine
├── CustomObjectsBundle.php
├── Tests/Unit/...
├── .github/workflows/tests.yml
└── composer.json
```

### DynamicSchemaManager

Uses `Doctrine\DBAL\Schema\AbstractSchemaManager` to:

1. Build table name: `{MAUTIC_TABLE_PREFIX}custom_obj_{sanitized_name}`
2. Add `id` (PK, autoincrement) + `contact_id` (indexed, optional FK to `leads`)
3. Add `date_added` / `date_modified`
4. Append user-defined columns
5. Execute `createTable` / `dropTable`

### CampaignSubscriber

- Registers decision `customobjects.check_property` on `CampaignEvents::CAMPAIGN_ON_BUILD` (when CampaignBundle is present)
- Pure evaluation helpers `evaluateCustomObjectProperty()` and `countForContact()` usable from listeners or tests

## Requirements

- Mautic **7.2+**
- PHP **8.1+** (CI covers 8.2, 8.5, 8.6)
- Doctrine DBAL (already provided by Mautic)

## Installation

```text
1. Copy the plugin folder into `plugins/` as `CustomObjectsBundle`:

plugins/
   └── CustomObjectsBundle/

2. Log in to Mautic admin → Settings → Plugins
   → Click Install/Upgrade Plugins
   → The bundle should appear → install it

3. Clear cache:
```

```bash
php bin/console cache:clear
```

## Quick usage (API)

### Create a custom object

```bash
curl -X POST /s/customobjects/schema/create \
  -H 'Content-Type: application/json' \
  -d '{
    "objectName": "Vehicles",
    "fields": [
      {"name": "car_model", "type": "string"},
      {"name": "year", "type": "integer"},
      {"name": "notes", "type": "text"}
    ]
  }'
```

Creates table `{prefix}custom_obj_vehicles` with columns `id`, `contact_id`, `date_added`, `date_modified`, `car_model`, `year`, `notes`.

### List tables

```bash
curl /s/customobjects/schema/list
```

### Drop

```bash
curl -X DELETE /s/customobjects/schema/drop/Vehicles
```

## Campaign Decision example

Once a table exists and rows are inserted (e.g. via a future Item CRUD UI or direct SQL), the decision node can evaluate:

```php
$subscriber->evaluateCustomObjectProperty(
    contactId: $lead->getId(),
    objectName: 'vehicles',
    fieldName: 'car_model',
    expectedValue: 'Model 3'
);
```

True → green path, false → red path.

## Testing

```bash
cd plugins/CustomObjectsBundle   # or the repo root when running standalone
composer install
vendor/bin/phpunit
```

CI runs on **PHP 8.2, 8.5, and 8.6** with PHPUnit **11.5**.

Unit tests cover:

- Table name sanitisation & prefix handling
- Idempotent create / drop / list
- Field type validation
- Campaign decision registration & evaluation
- SchemaController JSON API (success + error paths)

## Roadmap / next steps toward HubSpot parity

- Full admin Twig UI (object + field builder with JS row adder)
- Custom Item CRUD (list / form / delete) linked to Contacts
- Segment filters on custom object properties
- Import / export of custom objects
- Permissions & audit log integration

## Support the project

If this plugin saves you time, you can support development:

- **GitHub Sponsors:** [github.com/sponsors/wieslawgolec](https://github.com/sponsors/wieslawgolec)
- **Buy Me a Coffee:** [buymeacoffee.com/wieslawgolec](https://buymeacoffee.com/wieslawgolec)

Use the **Sponsor** button on this repository for the same links.

## License

MIT

## Author

Wieslaw Golec

Feel free to contribute or report issues.
