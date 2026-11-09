<?php

namespace App\Jobs;

use App\Services\Catalog\StaticRebuild;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * POST /repos/{owner}/{repo}/dispatches. Токен — fine-grained PAT только на этот репозиторий
 * с правом «Contents: read & write» (минимум, который требует repository_dispatch).
 */
class DispatchStaticRebuild implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function handle(StaticRebuild $rebuild): void
    {
        $paths = $rebuild->pullPending();
        if ($paths === []) {
            return;
        }
        $repo = config('shop.rebuild.repository');
        $res = Http::withToken((string) config('shop.rebuild.token'))
            ->withHeaders(['Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28'])
            ->timeout(15)
            ->post("https://api.github.com/repos/{$repo}/dispatches", [
                'event_type' => config('shop.rebuild.event'),
                'client_payload' => [
                    'environment' => config('shop.rebuild.environment'),
                    'paths' => array_slice($paths, 0, 500),
                ],
            ]);
        if (! $res->successful()) {
            Log::warning('static rebuild dispatch failed', ['status' => $res->status()]);
            $rebuild->request($paths);
            $res->throw();
        }
    }
}
