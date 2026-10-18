# Local and RepoRun operations

Updated on 18 October 2026. **The user resumed RepoRun setup and validation; deployment remains paused.** The private GitHub repository is [viktordukoski/fitness-factory-team39](https://github.com/viktordukoski/fitness-factory-team39), with branch `main` and repository root `/`. Its approved read-only RepoRun deploy key is installed. The first platform validation fetched the repository successfully, then reported interpolated resource fields and missing application environment values. Standard settings have been saved; private values require operator entry. The resource correction below requires another platform validation. No live website or remote persistence result is claimed.

Follow [UPLOAD.md](UPLOAD.md) for future manual source updates. Uploading source does not itself deploy the website; no automatic deployment webhook is enabled. The local checks in [TESTING.md](TESTING.md) do not verify the Linux Docker build or a working public endpoint. The first platform validation operation was `op_01M587SMHJEEASFH4VKSDKQPCM`; its reported errors are not a successful validation.

`APP_URL` must be the actual HTTPS endpoint. Before deployment, Team 39's endpoint table is empty. RepoRun's Guide describes a primary hostname of `https://<team-slug>--<workspace-slug>.<base-domain>`, but did not provide this installation's base domain. Confirm that domain or the exact generated endpoint before setting `APP_URL`; do not use a guessed address or localhost to bypass validation.

The signed-in Workspaces page shows ceilings of **500m (0.5 CPU), 1000 MB memory and 2000 MB disk** for `delovna2526`. Team 39 shows no explicit CPU, memory or disk override. The hosted Compose file sets literal `cpus: 0.25` on each service, `mem_limit: 512m` for web and `mem_limit: 384m` for the database. The total is 0.5 CPU / 896 MiB (939,524,096 bytes), below the displayed 1000 MB memory ceiling even when interpreted as decimal bytes. RepoRun's validator rejected interpolated resource fields, then rejected the former 512m + 512m allocation with `compose.quota.memory_exceeded`. The adjusted allocation requires another platform validation. Runtime sufficiency and disk usage require live checks; these configured limits are not a guarantee of capacity.

## Verified platform reference

The supplied [RepoRun Docs](https://reporun.finki.net.mk/docs) was fetched on 18 October 2026. Its public application bundle contains the current Docs and Guide text. The docs confirm a single-host Compose platform, one stack per team, repository/branch connection, required Compose/stack files, stack environment values, lifecycle actions, preservation of `.stack-data/`, and endpoint access policies. The Guide's deployment constraints confirm explicit CPU/memory limits and prohibit Compose `ports`, `env_file`, `labels`, `restart`, `networks`, absolute host paths, privileged mode and Docker socket mounts. These restrictions are respected in `docker-compose.yml`.

Current access types are `public`, `cas`, `team`, and `basic_auth`; omitted access defaults to CAS. The old catalog term `authenticated` is explicitly retired in current Docs and must not be copied. `stack.yml` exposes only `web:80` with `public`. The course's actual required endpoint policy is **unconfirmed**. Change to `cas` if required; `team` excludes reviewers who are not members of that exact team. Do not enable public access against course policy. Application account/admin authentication remains mandatory regardless of ingress policy. The guide does not prove every backend validation rule; run the actual platform validator before any authorized deployment.

The exact image tags were checked against official image inventories: [PHP official tags](https://github.com/docker-library/official-images/blob/master/library/php) lists `8.3.35-apache-bookworm`; [MariaDB official tags](https://github.com/docker-library/official-images/blob/master/library/mariadb) lists `11.4.13-noble`. PHP 8.3 receives security fixes through 31 December 2027 according to [PHP support policy](https://www.php.net/supported-versions.php). Versions are pinned by tag, not digest; recheck available security updates and pin tested image digests when deployment access exists. A verified tag is not evidence that the Docker build was run in this workspace.

## Required files and runtime

The deployable repository root must contain this project's `Dockerfile`, `docker-compose.yml`, `stack.yml`, application directories, and deployment scripts. If the repository wraps this folder, configure an officially supported repository subdirectory only after verifying platform support, or make this folder the repository root. Do not invent a stack.yml root-path field.

PHP Apache serves only `public/`; configuration, schema and application PHP remain outside the document root. Extensions: PDO MySQL, mbstring, intl, GD, fileinfo (built into the base PHP image), and OPcache. GD decodes and re-encodes uploads as WebP. Apache blocks script execution and directory listings under uploads. MariaDB has no ingress endpoint or host port. The app account has privileges on its own database only; the root password is supplied only to the database container. Application migrations need schema DDL privileges within that database; the account is not a server administrator.

## Environment values

Use stack-level settings in RepoRun. `.env.example` is a secret-free reference and local configuration template. Never commit `.env`, credentials, backups, or `.stack-data/`. All names satisfy the platform's uppercase-name rule.

| Name | Purpose |
|---|---|
| `APP_URL` | Actual public origin, with HTTPS for RepoRun; localhost for local use |
| `APP_BASE_PATH` | Optional installation subdirectory, empty at the endpoint root |
| `APP_KEY` | Unique random secret; generate at least 32 random bytes, do not reuse |
| `APP_ENV` | `demo` for this university project |
| `DEMO_MODE`, `SEED_DEMO` | `true` for documented demonstration accounts/data; do not expose demo credentials in a non-demo deployment |
| `DEMO_ADMIN_PASSWORD` | Required for the first hosted demo administrator: unique private 16–72 byte password; optional on localhost. Never resets an existing account |
| `APP_INDEXABLE` | Keep `false` for the university demo |
| `SESSION_SECURE` | `true` for HTTPS; local override uses `false` |
| `TRUST_PROXY` | Reserved, default `false`; currently no proxy headers are trusted |
| `DB_NAME`, `DB_USER` | Dedicated database and application account; avoid root as the app user |
| `DB_PASSWORD`, `DB_ROOT_PASSWORD` | Different strong secrets; existing database users require explicit password rotation |
| `WEB_CPUS`, `DB_CPUS` | Local override only; hosted CPU limits are literal values in `docker-compose.yml` |
| `WEB_MEMORY`, `DB_MEMORY` | Local override only; hosted memory limits are literal values in `docker-compose.yml` |
| `LOCAL_PORT` | Local override only; default 8080 |

Generate a secret locally with PHP: `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`; copy the result into the secure environment setting, never a public document. Keep a private recovery copy of environment values. No mail credentials or payment gateway is required; outbound email and real payments are not implemented.

The hosted administrator email remains `admin@demo.test`, but its initial password is the private `DEMO_ADMIN_PASSWORD`, not the local password in README. Fresh remote demo startup fails if this value is absent or invalid. Only exact `localhost`, `127.0.0.1` and `::1` hosts may use the documented local default. Share the hosted administrator password privately with project administrators only. The member demonstration credential remains available for demonstration use; visitors can register their own member accounts.

## Resources: observed ceiling and remaining checks

The observed workspace ceilings are 0.5 CPU, 1000 MB memory and 2000 MB disk, with no Team 39 override displayed. `docker-compose.yml` assigns a literal 0.25 CPU per service, 512 MiB to web and 384 MiB to MariaDB. The 896 MiB total is 939,524,096 bytes, below 1,000,000,000 bytes; the previous 1 GiB total exceeded that ceiling and produced `compose.quota.memory_exceeded`. Earlier validation returned `compose.service.cpus_invalid` and `compose.service.mem_limit_invalid` for interpolated limits even when their stack environment values were present. `WEB_CPUS`, `DB_CPUS`, `WEB_MEMORY` and `DB_MEMORY` now affect only `compose.local.yml`, whose defaults remain 1 CPU / 1 GiB for local use. Do not load that override in RepoRun. Allow room for Apache/PHP workers, OPcache, MariaDB's configured 128 MiB buffer pool, connections and OS overhead. Worker count is limited to four; PHP per-request memory is 96 MiB, not reserved allocation. MariaDB allows 30 connections. The reduced database limit has not been tested at runtime: validate the adjusted source, then check startup, normal usage, memory and persistent disk growth after authorized deployment. If the assigned quota cannot sustain the stack, reduce Apache worker count and DB limits with tests or ask the course administrator for an appropriate quota. Do not silently claim compatibility with a 64 MiB static template.

## Local Docker procedure

1. Install Docker with Compose v2. Copy `.env.example` to a private `.env`, set all secrets and confirm resource allocations for the local machine.
2. From this directory run `docker compose -f docker-compose.yml -f compose.local.yml config --quiet`. Avoid printing the resolved configuration because it includes secrets.
3. Run `docker compose -f docker-compose.yml -f compose.local.yml up --build -d`.
4. Open `http://localhost:8080` (or `LOCAL_PORT`), then inspect health and logs. Use documented demo accounts only in demo mode.
5. Stop with `docker compose -f docker-compose.yml -f compose.local.yml down`. Do not remove persistent data for routine restarts.

Only `compose.local.yml` maps a loopback host port; it must never be loaded by RepoRun. The standard deployment file contains no prohibited networking fields. Build requires downloading the pinned base images and Linux packages. Current execution evidence is recorded in `TESTING.md`; do not assume Docker is installed or this procedure has been exercised.

## Initialization and persistent storage

The official MariaDB entrypoint initializes its dedicated database/account only when `.stack-data/db` is empty and initializes required ownership. On web startup, `bin/entrypoint.sh` initializes ownership of the dedicated upload directory, waits up to 120 seconds for an authenticated DB connection, then runs `bin/migrate.php` before Apache. Migrations maintain a version ledger and repeat-safe seeds; they never replace a populated database. Database initialization environment variables do not update existing users/databases on later deployments. Changing password values alone will cause authentication failure until the existing account is deliberately rotated.

`DEMO_ADMIN_PASSWORD` is used only when the demonstration administrator is first created. Changing or removing it after successful seeding does not reset the stored hash, and retrying a partial seed preserves an existing administrator. Rotate that account through its authenticated password settings. An existing installation that previously used the documented local administrator password must be rotated before public access; supplying a new environment value alone does not rotate it. Never copy the local test database into the public deployment.

Persistent paths are `./.stack-data/db:/var/lib/mysql` and `./.stack-data/uploads:/var/www/html/public/uploads`. They stay outside Git and the image. Linux upload ownership becomes `www-data`; permissions are 0750. The parent directory and bind mount must permit container ownership changes. If platform filesystem policy blocks that, arrange directory ownership through the platform administrator, then rerun validation/start; do not use world-writable 0777 permissions. Images contain immutable application code; reset/reclone preserves `.stack-data/` according to RepoRun Docs.

## RepoRun procedure after the user asks to resume

1. Confirm the course access policy, actual team quota, repository URL, branch, reviewer access and authorization to deploy. Configure private repository read access using the supported deploy key or read-only token workflow.
2. Connect the deployable repository root and branch in the team's stack. Enter application/database environment values, and confirm that the literal limits in `docker-compose.yml` fit the assigned quota. Confirm public ingress is allowed or change `stack.yml` to an accepted required policy.
3. Run **Validate** without deployment; fix every reported issue and record the result. Source inspection here is not RepoRun validation.
4. After explicit user authorization, choose Deploy/Redeploy. It fetches the configured branch and builds/starts services. Start/Wake uses the last trusted deployed configuration and does not fetch changes or rebuild.
5. Check database and web health/logs; a successful deployment operation can coexist with an unhealthy container. Test the live health endpoint, static assets, registration/login, member ownership, booking, simulated checkout, upload and public/private ingress. Keep secrets out of screenshots and shared logs.
6. Create a marker record and uploaded image, perform a normal Redeploy, then verify both remain and verify migrations did not duplicate seeds. Record actual checks and dates.

Idle stacks may sleep and wake on a request. The first request can be delayed while containers and database become ready. Essential booking and entitlement checks happen during requests; they do not require a permanent scheduler.

## Backups, recovery and updates

Before schema changes or image upgrades, make a consistent logical database dump and copy uploaded images, plus the migration version and application commit. Store backups privately outside `.stack-data/` and Git; encrypt them because they include user data and hashes. Use platform-supported backup/download facilities, or ask the platform administrator if shell access is unavailable. Host shell access is not assumed.

For a local operator with Docker access, stream a logical dump from the DB container with `mariadb-dump --single-transaction --routines --triggers` using the app DB account and a temporary protected client option file (avoid passwords in command arguments). Copy the upload directory at the same maintenance window. Preserve owners and check backup readability without revealing data. Pause writes during restoration.

Restore into an isolated empty database first, import the SQL, restore uploads with the documented ownership, point an isolated app instance at it, then verify counts, login, pass/order history, bookings and image URLs. Do not test restore against the only live copy. For a migration failure keep the affected version's logs privately, restore the known-good database/uploads and matching code, and investigate before retry. Documented backup steps are not an executed backup or restore test.

RepoRun **Delete runtime data** destroys the database and uploads. Never use it for routine deployment, recovery attempts, password rotation or schema updates. Stop preserves volumes. Reset/reclone preserves `.stack-data/` but resets validation; revalidate before deploy. Actual deploy, backup/restore, resource behavior and redeployment persistence remain NOT VERIFIED until performed on the target platform with authorization.
