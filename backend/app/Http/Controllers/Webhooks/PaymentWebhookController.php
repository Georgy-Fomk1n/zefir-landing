<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\InvalidWebhook;
use App\Services\Payments\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentService $payments): Response
    {
        try {
            $payments->handleWebhook($request);
        } catch (InvalidWebhook $e) {
            Log::warning('payment webhook rejected', ['reason' => $e->getMessage(), 'ip' => $request->ip()]);

            return response('invalid', 400);
        }

        return response('ok', 200);
    }

    /** Страница фейковой оплаты (только не-production): «оплатить» или «отклонить». */
    public function fake(Request $request, Payment $payment, PaymentService $payments): RedirectResponse|Response
    {
        abort_if(app()->isProduction(), 404);
        abort_unless($request->hasValidSignatureWhileIgnoring(['action']), 403);
        $action = $request->query('action');
        if (! $action) {
            $html = '<!doctype html><meta charset="utf-8"><title>Тестовая оплата</title><body style="font-family:sans-serif;padding:40px">'
                .'<h1>Тестовая оплата: '.number_format($payment->amount / 100, 2, ',', ' ').' ₽</h1>'
                .'<p><a href="'.e($request->fullUrlWithQuery(['action' => 'pay'])).'">Оплатить</a> · '
                .'<a href="'.e($request->fullUrlWithQuery(['action' => 'decline'])).'">Отклонить</a></p></body>';

            return response($html)->header('X-Robots-Tag', 'noindex');
        }
        $status = $action === 'pay' ? PaymentStatus::Paid : PaymentStatus::Failed;
        $payments->apply($payment, $status, $request->boolean('save') ? 'fake_card_'.$payment->id : null, $request->boolean('save') ? 'Карта •• 4242' : null);

        return redirect()->away($status === PaymentStatus::Paid ? (string) $request->query('success') : (string) $request->query('fail'));
    }
}
