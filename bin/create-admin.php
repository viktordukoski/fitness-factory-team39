<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
try {
    $options=getopt('',['email:','name:']);
    $email=valid_email($options['email'] ?? ''); $name=text_input($options['name'] ?? 'Administrator',2,100);
    // Passwords must never be command arguments. Use a private stdin stream or
    // a temporary ADMIN_INITIAL_PASSWORD environment value and remove it after use.
    $password=env('ADMIN_INITIAL_PASSWORD');
    if ($password==='') {
        if (function_exists('stream_isatty') && stream_isatty(STDIN)) throw new RuntimeException('Supply the initial password through a private stdin stream or ADMIN_INITIAL_PASSWORD, never as a command argument.');
        $password=rtrim(stream_get_contents(STDIN),"\r\n");
    }
    validate_password($password);
    if (fetch_one('SELECT id FROM users WHERE email=?',[$email])) throw new RuntimeException('That email already exists. Use the account settings to change its password; this command never overwrites accounts.');
    execute('INSERT INTO users(role_id,name,email,password_hash,is_demo,active) VALUES(1,?,?,?,0,1)',[$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
    unset($password);fwrite(STDOUT,"Administrator created. No credentials were printed.\n");
} catch (Throwable $error) {
    fwrite(STDERR,$error instanceof PDOException ? "Administrator creation failed. Check private database configuration.\n" : $error->getMessage()."\n");exit(1);
}
