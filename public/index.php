<?php
declare(strict_types=1);
try {
    require dirname(__DIR__) . '/app/bootstrap.php';
    require APP_ROOT . '/app/http.php';
    dispatch();
} catch (Throwable $exception) {
    error_log('Fitness Factory request failed: ' . get_class($exception));
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Temporarily unavailable · Fitness Factory</title><body style="background:#111;color:#fff;font:18px system-ui;padding:8vw;max-width:700px"><p>FITNESS FACTORY · UNIVERSITY DEMO</p><h1>We could not connect right now.</h1><p>The project database may be starting. Please try again shortly.</p><a style="color:#ffd000" href="">Try again</a></body></html>';
}
