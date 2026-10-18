<?php
declare(strict_types=1);

function transaction(callable $work): mixed
{
    db()->beginTransaction();
    try { $result = $work(); db()->commit(); return $result; }
    catch (Throwable $error) { if (db()->inTransaction()) db()->rollBack(); throw $error; }
}
function text_input(mixed $value, int $min, int $max): string
{
    if (!is_string($value)) throw new RuntimeException('validation', 422);
    $value = trim($value);
    $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    if ($length < $min || $length > $max || str_contains($value, "\0")) throw new RuntimeException('validation', 422);
    return $value;
}
function valid_email(mixed $value): string
{
    $email = strtolower(text_input($value, 3, 190));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('email_invalid', 422);
    return $email;
}
function validate_password(string $password): void
{
    if (str_contains($password, "\0")) throw new RuntimeException('validation',422);
    if (strlen($password) < 12 || strlen($password) > 72) throw new RuntimeException('password_short',422);
}
function rate_limit(string $purpose, int $limit = 10, int $seconds = 900, ?string $identity = null): void
{
    $identity ??= $_SERVER['REMOTE_ADDR'] ?? 'cli';
    $bucket = env('APP_KEY') !== '' ? hash_hmac('sha256',$purpose . ':' . $identity,env('APP_KEY')) : hash('sha256',$purpose . ':' . $identity);
    $cutoff = gmdate('Y-m-d H:i:s', time() - $seconds);
    execute('INSERT INTO rate_limits(bucket,hits,window_start) VALUES(?,1,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE hits=IF(window_start<?,1,hits+1),window_start=IF(window_start<?,UTC_TIMESTAMP(),window_start)', [$bucket,$cutoff,$cutoff]);
    $record = fetch_one('SELECT hits FROM rate_limits WHERE bucket=?', [$bucket]);
    if ((int)$record['hits'] > $limit) throw new RuntimeException('rate_limited', 429);
    if (random_int(1,100) === 1) execute('DELETE FROM rate_limits WHERE window_start<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 DAY)');
}
function register_user(string $name, string $email, string $password, ?string $language = null): int
{
    rate_limit('register', 5, 3600);
    $name = text_input($name, 2, 100); $email = valid_email($email); validate_password($password);
    if (fetch_one('SELECT id FROM users WHERE email=?', [$email])) throw new RuntimeException('email_exists', 422);
    try { execute('INSERT INTO users(name,email,password_hash,language,role_id) VALUES(?,?,?,?,2)', [$name,$email,password_hash($password,PASSWORD_DEFAULT),in_array($language,LANGUAGES,true) ? $language : lang()]); }
    catch (PDOException $error) { if ((int)($error->errorInfo[1] ?? 0) === 1062) throw new RuntimeException('email_exists',422); throw $error; }
    return (int)db()->lastInsertId();
}
function login_user(string $email, string $password): array
{
    rate_limit('login-ip', 40, 900);
    $email = valid_email($email); rate_limit('login-email', 10, 900, $email);
    $user = fetch_one('SELECT u.*,r.name role FROM users u JOIN roles r ON r.id=u.role_id WHERE email=?', [$email]);
    $dummy = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
    if (!password_verify($password, $user['password_hash'] ?? $dummy) || !$user || !$user['active'] || (!demo_mode() && $user['is_demo'])) throw new RuntimeException('login_failed', 422);
    if (password_needs_rehash($user['password_hash'],PASSWORD_DEFAULT)) execute('UPDATE users SET password_hash=? WHERE id=?', [password_hash($password,PASSWORD_DEFAULT),$user['id']]);
    if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id']; $_SESSION['csrf'] = bin2hex(random_bytes(32)); $_SESSION['lang'] = $user['language'];
    unset($user['password_hash']);
    return $user;
}
function logout_user(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) { session_regenerate_id(true); $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
}
function save_profile(int $userId, array $data): void
{
    assert_user($userId); $name = text_input($data['name'] ?? '',2,100); $language = $data['language'] ?? lang();
    if (!in_array($language,LANGUAGES,true)) throw new RuntimeException('validation',422);
    // Email remains the login identity; changing it requires a verified transport, not configured here.
    execute('UPDATE users SET name=?,language=? WHERE id=?', [$name,$language,$userId]); $_SESSION['lang'] = $language;
}
function change_password(int $userId, string $current, string $new): void
{
    rate_limit('password', 8, 900, (string)$userId); $user = assert_user($userId);
    if (!password_verify($current,$user['password_hash'])) throw new RuntimeException('password_current',422);
    validate_password($new); execute('UPDATE users SET password_hash=? WHERE id=?', [password_hash($new,PASSWORD_DEFAULT),$userId]);
    if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
}
function reference(string $prefix): string { return $prefix . '-' . strtoupper(bin2hex(random_bytes(6))); }

function create_order(int $userId, int $planId, string $method, string $scenario, string $key): int
{
    assert_user($userId);
    if (!in_array($method,['card','bank','reception'],true) || !in_array($scenario,['success','failure','pending'],true)) throw new RuntimeException('payment_invalid',422);
    if (!preg_match('/^[A-Za-z0-9_-]{16,100}$/',$key)) throw new RuntimeException('validation',422);
    return transaction(function () use ($userId,$planId,$method,$scenario,$key): int {
        // A per-user lock makes the idempotency check and first insertion atomic.
        $user = fetch_one('SELECT id,active FROM users WHERE id=? FOR UPDATE',[$userId]);
        if (!$user || !$user['active']) throw new RuntimeException('account_inactive',403);
        $existing = fetch_one('SELECT o.id,o.method,i.plan_id FROM orders o JOIN order_items i ON i.order_id=o.id WHERE o.user_id=? AND o.idempotency_key=?',[$userId,$key]);
        if ($existing) {
            if ((int)$existing['plan_id'] !== $planId || $existing['method'] !== $method) throw new RuntimeException('checkout_conflict',409);
            return (int)$existing['id'];
        }
        $plan = fetch_one('SELECT p.*,tr.title FROM membership_plans p JOIN membership_plan_translations tr ON tr.plan_id=p.id AND tr.language=\'en\' WHERE p.id=? AND p.active=1 FOR UPDATE',[$planId]);
        if (!$plan) throw new RuntimeException('plan_unavailable',422);
        // Transfer/reception cannot activate without an administrator's explicit confirmation.
        if ($method !== 'card' && $scenario === 'success') $scenario = 'pending';
        $status = ['success'=>'paid','failure'=>'failed','pending'=>'pending'][$scenario];
        execute('INSERT INTO orders(reference,user_id,idempotency_key,total,status,method) VALUES(?,?,?,?,?,?)',[reference('FFO'),$userId,$key,$plan['price'],$status,$method]);
        $orderId = (int)db()->lastInsertId();
        execute('INSERT INTO order_items(order_id,plan_id,title_snapshot,price_snapshot,duration_days,kind,access_start,access_end) VALUES(?,?,?,?,?,?,?,?)',[$orderId,$planId,$plan['title'],$plan['price'],$plan['duration_days'],$plan['kind'],$plan['access_start'],$plan['access_end']]);
        execute('INSERT INTO demo_payments(order_id,status,scenario) VALUES(?,?,?)',[$orderId,['success'=>'success','failure'=>'failed','pending'=>'pending'][$scenario],$scenario]);
        if ($status === 'pending') execute('INSERT INTO memberships(user_id,order_id,plan_id,status) VALUES(?,?,?,\'pending\')',[$userId,$orderId,$planId]);
        if ($status === 'paid') activate_order($orderId);
        return $orderId;
    });
}
/** Internal: caller must hold the order lock / creating transaction. */
function activate_order(int $orderId): void
{
    $order = fetch_one('SELECT o.*,i.plan_id,i.duration_days FROM orders o JOIN order_items i ON i.order_id=o.id WHERE o.id=?',[$orderId]);
    if (!$order || $order['status'] !== 'paid') throw new RuntimeException('order_transition',409);
    $membership = fetch_one('SELECT * FROM memberships WHERE order_id=? FOR UPDATE',[$orderId]);
    if ($membership && $membership['status'] === 'active') return;
    $starts = gmdate('Y-m-d H:i:s'); $ends = gmdate('Y-m-d H:i:s', time() + (int)$order['duration_days'] * 86400);
    if ($membership) { execute('UPDATE memberships SET status=\'active\',starts_at=?,ends_at=? WHERE id=?',[$starts,$ends,$membership['id']]); $memberId = (int)$membership['id']; }
    else { execute('INSERT INTO memberships(user_id,order_id,plan_id,status,starts_at,ends_at) VALUES(?,?,?,\'active\',?,?)',[$order['user_id'],$orderId,$order['plan_id'],$starts,$ends]); $memberId = (int)db()->lastInsertId(); }
    execute('INSERT IGNORE INTO passes(membership_id,token,identifier) VALUES(?,?,?)',[$memberId,bin2hex(random_bytes(32)),reference('DEMO')]);
}
function confirm_order(int $adminId, int $orderId, string $status): void
{
    assert_admin($adminId);
    if ($status === 'success') $status = 'paid';
    if (!in_array($status,['paid','failed','cancelled'],true)) throw new RuntimeException('order_transition',422);
    transaction(function () use ($adminId,$orderId,$status): void {
        $order = fetch_one('SELECT * FROM orders WHERE id=? FOR UPDATE',[$orderId]);
        if (!$order) throw new RuntimeException('not_found',404);
        if ($order['status'] === $status) return;
        if ($order['status'] !== 'pending') throw new RuntimeException('order_transition',409);
        execute('UPDATE orders SET status=? WHERE id=?',[$status,$orderId]);
        execute('UPDATE demo_payments SET status=?,confirmed_by=?,confirmed_at=UTC_TIMESTAMP() WHERE order_id=?',[$status === 'paid' ? 'success' : $status,$adminId,$orderId]);
        if ($status === 'paid') activate_order($orderId);
        else execute('UPDATE memberships SET status=\'cancelled\' WHERE order_id=? AND status=\'pending\'',[$orderId]);
        audit($adminId,'order_status','order',$orderId,['from'=>$order['status'],'to'=>$status]);
    });
}
function effective_access(array $plan, int $weekday): ?array
{
    if (empty($plan['access_start']) || empty($plan['access_end'])) return null;
    $hours = $weekday === 6 ? ['08:00:00','22:00:00'] : ($weekday === 7 ? ['09:00:00','21:00:00'] : ['06:00:00','22:00:00']);
    $configured=json_decode(site_setting('opening_hours','{}'),true);
    $key=$weekday===6?'saturday':($weekday===7?'sunday':'monday_friday');
    if (isset($configured[$key]) && preg_match('/^([0-2][0-9]:[0-5][0-9])[–-]([0-2][0-9]:[0-5][0-9])$/u',$configured[$key],$parts)) $hours=[$parts[1].':00',$parts[2].':00'];
    return [max($plan['access_start'],$hours[0]),min($plan['access_end'],$hours[1])];
}
function booking_eligible(int $userId, array $session): bool
{
    if (!(bool)$session['requires_membership']) return true;
    $memberships = fetch_all('SELECT m.*,i.access_start,i.access_end FROM memberships m JOIN order_items i ON i.order_id=m.order_id WHERE m.user_id=? AND m.status=\'active\' AND m.starts_at<=? AND m.ends_at>=?',[$userId,$session['starts_at'],$session['ends_at']]);
    $start = new DateTimeImmutable($session['starts_at'],new DateTimeZone('UTC')); $start = $start->setTimezone(new DateTimeZone('Europe/Skopje'));
    $end = (new DateTimeImmutable($session['ends_at'],new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Europe/Skopje'));
    foreach ($memberships as $membership) {
        $window = effective_access($membership,(int)$start->format('N'));
        // Unspecified day/tourist access is not invented; demo eligibility is a separate project rule.
        if ($window === null || ($start->format('Y-m-d') === $end->format('Y-m-d') && $start->format('H:i:s') >= $window[0] && $end->format('H:i:s') <= $window[1])) return true;
    }
    return false;
}
function reserve_class(int $userId, int $sessionId): int
{
    assert_user($userId);
    return transaction(function () use ($userId,$sessionId): int {
        // Every reservation, cancellation and session edit locks this row first.
        $session = fetch_one('SELECT s.*,c.published,c.archived FROM class_sessions s JOIN content c ON c.id=s.class_type_id WHERE s.id=? FOR UPDATE',[$sessionId]);
        if (!$session || !$session['published'] || $session['archived'] || $session['status'] !== 'scheduled') throw new RuntimeException('session_unavailable',409);
        if ($session['starts_at'] <= gmdate('Y-m-d H:i:s')) throw new RuntimeException('session_past',409);
        $existing = fetch_one('SELECT * FROM bookings WHERE user_id=? AND session_id=? FOR UPDATE',[$userId,$sessionId]);
        if ($existing && $existing['status'] !== 'cancelled') throw new RuntimeException('already_booked',409);
        if (!booking_eligible($userId,$session)) throw new RuntimeException('membership_required',422);
        $count = (int)fetch_one('SELECT COUNT(*) total FROM bookings WHERE session_id=? AND status IN (\'confirmed\',\'attended\',\'no_show\')',[$sessionId])['total'];
        if ($count >= (int)$session['capacity']) throw new RuntimeException('class_full',409);
        if ($existing) { execute('UPDATE bookings SET status=\'confirmed\',cancellation_reason=\'\',created_at=UTC_TIMESTAMP() WHERE id=?',[$existing['id']]); return (int)$existing['id']; }
        execute('INSERT INTO bookings(reference,user_id,session_id) VALUES(?,?,?)',[reference('FFB'),$userId,$sessionId]);
        return (int)db()->lastInsertId();
    });
}
function cancel_booking(int $userId, int $bookingId): void
{
    assert_user($userId); $booking = fetch_one('SELECT session_id FROM bookings WHERE id=? AND user_id=?',[$bookingId,$userId]);
    if (!$booking) throw new RuntimeException('not_found',404);
    transaction(function () use ($userId,$bookingId,$booking): void {
        $session = fetch_one('SELECT * FROM class_sessions WHERE id=? FOR UPDATE',[$booking['session_id']]);
        $row = fetch_one('SELECT * FROM bookings WHERE id=? AND user_id=? FOR UPDATE',[$bookingId,$userId]);
        if (!$row || $row['status'] !== 'confirmed') throw new RuntimeException('cancellation_closed',409);
        if (strtotime($session['starts_at'] . ' UTC') - (int)$session['cancellation_minutes']*60 <= time()) throw new RuntimeException('cancellation_closed',409);
        execute('UPDATE bookings SET status=\'cancelled\',cancellation_reason=\'member_cancelled\' WHERE id=?',[$bookingId]);
    });
}
function toggle_favorite(int $userId, int $contentId): bool
{
    assert_user($userId); $item = content_item($contentId);
    if (!$item || !in_array($item['kind'],['service','trainer','class_type'],true)) throw new RuntimeException('not_found',404);
    return transaction(function () use ($userId,$contentId): bool {
        fetch_one('SELECT id FROM users WHERE id=? FOR UPDATE',[$userId]);
        if (fetch_one('SELECT user_id FROM favorites WHERE user_id=? AND content_id=?',[$userId,$contentId])) { execute('DELETE FROM favorites WHERE user_id=? AND content_id=?',[$userId,$contentId]); return false; }
        execute('INSERT INTO favorites(user_id,content_id) VALUES(?,?)',[$userId,$contentId]); return true;
    });
}
function subscribe_newsletter(string $email, bool $consent): array
{
    rate_limit('newsletter',10,3600); $email = valid_email($email);
    if (!$consent) throw new RuntimeException('consent_required',422);
    $existing = fetch_one('SELECT id,status FROM newsletter_subscribers WHERE email=?',[$email]);
    if ($existing && $existing['status'] === 'subscribed') throw new RuntimeException('already_subscribed',409);
    $token = bin2hex(random_bytes(32));
    if ($existing) execute('UPDATE newsletter_subscribers SET status=\'subscribed\',token=?,consent_at=UTC_TIMESTAMP(),unsubscribed_at=NULL WHERE id=?',[$token,$existing['id']]);
    else {
        try { execute('INSERT INTO newsletter_subscribers(email,token,consent_at) VALUES(?,?,UTC_TIMESTAMP())',[$email,$token]); }
        catch (PDOException $error) { if ((int)($error->errorInfo[1] ?? 0) === 1062) throw new RuntimeException('already_subscribed',409); throw $error; }
    }
    return ['status'=>'subscribed','token'=>$token];
}
function unsubscribe_newsletter(string $token): void
{
    if (!preg_match('/^[a-f0-9]{64}$/',$token)) throw new RuntimeException('token_invalid',422);
    $row = fetch_one('SELECT id FROM newsletter_subscribers WHERE token=?',[$token]);
    if (!$row) throw new RuntimeException('token_invalid',404);
    execute('UPDATE newsletter_subscribers SET status=\'unsubscribed\',unsubscribed_at=UTC_TIMESTAMP() WHERE id=?',[$row['id']]);
}
function set_newsletter_preference(int $userId, bool $subscribed): void
{
    $user = assert_user($userId); $row = fetch_one('SELECT * FROM newsletter_subscribers WHERE email=?',[$user['email']]);
    if ($subscribed && (!$row || $row['status'] !== 'subscribed')) subscribe_newsletter($user['email'],true);
    elseif (!$subscribed && $row) unsubscribe_newsletter($row['token']);
}
function save_contact(array $data): int
{
    rate_limit('contact',8,3600);
    if (!empty($data['website'])) throw new RuntimeException('validation',422);
    $name = text_input($data['name'] ?? '',2,100); $email = valid_email($data['email'] ?? '');
    $subject = text_input($data['subject'] ?? '',3,160); $message = text_input($data['message'] ?? '',10,5000);
    execute('INSERT INTO contact_messages(name,email,subject,message) VALUES(?,?,?,?)',[$name,$email,$subject,$message]); return (int)db()->lastInsertId();
}
function user_orders(int $userId): array { return fetch_all('SELECT o.*,i.title_snapshot title,i.plan_id,p.status payment_status FROM orders o JOIN order_items i ON i.order_id=o.id JOIN demo_payments p ON p.order_id=o.id WHERE o.user_id=? ORDER BY o.created_at DESC,o.id DESC',[$userId]); }
function order_item(int $id, int $userId): ?array { return fetch_one('SELECT o.*,i.title_snapshot title,i.plan_id,i.duration_days,i.access_start,i.access_end,i.kind,p.status payment_status FROM orders o JOIN order_items i ON i.order_id=o.id JOIN demo_payments p ON p.order_id=o.id WHERE o.id=? AND o.user_id=?',[$id,$userId]); }
function user_memberships(int $userId): array
{
    return fetch_all('SELECT m.*,CASE WHEN m.status=\'active\' AND m.ends_at<UTC_TIMESTAMP() THEN \'expired\' ELSE m.status END effective_status,i.title_snapshot title,i.access_start,i.access_end,i.kind,o.reference order_reference,p.token,p.identifier FROM memberships m JOIN order_items i ON i.order_id=m.order_id JOIN orders o ON o.id=m.order_id LEFT JOIN passes p ON p.membership_id=m.id WHERE m.user_id=? ORDER BY m.id DESC',[$userId]);
}
function user_bookings(int $userId): array
{
    return fetch_all('SELECT b.*,s.starts_at,s.ends_at,s.status session_status,s.change_note,s.cancellation_minutes,s.original_starts_at,r.name room,COALESCE(tr.title,en.title) title,COALESCE(tt.title,te.title) trainer FROM bookings b JOIN class_sessions s ON s.id=b.session_id JOIN rooms r ON r.id=s.room_id JOIN content_translations en ON en.content_id=s.class_type_id AND en.language=\'en\' LEFT JOIN content_translations tr ON tr.content_id=s.class_type_id AND tr.language=? LEFT JOIN content_translations te ON te.content_id=s.trainer_id AND te.language=\'en\' LEFT JOIN content_translations tt ON tt.content_id=s.trainer_id AND tt.language=? WHERE b.user_id=? ORDER BY s.starts_at DESC',[lang(),lang(),$userId]);
}
function booking_item(int $id, int $userId): ?array { foreach (user_bookings($userId) as $row) if ((int)$row['id'] === $id) return $row; return null; }
function user_favorites(int $userId): array { return array_map('decode_content',fetch_all(content_select() . ' JOIN favorites f ON f.content_id=c.id WHERE f.user_id=? AND c.published=1 AND c.archived=0 ORDER BY f.created_at DESC',[lang(),$userId])); }
function related_services(int $trainerId): array { return array_map('decode_content',fetch_all(content_select().' JOIN trainer_services ts ON ts.service_id=c.id WHERE ts.trainer_id=? AND c.published=1 AND c.archived=0',[lang(),$trainerId])); }
function related_trainers(int $serviceId): array { return array_map('decode_content',fetch_all(content_select().' JOIN trainer_services ts ON ts.trainer_id=c.id WHERE ts.service_id=? AND c.published=1 AND c.archived=0',[lang(),$serviceId])); }
function verify_pass(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/',$token)) return null;
    return fetch_one('SELECT p.identifier,CASE WHEN m.status=\'active\' AND m.ends_at<UTC_TIMESTAMP() THEN \'expired\' ELSE m.status END status,m.starts_at,m.ends_at FROM passes p JOIN memberships m ON m.id=p.membership_id JOIN users u ON u.id=m.user_id WHERE p.token=? AND u.active=1',[$token]);
}
