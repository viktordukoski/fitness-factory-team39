# Fitness Factory Ohrid — university demonstration

A PHP/MariaDB presentation and member-service application for Business Practice 2025/2026. All payments, passes, trainers and class reservations are demonstrations. This project does not claim to be Fitness Factory's authorized official website.

**Deployment status: authorized and being configured for RepoRun Team 39.** The source repository is private; the requested website endpoint will be public. No successful platform validation or live deployment is claimed until recorded in [DEPLOYMENT.md](docs/DEPLOYMENT.md).

## Run locally with Docker

Requirements: Docker Engine with Compose v2. The application uses PHP 8.3 Apache and MariaDB 11.4; no Node application server, framework or paid service is required.

1. Copy `.env.example` to `.env` in this directory. Keep `.env` private and out of Git.
2. Supply new random application/database secrets and set `APP_URL=http://localhost:8080`. The example resource values are local starting points, **not a confirmed team quota**.
3. Run `docker compose -f docker-compose.yml -f compose.local.yml up --build`.
4. Open `http://localhost:8080`. The database healthcheck, readiness wait, schema migration and repeat-safe seeding run before Apache starts. Initial startup can take longer than subsequent starts.

The optional local override publishes only loopback port 8080. RepoRun uses `docker-compose.yml` and `stack.yml`; **do not load `compose.local.yml` there**.

The Apache document root is `public/`. Application code, configuration, SQL and logs must never be served as public files. To run without Docker, install PHP 8.3+ with PDO MySQL, mbstring, intl, GD and fileinfo plus MariaDB 11.4; create a database and a database-scoped user, export the environment reference, run `php bin/migrate.php`, and serve `public/`. The PHP development server command is `php -S 127.0.0.1:8080 -t public public/router.php`. XAMPP/WAMP/LAMP can use the same document root and environment; no privileged `CREATE DATABASE` is required by migrations.

## Demo accounts

These default credentials are for a local university demonstration:

| Role | Email | Password |
|---|---|---|
| Administrator | `admin@demo.test` | `FactoryAdmin!2026` |
| Member | `member@demo.test` | `FactoryMember!2026` |

For the hosted website, `admin@demo.test` uses the private initial password entered as `DEMO_ADMIN_PASSWORD` in RepoRun, **not the local administrator password above**. A fresh demo installation whose `APP_URL` host is not `localhost`, `127.0.0.1` or `::1` requires this value. Use a unique random password of 16–72 bytes and share it privately only with project administrators. It is hashed before storage and is never printed. Changing this environment value later does not reset an existing account; use the account password settings for rotation.

Seed files store password hashes. `DEMO_MODE=false` rejects all seeded demo accounts, and `SEED_DEMO=false` prevents demonstration account/workflow seeding on a fresh installation. These switches do not erase existing data. Before any non-demo use, create a separate administrator with `bin/create-admin.php`, remove/deactivate demo accounts, review all fictional content, obtain business authorization, configure appropriate operations and policies, and replace demo-only workflows as needed. This version collects no real payments under any setting.

## What is implemented

- Public business overview, sourced facilities/equipment, searchable services and fictional trainer profiles, memberships, class list/calendar, news, gallery, FAQ, contact, directions, global search and policies.
- Six locales: Macedonian, English, Albanian, German, French and Italian. Database translations and interface dictionaries use English fallback for missing CMS translations. Locale selection preserves the current route and persists in the necessary session; cross-visit language cookies require optional preference consent.
- Registration/login/logout, profile/password changes, favorites, bookings, order history and printable demo passes with opaque status-verification links.
- Transactional capacity checks, duplicate booking protection, cancellation windows, administrator rescheduling/cancellation and member-visible changes.
- Simulated card/transfer/reception scenarios, server-priced immutable order snapshots, idempotent submission and administrator confirmation of pending orders. No card number, CVV or banking credential fields.
- Protected CMS for content/translations, class types/sessions/rooms, plans, uploaded images, inquiries, subscription status, users, source records, business settings and audit history.
- Consent controls for necessary and preference storage. Analytics is not installed. Maps are ordinary outbound links; no external embeds or tracking are loaded.

## Business facts and limitations

The supplied Instagram profile gives **+389 76 435 353**, **15th Korpus No.21, Ohrid 6000**, and [@fitnessfactory.mk](https://www.instagram.com/fitnessfactory.mk/). It does not verify ownership of an operating website at that domain or the precise entrance.

The ten packages are seeded at **300; 1,000; 1,600; 2,000; 4,350; 5,400; 7,700; 9,600; 14,400; 18,000 MKD**, preserving duration/access distinctions. The screenshot has no visible publication date; prices are pending current confirmation. Displayed hours are **Mon–Fri 06:00–22:00, Sat 08:00–22:00, Sun 09:00–21:00**, from a post dated **19 October 2023**, also pending current confirmation. Package windows intersect daily opening hours. Single-session/tourist windows and holiday exceptions are unspecified.

No official email, real staff roster, timetable, qualification, precise area, founding date, current spa availability, class inclusions, parking or accessibility promise has been invented. Historical sources conflict on floor area; the application publishes no definitive area. Photographs from the promotional screenshots are not reused. Local original vector illustrations and fictional avatars are labeled representative.

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

See [TESTING.md](docs/TESTING.md) for executed results and limits, [REQUIREMENTS.md](docs/REQUIREMENTS.md) for the full audit, [ARCHITECTURE.md](docs/ARCHITECTURE.md) for design decisions, [DEPLOYMENT.md](docs/DEPLOYMENT.md) for RepoRun, and [CONTENT-PROVENANCE.md](docs/CONTENT-PROVENANCE.md) for source/asset status. The original requested scope is preserved in [SPECIFICATION.txt](docs/SPECIFICATION.txt).

## Course context

The supplied course requirements cover basic information, team/employees, services, contact form/details, news/blog, multilingual functionality, cookies and relevant detail. Teams have 2–4 students. Assessment is 50 teacher + 30 classmates + 10 reviewing at least three assigned projects + 10 automated-agent checks. The automated agent alone does not award a final grade of 10. The supplied deadline says September without an exact date/year; no missing date is assumed. Submission is one member's website link on Courses after authorized FCSE deployment.
