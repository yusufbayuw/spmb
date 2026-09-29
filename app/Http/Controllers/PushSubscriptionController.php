<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PushSubscriptionController extends Controller
{
    public function publicKey(): JsonResponse
    {
        $publicKey = config('webpush.vapid.public_key');

        abort_if(blank($publicKey), 503, 'Web Push belum dikonfigurasi.');

        return response()->json(['publicKey' => $publicKey]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url:http,https', 'max:1024'],
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
        $data = $request->validate([
            'endpoint' => ['required', 'url:http,https', 'max:1024'],
        ]);

        $request->user()->deletePushSubscription($data['endpoint']);

        return response()->json(null, 204);
    }
}
