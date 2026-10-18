<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
const LANGUAGES = ['en', 'mk', 'sq', 'de', 'fr', 'it'];
date_default_timezone_set('UTC');

function env(string $key, string $default = ''): string
{
    $value = getenv($key);
    return $value === false ? $default : $value;
}
function demo_mode(): bool { return filter_var(env('DEMO_MODE', env('APP_ENV', 'demo') === 'production' ? 'false' : 'true'), FILTER_VALIDATE_BOOLEAN); }
function base_path(): string { return '/' . trim(env('APP_BASE_PATH'), '/'); }
function is_https(): bool { return env('SESSION_SECURE',env('COOKIE_SECURE')) === 'true' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || str_starts_with(env('APP_URL'), 'https://'); }

if (PHP_SAPI !== 'cli') {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('ff_session');
    session_set_cookie_params(['lifetime' => 0, 'path' => rtrim(base_path(), '/') . '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
    if (is_https()) header('Strict-Transport-Security: max-age=31536000');
    if (demo_mode()) header('X-Robots-Tag: noindex, nofollow');
}

function db(): PDO
{
    static $connection;
    if (!$connection) {
        $dsn = 'mysql:host=' . env('DB_HOST', '127.0.0.1') . ';port=' . env('DB_PORT', '3306') . ';dbname=' . env('DB_NAME', 'fitness_factory') . ';charset=utf8mb4';
        $connection = new PDO($dsn, env('DB_USER', 'fitness_app'), env('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
        $connection->exec("SET time_zone = '+00:00'");
    }
    return $connection;
}
function execute(string $sql, array $params = []): PDOStatement { $q = db()->prepare($sql); $q->execute($params); return $q; }
function fetch_all(string $sql, array $params = []): array { return execute($sql, $params)->fetchAll(); }
function fetch_one(string $sql, array $params = []): ?array { $row = execute($sql, $params)->fetch(); return $row === false ? null : $row; }
function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function lang(): string
{
    $consent=json_decode($_COOKIE['ff_consent']??'{}',true);
    $storedLanguage=is_array($consent)&&!empty($consent['preferences'])?($_COOKIE['ff_language']??'en'):'en';
    $choice = $_GET['lang'] ?? $_SESSION['lang'] ?? $storedLanguage;
    if (!is_string($choice) || !in_array($choice, LANGUAGES, true)) $choice = 'en';
    $_SESSION['lang'] = $choice;
    return $choice;
}
function t(string $key, array $replace = []): string
{
    static $dictionaries = [];
    static $marketing;
    $marketing ??= require __DIR__ . '/lang/marketing.php';
    static $supplement;
    $supplement ??= require __DIR__ . '/lang/supplement.php';
    $language = lang();
    foreach (array_unique(['en', $language]) as $code) {
        if (!isset($dictionaries[$code])) {
            $file = __DIR__ . '/lang/' . $code . '.php';
            $dictionaries[$code] = array_merge(is_file($file) ? require $file : [], $marketing[$code]??[], $supplement[$code]??[]);
        }
    }
    $value = $dictionaries[$language][$key] ?? $dictionaries['en'][$key] ?? (preg_match('/^[a-z][a-z0-9_]*$/D',$key)?ucwords(str_replace('_',' ',$key)):$key);
    if(env('TRANSLATION_AUDIT')==='true'&&!isset($dictionaries[$language][$key])&&!in_array($key,$dictionaries[$language],true)) $GLOBALS['missing_translations'][$language][$key]=true;
    foreach ($replace as $name => $replacement) $value = str_replace(['{' . $name . '}', ':' . $name], (string)$replacement, $value);
    return $value;
}
function url(string $page = 'home', array $params = []): string
{
    return rtrim(base_path(), '/') . '/?' . http_build_query(array_merge(['page' => $page, 'lang' => lang()], $params), '', '&', PHP_QUERY_RFC3986);
}
function asset(string $path): string { return rtrim(base_path(), '/') . '/' . ltrim($path, '/'); }
function local_time(?string $utc, string $format = 'd M Y H:i'): string
{
    if (!$utc) return '—';
    $date=(new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Europe/Skopje'));
    $patterns=['d M Y H:i'=>'dd MMM yyyy HH:mm','d M Y'=>'dd MMM yyyy','M / D'=>'MMM / EEE','D, d M'=>'EEE, dd MMM','d M'=>'dd MMM','D, d M Y'=>'EEE, dd MMM yyyy'];
    if(isset($patterns[$format]) && class_exists('IntlDateFormatter')) return (new IntlDateFormatter(lang(),IntlDateFormatter::NONE,IntlDateFormatter::NONE,'Europe/Skopje',IntlDateFormatter::GREGORIAN,$patterns[$format]))->format($date);
    return $date->format($format);
}
function utc_time(string $local): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $local, new DateTimeZone('Europe/Skopje'));
    if (!$date || $date->format('Y-m-d\TH:i') !== $local) throw new RuntimeException('date_invalid');
    return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}
function money(mixed $amount): string
{
    [$whole,$fraction]=array_pad(explode('.',(string)$amount,2),2,'00');
    $fraction=substr(str_pad($fraction,2,'0'),0,2);
    $formatter=class_exists('NumberFormatter')?new NumberFormatter(lang(),NumberFormatter::DECIMAL):null;
    $formatted=$formatter?$formatter->format((int)$whole):number_format((int)$whole,0,'.',',');
    if($fraction!=='00') $formatted.=($formatter?$formatter->getSymbol(NumberFormatter::DECIMAL_SEPARATOR_SYMBOL):'.').$fraction;
    return $formatted.' MKD';
}
function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function verify_csrf(?string $token = null): void
{
    $token ??= is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '';
    if (!hash_equals(csrf_token(), $token)) throw new RuntimeException('csrf_invalid', 419);
}
function flash(string $message, string $type = 'success'): void { $_SESSION['flashes'][] = ['message' => $message, 'type' => $type]; }
function flashes(): array { $messages = $_SESSION['flashes'] ?? []; unset($_SESSION['flashes']); return $messages; }
function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) return null;
    $user = fetch_one('SELECT u.id,u.name,u.email,u.language,u.active,u.is_demo,u.role_id,r.name AS role FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=?', [(int)$_SESSION['user_id']]);
    if (!$user || !$user['active'] || (!demo_mode() && $user['is_demo'])) { unset($_SESSION['user_id']); return null; }
    return $user;
}
function require_user(): array { return current_user() ?? throw new RuntimeException('forbidden', 401); }
function require_admin(): array { $user = require_user(); if ($user['role'] !== 'admin') throw new RuntimeException('forbidden', 403); return $user; }
function assert_user(int $id): array
{
    $user = fetch_one('SELECT u.*,r.name AS role FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? AND u.active=1', [$id]);
    if (!$user || (!demo_mode() && $user['is_demo'])) throw new RuntimeException('account_inactive', 403);
    return $user;
}
function assert_admin(int $id): void { if (assert_user($id)['role'] !== 'admin') throw new RuntimeException('forbidden', 403); }
function site_setting(string $key, string $default = ''): string { return fetch_one('SELECT value FROM site_settings WHERE setting_key=?', [$key])['value'] ?? $default; }

function content_select(): string
{
    return 'SELECT c.*,COALESCE(NULLIF(tr.title,\'\'),en.title) title,COALESCE(NULLIF(tr.summary,\'\'),en.summary) summary,COALESCE(NULLIF(tr.body,\'\'),en.body) body,COALESCE(NULLIF(tr.alt,\'\'),en.alt) alt FROM content c JOIN content_translations en ON en.content_id=c.id AND en.language=\'en\' LEFT JOIN content_translations tr ON tr.content_id=c.id AND tr.language=?';
}
function decode_content(array $row): array { $row['metadata'] = json_decode($row['metadata'] ?? '{}', true) ?: []; return $row; }
function content_query(string $kind = '', array $filters = []): array
{
    $sql = content_select() . ' WHERE c.published=1 AND c.archived=0'; $params = [lang()];
    if ($kind) { $sql .= ' AND c.kind=?'; $params[] = $kind; }
    foreach (['category','level','format'] as $field) if (!empty($filters[$field])) { $sql .= ' AND c.' . $field . '=?'; $params[] = $filters[$field]; }
    if (!empty($filters['q'])) { $sql .= ' AND (COALESCE(tr.title,en.title) LIKE ? OR COALESCE(tr.summary,en.summary) LIKE ? OR COALESCE(tr.body,en.body) LIKE ?)'; $term = '%' . str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], (string)$filters['q']) . '%'; array_push($params, $term, $term, $term); }
    if(!empty($filters['kinds'])) { $kinds=array_values(array_intersect((array)$filters['kinds'],['service','facility','trainer','class_type','news','faq','gallery'])); if($kinds){$sql.=' AND c.kind IN ('.implode(',',array_fill(0,count($kinds),'?')).')';array_push($params,...$kinds);} }
    return [$sql,$params];
}
function content_count(string $kind = '', array $filters = []): int
{
    [$sql,$params]=content_query($kind,$filters);
    return (int)fetch_one('SELECT COUNT(*) total FROM ('.$sql.') matches',$params)['total'];
}
function content(string $kind = '', array $filters = []): array
{
    [$sql,$params]=content_query($kind,$filters);
    $sql .= $kind === 'news' ? ' ORDER BY c.published_at DESC,c.id DESC' : ' ORDER BY c.id';
    $sql .= ' LIMIT ' . min(200, max(1, (int)($filters['limit'] ?? 100))) . ' OFFSET ' . max(0, (int)($filters['offset'] ?? 0));
    return array_map('decode_content', fetch_all($sql, $params));
}
function content_item(int $id): ?array { $row = fetch_one(content_select() . ' WHERE c.id=? AND c.published=1 AND c.archived=0', [lang(),$id]); return $row ? decode_content($row) : null; }
function plans(bool $all = false): array
{
    return fetch_all('SELECT p.*,COALESCE(NULLIF(tr.title,\'\'),en.title) title,COALESCE(NULLIF(tr.description,\'\'),en.description) description FROM membership_plans p JOIN membership_plan_translations en ON en.plan_id=p.id AND en.language=\'en\' LEFT JOIN membership_plan_translations tr ON tr.plan_id=p.id AND tr.language=?' . ($all ? '' : ' WHERE p.active=1') . ' ORDER BY p.id', [lang()]);
}
function session_select(): string
{
    return 'SELECT s.*,c.slug,c.category,c.level,c.format,c.image,c.is_demo,c.published,c.archived,COALESCE(tr.title,en.title) title,COALESCE(tr.summary,en.summary) summary,COALESCE(tr.body,en.body) body,COALESCE(tr.alt,en.alt) alt,COALESCE(tt.title,te.title) trainer,r.name room,(SELECT COUNT(*) FROM bookings b WHERE b.session_id=s.id AND b.status IN (\'confirmed\',\'attended\',\'no_show\')) booked FROM class_sessions s JOIN content c ON c.id=s.class_type_id JOIN content_translations en ON en.content_id=c.id AND en.language=\'en\' LEFT JOIN content_translations tr ON tr.content_id=c.id AND tr.language=? LEFT JOIN content_translations te ON te.content_id=s.trainer_id AND te.language=\'en\' LEFT JOIN content_translations tt ON tt.content_id=s.trainer_id AND tt.language=? JOIN rooms r ON r.id=s.room_id';
}
function sessions(array $filters = []): array
{
    $sql = session_select() . ' WHERE c.published=1 AND c.archived=0'; $params = [lang(),lang()];
    if (($filters['period'] ?? '') === 'past') $sql .= ' AND s.starts_at<UTC_TIMESTAMP()';
    elseif (empty($filters['all'])) $sql .= ' AND s.ends_at>=UTC_TIMESTAMP()';
    foreach (['category','level'] as $field) if (!empty($filters[$field])) { $sql .= ' AND c.' . $field . '=?'; $params[] = $filters[$field]; }
    foreach (['trainer_id','class_type_id'] as $field) if (!empty($filters[$field])) { $sql .= ' AND s.' . $field . '=?'; $params[] = (int)$filters[$field]; }
    if (!empty($filters['q'])) { $sql .= ' AND (COALESCE(tr.title,en.title) LIKE ? OR COALESCE(tt.title,te.title) LIKE ?)'; $params[] = '%' . $filters['q'] . '%'; $params[] = '%' . $filters['q'] . '%'; }
    if (!empty($filters['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date'])) { $sql .= ' AND s.starts_at>=? AND s.starts_at<?'; $date = new DateTimeImmutable($filters['date'], new DateTimeZone('Europe/Skopje')); $params[] = $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'); $params[] = $date->modify('+1 day')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'); }
    $sql .= ' ORDER BY s.starts_at LIMIT 200';
    $rows = fetch_all($sql, $params);
    foreach ($rows as &$row) $row['remaining'] = max(0, (int)$row['capacity'] - (int)$row['booked']);
    if (($filters['availability'] ?? '') === 'available') $rows = array_values(array_filter($rows, fn($r) => $r['remaining'] > 0 && $r['status'] === 'scheduled' && $r['starts_at'] > gmdate('Y-m-d H:i:s')));
    return $rows;
}
function session_item(int $id): ?array { $row = fetch_one(session_select() . ' WHERE s.id=? AND c.published=1 AND c.archived=0', [lang(),lang(),$id]); if ($row) $row['remaining'] = max(0,(int)$row['capacity']-(int)$row['booked']); return $row; }

require_once __DIR__ . '/domain.php';
require_once __DIR__ . '/admin.php';
