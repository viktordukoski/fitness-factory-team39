# Fitness Factory implementation architecture

## Phase 1 — scope and trust boundary
Local university demonstration for Business Practice 2025/2026. PHP 8.3+, MariaDB 11.4, semantic HTML, CSS, vanilla JavaScript. No application Node runtime. No deployment or publication authorized. Sources under the parent `sources/` directory remain untouched.

Published contact, prices and hours come from supplied Instagram screenshots, received 2026-10-18. Price validity and current hours need owner confirmation. Opening-hours source date is 2023-10-19. Fictional people, classes, policies and transactions are clearly marked. No official-site authorization, email delivery, payment collection or real gym admission is claimed.

## Phases 2–4 — data and implementation boundaries
Front controller: `public/index.php`, with route query `?page=...` for restricted hosting/subdirectory compatibility. Apache routes all pretty URLs through the same controller. All actions use POST + CSRF. Reusable application helpers in `app/bootstrap.php`; domain services in `app/domain.php`; repositories use PDO MySQL prepared statements. Templates in `app/views/`, translations in `app/lang/`, local assets in `public/assets/`.

Normalized core entities: users/roles; content and content_translations (one shared content model for services, facilities, trainers, class types, articles, FAQs, gallery); trainer_services; rooms; class_sessions; bookings; membership_plans and translations; orders/order_items/demo_payments; memberships/passes; favorites; contact_messages; newsletter_subscribers; content_sources/site_settings/admin_audit_log. The shared content model avoids identical CRUD/translation machinery while retaining typed records and publication state.

Money is integer MKD at application boundaries and DECIMAL(10,2) in storage. Times are UTC in storage, Europe/Skopje in input/display. Booking holds a session row lock and checks capacity, publication, status, time and active demo entitlement; unique (user,session) rows are reactivated only within the same transaction. Checkout uses a per-user idempotency key and immutable order item snapshots; only success activates membership/pass once. Pending transfer/reception requires admin confirmation. No card fields.

Public routes: home, about, facilities, services, trainers, classes, class, memberships, plan, news, article, gallery, contact, faq, search, privacy, cookies, terms, verify. Account: register, login, dashboard, bookings, favorites, orders, passes, settings, checkout, order, booking. Admin: dashboard, content, edit, sessions, plans, users, inquiries, subscribers, orders, settings, sources, audit. Footer links expose all public routes.

Deployment: PHP Apache web service + private MariaDB service; no Compose ingress ports, env_file, labels, restart or networks. `stack.yml` supplies web:80 public ingress. `.stack-data/` bind mounts preserve database/uploads. Versioned forward migrations, repeat-safe seeding, readiness wait. Resources parameterized and explicitly documented as unconfirmed until team quota is supplied.

## Phase 5 — design system
Proposed brand interpretation: coal black, warm white, saturated yellow; no claim to exact official brand codes. Editorial large type, generous whitespace, angular lines, visible focus, responsive cards and table wrappers. Original vector gym illustrations and fictional avatar artwork avoid unlicensed promotional photography. All have provenance records. Reduced motion and a keyboard-accessible list alternative to calendar.

## Phases 6–8 — delivery gates
Implement domain transactions and database first, then public/account/admin screens. Execute PHP lint, database integration and HTTP workflow tests where runtimes are available; browser checks at 320/375/390/430/768/1024/1280/1440/1920. Track each specification section and each executed check in `REQUIREMENTS.md` and `TESTING.md`. Unexecuted tests are NOT VERIFIED. RepoRun Validate, live endpoint and redeploy persistence require platform access and deployment authorization; they remain NOT VERIFIED until actually performed.

## Shared code contract
Global functions: `db(): PDO`, `e(mixed): string`, `t(string,array=[]): string`, `url(string,array=[]): string`, `current_user(): ?array`, `csrf_field(): string`, `content(string,array=[]): array`, `content_item(int): ?array`, `plans(): array`, `sessions(array=[]): array`, `site_setting(string,string=''): string`, `local_time(string,string='d M Y H:i'): string`, `money(mixed): string`, `render(string,array=[]): void`, `flash(string,string='success'): void`. `render` extracts data into `app/views/{name}.php`, inside layout. `$page`, `$user`, `$lang`, `$title` available in all templates. Request variables are validated in controller/domain; view output is escaped. Content records expose id, kind, slug, title, summary, body, category, level, format, image, alt, is_demo, published plus metadata JSON. Page routes use `url('services')`, details `url('detail',['id'=>...])`, session `url('class',['id'=>...])`. POST forms include `action`, CSRF token and record ids.
