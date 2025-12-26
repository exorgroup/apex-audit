<?php

/*
Copyright EXOR Group ltd 2025 
Version 1.0.0.0 
APEX Laravel Auditing
Description: Helper functions for APEX Laravel Auditing package. Provides utility functions for translations, tenant context detection, and common audit operations.
*/

use Apex\Audit\Services\ApexAuditLanguageService;
use Illuminate\Support\Facades\Log;

if (!function_exists('apex_trans')) {
    /**
     * Translate an APEX audit key with parameters.
     *
     * @param string $key
     * @param array $replace
     * @param string|null $locale
     * @return string
     */
    function apex_trans(string $key, array $replace = [], ?string $locale = null): string
    {
        try {
            $languageService = app(ApexAuditLanguageService::class);
            return $languageService->trans($key, $replace, $locale);
        } catch (\Exception $e) {
            // Fallback to Laravel's trans if service is not available
            return trans("apex-audit::audit.{$key}", $replace, $locale);
        }
    }
}

if (!function_exists('apex_audit_enabled')) {
    /**
     * Check if APEX audit is enabled.
     *
     * @return bool
     */
    function apex_audit_enabled(): bool
    {
        return config('apex.audit.audit.enabled', true);
    }
}

if (!function_exists('apex_history_enabled')) {
    /**
     * Check if APEX history is enabled.
     *
     * @return bool
     */
    function apex_history_enabled(): bool
    {
        return config('apex.audit.history.enabled', true);
    }
}

if (!function_exists('apex_audit_tenant_connection')) {
    /**
     * Get the database connection name for audit tables in tenant context.
     *
     * @return string|null
     */
    function apex_audit_tenant_connection(): ?string
    {
        if (function_exists('tenant') && tenant()) {
            $tenant = tenant();

            // If tenant has a specific method to get connection name
            if (method_exists($tenant, 'getTenantConnectionName')) {
                return $tenant->getTenantConnectionName();
            }

            // Default tenant connection
            return 'tenant';
        }

        return config('apex.audit.audit.connection');
    }
}

if (!function_exists('apex_audit_log')) {
    /**
     * Quick helper to log a custom audit action.
     *
     * @param string $action
     * @param array $data
     * @return void
     */
    function apex_audit_log(string $action, array $data = []): void
    {
        if (!apex_audit_enabled()) {
            return;
        }

        try {
            $auditService = app(\Apex\Audit\Services\AuditService::class);
            $auditService->logCustomAction(array_merge([
                'action_type' => $action,
                'event_type' => 'custom',
            ], $data));
        } catch (\Exception $e) {
            // Log error but don't break the application
            Log::error('APEX Audit logging failed: ' . $e->getMessage());
        }
    }
}

if (!function_exists('apex_audit_ui_action')) {
    /**
     * Quick helper to log a UI action.
     *
     * @param string $action
     * @param string|null $element
     * @param array $additionalData
     * @return void
     */
    function apex_audit_ui_action(string $action, ?string $element = null, array $additionalData = []): void
    {
        if (!apex_audit_enabled() || !config('apex.audit.audit.track_ui_actions', true)) {
            return;
        }

        try {
            $auditService = app(\Apex\Audit\Services\AuditService::class);
            $auditService->logUIAction([
                'action' => $action,
                'element' => $element,
                'additional_data' => $additionalData,
            ]);
        } catch (\Exception $e) {
            // Log error but don't break the application
            Log::error('APEX UI action logging failed: ' . $e->getMessage());
        }
    }
}

if (!function_exists('apex_get_tenant_info')) {
    /**
     * Get current tenant information for audit logging.
     *
     * @return array
     */
    function apex_get_tenant_info(): array
    {
        if (!function_exists('tenant') || !tenant()) {
            return [
                'tenant_id' => 'unknown',
                'database_connection' => config('database.default'),
            ];
        }

        $tenant = tenant();

        return [
            'tenant_id' => $tenant->getTenantKey(),
            'tenant_database' => $tenant->tenancy_db_name ?? null,
            'database_connection' => apex_audit_tenant_connection(),
        ];
    }
}

if (!function_exists('apex_format_bytes')) {
    /**
     * Format bytes to human readable format.
     *
     * @param int $bytes
     * @param int $precision
     * @return string
     */
    function apex_format_bytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}

if (!function_exists('apex_anonymize_ip')) {
    /**
     * Anonymize IP address for privacy.
     *
     * @param string $ip
     * @return string
     */
    function apex_anonymize_ip(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // Anonymize IPv4 by removing last octet
            $parts = explode('.', $ip);
            $parts[3] = '0';
            return implode('.', $parts);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // Anonymize IPv6 by removing last 64 bits
            $parts = explode(':', $ip);
            for ($i = 4; $i < 8; $i++) {
                $parts[$i] = '0';
            }
            return implode(':', $parts);
        }

        return $ip;
    }
}

if (!function_exists('apex_get_model_label')) {
    /**
     * Get a human-readable label for a model class.
     *
     * @param string $modelClass
     * @return string
     */
    function apex_get_model_label(string $modelClass): string
    {
        $basename = class_basename($modelClass);

        // Convert StudlyCase to words
        $label = preg_replace('/(?<!^)[A-Z]/', ' $0', $basename);

        return $label;
    }
}

if (!function_exists('apex_lang')) {
    /**
     * Get current APEX Audit language.
     *
     * @return string
     */
    function apex_lang(): string
    {
        try {
            return app(ApexAuditLanguageService::class)->getCurrentLanguage();
        } catch (\Exception $e) {
            return config('apex.audit.language.default', 'en');
        }
    }
}

if (!function_exists('apex_supported_languages')) {
    /**
     * Get supported APEX Audit languages.
     *
     * @return array
     */
    function apex_supported_languages(): array
    {
        try {
            return app(ApexAuditLanguageService::class)->getSupportedLanguages();
        } catch (\Exception $e) {
            return config('apex.audit.language.supported', ['en' => 'English']);
        }
    }
}

if (!function_exists('apex_set_language')) {
    /**
     * Set APEX Audit language.
     *
     * @param string $language
     * @return void
     */
    function apex_set_language(string $language): void
    {
        try {
            app(ApexAuditLanguageService::class)->setLanguage($language);
        } catch (\Exception $e) {
            // Silent fail
            Log::error('Failed to set APEX language: ' . $e->getMessage());
        }
    }
}

if (!function_exists('apex_format_date')) {
    /**
     * Format date according to APEX Audit language settings.
     *
     * @param \DateTime $date
     * @param string $format
     * @param string|null $language
     * @return string
     */
    function apex_format_date(\DateTime $date, string $format = 'medium', ?string $language = null): string
    {
        try {
            return app(ApexAuditLanguageService::class)->formatDate($date, $format, $language);
        } catch (\Exception $e) {
            return $date->format('Y-m-d H:i:s');
        }
    }
}

if (!function_exists('apex_format_number')) {
    /**
     * Format number according to APEX Audit language settings.
     *
     * @param float $number
     * @param int $decimals
     * @param string|null $language
     * @return string
     */
    function apex_format_number(float $number, int $decimals = 0, ?string $language = null): string
    {
        try {
            return app(ApexAuditLanguageService::class)->formatNumber($number, $decimals, $language);
        } catch (\Exception $e) {
            return number_format($number, $decimals);
        }
    }
}
