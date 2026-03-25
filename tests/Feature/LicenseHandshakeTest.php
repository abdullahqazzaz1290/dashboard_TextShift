<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\License;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LicenseHandshakeTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_activates_a_paid_license_for_the_first_device(): void
    {
        $secret = 'secret-activation-token-1234567890';
        $license = $this->createLicenseWithCopySecret($secret);

        $response = $this->postJson(route('licenses.handshake'), [
            'license_code' => $license->license_code,
            'activation_secret' => $secret,
            'device_id' => 'DEV-PRIMARY-001',
            'machine_fingerprint' => 'fingerprint-primary-001',
            'local_ip' => '192.168.1.7',
            'local_ips' => '192.168.1.7,10.0.0.5',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('license.license_code', $license->license_code)
            ->assertJsonPath('license.device_id', 'DEV-PRIMARY-001');

        $license->refresh();
        $activation = data_get($license->metadata, 'activations.' . $license->license_code);

        $this->assertSame('DEV-PRIMARY-001', $activation['device_id'] ?? null);
        $this->assertSame('fingerprint-primary-001', $activation['machine_fingerprint'] ?? null);
        $this->assertSame('DEV-PRIMARY-001', $license->device_id);
    }

    public function test_it_rejects_the_same_copy_on_a_different_device(): void
    {
        $secret = 'secret-activation-token-1234567890';
        $license = $this->createLicenseWithCopySecret($secret);

        $this->postJson(route('licenses.handshake'), [
            'license_code' => $license->license_code,
            'activation_secret' => $secret,
            'device_id' => 'DEV-PRIMARY-001',
            'machine_fingerprint' => 'fingerprint-primary-001',
            'local_ip' => '192.168.1.7',
            'local_ips' => '192.168.1.7',
        ])->assertOk();

        $response = $this->postJson(route('licenses.handshake'), [
            'license_code' => $license->license_code,
            'activation_secret' => $secret,
            'device_id' => 'DEV-SECONDARY-999',
            'machine_fingerprint' => 'fingerprint-secondary-999',
            'local_ip' => '192.168.1.9',
            'local_ips' => '192.168.1.9',
        ]);

        $response
            ->assertStatus(409)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('code', 'LICENSE_DEVICE_MISMATCH');
    }

    protected function createLicenseWithCopySecret(string $secret): License
    {
        $customer = Customer::create([
            'name' => 'عميل تجريبي',
            'country' => 'Egypt',
        ]);

        return License::create([
            'customer_id' => $customer->id,
            'license_code' => 'SUB-20260322-ABC123',
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
            'jsxbin_package_path' => null,
            'metadata' => [
                'delivery' => [
                    'copies' => [
                        [
                            'copy_number' => 1,
                            'folder_name' => 'root',
                            'license_code' => 'SUB-20260322-ABC123',
                            'customer_name' => $customer->name,
                            'expiry' => Carbon::today()->addMonth()->format('Y-m-d'),
                            'activation_secret_hash' => hash('sha256', $secret),
                        ],
                    ],
                ],
            ],
        ]);
    }
}
