<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.debug', false);

        Route::get('/__test/request-id', fn () => request()->attributes->get('request_id'));
        Route::get('/__test/error/403', fn () => abort(403));
        Route::get('/__test/error/419', fn () => abort(419));
        Route::get('/__test/error/429', fn () => abort(429));
        Route::get('/__test/error/503', fn () => abort(503));
        Route::get('/__test/error/500', fn () => throw new RuntimeException('Synthetic server error for testing.'));
    }

    public function test_request_id_is_added_to_successful_responses(): void
    {
        $response = $this->get('/__test/request-id');

        $response->assertOk();

        $requestId = $response->headers->get('X-Request-ID');

        $this->assertNotNull($requestId);
        $this->assertMatchesRegularExpression('/^SPMB-\d{6}-[A-Z0-9]{8}$/', $requestId);
        $response->assertSeeText($requestId);
    }

    public function test_not_found_page_is_branded_and_friendly(): void
    {
        $this->get('/__test/route-that-does-not-exist')
            ->assertNotFound()
            ->assertSeeText('SPMB Taruna Bakti')
            ->assertSeeText('Halaman tidak ditemukan');
    }

    public function test_expected_http_errors_have_indonesian_pages(): void
    {
        $cases = [
            403 => 'Akses tidak diizinkan',
            419 => 'Sesi Anda telah berakhir',
            429 => 'Terlalu banyak percobaan',
            503 => 'Sistem sedang tidak tersedia',
        ];

        foreach ($cases as $status => $message) {
            $this->get('/__test/error/'.$status)
                ->assertStatus($status)
                ->assertSeeText('SPMB Taruna Bakti')
                ->assertSeeText($message);
        }
    }

    public function test_server_error_shows_safe_message_and_reference_id(): void
    {
        $this->get('/__test/error/500')
            ->assertStatus(500)
            ->assertSeeText('Terjadi gangguan pada sistem')
            ->assertSee('SPMB-', false)
            ->assertDontSee('Synthetic server error for testing.');
    }
}
