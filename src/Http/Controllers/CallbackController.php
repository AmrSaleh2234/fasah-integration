<?php

namespace AmrSaleh\FasahPay\Http\Controllers;

use AmrSaleh\FasahPay\Events\InvoiceNotificationReceived;
use AmrSaleh\FasahPay\Events\SettlementNotificationReceived;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

class CallbackController extends Controller
{
    public function invoice(Request $request): JsonResponse
    {
        if (! $this->authorized($request)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $this->dispatchAll([$request->all()], fn (array $p) => event(new InvoiceNotificationReceived($p)));
    }

    public function settlement(Request $request): JsonResponse
    {
        if (! $this->authorized($request)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $body = $request->all();
        $payloads = array_is_list($body) ? $body : [$body];

        return $this->dispatchAll($payloads, fn (array $p) => event(new SettlementNotificationReceived($p)));
    }

    /**
     * @param  list<mixed>  $payloads
     * @param  callable(array<string, mixed>): mixed  $dispatch
     */
    private function dispatchAll(array $payloads, callable $dispatch): JsonResponse
    {
        try {
            foreach ($payloads as $payload) {
                if (is_array($payload)) {
                    $dispatch($payload);
                }
            }
        } catch (Throwable $e) {
            report($e);

            return response()->json(['statusCode' => '00001', 'statusDesc' => 'Processing failed'], 503);
        }

        return response()->json(['statusCode' => '00000', 'statusDesc' => 'Received']);
    }

    private function authorized(Request $request): bool
    {
        $secret = config('fasahpay.callbacks.secret');

        if (! $secret) {
            return true;
        }

        return hash_equals((string) $secret, (string) $request->header('X-Callback-Secret', $request->query('secret', '')));
    }
}
