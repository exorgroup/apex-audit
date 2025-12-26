<?php

/*
Copyright EXOR Group ltd 2025 
Version 1.0.0.0 
APEX Laravel Auditing
Description: Service provider for APEX Laravel Auditing package. Registers audit services, observers, and configuration for comprehensive forensic-grade audit trails with digital signatures and rollback capabilities.
*/

namespace Apex\Audit;

use Illuminate\Support\ServiceProvider;
use Apex\Audit\Services\AuditService;
use Apex\Audit\Services\AuditSignatureService;
use Apex\Audit\Services\HistoryService;
use Apex\Audit\Services\RollbackService;
use Apex\Audit\Services\ApexAuditLanguageService;
use Apex\Audit\Middleware\ApexAuditConfig;
use Apex\Audit\Console\Commands\AuditVerifyCommand;
use Apex\Audit\Console\Commands\AuditCleanupCommand;
use Apex\Audit\Console\Commands\GenerateAuditKeyCommand;

class AuditServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/audit.php',
            'apex.audit'
        );

        $this->app->singleton(AuditSignatureService::class);
        $this->app->singleton(AuditService::class);
        $this->app->singleton(HistoryService::class);
        $this->app->singleton(RollbackService::class);
        $this->app->singleton(ApexAuditLanguageService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'apex-audit');
        $this->registerMigrations();
        $this->registerMiddleware();
        $this->registerCommands();
        $this->publishAssets();
    }

    /**
     * Register audit migrations.
     */
    protected function registerMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    /**
     * Register middleware.
     */
    protected function registerMiddleware(): void
    {
        $this->app['router']->aliasMiddleware('apex.audit.config', ApexAuditConfig::class);
    }

    /**
     * Register console commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditVerifyCommand::class,
                AuditCleanupCommand::class,
                GenerateAuditKeyCommand::class,
            ]);
        }
    }

    /**
     * Publish package assets.
     */
    protected function publishAssets(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/audit.php' => config_path('apex/audit.php'),
            ], 'apex-audit-config');

            // Smart migration publishing based on architecture detection
            $isMultiTenant = $this->detectMultiTenancy();
            $migrationPath = $isMultiTenant 
                ? database_path('migrations/tenant')
                : database_path('migrations');

            $this->publishes([
                __DIR__ . '/../database/migrations' => $migrationPath,
            ], 'apex-audit-migrations');

            $this->publishes([
                __DIR__ . '/../resources/lang' => resource_path('lang/vendor/apex-audit'),
            ], 'apex-audit-lang');
        }

        // Load helper functions
        if (file_exists(__DIR__ . '/helpers.php')) {
            require_once __DIR__ . '/helpers.php';
        }
    }

    /**
     * Detect if the application uses multi-tenancy architecture.
     *
     * @return bool
     */
    protected function detectMultiTenancy(): bool
    {
        try {
            // 1. Explicit configuration wins (most reliable)
            $enabled = config('apex.audit.tenancy.enabled', 'auto');
            if ($enabled !== 'auto') {
                return (bool) $enabled;
            }

            // 2. Check for tenant migrations folder (very reliable)
            if (is_dir(database_path('migrations/tenant'))) {
                return true;
            }

            // 3. Check for Stancl Tenancy package (reliable)
            if (class_exists('\Stancl\Tenancy\TenancyServiceProvider')) {
                return true;
            }

            // 4. Default to single-tenant (fallback)
            return false;

        } catch (\Exception $e) {
            // Log warning and default to safe option
            \Illuminate\Support\Facades\Log::warning('APEX Audit: Could not detect tenancy mode, defaulting to single-tenant', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
