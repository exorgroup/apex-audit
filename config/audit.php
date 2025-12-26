<?php

/*
Copyright EXOR Group ltd 2025 
Version 1.0.0.0 
APEX Laravel Auditing
Description: Complete configuration file for APEX Laravel Auditing package. Defines audit and history settings, signature security, field exclusions, rollback permissions, and multi-language support for comprehensive audit trail management.
*/

return [
    /*
    |--------------------------------------------------------------------------
    | APEX Audit Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for APEX Laravel Auditing package
    | which provides forensic-grade audit trails with digital signatures.
    |
    */

    'audit' => [
        /*
        |--------------------------------------------------------------------------
        | Enable Audit Logging
        |--------------------------------------------------------------------------
        |
        | This option controls whether audit logging is enabled globally.
        | When disabled, no audit records will be created.
        |
        */
        'enabled' => env('APEX_AUDIT_ENABLED', true),

        /*
        |--------------------------------------------------------------------------
        | Track UI Actions
        |--------------------------------------------------------------------------
        |
        | Enable tracking of UI actions like button clicks, form submissions,
        | and other non-model interactions.
        |
        */
        'track_ui_actions' => env('APEX_AUDIT_UI_ACTIONS', true),

        /*
        |--------------------------------------------------------------------------
        | Track Model Retrievals
        |--------------------------------------------------------------------------
        |
        | Enable tracking of model retrieval operations. This can be very verbose
        | and is typically disabled unless specifically needed for security auditing.
        |
        */
        'track_retrievals' => env('APEX_AUDIT_TRACK_RETRIEVALS', false),

        /*
        |--------------------------------------------------------------------------
        | Digital Signature Settings
        |--------------------------------------------------------------------------
        |
        | Configuration for digital signatures used to ensure audit trail integrity.
        | The secret key should be stored securely and never changed in production.
        |
        */
        'signature' => [
            'enabled' => env('APEX_AUDIT_SIGNATURE_ENABLED', true),
            'secret_key' => env('APEX_AUDIT_SECRET_KEY', ''),
            'algorithm' => 'sha512',
        ],

        /*
        |--------------------------------------------------------------------------
        | Audit Retention
        |--------------------------------------------------------------------------
        |
        | Number of days to retain audit records. Set to null for permanent retention.
        | Note: Audit records are designed to be immutable and deletion should be rare.
        |
        */
        'retention_days' => env('APEX_AUDIT_RETENTION', null),

        /*
        |--------------------------------------------------------------------------
        | Global Field Exclusions
        |--------------------------------------------------------------------------
        |
        | Fields that should never be audited across all models.
        | These are typically sensitive fields like passwords or tokens.
        |
        */
        'global_excludes' => [
            'password',
            'remember_token',
            'api_token',
            'email_verified_at',
            'two_factor_secret',
            'two_factor_recovery_codes',
            'created_at',
            'updated_at',
        ],

        /*
        |--------------------------------------------------------------------------
        | Model-Specific Overrides
        |--------------------------------------------------------------------------
        |
        | Override audit settings for specific models.
        | These settings take precedence over model-level configurations.
        |
        */
        'model_overrides' => [
            // Example:
            // 'App\Models\User' => [
            //     'audit_events' => ['create', 'delete'],
            //     'audit_exclude' => ['last_login', 'login_count'],
            //     'rollbackable_actions' => [],
            // ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Database Connection
        |--------------------------------------------------------------------------
        |
        | Database connection to use for audit tables.
        | Can be different from the main application database for security.
        |
        */
        'connection' => env('APEX_AUDIT_CONNECTION', null),

        /*
        |--------------------------------------------------------------------------
        | Queue Audit Processing
        |--------------------------------------------------------------------------
        |
        | Enable queueing of audit processing for better performance.
        | Recommended for high-traffic applications.
        |
        */
        'queue' => [
            'enabled' => env('APEX_AUDIT_QUEUE_ENABLED', false),
            'connection' => env('APEX_AUDIT_QUEUE_CONNECTION', null),
            'queue' => env('APEX_AUDIT_QUEUE_NAME', 'audit'),
        ],

        /*
        |--------------------------------------------------------------------------
        | Batch Processing
        |--------------------------------------------------------------------------
        |
        | Configuration for batch operations and bulk auditing.
        |
        */
        'batch' => [
            'enabled' => true,
            'chunk_size' => 1000,
            'track_individual_records' => false,
            'track_summary_only' => true,
        ],

        /*
        |--------------------------------------------------------------------------
        | Performance Settings
        |--------------------------------------------------------------------------
        |
        | Settings to optimize audit performance.
        |
        */
        'performance' => [
            'cache_signatures' => env('APEX_AUDIT_CACHE_SIGNATURES', true),
            'compress_large_data' => env('APEX_AUDIT_COMPRESS_DATA', true),
            'max_field_size' => env('APEX_AUDIT_MAX_FIELD_SIZE', 65535), // 64KB
        ],
    ],

    'history' => [
        /*
        |--------------------------------------------------------------------------
        | Enable History Tracking
        |--------------------------------------------------------------------------
        |
        | Controls whether user-facing history records are created.
        | History provides a clean interface for users to view changes.
        |
        */
        'enabled' => env('APEX_HISTORY_ENABLED', true),

        /*
        |--------------------------------------------------------------------------
        | Show History in UI
        |--------------------------------------------------------------------------
        |
        | Controls whether history is displayed in the user interface.
        | Can be disabled while still maintaining history records.
        |
        */
        'show_in_ui' => env('APEX_HISTORY_UI_ENABLED', true),

        /*
        |--------------------------------------------------------------------------
        | Enable Rollback Functionality
        |--------------------------------------------------------------------------
        |
        | Controls whether users can rollback changes through the history interface.
        | Requires appropriate permissions to be configured.
        |
        */
        'allow_rollback' => env('APEX_HISTORY_ROLLBACK', true),

        /*
        |--------------------------------------------------------------------------
        | History Retention
        |--------------------------------------------------------------------------
        |
        | Number of days to retain history records. Unlike audit records,
        | history can be cleaned up more frequently for performance.
        |
        */
        'retention_days' => env('APEX_HISTORY_RETENTION', 365),

        /*
        |--------------------------------------------------------------------------
        | Rollback Permissions
        |--------------------------------------------------------------------------
        |
        | Define who can perform rollback operations.
        | Can use Laravel's authorization system.
        |
        */
        'rollback_permissions' => [
            'gate' => 'apex.history.rollback',
            'roles' => ['admin', 'manager'],
            'permissions' => ['rollback_changes'],
        ],

        /*
        |--------------------------------------------------------------------------
        | History UI Configuration
        |--------------------------------------------------------------------------
        |
        | Configuration for history display in the user interface.
        |
        */
        'ui' => [
            'items_per_page' => 20,
            'show_user_info' => true,
            'show_ip_address' => false,
            'show_device_info' => false,
            'date_format' => 'Y-m-d H:i:s',
            'enable_search' => true,
            'enable_filters' => true,
            'enable_export' => true,
        ],

        /*
        |--------------------------------------------------------------------------
        | History Data Configuration
        |--------------------------------------------------------------------------
        |
        | Configure what data is stored in history records.
        |
        */
        'data' => [
            'store_field_labels' => true,
            'format_values' => true,
            'max_description_length' => 255,
            'truncate_long_values' => true,
            'max_value_length' => 100,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Additional security configuration for audit trail protection.
    |
    */
    'security' => [
        /*
        |--------------------------------------------------------------------------
        | Audit Table Protection
        |--------------------------------------------------------------------------
        |
        | Additional protection measures for audit table integrity.
        |
        */
        'table_protection' => [
            'prevent_truncate' => true,
            'prevent_drop' => true,
            'read_only_user' => env('APEX_AUDIT_READONLY_USER', null),
        ],

        /*
        |--------------------------------------------------------------------------
        | Signature Verification
        |--------------------------------------------------------------------------
        |
        | Automatic signature verification settings.
        |
        */
        'verification' => [
            'enabled' => env('APEX_AUDIT_VERIFY_ENABLED', true),
            'frequency' => env('APEX_AUDIT_VERIFY_FREQUENCY', 'daily'),
            'sample_rate' => env('APEX_AUDIT_VERIFY_SAMPLE_RATE', 0.1), // 10% of records
            'alert_on_tampering' => env('APEX_AUDIT_ALERT_TAMPERING', true),
        ],

        /*
        |--------------------------------------------------------------------------
        | Data Anonymization
        |--------------------------------------------------------------------------
        |
        | Settings for anonymizing sensitive data in audit logs.
        |
        */
        'anonymization' => [
            'enabled' => env('APEX_AUDIT_ANONYMIZE', false),
            'fields' => [
                'email' => 'partial', // Show first 3 chars: abc***@***.com
                'phone' => 'partial',
                'ssn' => 'hash',
                'credit_card' => 'mask',
            ],
            'hash_algorithm' => 'sha256',
        ],

        /*
        |--------------------------------------------------------------------------
        | IP Address Tracking
        |--------------------------------------------------------------------------
        |
        | Configuration for IP address tracking and privacy.
        |
        */
        'ip_tracking' => [
            'enabled' => env('APEX_AUDIT_TRACK_IP', true),
            'anonymize_ip' => env('APEX_AUDIT_ANONYMIZE_IP', false),
            'store_full_ip' => env('APEX_AUDIT_STORE_FULL_IP', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Language Support
    |--------------------------------------------------------------------------
    |
    | Configuration for APEX Audit multi-language support.
    |
    */
    'language' => [
        /*
        |--------------------------------------------------------------------------
        | Language Detection Method
        |--------------------------------------------------------------------------
        |
        | How should APEX Audit detect the language to use?
        | 
        | Options:
        | - 'config': Use the language set in this config file
        | - 'url': Detect language from URL (e.g., /en/admin, /es/admin)
        | - 'app': Use Laravel's app locale (App::getLocale())
        | - 'header': Use Accept-Language header from request
        |
        */
        'detection_method' => env('APEX_AUDIT_LANG_METHOD', 'app'),

        /*
        |--------------------------------------------------------------------------
        | Default Language
        |--------------------------------------------------------------------------
        |
        | The default language to use when detection fails or no language is set.
        |
        */
        'default' => env('APEX_AUDIT_LANG_DEFAULT', 'en'),

        /*
        |--------------------------------------------------------------------------
        | Supported Languages
        |--------------------------------------------------------------------------
        |
        | List of languages supported by APEX Audit. Add language codes here
        | and create corresponding language files in the Lang directory.
        |
        */
        'supported' => [
            'en' => 'English',
            'es' => 'Español',
            'fr' => 'Français',
            'de' => 'Deutsch',
            'it' => 'Italiano',
            'pt' => 'Português',
            'zh' => '中文',
            'ja' => '日本語',
            'ar' => 'العربية',
            'ru' => 'Русский',
            'mt' => 'Multi',
        ],

        /*
        |--------------------------------------------------------------------------
        | URL Language Detection
        |--------------------------------------------------------------------------
        |
        | Configuration for URL-based language detection.
        |
        */
        'url_detection' => [
            // URL segment position for language (1 = first segment after domain)
            'segment_position' => 1,

            // Fallback when no language in URL
            'fallback_to_default' => true,

            // Cache detected language in session
            'cache_in_session' => true,

            // Session key for language storage
            'session_key' => 'apex_audit_language',
        ],

        /*
        |--------------------------------------------------------------------------
        | Language File Caching
        |--------------------------------------------------------------------------
        |
        | Enable caching of language files for better performance.
        |
        */
        'cache' => [
            'enabled' => env('APEX_AUDIT_LANG_CACHE', true),
            'ttl' => 3600, // Cache TTL in seconds
            'prefix' => 'apex_audit_lang_',
        ],

        /*
        |--------------------------------------------------------------------------
        | Date and Number Formatting
        |--------------------------------------------------------------------------
        |
        | Language-specific formatting options.
        |
        */
        'formatting' => [
            'use_locale_formatting' => true,
            'date_formats' => [
                'short' => 'M j, Y',
                'medium' => 'M j, Y g:i A',
                'long' => 'F j, Y g:i:s A',
                'full' => 'l, F j, Y g:i:s A T',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Tenancy Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for multi-tenant applications using Stancl Tenancy package.
    |
    */
    'tenancy' => [
        /*
        |--------------------------------------------------------------------------
        | Enable Multi-Tenancy Support
        |--------------------------------------------------------------------------
        |
        | Controls multi-tenancy support for audit storage:
        | - 'auto': Automatically detect architecture (recommended)
        | - true: Force multi-tenancy mode
        | - false: Force single-tenancy mode
        |
        | Auto-detection checks for:
        | 1. Explicit configuration setting
        | 2. Existence of migrations/tenant/ folder
        | 3. Stancl Tenancy package installation
        |
        */
        'enabled' => env('APEX_AUDIT_TENANCY_ENABLED', 'auto'),

        /*
        |--------------------------------------------------------------------------
        | Tenant Detection Method
        |--------------------------------------------------------------------------
        |
        | How to detect the current tenant:
        | - 'auto': Automatically detect using Stancl Tenancy helpers
        | - 'connection': Use the default database connection
        | - 'manual': Manually specified connection
        |
        */
        'detection_method' => env('APEX_AUDIT_TENANCY_METHOD', 'auto'),

        /*
        |--------------------------------------------------------------------------
        | Central Database Connection
        |--------------------------------------------------------------------------
        |
        | Connection name for the central/main database.
        | Only used when tenancy is disabled or for central audit logging.
        |
        */
        'central_connection' => env('DB_CONNECTION_MULTITENANCY', 'central'),

        /*
        |--------------------------------------------------------------------------
        | Force Tenant Connection
        |--------------------------------------------------------------------------
        |
        | When enabled, all audit operations will use the tenant connection
        | even if the central connection is available.
        |
        */
        'force_tenant_connection' => env('APEX_AUDIT_FORCE_TENANT', true),

        /*
        |--------------------------------------------------------------------------
        | Fallback Behavior
        |--------------------------------------------------------------------------
        |
        | What to do when tenant context is not available:
        | - 'central': Use central database
        | - 'skip': Skip audit logging
        | - 'error': Throw an error
        |
        */
        'fallback_behavior' => env('APEX_AUDIT_TENANCY_FALLBACK', 'central'),
    ],
    'integrations' => [
        /*
        |--------------------------------------------------------------------------
        | Notification Channels
        |--------------------------------------------------------------------------
        |
        | Channels to notify when critical audit events occur.
        |
        */
        'notifications' => [
            'enabled' => env('APEX_AUDIT_NOTIFICATIONS', false),
            'channels' => ['mail', 'slack'],
            'events' => [
                'tampering_detected',
                'rollback_performed',
                'cleanup_executed',
                'verification_failed',
            ],
            'recipients' => [
                'security@company.com',
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | External Logging
        |--------------------------------------------------------------------------
        |
        | Send audit events to external logging services.
        |
        */
        'external_logging' => [
            'enabled' => env('APEX_AUDIT_EXTERNAL_LOG', false),
            'services' => [
                'elasticsearch' => [
                    'enabled' => false,
                    'index' => 'apex-audit',
                    'hosts' => ['localhost:9200'],
                ],
                'syslog' => [
                    'enabled' => false,
                    'facility' => 'user',
                    'level' => 'info',
                ],
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Webhook Integration
        |--------------------------------------------------------------------------
        |
        | Send audit events to external webhooks.
        |
        */
        'webhooks' => [
            'enabled' => env('APEX_AUDIT_WEBHOOKS', false),
            'endpoints' => [
                // 'https://your-webhook-endpoint.com/audit',
            ],
            'events' => [
                'high_priority_events',
                'security_events',
            ],
            'timeout' => 30,
            'retry_attempts' => 3,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Development Settings
    |--------------------------------------------------------------------------
    |
    | Settings useful during development and testing.
    |
    */
    'development' => [
        /*
        |--------------------------------------------------------------------------
        | Debug Mode
        |--------------------------------------------------------------------------
        |
        | Enable additional debugging information in audit logs.
        |
        */
        'debug_mode' => env('APEX_AUDIT_DEBUG', false),

        /*
        |--------------------------------------------------------------------------
        | Mock Data
        |--------------------------------------------------------------------------
        |
        | Generate mock audit data for testing purposes.
        |
        */
        'mock_data' => [
            'enabled' => env('APEX_AUDIT_MOCK_DATA', false),
            'count' => 1000,
            'models' => ['User', 'Post', 'Comment'],
        ],

        /*
        |--------------------------------------------------------------------------
        | Performance Profiling
        |--------------------------------------------------------------------------
        |
        | Profile audit performance during development.
        |
        */
        'profiling' => [
            'enabled' => env('APEX_AUDIT_PROFILING', false),
            'log_slow_queries' => true,
            'slow_threshold' => 1000, // milliseconds
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Widget Configuration
    |--------------------------------------------------------------------------
    |
    | Default configuration for APEX framework widgets.
    |
    */
    'widgets' => [
        /*
        |--------------------------------------------------------------------------
        | History Widget
        |--------------------------------------------------------------------------
        |
        | Default configuration for history display widgets.
        |
        */
        'history' => [
            'default_columns' => [
                'action_type',
                'description',
                'user',
                'created_at',
            ],
            'available_columns' => [
                'id',
                'action_type',
                'description',
                'user',
                'created_at',
                'can_rollback',
                'rolled_back_at',
            ],
            'default_filters' => [
                'action_type',
                'user_id',
                'date_range',
            ],
            'pagination' => [
                'per_page' => 20,
                'show_per_page_options' => true,
                'per_page_options' => [10, 20, 50, 100],
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Audit Summary Widget
        |--------------------------------------------------------------------------
        |
        | Configuration for audit summary widgets.
        |
        */
        'summary' => [
            'show_statistics' => true,
            'show_charts' => true,
            'time_periods' => ['today', 'week', 'month', 'year'],
            'chart_types' => ['line', 'bar', 'pie'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Event Handlers
    |--------------------------------------------------------------------------
    |
    | Register custom event handlers for audit events.
    |
    */
    'events' => [
        'handlers' => [
            // 'audit.created' => [MyCustomHandler::class],
            // 'rollback.performed' => [RollbackNotificationHandler::class],
        ],
    ],
];
