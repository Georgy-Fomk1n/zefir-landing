<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ShopAdmin extends Command
{
    protected $signature = 'shop:admin {email} {--name=Администратор} {--password= : Если не указан, спросим}';

    protected $description = 'Создаёт (или обновляет пароль) пользователя админки';

    public function handle(): int
    {
        $password = $this->option('password') ?: $this->secret('Пароль (не короче 10 символов)');
        if (! $password || mb_strlen($password) < 10) {
            $this->error('Пароль слишком короткий.');

            return self::FAILURE;
        }
        $u = User::updateOrCreate(['email' => $this->argument('email')], ['name' => $this->option('name'), 'password' => Hash::make($password)]);
        $this->info("Готово: {$u->email} может войти на /admin");

        return self::SUCCESS;
    }
}
