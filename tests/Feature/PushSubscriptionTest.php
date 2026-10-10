<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('webpush.vapid.public_key', 'test-vapid-public-key');
        config()->set('webpush.vapid.private_key', 'test-vapid-private-key');
    }

    public function test_subscription_endpoints_require_authentication(): void
    {
        $this->get(route('push.vapid-public-key'))->assertRedirect(route('login'));
        $this->postJson(route('push.subscriptions.store'), [])->assertUnauthorized();
        $this->deleteJson(route('push.subscriptions.destroy'), [])->assertUnauthorized();
    }

    public function test_user_can_register_multiple_devices_and_update_an_existing_endpoint(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('push.vapid-public-key'))
            ->assertOk()
            ->assertExactJson(['publicKey' => 'test-vapid-public-key']);

        $this->actingAs($user)
            ->postJson(route('push.subscriptions.store'), $this->payload('device-a'))
            ->assertOk()
            ->assertJson(['subscribed' => true]);

        $this->actingAs($user)
            ->postJson(route('push.subscriptions.store'), $this->payload('device-b'))
            ->assertOk();

        $updated = $this->payload('device-a');
        $updated['keys']['auth'] = 'updated-auth-token';

        $this->actingAs($user)
            ->postJson(route('push.subscriptions.store'), $updated)
            ->assertOk();

        $this->assertCount(2, $user->fresh()->pushSubscriptions);
        $this->assertDatabaseHas('push_subscriptions', [
            'endpoint' => $updated['endpoint'],
            'auth_token' => 'updated-auth-token',
        ]);
    }

    public function test_endpoint_moves_to_current_user_and_unsubscribe_only_removes_owned_endpoint(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $payload = $this->payload('shared-device');

        $this->actingAs($firstUser)
            ->postJson(route('push.subscriptions.store'), $payload)
            ->assertOk();

        $this->actingAs($secondUser)
            ->deleteJson(route('push.subscriptions.destroy'), ['endpoint' => $payload['endpoint']])
            ->assertNoContent();

        $this->assertDatabaseHas('push_subscriptions', [
            'endpoint' => $payload['endpoint'],
            'subscribable_id' => $firstUser->getKey(),
        ]);

        $this->actingAs($secondUser)
            ->postJson(route('push.subscriptions.store'), $payload)
            ->assertOk();

        $this->assertDatabaseHas('push_subscriptions', [
            'endpoint' => $payload['endpoint'],
            'subscribable_id' => $secondUser->getKey(),
        ]);
        $this->assertDatabaseMissing('push_subscriptions', [
            'endpoint' => $payload['endpoint'],
            'subscribable_id' => $firstUser->getKey(),
        ]);

        $this->actingAs($secondUser)
            ->deleteJson(route('push.subscriptions.destroy'), ['endpoint' => $payload['endpoint']])
            ->assertNoContent();

        $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => $payload['endpoint']]);
    }

    public function test_subscription_payload_is_validated(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('push.subscriptions.store'), [
                'endpoint' => 'not-a-url',
                'keys' => [],
                'contentEncoding' => 'unsupported',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'endpoint',
                'keys.p256dh',
                'keys.auth',
                'contentEncoding',
            ]);
    }

    public function test_public_key_endpoint_reports_missing_server_configuration(): void
    {
        config()->set('webpush.vapid.public_key', null);

        $this->actingAs(User::factory()->create())
            ->getJson(route('push.vapid-public-key'))
            ->assertStatus(503);
    }

    public function test_rejects_internal_arbitrary_or_insecure_push_hosts(): void
    {
        $user = User::factory()->create();
        foreach ([
            'http://fcm.googleapis.com/subscriptions/demo',
            'https://127.0.0.1/push',
            'https://localhost/push',
            'https://push.attacker.example/push',
            'https://fcm.googleapis.com:8443/push',
        ] as $endpoint) {
            $data = $this->payload('malicious');
            $data['endpoint'] = $endpoint;
            $this->actingAs($user)->postJson(route('push.subscriptions.store'), $data)
                ->assertUnprocessable()->assertJsonValidationErrors('endpoint');
        }
        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    /** @return array<string, mixed> */
    private function payload(string $device): array
    {
        return [
            'endpoint' => "https://fcm.googleapis.com/subscriptions/{$device}",
            'keys' => [
                'p256dh' => "public-key-{$device}",
                'auth' => "auth-token-{$device}",
            ],
            'contentEncoding' => 'aes128gcm',
        ];
    }
}
