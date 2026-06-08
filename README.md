# zabbix-public-statuspage

A Laravel status page backed by the Zabbix API.

The app discovers hosts from Zabbix tags, renders their active trigger state, exposes selected item values, and caches the generated page data so normal page loads do not hit Zabbix directly.

## Requirements

- PHP 8.3+
- Composer
- Node.js and npm
- A Zabbix API token with read access to hosts, triggers, items, macros, and history

## Setup

Install dependencies:

```bash
composer install
npm install
```

Create the local environment file:

```bash
cp .env.example .env
php artisan key:generate
```

Set the Zabbix API values:

```env
ZABBIX_API_URL=https://zabbix.example.com/api_jsonrpc.php
ZABBIX_API_TOKEN=your-token
```

If you use the default SQLite/database cache setup, create the database and run migrations:

```bash
touch database/database.sqlite
php artisan migrate
```

## Local Development

Run the full development stack:

```bash
composer run dev
```

That starts:

- Laravel dev server
- Laravel scheduler worker
- queue listener
- Laravel Pail logs
- Vite dev server

Open the Laravel app, usually:

```text
http://127.0.0.1:8000
```

Do not browse directly to the Vite URL; Vite only serves frontend assets.

## Build And Test

```bash
php artisan test
npm run build
./vendor/bin/pint
```

## Cached Polling

The web page reads from a cached status snapshot. Zabbix polling is handled by:

```bash
php artisan statuspage:poll
```

Force a refresh:

```bash
php artisan statuspage:poll --force
```

The scheduler runs the poll command every 10 seconds and the cache service decides whether a real Zabbix refresh is due. In local development the scheduled poll logs its summary; in non-local environments it runs quietly.

Relevant environment values:

```env
STATUSPAGE_CACHE_KEY=statuspage.snapshot
STATUSPAGE_POLL_INTERVAL=60
STATUSPAGE_STALE_AFTER=120
```

If cached data is older than `STATUSPAGE_STALE_AFTER`, the page shows a stale-data warning. This lets the status page keep serving the last known state while making it obvious that Zabbix polling may be broken.

## Zabbix Host Discovery

Hosts appear on the status page when they have a `statuspage` host tag matching a configured section:

```text
statuspage=public
statuspage=internal
statuspage=infrastructure
```

The default sections are configured in [config/zabbix.php](config/zabbix.php):

- `public`: external/customer-facing services
- `internal`: internal application services
- `infrastructure`: supporting infrastructure dependencies

## Private Sections

By default, `internal` and `infrastructure` are treated as private sections. Public visitors only see non-private sections, and the status summary is recalculated from the sections they are allowed to see.

Private sections are controlled with:

```env
STATUSPAGE_PRIVATE_SECTIONS=internal,infrastructure
STATUSPAGE_PRIVATE_IPS=203.0.113.10,198.51.100.0/24
```

`STATUSPAGE_PRIVATE_IPS` accepts exact IPs and CIDR ranges. For Tailscale clients, allow the Tailscale CGNAT range:

```env
STATUSPAGE_PRIVATE_IPS=100.64.0.0/10
```

If the app is behind Cloudflare, nginx, a load balancer, or another proxy, make sure Laravel sees the real client IP. Otherwise the allowlist may check the proxy IP instead of the visitor's IP.

For proxy deployments, configure trusted proxies:

```env
TRUSTED_PROXIES=REMOTE_ADDR
```

`REMOTE_ADDR` tells Laravel to trust the proxy currently connecting to the app and use forwarded client IP headers such as `X-Forwarded-For`. This is suitable when the app is only reachable through that proxy. If the app is directly reachable from the internet, use explicit proxy IPs or CIDR ranges instead.

## Service Health

The app fetches active triggers for each discovered host. A card's status is the highest-priority active trigger:

- OK
- Not classified
- Information
- Warning
- Average
- High
- Disaster

Active trigger descriptions and priorities are shown inside each expanded service card.

## Display Names

By default, cards use the Zabbix host name. You can override the public display name with a host macro:

```text
{$PUBLIC_DN}=Friendly service name
```

You can also add a service link to the card header with:

```text
{$PUBLIC_URL}=https://service.example.com
```

If `{$PUBLIC_URL_OVERRIDE}` is set, it is used for the card header link instead of `{$PUBLIC_URL}`:

```text
{$PUBLIC_URL_OVERRIDE}=https://alternate.example.com
```

Only valid `http` and `https` URLs are shown.

## Response Time

Response time is shown for sections listed in `latency_sections`, currently:

```php
['public', 'internal']
```

The preferred item key is:

```env
ZABBIX_LATENCY_ITEM_KEY=statuspage.web.latency
```

If that item is not present, the app falls back to recent numeric web-scenario history.

Response-time chart thresholds are controlled per host with macros:

```text
{$PUBLIC_HTTP_RESPONSE_WARN}=200
{$PUBLIC_HTTP_RESPONSE_HIGH}=1000
```

The graph shades:

- below warning: clear
- warning to high: orange
- above high: red

## API Health

The app can show an API health item using:

```env
ZABBIX_API_HEALTH_ITEM_KEY=api.health.status
ZABBIX_API_HEALTH_SUCCESS_VALUE=1
```

If the item has a Zabbix value map, the status page displays the mapped value instead of the raw value.

## Selected Metrics

Use host macros to choose which item values should be shown on the public card:

```text
{$PUBLIC_METRICS}=item.key.one,item.key.two,service.info["MSSQLSERVER",state]
{$PUBLIC_METRIC_MAP}=Label one,Label two,Service status
```

Notes:

- The macro name is historical; it applies to `public`, `internal`, and `infrastructure` hosts.
- Keys are matched exactly against Zabbix item keys.
- Commas inside item key brackets are supported.
- Floating-point values are shown with two decimals.
- Byte values with unit `B` are scaled to KB, MB, GB, TB, and so on.
- Zabbix value maps are respected when available.

## Available Items

Each card includes a disclosure section listing every available item and key for that host. This is intended to make it easier to choose values for `{$PUBLIC_METRICS}`.

## Frontend

The frontend is plain Blade, CSS, and a small JavaScript file:

- [resources/views/status](resources/views/status)
- [resources/css/app.css](resources/css/app.css)
- [resources/js/app.js](resources/js/app.js)

CSS and JS are built with Vite:

```bash
npm run dev
npm run build
```

The stylesheet imports local standard layout CSS from:

```css
@import url("https://spd.ltd/global_assets/css/fortress.css");
@import url("https://spd.ltd/global_assets/css/spd.css");
```

Remove or replace these imports if you are deploying this outside that environment.

## Analytics

Plausible analytics can be enabled with:

```env
PLAUSIBLE_DOMAIN=status.example.com
PLAUSIBLE_SCRIPT_URL=https://plausible.io/js/script.js
```

The script is only rendered when `PLAUSIBLE_DOMAIN` is set. If you self-host Plausible, set `PLAUSIBLE_SCRIPT_URL` to your own script URL.

## Deployment Notes

In production, run a scheduler process so cached data keeps refreshing:

```bash
php artisan schedule:work
```

or use cron:

```cron
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

Build frontend assets before deploying:

```bash
npm run build
```

Never commit `.env`; use `.env.example` for documentation only.
