<?php

/*
Copyright EXOR Group ltd 2025 
Version 1.0.0.0 
APEX Laravel Auditing
Description: English language file for APEX Audit messages and labels.
*/

return [
    'actions' => [
        'create' => 'Created',
        'update' => 'Updated',
        'delete' => 'Deleted',
        'restore' => 'Restored',
        'force_delete' => 'Permanently Deleted',
        'retrieve' => 'Retrieved',
        'rollback' => 'Rolled Back',
        'batch_operation' => 'Batch Operation',
        'custom' => 'Custom Action',
    ],

    'descriptions' => [
        'created_new' => 'Created new :model #:id',
        'updated_model' => 'Updated :model #:id - Changed: :fields',
        'deleted_model' => 'Deleted :model #:id',
        'restored_model' => 'Restored :model #:id',
        'force_deleted_model' => 'Permanently deleted :model #:id',
        'performed_action' => 'Performed :action on :model #:id',
    ],

    'fields' => [
        'id' => 'ID',
        'action' => 'Action',
        'description' => 'Description',
        'user' => 'User',
        'date' => 'Date',
        'model' => 'Model',
        'changes' => 'Changes',
        'old_value' => 'Old Value',
        'new_value' => 'New Value',
        'empty' => '(empty)',
        'field_label' => 'Field',
    ],

    'rollback' => [
        'title' => 'Rollback Action',
        'confirm' => 'Are you sure you want to rollback this action?',
        'success' => 'Action rolled back successfully',
        'failed' => 'Rollback failed',
        'not_allowed' => 'Rollback not allowed for this action',
        'already_rolled_back' => 'This action has already been rolled back',
        'no_permission' => 'You do not have permission to rollback this action',
        'missing_data' => 'No rollback data available for this action',
        'model_not_found' => 'The original record no longer exists',
        'record_exists' => 'Record already exists and cannot be restored',
        'validation_failed' => 'Rollback validation failed',
        'functionality_disabled' => 'Rollback functionality is disabled',
        'field_permission_denied' => 'Cannot rollback non-auditable fields',
        'preview_title' => 'Rollback Preview',
        'preview_description' => 'This will restore the following changes:',
    ],

    'history' => [
        'title' => 'Change History',
        'no_records' => 'No history records found',
        'show_changes' => 'Show Changes',
        'hide_changes' => 'Hide Changes',
        'view_details' => 'View Details',
        'rollback_action' => 'Rollback',
        'rolled_back_by' => 'Rolled back by :user on :date',
        'rollback_available' => 'Rollback Available',
        'cannot_rollback' => 'Cannot Rollback',
        'filter_by_action' => 'Filter by Action',
        'filter_by_user' => 'Filter by User',
        'filter_by_date' => 'Filter by Date',
        'clear_filters' => 'Clear Filters',
        'export' => 'Export History',
    ],

    'cleanup' => [
        'title' => 'Audit Cleanup',
        'dry_run_mode' => 'DRY RUN MODE - No records will actually be deleted',
        'history_retention' => 'History retention: :days days (cutoff: :date)',
        'audit_retention' => 'Audit retention: :days days (cutoff: :date)',
        'found_records' => 'Found :count records for cleanup',
        'no_records' => 'No records found for cleanup',
        'confirm_delete' => 'Delete :count records older than :date?',
        'deleted_successfully' => 'Successfully deleted :count records',
        'cancelled' => 'Cleanup cancelled by user',
        'skipped' => 'Skipped - :reason',
        'retention_not_configured' => 'No retention policy configured',
        'audit_warning' => 'WARNING: You are about to permanently delete audit records!',
        'audit_confirmation' => 'This action cannot be undone and may violate compliance requirements.',
        'absolutely_sure' => 'Are you ABSOLUTELY SURE you want to delete :count audit records?',
        'type_confirmation' => 'Type "DELETE AUDIT RECORDS" to confirm',
        'confirmation_failed' => 'Confirmation phrase not entered',
    ],

    'verification' => [
        'title' => 'Signature Verification',
        'starting' => 'Signature verification starting...',
        'signatures_disabled' => 'Audit signatures are disabled in configuration',
        'found_records' => 'Found :count audit records to verify',
        'no_records' => 'No audit records found for verification',
        'valid_signature' => 'VALID',
        'invalid_signature' => 'INVALID',
        'all_valid' => 'All signatures are valid - no tampering detected',
        'tampering_detected' => 'TAMPERING DETECTED!',
        'invalid_records' => 'The following records have invalid signatures:',
        'recommended_actions' => 'Recommended actions:',
        'investigate_changes' => 'Investigate when and how these records were modified',
        'check_access_logs' => 'Check database access logs for unauthorized changes',
        'notify_security' => 'Notify security team if tampering is confirmed',
        'restore_backup' => 'Consider restoring from backup if integrity is compromised',
        'verification_completed' => 'Verification completed',
        'results_summary' => 'Verification Results',
        'total_processed' => 'Total records processed: :count',
        'valid_count' => 'Valid signatures: :count',
        'invalid_count' => 'Invalid signatures: :count',
        'validity_rate' => 'Validity rate: :percentage%',
    ],

    'errors' => [
        'general_error' => 'An error occurred',
        'audit_failed' => 'Audit logging failed',
        'signature_failed' => 'Signature generation failed',
        'permission_denied' => 'Permission denied',
        'model_not_found' => 'Model not found',
        'invalid_data' => 'Invalid data provided',
        'database_error' => 'Database error occurred',
        'configuration_error' => 'Configuration error',
        'file_not_found' => 'File not found',
        'invalid_format' => 'Invalid format',
    ],

    'success' => [
        'audit_created' => 'Audit record created successfully',
        'action_completed' => 'Action completed successfully',
        'settings_saved' => 'Settings saved successfully',
        'data_exported' => 'Data exported successfully',
        'cache_cleared' => 'Cache cleared successfully',
    ],

    'filters' => [
        'all' => 'All',
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        'last_week' => 'Last Week',
        'last_month' => 'Last Month',
        'last_year' => 'Last Year',
        'custom_range' => 'Custom Range',
        'from_date' => 'From Date',
        'to_date' => 'To Date',
        'apply' => 'Apply',
        'reset' => 'Reset',
    ],

    'pagination' => [
        'previous' => 'Previous',
        'next' => 'Next',
        'showing' => 'Showing :first to :last of :total results',
        'per_page' => 'Per page',
    ],

    'commands' => [
        'cleanup_title' => 'APEX Audit Cleanup',
        'verify_title' => 'APEX Audit Verification',
        'completed_successfully' => 'completed successfully',
        'failed_with_errors' => 'failed with errors',
    ],

    'widgets' => [
        'history_widget' => 'History Widget',
        'audit_summary' => 'Audit Summary',
        'recent_activity' => 'Recent Activity',
        'user_activity' => 'User Activity',
        'system_stats' => 'System Statistics',
    ],

    'permissions' => [
        'view_audit' => 'View Audit',
        'view_history' => 'View History',
        'rollback_changes' => 'Rollback Changes',
        'cleanup_records' => 'Cleanup Records',
        'verify_signatures' => 'Verify Signatures',
        'export_data' => 'Export Data',
        'manage_settings' => 'Manage Settings',
    ],
];
