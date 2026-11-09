<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class InvalidTransition extends RuntimeException
{
    public function __construct(public string $machine, public string $from, public string $to)
    {
        parent::__construct("Переход {$from} → {$to} недопустим");
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }
}
