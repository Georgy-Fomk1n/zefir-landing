<?php

namespace App\Http\Requests;

trait CartLinesRules
{
    protected function lineRules(): array
    {
        return [
            'lines' => ['present', 'array', 'max:50'],
            'lines.*.variantId' => ['required', 'integer', 'min:1'],
            'lines.*.qty' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
