<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\SpmbDatabaseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductionNotificationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_notifications_are_written_once_for_same_delivery_uuid(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        config()->set('webpush.vapid.public_key', null);
        config()->set('webpush.vapid.private_key', null);

        $notification = new SpmbDatabaseNotification(
            event: 'production.probe', category: 'operational',
            title: 'Tes database notification', body: 'Pesan audit.'
        );
        Notification::sendNow($user, $notification, ['database']);
        Notification::sendNow($user, $notification, ['database']);

        $this->assertSame(1, $user->notifications()->count());
        $this->assertSame('production.probe', $user->notifications()->first()->data['spmb_event']);
        $this->assertNotNull($user->notifications()->first()->id);
    }

    public function test_notification_and_push_database_schema_is_present(): void
    {
        $this->assertTrue(Schema::hasTable('notifications'));
        $this->assertTrue(Schema::hasTable('push_subscriptions'));
        $this->assertTrue(Schema::hasColumns('notifications', [
            'id', 'notifiable_id', 'notifiable_type', 'data', 'read_at'
        ]));
    }

    public function test_push_and_pwa_artifacts_do_not_enable_private_offline_cache(): void
    {
        $sw = file_get_contents(public_path('sw.js'));
        $this->assertStringNotContainsString('caches.open', $sw);
        $this->assertStringNotContainsString("addEventListener('fetch'", $sw);
        $this->assertStringContainsString("requested.origin === self.location.origin", $sw);
        $this->assertFileExists(public_path('js/pwa.js'));
        $this->assertFileExists(public_path('images/pwa/icon-192.png'));
    }
}
