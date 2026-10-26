<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_unit_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id');
            $table->foreignId('member_id');
            $table->foreignId('unit_id');
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->string('source', 32)->default('manual');
            $table->foreignId('actor_user_id')->nullable();
            $table->foreignId('member_unit_transfer_id')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')->on('organizations')->cascadeOnDelete();

            $table->foreign('member_id')
                ->references('id')->on('members')->cascadeOnDelete();

            $table->foreign(['organization_id', 'unit_id'], 'member_unit_histories_org_unit_fk')
                ->references(['organization_id', 'id'])->on('units')->restrictOnDelete();

            $table->foreign('actor_user_id')
                ->references('id')->on('users')->nullOnDelete();

            $table->foreign('member_unit_transfer_id')
                ->references('id')->on('member_unit_transfers')->nullOnDelete();

            $table->index(['member_id', 'started_at']);
            $table->index(['member_id', 'ended_at']);
            $table->index(['unit_id', 'started_at']);
        });

        DB::table('members')
            ->whereNotNull('unit_id')
            ->orderBy('id')
            ->each(function (object $member): void {
                DB::table('member_unit_histories')->insert([
                    'organization_id' => $member->organization_id,
                    'member_id' => $member->id,
                    'unit_id' => $member->unit_id,
                    'started_at' => $member->joined_at ?? $member->created_at,
                    'ended_at' => null,
                    'source' => 'migration',
                    'actor_user_id' => null,
                    'member_unit_transfer_id' => null,
                    'reason' => 'Initial unit history migrated from the member record.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_unit_histories');
    }
};
