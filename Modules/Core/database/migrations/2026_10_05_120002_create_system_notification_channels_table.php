<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_notification_channels', function (Blueprint $table): void {
            $table->id();
            $table->string('channel', 50)->unique('snc_channel_unique');
            $table->boolean('is_enabled')->default(false);
            $table->longText('credentials')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_notification_channels');
    }
};
