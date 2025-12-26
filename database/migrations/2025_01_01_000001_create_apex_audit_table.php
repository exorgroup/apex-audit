<?php

/*
Copyright EXOR Group ltd 2025 
Version 1.0.0.0 
APEX Laravel Auditing
Description: Migration for creating the apex_audit table with all required fields for comprehensive audit trail storage including digital signatures and device fingerprinting.
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
        Schema::create('apex_audit', function (Blueprint $table) {
            // Primary key
            $table->bigIncrements('id');

            // Core audit fields
            $table->uuid('audit_uuid')->unique()->index();
            $table->enum('event_type', [
                'model_crud',
                'ui_action',
                'system_event',
                'custom',
                'batch_operation',
                'rollback_action'
            ])->index();
            $table->string('action_type', 50)->index();

            // Model information
            $table->string('model_type')->nullable()->index();
            $table->string('model_id')->nullable()->index();
            $table->string('table_name')->nullable()->index();

            // Source tracking
            $table->string('source_page')->nullable();
            $table->string('source_element')->nullable();

            // User and session information
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('session_id')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('device_fingerprint')->nullable();

            // Audit data
            $table->json('additional_data')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            // Security
            $table->string('signature', 128)->index();

            // Timestamps
            $table->timestamp('created_at')->index();

            // Composite indexes for performance
            $table->index(['model_type', 'model_id', 'created_at'], 'apex_audit_model_date_idx');
            $table->index(['user_id', 'created_at'], 'apex_audit_user_date_idx');
            $table->index(['event_type', 'action_type', 'created_at'], 'apex_audit_event_action_date_idx');
            $table->index(['table_name', 'created_at'], 'apex_audit_table_date_idx');
            $table->index(['source_page', 'created_at'], 'apex_audit_source_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apex_audit');
    }
};
