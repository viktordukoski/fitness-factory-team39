# Executed verification — 18 October 2026

All checks below ran locally. **Nothing was deployed or published.** PASS means the stated check actually ran and met its assertions; it is not a claim of formal security certification or blanket coverage.

## Environment

- Windows, portable PHP **8.5.11**, MariaDB **11.4.5**, a dedicated loopback database ending in `_test`, and Chrome driven by Playwright.
- The application server listened on `127.0.0.1:8098`; database on loopback only. No system service was installed. Downloaded runtimes and local test credentials stay outside the deliverable repository.
- Deployment targets PHP **8.3.35 Apache** and MariaDB **11.4.13**. Their exact Docker images and Linux behavior were **NOT VERIFIED** locally because Docker was unavailable. PHP code uses 8.3-compatible syntax; the tested 8.5 runtime is explicitly not represented as a Docker build.

## Results

| Check | Result | Evidence / scope |
|---|---|---|
| PHP syntax | **51/51 files PASS** | Every application, template, seed, migration, CLI and test PHP file linted |
| JavaScript syntax / SVG markup | PASS | `node --check` on app JavaScript; original SVG files parsed as XML |
| Domain integration | **20/20 PASS** | `tests/integration.php`, recorded in `evidence/integration-results.json` |
| True concurrent database requests | **3/3 PASS** | `tests/concurrency.php`, recorded in `evidence/concurrency-results.json` |
| HTTP workflows | **12/12 PASS** | `tests/http_workflows.py` against the real PHP/PDO app |
| HTTP edge regressions | **6/6 PASS** | `tests/route_edges.py`, `evidence/route-edges.json`; malformed/impossible dates, oversized query, unknown pass, news pagination and metadata/language alternates |
| Browser interactions / layout | **10/10 PASS** | `tests/browser.mjs`, `evidence/browser-results.json`; screenshots in `screenshots/` |
| Six-language layout sweep | **90/90 renders PASS** | `tests/locale_browser.mjs`, `evidence/locale-browser.json`; five public pages in six languages at 320/768/1440 px |
| Static RepoRun configuration | **15/15 PASS** | `tests/config_check.py`; YAML parsed, prohibited fields absent, resource variables required, app-only ingress and relative persistent mounts |
| Literal translation coverage | PASS | `tests/translations.php` checked 395 statically discoverable keys; no missing key in six locales, each containing 666 dictionary entries |
| Runtime translation inventory | PASS for interface labels | Six-language render audit; remaining fallback records are brand/language names, source-record titles and already localized database titles passed through display helpers, not missing interface translations |
| Independent semantic browser audit | PASS | 17 public routes each had one H1, no duplicate IDs, labeled controls, image alt text and no empty/placeholder links. Mobile More menu, cookie focus restoration and actual month-grid navigation were exercised |
| Images and upload restrictions | PASS | Local images loaded; executable upload rejected; valid raster upload decoded/re-encoded and retrieved as WebP |
| Unavailable database recovery | PASS | `tests/error_states.php`, `evidence/error-states.json`; intentionally unreachable database produced a safe HTTP 503 without SQL details or credentials |
| Fresh schema and seeds | PASS | Migrations executed against an initially empty local MariaDB database |
| Non-destructive subsequent migrations | PASS | Nine table counts unchanged; stored history retained; no duplicate seed installation |

### Domain and concurrency assertions

The twenty groups include exact ten package prices, unspecified pass windows, weekend access intersections, registration hashing/duplicate rejection, failed/successful login, profile/password/logout, role checks, checkout success/failure/pending state, idempotency, immutable historical prices after a plan-price edit, private order ownership, minimal pass verification, six-language seed data, favorites, inquiry validation/storage, newsletter consent/duplicates/unsubscribe, CMS publication/archive, trainer/service metadata relationships, booking/cancellation/rescheduling, full/past/cancelled rejection, CSRF, upload rejection, capacity reduction rejection, editable-hour validation and inactive-account/pass denial.

The concurrency harness starts independent PHP worker processes with independent PDO connections and a barrier. Two users competing for a one-place session resulted in **one confirmed booking and one `class_full` rejection**. Two simultaneous requests with one checkout key returned **the same order ID**, with exactly **one order, one membership and one pass**. A migration rerun preserved exact counts in nine content/history tables. These are executed races, not sequential simulations.

### HTTP and browser scope

The HTTP suite exercised registration → dashboard → logout/login; member/admin separation; checkout → order → pass; repeated checkout; booking review → confirmation → duplicate rejection → cancellation; favorites; inquiry validation/storage/admin visibility; newsletter lifecycle; CSRF/security headers/noindex; six-language routes and translated content; all administration pages/editors; public health and 404 routes.

Browser layout checks covered **320, 375, 390, 430, 768, 1024, 1280, 1440 and 1920 pixels** on home, memberships, classes, contact and login. Account pages were additionally checked at 320/390/768/1440, and admin dashboard/content/sessions/plans/users/inquiries at those widths. No page-level horizontal overflow was found. Wide tables/month grids intentionally scroll within their containers, with a class list alternative.

A separate sweep rendered home, memberships, classes, contact and news in all six languages at 320/768/1440 pixels. All **90/90 renders** passed after fixing German heading wrapping and flex sizing; overflow was not hidden to mask the problem.

Real-browser interactions checked cookie rejection, no optional language cookie before consent, preferences dialog and Escape, persisted consented language cookie, mobile keyboard navigation, list/calendar switching, gallery lightbox/arrows/Escape, login, member screens, administrator access, and accepted/rejected image uploads. No JavaScript runtime exception occurred. Desktop and mobile hero screenshots were visually inspected.

## Issues found and resolved during testing

- Reconciled form field names, booking/checkout consent, route IDs, newsletter token management and authenticated newsletter preferences.
- Fixed language cookie name mismatch and imported the marketing/supplement dictionaries. Preserved raw fallback sentences and added locale-aware date/number display.
- Corrected admin status transitions, session cancellation controls, settings/source/room editors, readonly email display and password length hints.
- Fixed trainer/availability filters and database-backed pagination; the news listing has a visible second page with seeded articles.
- Added visible session change notes, eligibility feedback, cancellation cutoffs and source notes.
- Added unique detail titles, descriptions, canonical URLs and language alternates.
- Fixed malformed-date handling and past/completed status display.
- Fixed German compound headings at the narrowest mobile width and reran the complete six-language layout sweep.
- Corrected test-only issues: Windows UTF-8 output, lazy-image loading in full-page screenshots, a selector targeting a hidden cookie control, and an invalid tiny PNG fixture. These were not silently counted as application failures or passing checks.

## Explicitly NOT VERIFIED

- Docker image build/start, exact production PHP/MariaDB pair, Apache rules under that image, Linux bind-mount ownership, and memory/CPU behavior under actual quota.
- RepoRun account/repository/branch connection, team CPU/RAM/disk quota, course-required endpoint policy, platform **Validate**, deployment, live ingress, wake-on-request behavior, and database/uploads surviving a real redeployment.
- Actual backup/restore on the target platform. Procedures are documented, not executed against a live stack.
- Current business prices/hours, real services/staff, exact entrance, domain ownership, photo/logo authorization, legal identity or course deadline details beyond supplied evidence.
- Independent native-speaker review of all six translations, formal screen-reader audit, exhaustive WCAG certification, penetration testing, performance/load testing, and full cross-browser/device testing.
- No email delivery or real payment test is possible: those integrations intentionally do not exist.

## Reproduce responsibly

Use a fresh isolated database whose name ends in `_test`, run `php bin/migrate.php`, then the documented suites. Tests create fictional records. The main integration and HTTP suites retain their fixtures in that isolated database; concurrency-specific fixtures are cleaned by their harness. Never run this suite against actual member data. Rate limiting is intentionally active, so repeated HTTP runs within the same window may be throttled; wait for the window or use a fresh isolated test database rather than weakening deployed protections.

Browser tests require a development installation of Playwright and an available Chrome/Chromium executable (`PLAYWRIGHT_MODULE_PATH`, `CHROME_PATH`, `TEST_BASE_URL`, `TEST_OUTPUT_DIR` can be set). Python configuration checks use PyYAML. These are verification tools only; none is required to run the website. The production image excludes tests and documentation.
