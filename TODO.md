# TODO: GitHub Readiness

This repo is in decent shape already: it has a clear Laravel structure, focused services, useful feature tests, and a README that explains the main workflows. The remaining work is mostly about making it less site-specific, easier for strangers to install, and safer to expose publicly.

## Must Do Before Publishing

- [x] Remove hardcoded SPD branding from public defaults.
  - Added generic, environment-controlled names, titles, footer text, and theme colour.
  - The web app manifest is generated from configuration, and an optional favicon URL supplies browser, manifest, and wallboard artwork.
  - Removed the favicon generator and committed brand assets; the wallboard falls back to its glowing status dot.

- [ ] Move hardcoded external CSS imports into environment-controlled custom CSS.
  - `resources/scss/partials/_vendor.scss` imports:
    - `https://spd.ltd/global_assets/css/fortress.css`
    - `https://spd.ltd/global_assets/css/spd.css`
  - This should not ship as the default GitHub experience.
  - Recommended fix: remove the SCSS imports and add a config value such as `STATUSPAGE_CUSTOM_CSS_URLS`, parsed as a comma-separated list of `http`/`https` URLs and rendered as optional `<link rel="stylesheet">` tags.
  - Keep the built-in SCSS looking reasonable without custom CSS.

- [ ] Delete or replace the default Laravel welcome view.
  - `resources/views/welcome.blade.php` is still tracked and contains Laravel promotional links.
  - It does not appear to be routed currently, but it reads unfinished in a public project.

- [ ] Add a license file.
  - `composer.json` says `MIT`, but there is no `LICENSE` file in the repo root.

- [ ] Add a GitHub-ready project hygiene set.
  - Add `CONTRIBUTING.md` or a short contributor section in `README.md`.
  - Add `SECURITY.md` with private vulnerability reporting instructions.
  - Add `.github/workflows/ci.yml` to run PHP tests, Pint, and frontend build.
  - Consider issue and PR templates if this is intended for outside contributors.

- [ ] Make install instructions copy-paste complete for a fresh clone.
  - The README is useful, but it should include a shortest-path setup from zero:
    - `composer install`
    - `npm install`
    - `cp .env.example .env`
    - `php artisan key:generate`
    - `touch database/database.sqlite`
    - `php artisan migrate`
    - `npm run build`
    - `php artisan statuspage:poll --force`
  - Mention that the first status-page render will fail until Zabbix API values are configured.

## Security And Privacy Scan

- [ ] Run dependency audits before release.
  - Composer: `composer audit`
  - npm: `npm audit`
  - Also run `composer outdated --direct` and `npm outdated` to spot stale major versions.

- [ ] Do a secret scan before pushing.
  - Suggested tools: `gitleaks detect` or `trufflehog filesystem .`
  - Current repo scan did not show obvious committed secrets, and `.env` is ignored.
  - Be careful with `database/database.sqlite`; it is present locally and should stay untracked.

- [ ] Review public exposure of diagnostics.
  - The footer debug block can show request IPs, real IP headers, shown sections, hidden sections, and poll duration when `$statusDebug` is passed.
  - Right now `routes/web.php` passes `statusDebug` to the public page and fragment.
  - Recommended fix: gate this behind `APP_DEBUG`, a dedicated `STATUSPAGE_SHOW_DEBUG_FOOTER`, or remove it from public responses.

- [ ] Tighten push subscription validation.
  - `routes/web.php` accepts arbitrary string endpoints for push subscriptions.
  - Recommended fix: validate endpoint as a URL, restrict to known web push endpoint schemes/hosts as much as practical, and set reasonable max lengths for endpoint and key fields.

- [ ] Consider encrypting browser push subscription secrets at rest.
  - `push_subscriptions.auth_token` and public key material are stored directly.
  - This is common for web push, but encryption via Laravel casts would reduce blast radius if the database leaks.

- [ ] Review trusted proxy defaults and docs carefully.
  - `TRUSTED_PROXIES=REMOTE_ADDR` is convenient but dangerous if the app is directly reachable.
  - The README warns about this already; keep that warning prominent.

- [ ] Validate externally configured script and stylesheet URLs.
  - Plausible script URL is configurable via `PLAUSIBLE_SCRIPT_URL`.
  - Any future custom CSS URL setting should only allow `http` and `https`.

## Refactor Candidates

- [ ] Split `StatusPageBuilder` after release-critical cleanup.
  - This class is doing discovery, Zabbix fetching, macro parsing, metric formatting, latency chart shaping, and service assembly.
  - It is not lazy-dev spaghetti, but it has grown into a "god service".
  - Suggested extractions:
    - `StatusPageHostDiscovery`
    - `StatusPageMetricFormatter`
    - `StatusPageLatencyFormatter`
    - `StatusPageMacroParser`
  - Keep the public output array stable while extracting.

- [ ] Remove repeated Zabbix item output definitions.
  - `fetchItems`, `fetchAvailableItems`, `fetchPublicMetricItems`, and `fetchLatencyItems` repeat similar `output` lists.
  - Recommended fix: define small private constants or helper methods for common item fields.

- [ ] Move route closures into controllers.
  - `routes/web.php` is readable, but it now contains page assembly plus push subscription persistence.
  - Suggested controllers:
    - `StatusPageController`
    - `StatusFragmentController`
    - `PushSubscriptionController`
  - This will make request validation and tests easier to navigate.

- [ ] Move Artisan closure commands into command classes.
  - `routes/console.php` contains several substantial commands.
  - Command classes would make `webpush:*` and `statuspage:poll` easier to test and maintain.

- [ ] Tidy `resources/js/app.js` into small modules once the public API is stable.
  - The file is understandable, but it combines details animation, refresh polling, relative time, and push notifications.
  - Suggested modules:
    - `details.js`
    - `refresh.js`
    - `push.js`
    - `relative-time.js`

- [ ] Replace text glyph controls with inline SVG/icon markup.
  - The refresh toggle uses `||` and `>`.
  - It works, but it looks a little improvised. Use proper pause/play icons or small SVGs.

## CSS And Frontend Tidy-Up

- [ ] Keep the SCSS partial structure, but remove `_vendor.scss` once custom CSS URLs exist.
  - Current partials are reasonably scoped by UI area.
  - Combining them would probably make the CSS less navigable, not cleaner.

- [ ] Refactor the partials to use Sass features where they improve locality and reduce repetition.
  - Nest component elements and states under their owning block, for example `.badge`, `.latency-chart`, `.service-details`, and `.wallboard`.
  - Use `&` for state classes, attributes, pseudo-elements, and `:hover`/`[open]` variants.
  - Keep nesting shallow (roughly three levels); do not reproduce the full DOM tree in SCSS.
  - Preserve the compiled selectors and visual output in the first pass so this remains a structural refactor.

- [ ] Split the 598-line `_wallboard.scss` partial by responsibility.
  - Suggested partials: `_wallboard-layout.scss`, `_wallboard-summary.scss`, `_wallboard-alerts.scss`, and `_wallboard-cards.scss`.
  - Put shared wallboard state selectors such as the normal, stale, and alerting modes behind short parent selectors or Sass variables so the long `:has(...)` expressions are not repeated throughout the file.
  - Keep wallboard overrides close to the component they override where that is easier to reason about; the goal is smaller coherent units, not fragmentation for its own sake.

- [ ] Introduce a small SCSS token layer for repeated design values.
  - Keep runtime/theme colours as CSS custom properties, but add the missing severity colours and borders so `#d92d20`, `#f04438`, `#f97066`, and the status border colours are not repeated across partials.
  - Define shared font stacks, radii, spacing, and transition durations only where repetition justifies them.
  - Consider Sass maps/mixins for the repeated `not-classified`/`information`, `warning`/`average`, and `high`/`disaster` state pairs, while keeping the emitted CSS easy to inspect.

- [ ] Remove dead, duplicate, and accidental declarations during the SCSS refactor.
  - Delete the commented-out `.service-details[open]` rule in `_cards.scss`.
  - Fix duplicate declarations in `_summary.scss` (`color` and stale-warning `margin`) and the stray `margin: -0` in `_wallboard.scss`.
  - Add the missing semicolon to `.last-update`'s `font-family` declaration.
  - Review whether `.metric.warning strong` intentionally uses `var(--danger)` rather than the warning colour.
  - Replace `0rem`/`0px` with `0` where units are unnecessary and normalize property ordering/quote style.

- [ ] Review `!important` use in base layout.
  - `resources/scss/partials/_base.scss` uses `!important` on body/main sizing, likely to override the imported SPD CSS.
  - `_summary.scss` and `_wallboard.scss` contain additional overrides that should be re-tested after vendor imports are removed.
  - Once vendor imports are gone, remove unnecessary `!important`s and solve remaining specificity conflicts through component scope.

- [ ] Add automated stylesheet quality checks.
  - Add Stylelint with SCSS-aware rules and a `npm run lint:css` script.
  - Keep rules focused on actual defects: invalid declarations, duplicate properties, selector mistakes, and excessive specificity; avoid a large formatting-only migration.
  - Run Sass compilation and the CSS lint command in CI.

- [ ] Remove unused frontend tooling after deleting the Laravel welcome view.
  - The status-page assets do not use Tailwind, but `@tailwindcss/vite` and `tailwindcss` are still configured in `vite.config.js` and `package.json`.
  - Remove those dependencies and the Vite plugin if no remaining tracked view or stylesheet needs them.

- [ ] Add a light visual QA pass after removing vendor CSS.
  - Check desktop and mobile layouts.
  - Confirm cards, summaries, stale warning, expanded service details, latency charts, and push/refresh controls still look intentional.
  - Check the wallboard at its documented 764 by 459 pixel target, in all-normal, alerting, and stale states.
  - Check keyboard focus, reduced-motion mode, long service names, and long metric values.

- [x] Make brand assets generic by default.
  - Brand artwork is optional and externally configurable; no site-specific image ships by default.

## Tests To Add

- [ ] Test custom CSS URL rendering once implemented.
  - Assert no custom stylesheet renders by default.
  - Assert configured `http`/`https` stylesheets render.
  - Assert invalid schemes such as `javascript:` are ignored.

- [x] Test configurable branding once implemented.
  - Page title, header title, footer text, manifest names, and theme color should be covered.

- [ ] Test debug footer gating.
  - Public production responses should not expose IP/debug metadata unless explicitly enabled.

- [ ] Add validation tests for push subscription endpoints and key lengths.

- [ ] Add a smoke test for `/status-fragment` not rendering full HTML layout.

## Documentation Polish

- [ ] Add screenshots or a short GIF once generic branding is in place.

- [ ] Document Zabbix permissions more explicitly.
  - State the minimum read permissions needed for hosts, triggers, items, macros, and history.

- [ ] Document production deployment assumptions.
  - Scheduler must run.
  - Queue worker must run if web push is enabled.
  - Cache/database migrations must be run.
  - `APP_DEBUG=false`.
  - `APP_URL` must be the public HTTPS URL.

- [ ] Document the private section model with examples.
  - Include safe examples for direct deployment, Cloudflare deployment, and private network/Tailscale deployment.

- [ ] Update README after moving custom CSS to env.
  - Replace the current "stylesheet imports local standard layout CSS" section with `STATUSPAGE_CUSTOM_CSS_URLS`.

## Nice To Have

- [ ] Add a demo mode or fixture-backed screenshot command.
  - Useful for GitHub visitors who do not have Zabbix running.

- [ ] Add Docker or compose files for easier local evaluation.

- [ ] Add Dependabot config for Composer, npm, and GitHub Actions.

- [ ] Consider a small `/health` endpoint for uptime checks.

- [ ] Consider making section definitions configurable from env or a published config example.

## Current Assessment

The code does not read like lazy autocomplete. The services and tests show deliberate work. The main things that make it feel not-yet-public are site-specific branding, hardcoded external CSS, public debug metadata, default Laravel leftovers, and missing GitHub release hygiene. Refactoring can happen, but it should come after the branding/security/docs cleanup unless you plan to maintain this as a larger public package.
