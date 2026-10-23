<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Пост-деплой хук (хостинг только с FTP, SSH нет): CI после загрузки файлов дергает
 * POST /api/_deploy/run с заголовком X-Deploy-Token. Защита: hash_equals, опционально IP,
 * rate limit 3/мин, выключается DEPLOY_HOOK_ENABLED=false. Ответ — без деталей ошибок.
 */
class DeployHookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $cfg = config('shop.deploy_hook');
        if (! $cfg['enabled'] || ! $cfg['token']) {
            abort(404);
        }
        if ($cfg['allowed_ips'] !== [] && ! in_array($request->ip(), $cfg['allowed_ips'], true)) {
            Log::warning('deploy hook: ip denied', ['ip' => $request->ip()]);
            abort(404);
        }
        if (! hash_equals((string) $cfg['token'], (string) $request->header('X-Deploy-Token'))) {
            Log::warning('deploy hook: bad token', ['ip' => $request->ip()]);
            abort(404);
        }

        $steps = [];
        $ok = true;
        try {
            Artisan::call('down', ['--retry' => 15]);
            $steps[] = 'down';
            foreach ([['migrate', ['--force' => true]], ['config:cache', []], ['route:cache', []], ['view:cache', []], ['event:cache', []]] as [$cmd, $args]) {
                Artisan::call($cmd, $args);
                $steps[] = $cmd;
            }
        } catch (Throwable $e) {
            $ok = false;
            Log::error('deploy hook failed', ['step' => end($steps) ?: 'start', 'error' => $e->getMessage()]);
        } finally {
            Artisan::call('up');
        }
        Log::info('deploy hook', ['ok' => $ok, 'steps' => $steps, 'release' => $request->header('X-Release')]);

        return response()->json(['ok' => $ok], $ok ? 200 : 500);
    }
}
