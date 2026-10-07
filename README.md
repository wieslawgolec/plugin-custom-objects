# CustomObjectsBundle

**Custom Objects (Custom Tables) Engine for Mautic 7.2+**

Unlocks infinite One-to-Many relational database entities inside Mautic — comparable to HubSpot Custom Objects.

**Current version: 1.1.0**

## Features

- **Dynamic schema** — create custom object tables without static Doctrine entities
- **Contact linkage** — `contact_id` FK to `leads` (`ON DELETE CASCADE`)
- **Typed fields** — string, integer, text, boolean, datetime, float, bigint
- **Admin Twig UI** — object list + form with JS field-row adder
- **Item CRUD** — list / create / edit / delete items linked to Contacts
- **Campaign Decision** — Check Custom Object Property
- **Segment filters** — custom-object properties in Segment builder
- **Import / Export** — CSV for items, JSON for definitions
- **Permissions** — granular view/create/edit/delete for objects & items
- **Audit log** — create/update/delete/import/export recorded
- **PHPUnit 11.x** + GitHub Actions (PHP 8.2 / 8.5 / 8.6)

## Installation

```text
1. Copy into plugins/CustomObjectsBundle
2. Settings → Plugins → Install/Upgrade Plugins
3. php bin/console cache:clear
```

## API

| Method | Path | Description |
|--------|------|-------------|
| GET | `/s/customobjects/objects` | List definitions |
| POST | `/s/customobjects/objects/new` | Create object + table |
| GET/DELETE | `/s/customobjects/objects/{name}` | View / delete |
| GET/POST | `/s/customobjects/{name}/items` | List / create items |
| GET/POST/DELETE | `/s/customobjects/{name}/items/{id}…` | View / edit / delete |
| GET/POST | `/s/customobjects/{name}/export/csv` / `import/csv` | CSV |
| GET/POST | `/s/customobjects/{name}/export/definition` / `import/definition` | JSON |

## Testing

```bash
composer install
vendor/bin/phpunit
```

CI matrix: PHP **8.2 / 8.5 / 8.6**, PHPUnit **11.5**.

## Support

- GitHub Sponsors: https://github.com/sponsors/wieslawgolec
- Buy Me a Coffee: https://buymeacoffee.com/wieslawgolec

## License

MIT — Wieslaw Golec
