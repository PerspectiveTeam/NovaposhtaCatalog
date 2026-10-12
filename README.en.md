# Perspective Nova Poshta Catalog

[Українська](README.md) · **English**

Nova Poshta Catalog imports Nova Poshta directories into Magento: cities,
branches, parcel lockers, streets, areas, and package types. The data stays
in your store, where shipping modules and other integrations can use it.

In Magento Admin, you can browse the directories, schedule updates, and map
Nova Poshta areas to Magento regions. The API key and all module settings
are in one section. The module retains historical records and checks API
responses before updating, so a temporary failure does not remove available addresses.

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
The grids include **Active**, **Missing confirmations**, and **Last seen**
columns and a **Deactivate selected** action. Deactivation keeps the record
in the database. If it appears in the API again, synchronization can reactivate it.

![Nova Poshta branch directory with addresses and operating statuses](images/admin-warehouses.png)

*A directory in the demonstration store. The record count depends on the data imported.*

## Update schedule

Choose a time and frequency under **Schedule** to update directories automatically.
The same section shows when each directory was last synchronized.
Magento cron must be running to carry out the schedule.

![Update schedule settings and last synchronization dates](images/admin-schedule.png)

*Settings for daily updates.*

## Protecting directories during updates

When a record disappears from a Nova Poshta response, the module checks again.
By default, a record becomes inactive after two confirmations of its absence,
at least 24 hours apart. Failed or rejected updates do not count.
The local ID and data remain available for historical lookups.

If a response is missing more than 20% of active records, the module rejects
the update for that directory or city before saving any changes.
This threshold helps protect stored data from incomplete API responses.

These settings are available under **Default Config → Synchronization safety**:

| Setting | Default | What it controls |
| --- | --- | --- |
| Missing confirmations | 2 | How many confirmations of absence are needed to deactivate a record |
| Hours between confirmations | 24 | Minimum hours between confirmations |
| Maximum missing active references (%) | 20 | The share of missing active records an update can accept |

![Settings for missing-record confirmations and the directory safety threshold](images/admin-sync-safety.png)

*Default synchronization safety settings.*

Queries for new address selections check whether records and their related
directories are active. Branches must also have Nova Poshta's **Working** status:
any other status makes a branch inactive during import without waiting for
missing-record confirmations. When a record returns in the API, the module
resets its absence counter and reactivates it; branches still require **Working** status.

If the API limits request frequency or a temporary connection error occurs,
the module waits and retries the request. Attempts and total waiting time
are bounded. If retries fail, data for the current directory or city stays
unchanged; synchronization can be retried later.

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
   the API key from your [Nova Poshta account](https://new.novaposhta.ua/dashboard/settings/developers).
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
| [Model/Availability.php](Model/Availability.php) | Filters available records for new selections, including related directories |
| [Model/Update/SyncPolicy.php](Model/Update/SyncPolicy.php) | Checks the missing-record threshold and manages deactivation |
| [Service/HTTP/RateLimitRetry.php](Service/HTTP/RateLimitRetry.php) | Retries requests after rate limits or transport failures |
| [etc/db_schema.xml](etc/db_schema.xml) | Local directory tables |
| [Test/Unit/](Test/Unit/) | Tests for Directory Map, synchronization safeguards, and request retries |

Get repositories through Magento DI. A plugin on
`Magento\Directory\Model\Region::getName()` returns Ukrainian names
for known UA region codes.

### Availability and historical records

City lookups by name and warehouse and street queries by city use
`Availability`. A city requires an active area; streets and warehouses also
require an active city. Warehouses need an active warehouse type and
`Working` status. Integrations using their own collections should explicitly
apply this filter for new selections.

General `getList(SearchCriteria)` methods and ID or Ref lookups retain access
to history and may return inactive records. All six tables include
`is_active`, `last_seen_at`, `missing_successful_syncs`, and `last_missing_at`.
Bulk deactivation in Admin does not permanently exclude records from synchronization:
a later import can reactivate a record present in the API.

### How synchronization works

- Cron group: `perspective_novaposhta_catalog`; job: `update_novaposhta_catalog`.
  The module imports directories in order: warehouse types, package types, areas,
  cities, warehouses, streets.
- The installation data patch queues an initial cron job. Saving Schedule settings
  with **Enable Scheduled Synchronization** enabled generates the recurring schedule
  using the selected time and frequency.
- Saving **Enable Scheduled Synchronization = No** clears the cron expression.
  The job checks this flag before every run, including previously queued jobs,
  jobs queued through **Schedule the update**, and the initial installation job.
  An import already running continues to completion. Enabling and saving again
  restores the schedule using the selected time and frequency.
  Direct manual imports remain available.
- **7 days**, **15 days**, and **30 days** set a day-of-month step in the cron
  expression, not a fixed elapsed interval between runs.
  Synchronization dates are stored in UTC.
- `DirectorySnapshot` validates rows and unique references before writing,
  preserves local IDs, and uses transactions and Magento Lock Manager.
  Empty responses are rejected for entire directories. An empty warehouse
  or street list for an individual city is checked against the missing-record threshold.
- The missing active reference threshold is checked before writing data or
  counters. An accepted response updates `last_seen_at` for present records
  and resets their absence confirmations. Missing records are deactivated
  according to `SyncPolicy`, with their data retained. Failed and rejected
  imports do not change counters; rerunning before the configured interval
  does not add a confirmation.
- Warehouses and streets are processed per city. Before saving a city,
  `PagedDirectory` fetches all pages and checks their completeness and
  consistent `totalCount`. If processing fails for a city, its changes
  are not saved; data already saved for earlier cities stays in place.
- `RateLimitRetry` retries the same request or page on HTTP 429, an API
  `too many requests` message, or a transport failure. The limit is 5 attempts
  in total and 180 seconds of cumulative delays, excluding request execution
  time. It respects `Retry-After`; otherwise, the delay increases between attempts.
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
