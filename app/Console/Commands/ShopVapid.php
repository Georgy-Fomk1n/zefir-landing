<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class ShopVapid extends Command
{
    protected $signature = 'shop:vapid {--force : Перегенерировать, даже если ключи уже есть}';

    protected $description = 'Создаёт VAPID-ключи для push-уведомлений и записывает их в .env';

    public function handle(): int
    {
        $env = base_path('.env');
        $text = is_file($env) ? file_get_contents($env) : '';
        if (! $this->option('force') && preg_match('/^VAPID_PUBLIC_KEY=.+$/m', $text)) {
            $this->warn('Ключи уже есть. Чтобы заменить их (все подписки перестанут работать), добавьте --force.');

            return self::SUCCESS;
        }
        $keys = VAPID::createVapidKeys();
        foreach (['VAPID_PUBLIC_KEY' => $keys['publicKey'], 'VAPID_PRIVATE_KEY' => $keys['privateKey']] as $k => $v) {
            $text = preg_match("/^$k=.*$/m", $text) ? preg_replace("/^$k=.*$/m", "$k=$v", $text) : rtrim($text) . "\n$k=$v\n";
        }
        file_put_contents($env, $text);
        $this->info('Готово: VAPID_PUBLIC_KEY и VAPID_PRIVATE_KEY записаны в .env. Если конфиг закэширован, выполните php artisan config:cache.');

        return self::SUCCESS;
    }
}
