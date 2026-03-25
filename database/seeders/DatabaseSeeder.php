<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $methods = [
            [
                'name' => 'InstaPay',
                'slug' => 'instapay',
                'payment_type' => 'wallet',
                'account_label' => 'InstaPay Handle',
                'account_identifier' => '',
                'payment_url' => '',
                'instructions' => 'ضع لينك التحويل أو اسم الحساب الذي سيظهر للعميل.',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Vodafone Cash',
                'slug' => 'vodafone-cash',
                'payment_type' => 'wallet',
                'account_label' => 'Vodafone Number',
                'account_identifier' => '',
                'payment_url' => '',
                'instructions' => 'اكتب رقم المحفظة أو رابط الدفع المختصر.',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Orange Cash',
                'slug' => 'orange-cash',
                'payment_type' => 'wallet',
                'account_label' => 'Orange Number',
                'account_identifier' => '',
                'payment_url' => '',
                'instructions' => 'اكتب رقم المحفظة أو رابط التحويل الخاص بك.',
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($methods as $method) {
            PaymentMethod::query()->firstOrCreate(
                ['slug' => $method['slug']],
                $method,
            );
        }
    }
}
