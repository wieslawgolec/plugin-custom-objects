# CustomObjectsBundle

**Custom Objects (Custom Tables) Engine for Mautic 7.2+**

Dynamically creates database tables linked to Contacts via Doctrine SchemaManager, with a full admin UI, Item CRUD, Campaign decisions, Segment filters, CSV/JSON import-export, permissions, and audit logging — comparable in scope to HubSpot Custom Objects.

**Current version: 1.1.0**

---

## Features

| Area | What you get |
|------|----------------|
| **Dynamic schema** | Create/drop custom object tables at runtime (no static Doctrine entities) |
| **Contact linkage** | Every row has `contact_id` → Mautic `leads.id` with `ON DELETE CASCADE` |
| **Typed fields** | `string`, `integer`, `text`, `boolean`, `datetime`, `float`, `bigint` |
| **Admin UI** | Twig list + form; JS field-row adder (`Assets/js/field-builder.js`) |
| **Item CRUD** | List / create / edit / delete items, optionally filtered by contact |
| **Campaign Decision** | “Check Custom Object Property” path evaluation |
| **Segment filters** | Filters under group `custom_object_{name}` (e.g. `vehicles.car_model`) |
| **Import / Export** | CSV for items; JSON for object definitions |
| **Permissions** | `customobjects:objects:*`, `customobjects:items:*`, import, export |
| **Audit log** | Table `custom_object_audit` for create/update/delete/import/export |
| **CI** | PHPUnit 11.5 on PHP 8.2 / 8.5 / 8.6 |

---

## Requirements

- **Mautic 7.2+**
- **PHP 8.1+** (CI validates 8.2, 8.5, 8.6)
- Doctrine DBAL (shipped with Mautic)

---

## Installation

```text
1. Copy this repository into:

   plugins/CustomObjectsBundle/

2. Mautic admin → Settings → Plugins → Install/Upgrade Plugins

3. Clear cache:
```

```bash
php bin/console cache:clear
```

Optional (Composer path if you vendor plugins):

```bash
composer require wieslawgolec/plugin-custom-objects
```

---

## Architecture

```text
CustomObjectsBundle/
├── Config/config.php                 # Routes, DI, menu
├── Controller/
│   ├── SchemaController.php          # Low-level schema API
│   ├── ObjectController.php           # Object definitions API
│   ├── ItemController.php             # Item CRUD API
│   └── ImportExportController.php
├── EventSubscriber/
│   ├── CampaignSubscriber.php
│   └── SegmentFilterSubscriber.php
├── Service/
│   ├── DynamicSchemaManager.php       # create/drop/list tables
│   ├── CustomObjectRegistry.php       # metadata registry table
│   ├── CustomItemRepository.php       # row CRUD + segment lookups
│   ├── ImportExportService.php
│   └── AuditLogger.php
├── Security/CustomObjectsPermissions.php
├── Views/Object|Item|Import/          # Twig admin templates
├── Assets/js/field-builder.js
├── Translations/en_US/messages.ini
└── Tests/Unit/...
```

**Physical tables**

| Table | Purpose |
|-------|---------|
| `{prefix}custom_object_registry` | Object definitions (name, labels, fields JSON) |
| `{prefix}custom_obj_{name}` | One data table per object (`id`, `contact_id`, timestamps, custom columns) |
| `{prefix}custom_object_audit` | Audit trail |

---

## HTTP API

All paths are under the Mautic secured prefix (typically `/s/...`).

### Object definitions

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/customobjects/objects` | List registered objects |
| `POST` | `/customobjects/objects/new` | Register object + create table |
| `GET` | `/customobjects/objects/{objectName}` | View definition |
| `DELETE` / `POST` | `/customobjects/objects/{objectName}/delete` | Unregister + drop table |

**Create object body**

```json
{
  "name": "vehicles",
  "singular": "Vehicle",
  "plural": "Vehicles",
  "fields": [
    { "name": "car_model", "type": "string", "label": "Car Model" },
    { "name": "year", "type": "integer", "label": "Year" }
  ]
}
```

### Items

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/customobjects/{objectName}/items?contactId=&limit=&offset=` | List |
| `POST` | `/customobjects/{objectName}/items/new` | Create (`contact_id` required) |
| `GET` | `/customobjects/{objectName}/items/{itemId}` | View |
| `POST`/`PUT`/`PATCH` | `/customobjects/{objectName}/items/{itemId}/edit` | Update |
| `DELETE`/`POST` | `/customobjects/{objectName}/items/{itemId}/delete` | Delete |

**Create item body**

```json
{
  "contact_id": 42,
  "car_model": "Model 3",
  "year": 2024
}
```

### Import / export

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/customobjects/{objectName}/export/csv` | Download items CSV |
| `POST` | `/customobjects/{objectName}/import/csv` | Upload CSV (body or `file`) |
| `GET` | `/customobjects/{objectName}/export/definition` | Definition JSON |
| `POST` | `/customobjects/import/definition` | Create object from JSON |

**CSV format:** header must include `contact_id` plus field names, e.g.

```csv
contact_id,car_model,year
10,Model 3,2024
20,Model Y,2025
```

### Legacy schema endpoints

Still available: `/customobjects/schema/create`, `/list`, `/drop/{objectName}`.

---

## Campaign decision

When CampaignBundle is present, decision `customobjects.check_property` is registered.

Evaluation helper (also unit-tested):

```php
$subscriber->evaluateCustomObjectProperty(
    contactId: $lead->getId(),
    objectName: 'vehicles',
    fieldName: 'car_model',
    expectedValue: 'Model 3'
);
```

---

## Segment filters

`SegmentFilterSubscriber` adds choices per registered field.

- Group key: `custom_object_{name}`
- Filter key: `{name}.{field}` (e.g. `vehicles.year`)
- Operators: `eq`, `neq`, `gt`, `gte`, `lt`, `lte`, `like`, `notlike`, `empty`, `notempty`

---

## Permissions

| Permission | Meaning |
|------------|---------|
| `customobjects:objects:view` | List / view definitions |
| `customobjects:objects:create` | Create definitions |
| `customobjects:objects:edit` | Edit definitions |
| `customobjects:objects:delete` | Delete definitions |
| `customobjects:items:view` | List / view items |
| `customobjects:items:create` | Create items |
| `customobjects:items:edit` | Edit items |
| `customobjects:items:delete` | Delete items |
| `customobjects:import:full` | CSV / definition import |
| `customobjects:export:full` | CSV / definition export |

Default standalone helper grants all (suitable for tests). Wire into Mautic Roles for production.

---

## Testing

### Local (standalone, no full Mautic)

```bash
cd plugins/CustomObjectsBundle   # or repo root

# mautic/core-lib is not on public Packagist
composer remove --no-update mautic/core-lib || true
composer update --prefer-dist --no-interaction --no-scripts
vendor/bin/phpunit --colors=always
```

### CI

GitHub Actions workflow `.github/workflows/tests.yml`:

- Triggers: `push`, `pull_request`, `workflow_dispatch`
- Matrix: **PHP 8.2, 8.5, 8.6**
- PHPUnit **^11.5**
- Version constraints are **quoted** so the shell does not treat `^` or `|` as operators

### What the suite covers

- Schema create / drop / list / sanitize / field validation
- Registry register / unregister / list
- Item CRUD + contact property lookup
- CSV / definition import & export
- Audit logger table + log rows
- Permissions defaults and denials
- Campaign + segment subscribers
- All JSON controllers (success and 4xx paths)

---

## Readiness checklist

| Item | Status |
|------|--------|
| Dynamic table engine + FK to leads | Done |
| Registry + item CRUD APIs | Done |
| Campaign decision hook | Done (soft-dep on CampaignBundle) |
| Segment filter choices | Done (soft-dep on LeadBundle) |
| Import / export CSV + definition JSON | Done |
| Permissions model | Done (integrate with Role UI in full Mautic) |
| Audit log | Done |
| Twig + JS field builder | Done (templates extend `@MauticCore`) |
| Unit tests + CI matrix | Done |
| Full in-browser Role UI wiring | Optional hardening |
| Segment *query* execution in core LeadList query builder | Optional hardening (IDs resolved via repository today) |
| Multi-tenant table prefix edge cases under heavy load | Ops-dependent |

The plugin is **usable for internal testing** for defining objects, storing contact-linked rows, campaign checks, CSV interchange, and audited changes. Segment *choice registration* is complete; full native LeadList SQL integration may need a small adapter on some Mautic minor versions.

---

## Support the project

- **GitHub Sponsors:** [github.com/sponsors/wieslawgolec](https://github.com/sponsors/wieslawgolec)
- **Buy Me a Coffee:** [buymeacoffee.com/wieslawgolec](https://buymeacoffee.com/wieslawgolec)

---

## License

MIT

## Author

Wieslaw Golec

Contributions and issues welcome.
