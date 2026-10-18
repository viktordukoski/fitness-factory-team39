# Executed verification - 18 October 2026

## RepoRun setup and validation — 18 October 2026

The user authorized setup and validation while keeping deployment paused. GitHub's read-only deploy key is installed, repository fetch works, and the four private environment values were entered by the user. YAML parsing, resource arithmetic and whitespace checks passed for the revised Compose files. The workspace UI reports 500m CPU, 1000 MB memory and 2000 MB disk; hosted limits now total 0.5 CPU / 896 MiB (939,524,096 bytes).

Actual platform runs identified and resolved interpolated resource-field errors and then a memory-quota error. The latest [validation operation](https://reporun.finki.net.mk/operations/op_01M588JK6P1EEXX4X35N3EH7PH) on application commit `e56b2f63b5094eaa52ac2da0bbb246e9a55630cb` reports **only missing `APP_URL`**. Overall validation remains **blocked**, not passed. The exact endpoint base domain is not displayed before deployment and has not been confirmed. No Deploy, Redeploy, Start or Wake action was performed; Linux image build, live health and persistence checks remain unexecuted.

## Café gallery replacement — 18 October 2026

The selected fourth gallery image now uses the user's unchanged café photograph. **9/9 targeted browser views PASS:** 320/714/1440 px, all six locales, album filtering, image loading, matching lightbox source/alt, full-image containment and unchanged other gallery photos. No horizontal overflow or browser exceptions were found. Both changed PHP templates passed syntax checks. See [browser evidence](evidence/cafe-gallery.json), [original-byte hash](evidence/cafe-asset.json) and the visually inspected [gallery card](screenshots/cafe-gallery.png). No database migration or deployment is involved.

## Browser-comment update — 18 October 2026

Implemented all nine browser comments: six supplied photos and the trainer names Boban, Emilija and Martin. Photos and names are consistent across the corresponding cards, profiles, article pages and schedule references. The existing layout is retained; full promotional posters use contain, portrait crops align to the top, and redundant overlay arrows are hidden on posters to keep text visible.

- **36 page renders PASS**, with no broken images, horizontal overflow or JavaScript exceptions: home, services, trainers, both News listing pages, relevant detail/article pages and classes at desktop, tablet and mobile widths, plus home in all six locales. [Browser evidence](evidence/comment-browser.json). The first check assumed all news appeared on one page; correcting it to inspect the existing pagination resolved that check-only failure. A follow-up viewport inspection confirmed the entire pricing poster fits the image frame at 714 px.
- **8/8 trainer migration checks PASS:** fresh seed definitions, all 18 locale rows, execution and repeat execution, independent preservation of custom titles/alts, stable IDs/relationships/bookings and restoration of isolated test values. [Migration evidence](evidence/comment-trainer-migration.json). Migration 003 is applied to the local preview; fresh installations use the updated seed names.
- **All six new files preserve supplied bytes:** [asset evidence](evidence/comment-image-assets.json). Emilija uses the exact screenshot path supplied in the clarification.
- **Final poster framing PASS at 320/714/1440 px:** both promotional images remain inside their frames, use contain, and have no overlapping arrow. [Framing evidence](evidence/comment-poster-framing.json).
- **56 PHP files passed syntax checks.** The final shared-template adjustment was checked again.

Representative [desktop portraits](screenshots/comment-trainers-1440.png), [desktop article cards](screenshots/comment-news-1440.png), [Pilates card](screenshots/comment-pilates.png), and the [tablet poster viewport](screenshots/comment-poster-frame-714.png) were inspected. Full-height element captures at narrow widths can include browser screenshot artifacts; the actual viewport geometry and screenshot confirm that the pricing poster is not clipped. This update does not deploy the application; previous broader verification below retains its original scope.


## Supplied-image update — 18 October 2026

The logo and four supplied gym images replace their matching placeholders. The overall layout is retained; About preserves the entire supplied composition. New alt text is localized in all six languages. No upload or deployment occurred.

- **43 public page renders PASS:** home, About, services, facilities and gallery at 320/768/1440 px; additional six-language mobile coverage, service/class detail and login views. Images loaded, logo aspect ratio stayed square, the About image remained uncropped, and no horizontal overflow or browser exceptions were recorded. Three gallery enlargements matched their thumbnail sources and descriptions. [Browser evidence](evidence/image-update.json).
- **8/8 existing content-display checks PASS:** the fixture uses the new cardio photo, custom CMS alt text remains escaped and visible, and the empty-image fallback uses the strength photo. Executed against the isolated recheck database after the image-helper adjustment. [Content evidence](evidence/image-content-display.json).
- **Five copied assets match the supplied bytes:** [asset hashes](evidence/image-assets.json). The locally supplied `image1.png` and attached `image2.png` are identical.
- **56 PHP files and app JavaScript passed syntax checks.** The two subsequently adjusted PHP files were checked again.

Final [desktop home](screenshots/image-home-desktop.png), [mobile home](screenshots/image-home-mobile.png), [desktop About](screenshots/image-about-desktop.png) and [mobile About](screenshots/image-about-mobile.png) layouts were visually inspected. The screenshots have the cookie banner dismissed. These checks cover the image update; previous broader reports below retain their original scope. The image update adds no database migration or deployment changes.

## Public-copy revision — 18 October 2026

These checks ran **after** the user's wording revision. The public pages now use direct gym information across all six languages; the third About paragraph, global banner and content/source badges were removed. Operational no-charge, reception-confirmation and email-delivery wording remains factual. No upload or deployment occurred.

| Check | Result | Evidence |
|---|---|---|
| Existing local preview: public text | **378 page views PASS** | [Visible-copy inspection](evidence/copy-public-pages.json): 63 routes/detail pages in each of six languages; no requested obsolete wording in the rendered text |
| Safe content migration | **4/4 PASS** | [Migration evidence](evidence/copy-migration.json): exact-old-value updates preserve CMS edits, order snapshots, prices, hours and private provenance; repeat execution preserves revised text |
| CMS content/template behavior | **8/8 PASS** | [Display evidence](evidence/copy-content-display.json): class fields, localization, escaping and normal price labels |
| Account and transaction journeys | **13/13 PASS** | [HTTP evidence](evidence/copy-http.json): existing workflow suite, including saved pass references |
| Browser interactions/layout | **10/10 PASS** | [Browser evidence](evidence/copy-browser.json): keyboard controls, public/member/admin layouts, calendar, gallery and uploads |
| Six-language layout sweep | **90/90 renders PASS** | [Locale evidence](evidence/copy-locale-browser.json) |
| Fresh initialization | **5/5 PASS** | [Fresh-install evidence](evidence/copy-fresh-install.json): migration on empty tables, private initial credential and repeat-safe initialization |
| PHP syntax and translations | **56 files PASS; no missing literal keys** | [Translation evidence](evidence/copy-translations.json): 381 discovered literal keys, 672 entries per locale |

Current [desktop](screenshots/copy-home-desktop.png) and [mobile](screenshots/copy-home-mobile.png) screenshots were visually inspected. The migration initially exposed a MariaDB collation mismatch; explicit binary comparisons corrected it, and the recorded migration and fresh-install checks passed afterward. Internal keys and private provenance remain for compatibility and traceability. Prices and opening hours did not change. Stored pass identifiers remain intact; member pages use a stable FF reference based on the pass record ID.

This is local verification. Docker, RepoRun Validate, hosted operation, resource behavior and live persistence remain unverified. Earlier reports below retain their original scope and describe the version before this copy revision.

## Earlier pre-upload recheck

The final pre-upload recheck ran locally against a fresh isolated database. **No source upload or deployment was performed during this recheck.** The user has paused publication and will upload the reviewed files themselves. PASS means the stated check actually ran and met its assertions; it does not establish blanket coverage, formal certification or hosted operation.

## Environment and evidence dates

- Windows, portable PHP **8.5.11**, MariaDB **11.4.5**, and Chrome driven by Playwright. The recheck application listened on `127.0.0.1:8099`; the original implementation checks used port `8098`. Databases and web previews were bound to loopback, and test database names ended in `_test`.
- No system service was installed. Downloaded runtimes and local credentials stay outside the deliverable repository. Test fixtures are fictional and separate from any future hosted database.
- Prepared deployment images are **PHP 8.3.35 Apache** and **MariaDB 11.4.13**. Their exact combination, Linux behavior and Docker execution remain **NOT VERIFIED**. The tested Windows PHP 8.5 runtime is not represented as a Docker build.
- Rows linked to `evidence/recheck-*.json` below are final recheck evidence. Current desktop/mobile hero screenshots were also visually inspected. The separate 17-route semantic/navigation audit is historical implementation evidence and was not repeated as that same independent audit during this recheck.

## Final recheck results

| Check | Result | Evidence / scope |
|---|---|---|
| PHP syntax | **56/56 files PASS** | Application, templates, seeds, migration, CLI and test PHP files linted |
| JavaScript syntax | PASS | App JavaScript passed syntax checking |
| Domain integration | **20/20 PASS** | [Evidence](evidence/recheck-integration.json); `tests/integration.php` |
| Concurrent bookings/checkout and migration preservation | **3/3 PASS** | [Evidence](evidence/recheck-concurrency.json); `tests/concurrency.php` |
| HTTP workflows | **13/13 PASS** | [Evidence](evidence/recheck-http.json); `tests/http_workflows.py`, including password-space preservation and NUL rejection |
| HTTP edge regressions | **6/6 PASS** | [Evidence](evidence/recheck-route-edges.json); `tests/route_edges.py` |
| Browser interactions/layout | **10/10 PASS** | [Evidence](evidence/recheck-browser.json); `tests/browser.mjs` |
| Six-language layout sweep | **90/90 renders PASS** | [Evidence](evidence/recheck-locale-browser.json); `tests/locale_browser.mjs` |
| Current visual screenshots | Inspected | [Desktop hero](screenshots/recheck-home-desktop.png) and [mobile hero](screenshots/recheck-home-mobile.png) were visually reviewed after the recheck |
| CMS relationship preservation | **6/6 PASS** | [Evidence](evidence/recheck-admin_relations.json); `tests/admin_relations.php` |
| CMS content display and translations | **8/8 PASS** | [Evidence](evidence/recheck-content_display.json); `tests/content_display.php` |
| Administrator deactivation safety | **3/3 PASS** | [Evidence](evidence/recheck-admin_safety.json); includes three simultaneous cross-deactivation races |
| Fresh database/hosted administrator initialization | **5/5 PASS** | [Evidence](evidence/recheck-fresh-install.json); real empty-database initialization, missing-secret rejection and retry/hash preservation |
| Isolated administrator seed boundaries | **7/7 PASS** | [Evidence](evidence/recheck-seed_admin.json); `tests/seed_admin.php` |
| Static RepoRun configuration | **15/15 PASS** | [Evidence](evidence/recheck-config.json); `tests/config_check.py`; not platform Validate or a Docker build |
| Literal translation coverage | PASS | [Evidence](evidence/recheck-translations.json); 392 statically discoverable keys, no missing key across six locales, 669 dictionary entries per locale |
| Runtime translation inventory | PASS for interface labels | [Evidence](evidence/recheck-translation_runtime.json); remaining entries are brand/language names, source titles and already localized DB titles passed through display helpers |
| Unavailable-database recovery | PASS | [Evidence](evidence/recheck-error_states.json); a safe HTTP 503 without SQL details or credentials |

These are separate suites with overlapping coverage. Their assertion counts should not be added into one claimed completeness score. Literal scanning does not discover every conditional translation lookup; the content-display suite separately exercises the new price labels in all six locales.

### Domain, concurrency and initialization scope

The twenty domain groups cover exact ten package prices, unspecified pass windows, weekend access intersections, registration hashing/duplicate rejection, login, profile/password/logout, roles, simulated payment outcomes, idempotency, historical price snapshots, order ownership, minimal pass verification, six-language seed data, favorites, inquiries, newsletter consent/preferences, CMS publication/archive, relationships, bookings/cancellation/rescheduling, unavailable sessions, CSRF, upload rejection, capacity reduction, editable hours and inactive accounts.

The booking/checkout harness starts independent PHP processes and PDO connections behind a barrier. A race for one class place produced one confirmed booking and one `class_full` rejection. Repeated simultaneous checkout returned one order with one membership and one pass. A subsequent migration preserved exact counts in nine content/history tables. This is not a container redeploy test.

Administrator checks exercise concurrent cross-deactivation with serialization on the administrator-role row. The original race could produce a database deadlock and HTTP 503; it was not shown to remove the final administrator. Three simultaneous cross-deactivation races now pass while preserving an active administrator and valid handling of the rejected action.

Fresh-install checks use a real empty local database with hosted-origin configuration. Initialization fails closed without a private initial administrator password; supplying it allows retry, stores a hash and preserves that hash on later initialization. The isolated seed-boundary suite checks related configuration and retry cases without a live database. Neither suite proves RepoRun startup or hosted secret configuration.

### CMS recheck scope

The relation suite exercises the actual admin controller/form data and save handlers. Unchanged trainer/service edits preserve draft and archived relationships in both directions. New links to archived records remain prohibited, public directories still hide unpublished records, and explicit removal works. Fixtures are cleaned by the harness.

The content-display suite saves CMS content and renders the real templates. It checks stored class image/alt/preparation/equipment, escaped text, accessible fallbacks, separate metadata translations, English/legacy fallback and blank untranslated CMS fields. Specialties, languages, equipment and preparation are stored under per-locale metadata; duration remains shared. Explicit empty translations are respected. It also checks custom demonstration-price labels in all six languages and verifies that the ten sourced amounts and their attribution remain unchanged. These fixtures are cleaned by the harness.

### HTTP and browser scope

The HTTP suite exercises registration/dashboard/logout/login; member/admin separation; checkout/order/pass and repeated checkout; booking review/confirmation/duplicate rejection/cancellation; favorites; contact validation/storage/admin visibility; newsletter lifecycle; CSRF/security headers/noindex; six-language routes; administration pages/editors; health and unknown routes. Passwords with leading/trailing spaces remain unchanged across registration, login and password changes; NUL-containing passwords produce validation errors.

Browser layout checks cover **320, 375, 390, 430, 768, 1024, 1280, 1440 and 1920 pixels** on home, memberships, classes, contact and login. Selected account/admin pages are checked at 320/390/768/1440. The separate locale sweep covers home, memberships, classes, contact and news in all six languages at 320/768/1440: **90/90 renders passed**. No tested page-level horizontal overflow remained. Tables and month grids may scroll inside their own containers.

Browser interactions include optional-cookie rejection, consent-dependent language storage, preferences dialog/Escape, mobile keyboard navigation, list/calendar switching, gallery navigation, login and member/admin screens. Executable upload is rejected and an accepted raster image is re-encoded/retrieved as WebP. No captured JavaScript runtime exception occurred. These checks do not cover every page, browser, viewport or accessibility interaction.

## Earlier implementation evidence retained

An earlier independent audit checked 17 public routes for one H1, duplicate IDs, labels, alt attributes and empty/placeholder links. It also exercised the mobile More menu, cookie focus restoration and a 35-day month grid. This exact independent audit was **not rerun** for the final recheck. Earlier desktop/mobile screenshots were visually inspected, and 13 original SVG files parsed as XML. Current browser results provide narrower fresh evidence, not a retroactive rerun of all historical checks.

## Issues corrected

The initial implementation corrected form/route integration, consent and newsletter management, language cookies and translations, admin controls, filters/pagination, eligibility feedback, SEO metadata, invalid dates and narrow German layout overflows. Earlier test-only fixture/selector/encoding repairs were not silently counted as product fixes.

The final pre-upload review additionally corrected:

- Silent loss of draft/archived trainer-service links during an unchanged CMS save.
- Class details ignoring configured images, alt text, preparation and equipment.
- Metadata in one locale overwriting the same textual fields in other locales.
- Custom demo plans being falsely attributed to the supplied Instagram price screenshot.
- Password handling trimming intentional spaces or allowing NUL-related runtime errors.
- Concurrent administrator cross-deactivation causing a database deadlock/HTTP 503; no final-administrator lockout was established in the baseline.

## Explicitly NOT VERIFIED

- Docker image build/start, the exact target PHP/MariaDB pair, Apache rules in that image, Linux mount ownership, and behavior under accepted CPU/memory limits.
- RepoRun private-repository read access, required key/environment configuration, memory/disk acceptance, course-required ingress policy, platform Validate, deployment, live health, wake-on-request and database/uploads surviving a normal redeployment. Team 39, the private repository/branch/root and an observed 0.5 CPU workspace ceiling are recorded in [DEPLOYMENT.md](DEPLOYMENT.md); these observations do not prove runtime acceptance.
- Hosted proxy behavior: rate limits currently use `REMOTE_ADDR`. `TRUST_PROXY` is reserved and unused; forwarded client headers are not trusted. Verify whether RepoRun preserves visitor addresses or causes visitors to share the proxy's rate-limit bucket before treating hosted behavior as checked.
- Backup/restore on the target platform. Procedures exist; a live restoration has not been demonstrated.
- Current business prices/hours, staff/services, exact entrance, domain ownership, media authorization, legal identity and unspecified course rules.
- Independent native-speaker review, formal screen-reader/WCAG audit, penetration testing, load/performance testing and full cross-browser/device coverage.
- Email delivery and real payments: these integrations intentionally do not exist.

## Reproduce responsibly

Use a fresh disposable database ending in `_test`. Run `php tests/fresh_install.php` against the **empty database before normal migration or other suites**; it exercises initial installation itself. Then run `php bin/migrate.php` as needed and the other documented suites. `tests/admin_safety.php` temporarily changes administrator states and creates a test-only trigger: run it **exclusively in a disposable test database**, with `TRIGGER` granted for that database. Do not run it alongside other suites or against member data. Production application operation does not need the test's `TRIGGER` permission.

Tests create fictional data. The principal integration and HTTP suites retain their fixtures in the isolated database; focused relation/content-display and concurrency harnesses clean their own fixtures. Rate limits stay active; use a fresh isolated test database or wait for the window instead of weakening protections. The final recheck preview on port 8099 was stopped after verification.

Browser tests need development-only Playwright and Chrome/Chromium (`PLAYWRIGHT_MODULE_PATH`, `CHROME_PATH`, `TEST_BASE_URL`, `TEST_OUTPUT_DIR` may be set). Python configuration checks need PyYAML. None is a website runtime dependency. The production image excludes tests and documentation.
