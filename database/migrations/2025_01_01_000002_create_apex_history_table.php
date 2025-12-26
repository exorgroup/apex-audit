<?php

/*
Copyright EXOR Group ltd 2025 
Version 1.0.0.0 
APEX Laravel Auditing
Description: Migration for creating the apex_history table for user-facing audit history with rollback capabilities and simplified CRUD operation tracking.
*/

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
        Schema::create('apex_history', function (Blueprint $table) {
            // Primary key
            $table->bigIncrements('id');

            // Link to audit table (optional - some history may not have audit)
            $table->unsignedBigInteger('audit_id')->nullable()->index();

            // Model information
            $table->string('model_type')->index();
            $table->string('model_id')->index();

            // Action information
            $table->enum('action_type', [
                'create',
                'update',
                'delete',
                'restore',
                'rollback'
            ])->index();

            // Human-readable description
            $table->text('description');

            // Field changes (for updates)
            $table->json('field_changes')->nullable();

            // User information
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_name')->nullable();

            // Rollback information
            $table->boolean('can_rollback')->default(false)->index();
            $table->json('rollback_data')->nullable();
            $table->timestamp('rolled_back_at')->nullable()->index();
            $table->unsignedBigInteger('rolled_back_by')->nullable();

            // Timestamps
            $table->timestamps();

            // Composite indexes for performance
            $table->index(['model_type', 'model_id', 'created_at'], 'apex_history_model_date_idx');
            $table->index(['user_id', 'created_at'], 'apex_history_user_date_idx');
            $table->index(['action_type', 'created_at'], 'apex_history_action_date_idx');
            $table->index(['can_rollback', 'rolled_back_at'], 'apex_history_rollback_idx');

            // Foreign key to audit table (if audit exists)
            $table->foreign('audit_id')
                ->references('id')
                ->on('apex_audit')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apex_history');
    }
};
