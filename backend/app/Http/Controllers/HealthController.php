<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('select 1');
            $db = true;
        } catch (Throwable) {
            $db = false;
        }
        $queueLag = DB::table('jobs')->min('available_at');

        return response()->json([
            'status' => $db ? 'ok' : 'degraded',
            'db' => $db,
            'queueOldestSeconds' => $queueLag ? max(0, time() - (int) $queueLag) : 0,
            'version' => config('app.version'),
        ], $db ? 200 : 503);
    }
}
