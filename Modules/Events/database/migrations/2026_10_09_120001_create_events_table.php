<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('title', 200);
            $table->string('subtitle', 255)->nullable();
            $table->longText('description')->nullable();
            $table->string('venue_name', 200)->nullable();
            $table->text('venue_address')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('timezone', 64)->default('Asia/Kolkata');
            $table->string('scope', 20)->default('central');
            $table->string('visibility', 20)->default('restricted');
            $table->string('status', 20)->default('draft');
            $table->string('cover_image_path')->nullable();
            $table->string('thumbnail_image_path')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'id'], 'events_org_id_unique');
            $table->foreign(['organization_id', 'unit_id'], 'events_org_unit_fk')
                ->references(['organization_id', 'id'])
                ->on('units')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'category_id'], 'events_org_category_fk')
                ->references(['organization_id', 'id'])
                ->on('event_categories')
                ->restrictOnDelete();
            $table->index(['organization_id', 'status', 'starts_at'], 'events_org_status_start_idx');
            $table->index(['organization_id', 'visibility', 'starts_at'], 'events_org_visibility_start_idx');
            $table->index(['organization_id', 'unit_id', 'starts_at'], 'events_org_unit_start_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
