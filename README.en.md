# Perspective Nova Poshta Catalog

[Українська](README.md) · **English**

Nova Poshta Catalog imports Nova Poshta directories into Magento: cities,
branches, parcel lockers, streets, areas, and package types. The data stays
in your store, where shipping modules and other integrations can use it.

In Magento Admin, you can browse the directories, schedule updates, and map
Nova Poshta areas to Magento regions. The API key and all module settings
are in one section.

![Nova Poshta Catalog settings and directory access in Magento Admin](images/admin-overview.png)

*Stores → Configuration → Perspective Studio → Nova Poshta Catalog.*

## Directories

| Directory | How you can use it |
| --- | --- |
| Areas | Map Magento regions to Nova Poshta areas |
| Cities | Look up settlements and fill in addresses in your integrations |
| Branches and parcel lockers | Check addresses, cities, and operating statuses |
| Warehouse types | Distinguish types of pickup locations |
| Streets | Fill in addresses in integrations with door delivery |
| Package types | Get packaging data for compatible shipping modules |

Delivery selection at checkout, shipping rates, and waybill creation
require a separate shipping module.

## Browsing branches

The warehouse list shows each branch's name, city, address, and operating status.
You can filter records, choose columns, and move between pages.
**View Lists** also opens cities, streets, warehouse types, and package types.

![Nova Poshta branch directory with addresses and operating statuses](images/admin-warehouses.png)

*A directory in the demonstration store. The record count depends on the data imported.*

## Update schedule

Choose a time and frequency under **Schedule** to update directories automatically.
The same section shows when each directory was last synchronized.
Magento cron must be running to carry out the schedule.

![Update schedule settings and last synchronization dates](images/admin-schedule.png)

*Settings for daily updates.*

## Region mapping

In **Directory Map**, choose which Nova Poshta area matches each Magento region.
The module includes initial mappings. You can change them in Admin at Default,
Website, or Store View scope without editing code.

![Mapping Nova Poshta areas to Magento regions in Directory Map](images/admin-directory-map.png)

*A Nova Poshta area and its matching Magento region.*

## Getting started

Once your developer has installed the module:

1. Open **Stores → Configuration → Perspective Studio → Nova Poshta Catalog**.
2. Select **Default Config**, enable the module in **General settings**, and enter
   the API key from your [Nova Poshta account](https://my.novaposhta.ua/settings/index#apikeys).
3. Under **Schedule**, set **Enable Scheduled Synchronization** to **Yes**,
   choose a time and frequency, and click **Save Config**.
4. Wait for synchronization to finish, then open the directories through **View Lists**.
5. Review **Directory Map** once the module has imported the areas.

The first branch and street import may take longer because the module processes
data for many cities. The API key is hidden in the screenshot.

## For developers

### Requirements

You need a Nova Poshta API key.

### Installation

Run from the Magento root:

```bash
composer require perspectiveteam/module-novaposhtacatalog
php bin/magento module:enable Perspective_NovaposhtaCatalog
php bin/magento setup:upgrade
php bin/magento cache:clean config layout block_html
```

<details>
<summary><strong>Integration and synchronization</strong></summary>

### Integration points

| Component | Purpose |
| --- | --- |
| [Api/](Api/) | Area, city, warehouse, and street repositories and data interfaces |
| [Helper/Config.php](Helper/Config.php) | `getDirectoryMap($storeId)` returns a `[regionCode => areaRef]` map for the selected scope |
| [Service/DirectorySyncMapper.php](Service/DirectorySyncMapper.php) | `getRegionIdToAreaRefMap()` maps Magento region IDs to Nova Poshta area references |
| [etc/db_schema.xml](etc/db_schema.xml) | Local directory tables |
| [Test/Unit/](Test/Unit/) | Directory Map configuration and area synchronization safeguard tests |

Get repositories through Magento DI. A plugin on
`Magento\Directory\Model\Region::getName()` returns Ukrainian names
for known UA region codes.

### How synchronization works

- Cron group: `perspective_novaposhta_catalog`; job: `update_novaposhta_catalog`.
  The module imports directories in order: warehouse types, package types, areas,
  cities, warehouses, streets.
- The installation data patch queues an initial cron job. Saving Frequency
  in Admin generates the recurring schedule.
- **7 days**, **15 days**, and **30 days** set a day-of-month step in the cron
  expression, not a fixed elapsed interval between runs.
  Synchronization dates are stored in UTC.
- Area updates preserve local IDs and insert new records. The module rejects
  empty or malformed responses, duplicate references, and lists missing any
  stored reference. The directory and its last successful synchronization date
  stay unchanged. Writes run in a transaction with Magento Lock Manager.
- Areas are never deleted automatically. If an area permanently disappears from
  the API, review and remove its local record before the next import.
  On the first import, the module validates the received rows.
- City, warehouse type, and package type imports also check completeness.
  `DirectorySnapshot` saves them without automatically deleting records.
  The module processes warehouses and streets per city. If one city fails,
  data already saved for earlier cities stays in place.
- Individual Store View updates and grid synchronization buttons run the import
  within the HTTP request. Use cron for large directories.
- A successful overall cron status does not mean every directory was updated.
  Check the data and Magento logs: a timestamp alone does not confirm a complete
  import for every city.
- Configuration access: `Perspective_NovaposhtaCatalog::settings`.
  Lists open at Default Config scope; there is no separate Admin areas grid.
  Hide API keys in logs and materials you share.

</details>

## License

[MIT](LICENSE.md) · Perspective Studio
