<?php
declare(strict_types=1);

/* Заказы: SQLite-файл (data/shop.sqlite) + уведомление в Telegram и/или на почту. */

function db(): PDO
{
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }
    $path = (string) cfg('db_path');
    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0775, true);
    }
    $pdo = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('CREATE TABLE IF NOT EXISTS orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        created_at TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT \'new\',
        name TEXT NOT NULL, phone TEXT NOT NULL,
        shipping TEXT NOT NULL, address TEXT NOT NULL,
        slot_date TEXT NOT NULL, slot_hour INTEGER NOT NULL,
        comment TEXT NOT NULL DEFAULT \'\',
        card_text TEXT NOT NULL DEFAULT \'\',
        items TEXT NOT NULL, subtotal INTEGER NOT NULL, fee INTEGER NOT NULL, total INTEGER NOT NULL
    )');
    return $pdo;
}

function order_number(int $id): string
{
    return (string) (1000 + $id);
}

/** Сохраняет заказ и возвращает его id. Бросает исключение, если запись не удалась. */
function order_create(array $o): int
{
    $st = db()->prepare('INSERT INTO orders (created_at, name, phone, shipping, address, slot_date, slot_hour, comment, card_text, items, subtotal, fee, total)
        VALUES (:created_at, :name, :phone, :shipping, :address, :slot_date, :slot_hour, :comment, :card_text, :items, :subtotal, :fee, :total)');
    $st->execute([
        ':created_at' => date('c'), ':name' => $o['name'], ':phone' => $o['phone'], ':shipping' => $o['shipping'], ':address' => $o['address'],
        ':slot_date' => $o['date'], ':slot_hour' => $o['hour'], ':comment' => $o['comment'], ':card_text' => $o['card_text'],
        ':items' => json_encode($o['items'], JSON_UNESCAPED_UNICODE), ':subtotal' => $o['subtotal'], ':fee' => $o['fee'], ':total' => $o['total'],
    ]);
    return (int) db()->lastInsertId();
}

function order_get(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM orders WHERE id = ?');
    $st->execute([$id]);
    $r = $st->fetch();
    if (!$r) {
        return null;
    }
    $r['items'] = json_decode($r['items'], true) ?: [];
    return $r;
}

function shop_log(string $msg): void
{
    @file_put_contents(ROOT . '/data/error.log', '[' . date('c') . '] ' . $msg . "\n", FILE_APPEND);
}

/** Текст заказа для сообщений */
function order_text(array $o, int $id): string
{
    $lines = ['Новый заказ № ' . order_number($id), ''];
    foreach ($o['items'] as $it) {
        $lines[] = '• ' . $it['name'] . ' × ' . $it['q'] . ' = ' . $it['sum'] . ' ₽';
    }
    if ($o['card_text'] !== '') {
        $lines[] = 'Открытка: ' . $o['card_text'];
    }
    $lines[] = '';
    $lines[] = ($o['shipping'] === 'courier' ? 'Курьер, ' : 'Самовывоз, ') . $o['address'];
    $lines[] = 'Когда: ' . day_label($o['date']) . ', ' . $o['hour'] . ':00–' . ($o['hour'] + 1) . ':00';
    $lines[] = 'Кто: ' . $o['name'] . ', ' . $o['phone'];
    if ($o['comment'] !== '') {
        $lines[] = 'Комментарий: ' . $o['comment'];
    }
    $lines[] = 'Итого: ' . $o['total'] . ' ₽' . ($o['fee'] ? ' (с доставкой ' . $o['fee'] . ' ₽)' : '');
    return implode("\n", $lines);
}

/** Уведомляет кондитерскую. Ошибки пишем в лог и не роняем оформление: заказ уже сохранён. */
function order_notify(array $o, int $id): void
{
    $text = order_text($o, $id);

    $token = (string) cfg('notify.telegram_token');
    $chat = (string) cfg('notify.telegram_chat_id');
    if ($token !== '' && $chat !== '' && function_exists('curl_init')) {
        $ch = curl_init('https://api.telegram.org/bot' . $token . '/sendMessage');
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => ['chat_id' => $chat, 'text' => $text], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 6, CURLOPT_CONNECTTIMEOUT => 4]);
        $res = curl_exec($ch);
        if ($res === false || curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200) {
            shop_log('Telegram: ' . ($res === false ? curl_error($ch) : $res));
        }
        curl_close($ch);
    }

    $to = (string) cfg('notify.mail_to');
    if ($to !== '') {
        $from = (string) cfg('notify.mail_from');
        $ok = @mail($to, '=?UTF-8?B?' . base64_encode('Новый заказ № ' . order_number($id)) . '?=', $text,
            "From: $from\r\nContent-Type: text/plain; charset=UTF-8\r\n");
        if (!$ok) {
            shop_log('mail() не отправил письмо о заказе ' . order_number($id));
        }
    }
}
