<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_activities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('organization_id')
                ->nullable()
                ->constrained('organizations')
                ->nullOnDelete();

            $table->foreignId('token_id')
                ->nullable()
                ->constrained('personal_access_tokens')
                ->nullOnDelete();

            $table->string('channel', 30);

            $table->ipAddress('ip_address')->nullable();

            $table->text('user_agent')->nullable();

            $table->string('device_name')->nullable();
            $table->string('platform')->nullable();

            $table->timestamp('logged_in_at');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('logged_out_at')->nullable();

            $table->timestamps();

            $table->index(
                ['user_id', 'logged_in_at'],
                'login_activities_user_logged_in_idx'
            );

            $table->index(
                ['organization_id', 'logged_in_at'],
                'login_activities_org_logged_in_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_activities');
    }
};