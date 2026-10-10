<?php

namespace App\Http\Controllers;

use App\Services\PushEndpointPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PushSubscriptionController extends Controller
{
    public function publicKey(): JsonResponse
    {
        abort_unless(auth()->user()?->is_active, 403);
        $publicKey = config('webpush.vapid.public_key');

        abort_if(blank($publicKey), 503, 'Web Push belum dikonfigurasi.');

        return response()->json(['publicKey' => $publicKey]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->is_active, 403);
        $data = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:1024', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! app(PushEndpointPolicy::class)->allows((string) $value)) {
                    $fail('Endpoint push harus HTTPS dan menggunakan penyedia push tepercaya.');
                }
            }],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string', 'max:512'],
            'keys.auth' => ['required', 'string', 'max:512'],
            'contentEncoding' => ['nullable', 'string', Rule::in(['aes128gcm', 'aesgcm'])],
        ]);

        $request->user()->updatePushSubscription(
            $data['endpoint'],
            $data['keys']['p256dh'],
            $data['keys']['auth'],
            $data['contentEncoding'] ?? 'aes128gcm',
        );

        return response()->json(['subscribed' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        abort_unless($request->user()?->is_active, 403);
        $data = $request->validate([
            'endpoint' => ['required', 'url:http,https', 'max:1024'],
        ]);

        $request->user()->deletePushSubscription($data['endpoint']);

        return response()->json(null, 204);
    }
}
