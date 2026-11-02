<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));

/** Настройки: config.php, если есть, иначе значения по умолчанию из config.example.php */
function cfg(?string $path = null)
{
    static $c = null;
    if ($c === null) {
        $file = is_file(ROOT . '/config.php') ? ROOT . '/config.php' : ROOT . '/config.example.php';
        $c = require $file;
    }
    if ($path === null) {
        return $c;
    }
    $v = $c;
    foreach (explode('.', $path) as $k) {
        if (!is_array($v) || !array_key_exists($k, $v)) {
            return null;
        }
        $v = $v[$k];
    }
    return $v;
}

date_default_timezone_set((string) (cfg('timezone') ?: 'Europe/Moscow'));

if (session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params(['lifetime' => 60 * 60 * 24 * 30, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => $https]);
    session_name('zp_sid');
    session_start();
}

require_once __DIR__ . '/catalog.php';
require_once __DIR__ . '/cart.php';
require_once __DIR__ . '/schedule.php';
require_once __DIR__ . '/orders.php';
require_once __DIR__ . '/layout.php';

/** Экранирование для HTML */
function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Цена: 1 050 ₽ (неразрывные пробелы) */
function rub(int $n): string
{
    return number_format($n, 0, '', "\u{00A0}") . "\u{00A0}₽";
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_ok(?string $t): bool
{
    return is_string($t) && $t !== '' && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}

function redirect(string $to, int $code = 303): never
{
    header('Location: ' . $to, true, $code);
    exit;
}
