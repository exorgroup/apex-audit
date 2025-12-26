<?php

/*
Copyright EXOR Group ltd 2025 
Version 1.0.0.0 
APEX Laravel Auditing
Description: Middleware for configuring APEX audit behavior on a per-route basis. Allows fine-grained control over audit settings including field tracking, source context, and selective disabling.
*/

namespace Apex\Audit\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class ApexAuditConfig
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string|null  $config
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, ?string $config = null): Response
    {
        // Parse configuration from middleware parameter
        $auditConfig = $this->parseConfig($config);

        // Store configuration for this request
        app()->instance('apex.audit.request.config', $auditConfig);

        // Check if audit should be disabled for this request
        if ($auditConfig['disable_audit'] ?? false) {
            config(['apex.audit.audit.enabled' => false]);
        }

        // Check if history should be disabled for this request
        if ($auditConfig['disable_history'] ?? false) {
            config(['apex.audit.history.enabled' => false]);
        }

        // Set tenant context if specified
        if (isset($auditConfig['tenant_context'])) {
            $this->setTenantContext($auditConfig['tenant_context']);
        }

        // Process the request
        $response = $next($request);

        // Log UI action if configured
        if ($auditConfig['log_ui_action'] ?? false) {
            $this->logUIAction($request, $response, $auditConfig);
        }

        return $response;
    }

    /**
     * Parse configuration string or array.
     *
     * @param  string|null  $config
     * @return array
     */
    protected function parseConfig(?string $config): array
    {
        if (empty($config)) {
            return [];
        }

        // Check if it's JSON
        if (str_starts_with($config, '{')) {
            return json_decode($config, true) ?: [];
        }

        // Parse key:value pairs
        $parsed = [];
        $pairs = explode(',', $config);

        foreach ($pairs as $pair) {
            $pair = trim($pair);
            if (strpos($pair, ':') !== false) {
                [$key, $value] = explode(':', $pair, 2);
                $key = trim($key);
                $value = trim($value);

                // Convert string boolean values
                if ($value === 'true') {
                    $value = true;
                } elseif ($value === 'false') {
                    $value = false;
                } elseif (is_numeric($value)) {
                    $value = $value + 0; // Convert to int or float
                }

                $parsed[$key] = $value;
            } else {
                // Single values are treated as flags
                $parsed[$pair] = true;
            }
        }

        return $parsed;
    }

    /**
     * Set tenant context for audit operations.
     *
     * @param  string  $tenantContext
     * @return void
     */
    protected function setTenantContext(string $tenantContext): void
    {
        // If using tenancy package, ensure we're in the right context
        if (function_exists('tenant') && !tenant()) {
            // Try to initialize tenant context if not already set
            $tenant = \App\Models\Tenant::find($tenantContext);
            if ($tenant) {
                tenancy()->initialize($tenant);
            }
        }
    }

    /**
     * Log UI action for this request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @param  array  $config
     * @return void
     */
    protected function logUIAction(Request $request, Response $response, array $config): void
    {
        if (!config('apex.audit.audit.enabled') || !config('apex.audit.audit.track_ui_actions')) {
            return;
        }

        try {
            $auditService = app(\Apex\Audit\Services\AuditService::class);

            $actionData = [
                'action' => $config['ui_action_name'] ?? $request->route()?->getName() ?? $request->path(),
                'element' => $config['source_element'] ?? null,
                'additional_data' => array_merge(
                    [
                        'method' => $request->method(),
                        'path' => $request->path(),
                        'status_code' => $response->getStatusCode(),
                        'request_data' => $this->getFilteredRequestData($request, $config),
                    ],
                    $config['additional_data'] ?? []
                ),
            ];

            $auditService->logUIAction($actionData);
        } catch (\Exception $e) {
            // Log error but don't break the request
            Log::error('Failed to log UI action in ApexAuditConfig middleware: ' . $e->getMessage());
        }
    }

    /**
     * Get filtered request data for audit logging.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  array  $config
     * @return array
     */
    protected function getFilteredRequestData(Request $request, array $config): array
    {
        $data = $request->all();

        // Remove sensitive fields
        $sensitiveFields = array_merge(
            ['password', 'password_confirmation', 'token', 'api_key'],
            $config['exclude_fields'] ?? []
        );

        foreach ($sensitiveFields as $field) {
            unset($data[$field]);
        }

        // Only include specified fields if configured
        if (!empty($config['include_fields'])) {
            $data = array_intersect_key($data, array_flip($config['include_fields']));
        }

        return $data;
    }
}
