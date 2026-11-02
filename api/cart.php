<?php
declare(strict_types=1);

/*
 * Корзина по HTTP.
 *   GET  api/cart.php                       — текущее состояние
 *   POST api/cart.php  (JSON, X-CSRF-Token) — {action: add|set|card|clear, ...} → новое состояние
 */
require __DIR__ . '/../app/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function out(array $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    out(['ok' => true, 'cart' => cart_state()]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out(['ok' => false, 'error' => 'method'], 405);
}
if (!csrf_ok($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    out(['ok' => false, 'error' => 'csrf'], 403);
}

$in = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($in)) {
    out(['ok' => false, 'error' => 'json'], 400);
}

$id = (string) ($in['id'] ?? '');
$vid = (string) ($in['v'] ?? '');
$q = (int) ($in['q'] ?? 1);

switch ($in['action'] ?? '') {
    case 'add':
        if (!cart_add($id, $vid, $q)) {
            out(['ok' => false, 'error' => 'not_buyable'], 422);
        }
        if (isset($in['card_text']) && trim((string) $in['card_text']) !== '') {
            cart_set_card(true, (string) $in['card_text']);
        }
        break;
    case 'set':
        cart_set($id, $vid, $q);
        break;
    case 'card':
        cart_set_card(!empty($in['on']), (string) ($in['text'] ?? ''));
        break;
    case 'clear':
        cart_clear();
        break;
    default:
        out(['ok' => false, 'error' => 'action'], 400);
}

out(['ok' => true, 'cart' => cart_state()]);
