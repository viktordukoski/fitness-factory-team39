# Fitness Factory Ohrid — university demonstration

A PHP/MariaDB presentation and member-service application for Business Practice 2025/2026. Checkout records application orders without collecting money; bookings must be checked with reception before attending. This repository does not establish authorization as the business's official website.

**Public-copy update — 18 October 2026:** the user affirmed the supplied gym details as real and requested direct business copy in all six languages. Public fictional/demo/simulated, Instagram, historical, unconfirmed and published-price framing, content badges and the global banner were removed; equipment names are listed directly and the third About paragraph was deleted. This later instruction supersedes the original specification's public-label requirements. The original specification, private provenance and internal source flags remain a truthful record of how content was supplied or created; this copy change is not independent business re-verification. Checkout still charges no money, bookings are saved in this application and require reception confirmation, and stored contact/newsletter entries do not imply email delivery.

**Deployment status: awaiting RepoRun setup.** Source updates target the private GitHub repository [viktordukoski/fitness-factory-team39](https://github.com/viktordukoski/fitness-factory-team39), branch `main`. RepoRun Team 39's repository field has been set; repository access keys, environment values, Validate and deployment have not been completed. There is no shared website link yet.

For future manual source updates, follow [UPLOAD.md](docs/UPLOAD.md). The user authorized a GitHub source push on 18 October 2026; deployment remains paused. Uploading the files to GitHub does not deploy this application. Local test results do not verify a Docker build or a working RepoRun deployment; see [DEPLOYMENT.md](docs/DEPLOYMENT.md) for the remaining steps.

## Run locally with Docker

Requirements: Docker Engine with Compose v2. The application uses PHP 8.3 Apache and MariaDB 11.4; no Node application server, framework or paid service is required.

1. Copy `.env.example` to `.env` in this directory. Keep `.env` private and out of Git.
2. Supply new random application/database secrets and set `APP_URL=http://localhost:8080`. The example resource values are local starting points, **not a confirmed team quota**.
3. Run `docker compose -f docker-compose.yml -f compose.local.yml up --build`.
4. Open `http://localhost:8080`. The database healthcheck, readiness wait, schema migration and repeat-safe seeding run before Apache starts. Initial startup can take longer than subsequent starts.

The optional local override publishes only loopback port 8080. RepoRun uses `docker-compose.yml` and `stack.yml`; **do not load `compose.local.yml` there**.

The Apache document root is `public/`. Application code, configuration, SQL and logs must never be served as public files. To run without Docker, install PHP 8.3+ with PDO MySQL, mbstring, intl, GD and fileinfo plus MariaDB 11.4; create a database and a database-scoped user, export the environment reference, run `php bin/migrate.php`, and serve `public/`. The PHP development server command is `php -S 127.0.0.1:8080 -t public public/router.php`. XAMPP/WAMP/LAMP can use the same document root and environment; no privileged `CREATE DATABASE` is required by migrations.

## Demo accounts

These default credentials are for demostrational purposes only:

| Role | Email | Password |
|---|---|---|
| Administrator | `admin@demo.test` | `FactoryAdmin!2026` |
| Member | `member@demo.test` | `FactoryMember!2026` |

For the hosted website, `admin@demo.test` uses the private initial password entered as `DEMO_ADMIN_PASSWORD` in RepoRun, **not the local administrator password above**. A fresh demo installation whose `APP_URL` host is not `localhost`, `127.0.0.1` or `::1` requires this value. Use a unique random password of 16–72 bytes and share it privately only with project administrators. It is hashed before storage and is never printed. Changing this environment value later does not reset an existing account; use the account password settings for rotation.

Seed files store password hashes. `DEMO_MODE=false` rejects all seeded demo accounts, and `SEED_DEMO=false` prevents demonstration account/workflow seeding on a fresh installation. These switches do not erase existing data. Before any non-demo use, create a separate administrator with `bin/create-admin.php`, remove/deactivate demo accounts, review all fictional content, obtain business authorization, configure appropriate operations and policies, and replace demo-only workflows as needed. This version collects no real payments under any setting.

## What is implemented

- Public business overview, facilities/equipment, searchable services and trainer profiles, memberships, class list/calendar, news, gallery, FAQ, contact, directions, global search and policies. Public wording follows the later copy instruction recorded above; content origin remains documented privately.
- Six locales: Macedonian, English, Albanian, German, French and Italian. Database translations and interface dictionaries use English fallback for missing CMS translations. Locale selection preserves the current route and persists in the necessary session; cross-visit language cookies require optional preference consent.
- Registration/login/logout, profile/password changes, favorites, bookings, order history and printable demo passes with opaque status-verification links.
- Transactional capacity checks, duplicate booking protection, cancellation windows, administrator rescheduling/cancellation and member-visible changes.
- Simulated card/transfer/reception scenarios, server-priced immutable order snapshots, idempotent submission and administrator confirmation of pending orders. No card number, CVV or banking credential fields.
- Protected CMS for content/translations, class types/sessions/rooms, plans, uploaded images, inquiries, subscription status, users, source records, business settings and audit history.
- Consent controls for necessary and preference storage. Analytics is not installed. Maps are ordinary outbound links; no external embeds or tracking are loaded.

## Business facts and limitations

The supplied profile gives **+389 76 435 353**, **15th Korpus No.21, Ohrid 6000**, and [the social profile](https://www.instagram.com/fitnessfactory.mk/). The user subsequently affirmed the supplied gym details as real. The underlying source record remains unchanged; no separate domain-ownership or entrance verification was performed.

The ten packages are seeded at **300; 1,000; 1,600; 2,000; 4,350; 5,400; 7,700; 9,600; 14,400; 18,000 MKD**, preserving duration/access distinctions. Displayed hours are **Mon–Fri 06:00–22:00, Sat 08:00–22:00, Sun 09:00–21:00**. These details now appear directly following the user's affirmation. For the private provenance record, the price screenshot has no visible publication date and the hours source is dated **19 October 2023**; their internal source flags were not rewritten as independent verification. Package windows intersect daily opening hours. Single-session/tourist windows and holiday exceptions remain unspecified.

The original research did not establish an official email, staff roster, timetable, qualifications, precise area, founding date, spa availability, package inclusions, parking or accessibility arrangements. Public caveats and labels were removed at the user's request; this does not add independent evidence for those details. The conflicting floor-area figures are not published as a definitive area. Promotional screenshot photos are not reused; artwork and profile origins remain in the private provenance record.

Contact submissions and newsletter preferences are stored for project administrators. **No email transport or newsletter delivery is configured.** Anonymous subscribers receive an on-screen management link; account holders manage subscription preferences in Settings. No messages are sent to the real gym.

## Architecture and data

`public/index.php` dispatches through `app/http.php`. `app/bootstrap.php` contains configuration, session, localization and data-access helpers; `app/domain.php` owns authentication, order and booking transactions; `app/admin.php` owns CMS mutations and upload validation. `app/admin_forms.php` supplies editable fields. Templates live in `app/views/`; local CSS, JavaScript and SVG artwork in `public/assets/`.

`database/schema.sql` defines normalized users/roles, typed content and translations, trainer/service relations, rooms/sessions/bookings, plans/translations, immutable orders/items/payments, memberships/passes, favorites, inquiries, subscribers, settings, provenance and audit tables. A shared typed content table replaces repeated near-identical tables for seven content families. Foreign keys and uniqueness protect history; referenced records are archived, not deleted.

All stored dates/times are UTC; user-facing schedules use **Europe/Skopje**. Membership periods use explicitly fictional fixed-duration rules (1/7/30/90/180/365 days), not claims about business contract terms. Money uses exact `DECIMAL(10,2)` storage. Sessions and per-user checkout rows are locked within transactions to serialize capacity and idempotency checks.

Migrations are forward-only files in `database/migrations/` with recorded versions. Initial schema and seeds run once; redeployment does not overwrite CMS edits, reset passwords or regenerate sessions. Fresh demonstration dates are relative to installation. Use the administrator schedule editor to keep classes current; essential workflows do not rely on a permanently running scheduler. See the deployment guide before resetting or reseeding any persistent database.

## Routes and administration

Normal anchors use `/?page=…&lang=…`, which also works in restricted hosting. Apache routes path forms such as `/admin` through the same controller. Set `APP_BASE_PATH` for a subdirectory. Public routes include `home`, `about`, `facilities`, `services`, `trainers`, `detail`, `classes`, `class`, `memberships`, `plan`, `news`, `article`, `gallery`, `contact`, `faq`, `search`, `privacy`, `cookies`, `terms`, `newsletter` and `verify`.

Account routes: `register`, `login`, `dashboard`, `booking`, `bookings`, `checkout`, `order`, `orders`, `passes`, `favorites`, `settings`. `/admin` requires administrator authorization on every read and mutation. From its Content section choose a type, create/edit records, switch locale to edit translations, and publish or archive. Upload an authorized raster image and use its returned path with descriptive alt text. Sessions support explicit capacity and cancellation cutoffs; existing confirmed reservations prevent reducing capacity below occupancy. Pending orders can be marked paid, failed or cancelled; paid history cannot be silently reversed.

`/health` checks database connectivity. `/robots.txt` and `/sitemap.xml` are dynamically served. Demo mode is noindex; authentication is still enforced independently.

## Security and tests

Prepared PDO statements, hashed passwords, CSRF tokens, session regeneration, owner checks, administrator checks, input validation and database-backed rate limits protect actions. Uploaded JPEG/PNG/WebP files are decoded, re-encoded with generated names and stored outside source code. Executable uploads and user-supplied SVG uploads are rejected. Output is escaped; errors expose no database credentials.

Run tests only against an isolated seeded database named with an `_test` suffix. `php tests/integration.php` exercises domain workflows; `php tests/concurrency.php` starts independent worker connections to race the last class place and duplicate checkout. `python tests/http_workflows.py http://127.0.0.1:8080` exercises HTTP journeys. Browser and deployment checks are recorded separately; do not infer a test passed merely because its code exists.

The recorded pre-upload recheck predates the latest public-copy change; it is not evidence that the revised wording was already tested. See [TESTING.md](docs/TESTING.md) for separately recorded copy validation, executed results and limits, [REQUIREMENTS.md](docs/REQUIREMENTS.md) for the full audit, [ARCHITECTURE.md](docs/ARCHITECTURE.md) for design decisions, [DEPLOYMENT.md](docs/DEPLOYMENT.md) for RepoRun, and [CONTENT-PROVENANCE.md](docs/CONTENT-PROVENANCE.md) for source/asset status. The original scope remains preserved in [SPECIFICATION.txt](docs/SPECIFICATION.txt), with the later public-copy instruction documented above.

## Course context

The supplied course requirements cover basic information, team/employees, services, contact form/details, news/blog, multilingual functionality, cookies and relevant detail. Teams have 2–4 students. Assessment is 50 teacher + 30 classmates + 10 reviewing at least three assigned projects + 10 automated-agent checks. The automated agent alone does not award a final grade of 10. The supplied deadline says September without an exact date/year; no missing date is assumed. Submission is one member's website link on Courses after authorized FCSE deployment.
