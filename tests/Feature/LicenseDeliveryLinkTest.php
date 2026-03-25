<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\License;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LicenseDeliveryLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_copy_link_downloads_once_and_records_request_details(): void
    {
        Storage::fake('local');

        $license = $this->createLicenseWithDeliveryLink();

        Storage::disk('local')->put(
            data_get($license->metadata, 'delivery.copies.0.archive_relative'),
            'demo-binary-content'
        );

        $response = $this->get(route('licenses.delivery.public-copy', [
            'license' => $license,
            'copyNumber' => 1,
            'token' => 'delivery-token-abc123',
        ]));

        $response
            ->assertOk()
            ->assertDownload('01-sub-20260322-del123.zip');

        $license->refresh();
        $copy = data_get($license->metadata, 'delivery.copies.0');

        $this->assertSame('downloaded', $copy['download_status'] ?? null);
        $this->assertSame('127.0.0.1', $copy['downloaded_ip'] ?? null);
        $this->assertNotEmpty($copy['downloaded_at'] ?? null);
    }

    public function test_public_copy_link_cannot_be_used_twice(): void
    {
        Storage::fake('local');

        $license = $this->createLicenseWithDeliveryLink();

        Storage::disk('local')->put(
            data_get($license->metadata, 'delivery.copies.0.archive_relative'),
            'demo-binary-content'
        );

        $this->get(route('licenses.delivery.public-copy', [
            'license' => $license,
            'copyNumber' => 1,
            'token' => 'delivery-token-abc123',
        ]))->assertOk();

        $this->get(route('licenses.delivery.public-copy', [
            'license' => $license,
            'copyNumber' => 1,
            'token' => 'delivery-token-abc123',
        ]))->assertStatus(410);
    }

    protected function createLicenseWithDeliveryLink(): License
    {
        $customer = Customer::create([
            'name' => 'عميل رابط تنزيل',
            'country' => 'Egypt',
        ]);

        return License::create([
            'customer_id' => $customer->id,
            'license_code' => 'SUB-20260322-DEL123',
            'device_id' => null,
            'copies_count' => 1,
            'plan_months' => 1,
            'starts_at' => Carbon::today(),
            'expires_at' => Carbon::today()->addMonth(),
            'status' => 'active',
            'payment_status' => 'paid',
            'amount' => 250,
            'unit_price' => 250,
            'currency' => 'EGP',
            'notes' => null,
            'delivery_notes' => null,
            'jsx_package_path' => null,
            'jsxbin_package_path' => 'deliveries/archives/sub-20260322-del123.zip',
            'metadata' => [
                'delivery' => [
                    'copies' => [
                        [
                            'copy_number' => 1,
                            'folder_name' => 'root',
                            'license_code' => 'SUB-20260322-DEL123',
                            'customer_name' => $customer->name,
                            'expiry' => Carbon::today()->addMonth()->format('Y-m-d'),
                            'archive_relative' => 'deliveries/copy-archives/sub-20260322-del123/01-sub-20260322-del123.zip',
                            'archive_name' => '01-sub-20260322-del123.zip',
                            'download_token' => 'delivery-token-abc123',
                            'download_status' => 'ready',
                            'downloaded_at' => null,
                            'downloaded_ip' => null,
                            'downloaded_user_agent' => null,
                            'activation_secret_hash' => hash('sha256', 'secret-activation-token-1234567890'),
                        ],
                    ],
                ],
            ],
        ]);
    }
}
