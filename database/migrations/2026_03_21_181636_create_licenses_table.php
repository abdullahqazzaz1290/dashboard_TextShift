<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('license_code')->unique();
            $table->string('device_id')->nullable()->index();
            $table->unsignedTinyInteger('plan_months');
            $table->date('starts_at');
            $table->date('expires_at')->index();
            $table->string('status')->default('draft')->index();
            $table->string('payment_status')->default('unpaid')->index();
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('currency', 8)->default('EGP');
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->text('delivery_notes')->nullable();
            $table->string('jsx_package_path')->nullable();
            $table->string('jsxbin_package_path')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};
