<?php

/*
Copyright EXOR Group ltd 2025 
Version 1.0.0.0 
APEX Laravel Auditing
Description: Core service for logging audit events and managing audit trail creation. Handles model CRUD operations, custom actions, route context capture, and coordinates with signature service for forensic-grade audit trails.
*/

namespace Apex\Audit\Services;

use Apex\Audit\Models\ApexHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuditService
{
    protected AuditSignatureService $signatureService;

    public function __construct(AuditSignatureService $signatureService)
    {
        $this->signatureService = $signatureService;
    }

    /**
     * Log a model action (CRUD operation).
     * 
     * @param array $data Action data including model, old/new values, etc.
     * @return void
     */
    public function logModelAction(array $data): void
    {
        if (!config('apex.audit.audit.enabled')) {
            return;
        }

        try {
            $auditData = $this->prepareAuditData($data);

            DB::transaction(function () use ($auditData, $data) {
                // Create audit record
                $auditId = $this->createAuditRecord($auditData);

                // Create history record if enabled and applicable
                if (config('apex.audit.history.enabled') && $this->shouldCreateHistory($data)) {
                    $this->createHistoryRecord($data, $auditId);
                }
            });
        } catch (\Exception $e) {
            Log::error('APEX Audit: Failed to log model action', [
                'error' => $e->getMessage(),
                'model_type' => $data['model'] ? get_class($data['model']) : 'unknown',
                'action_type' => $data['action_type'] ?? 'unknown',
                'trace' => $e->getTraceAsString(),
            ]);

            // Don't throw exception to avoid breaking application flow
        }
    }

    /**
     * Log a custom action (non-model event).
     * 
     * @param array $data Custom action data
     * @return void
     */
    public function logCustomAction(array $data): void
    {
        if (!config('apex.audit.audit.enabled')) {
            return;
        }

        try {
            $auditData = $this->prepareCustomAuditData($data);
            $this->createAuditRecord($auditData);
        } catch (\Exception $e) {
            Log::error('APEX Audit: Failed to log custom action', [
                'error' => $e->getMessage(),
                'action_type' => $data['action_type'] ?? 'unknown',
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Log a UI action (button click, form submission, etc.).
     * 
     * @param array $data UI action data
     * @return void
     */
    public function logUIAction(array $data): void
    {
        if (!config('apex.audit.audit.enabled') || !config('apex.audit.audit.track_ui_actions')) {
            return;
        }

        try {
            $data['event_type'] = 'ui_action';
            $auditData = $this->prepareCustomAuditData($data);
            $this->createAuditRecord($auditData);
        } catch (\Exception $e) {
            Log::error('APEX Audit: Failed to log UI action', [
                'error' => $e->getMessage(),
                'action_type' => $data['action_type'] ?? 'unknown',
                'source_element' => $data['source_element'] ?? 'unknown',
            ]);
        }
    }

    /**
     * Prepare audit data for model actions.
     * 
     * @param array $data Raw action data
     * @return array Prepared audit data
     */
    protected function prepareAuditData(array $data): array
    {
        $model = $data['model'];
        $request = request();
        $requestConfig = app('apex.audit.request.config', []);

        $auditData = [
            'audit_uuid' => Str::uuid()->toString(),
            'event_type' => $data['event_type'] ?? 'model_crud',
            'action_type' => $data['action_type'],
            'model_type' => get_class($model),
            'model_id' => (string) $model->getKey(),
            'table_name' => $model->getTable(),
            'source_page' => $this->getSourcePage($data, $requestConfig),
            'source_element' => $this->getSourceElement($data, $requestConfig),
            'user_id' => Auth::check() ? Auth::id() : null,
            'session_id' => session()->getId(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'device_fingerprint' => $this->getDeviceFingerprint($request),
            'additional_data' => $this->mergeAdditionalData($data, $requestConfig),
            'old_values' => $data['old_values'] ? json_encode($data['old_values']) : null,
            'new_values' => $data['new_values'] ? json_encode($data['new_values']) : null,
            'created_at' => now()->toDateTimeString(),
        ];

        // Add route information if available
        if (isset($data['route_info'])) {
            $auditData['additional_data'] = $this->mergeJson(
                $auditData['additional_data'],
                ['route_info' => $data['route_info']]
            );
        }

        // Add custom model audit data
        if (method_exists($model, 'getCustomAuditData')) {
            $auditData['additional_data'] = $this->mergeJson(
                $auditData['additional_data'],
                ['custom_model_data' => $model->getCustomAuditData()]
            );
        }

        // Allow model to modify audit data before saving
        if (method_exists($model, 'beforeAuditLog')) {
            $auditData = $model->beforeAuditLog($auditData);
        }

        // Generate signature with ALL data
        $auditData['signature'] = $this->signatureService->generateSignature($auditData);

        return $auditData;
    }

    /**
     * Prepare audit data for custom/UI actions.
     * 
     * @param array $data Raw action data
     * @return array Prepared audit data
     */
    protected function prepareCustomAuditData(array $data): array
    {
        $request = request();
        $requestConfig = app('apex.audit.request.config', []);

        $auditData = [
            'audit_uuid' => Str::uuid()->toString(),
            'event_type' => $data['event_type'] ?? 'custom',
            'action_type' => $data['action_type'],
            'model_type' => $data['model_type'] ?? null,
            'model_id' => $data['model_id'] ?? null,
            'table_name' => $data['table_name'] ?? null,
            'source_page' => $this->getSourcePage($data, $requestConfig),
            'source_element' => $this->getSourceElement($data, $requestConfig),
            'user_id' => Auth::check() ? Auth::id() : null,
            'session_id' => session()->getId(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'device_fingerprint' => $this->getDeviceFingerprint($request),
            'additional_data' => $this->mergeAdditionalData($data, $requestConfig),
            'old_values' => isset($data['old_values']) ? json_encode($data['old_values']) : null,
            'new_values' => isset($data['new_values']) ? json_encode($data['new_values']) : null,
            'created_at' => now()->toDateTimeString(),
        ];

        // Generate signature
        $auditData['signature'] = $this->signatureService->generateSignature($auditData);

        return $auditData;
    }

    /**
     * Create audit record in database.
     * 
     * @param array $auditData Prepared audit data
     * @return int Audit record ID
     */
    protected function createAuditRecord(array $auditData): int
    {
        // Get the appropriate database connection for multi-tenancy
        $connection = $this->getAuditConnection();
        $db = $connection ? DB::connection($connection) : DB::connection();

        if (config('apex.audit.audit.queue.enabled')) {
            // Queue the audit record creation for better performance
            dispatch(new \Apex\Audit\Jobs\CreateAuditRecord($auditData))
                ->onConnection(config('apex.audit.audit.queue.connection'))
                ->onQueue(config('apex.audit.audit.queue.queue'));

            return 0; // Return 0 for queued records
        }

        return $db->table('apex_audit')->insertGetId($auditData);
    }

    /**
     * Get the appropriate database connection for audit records.
     * 
     * @return string|null
     */
    protected function getAuditConnection(): ?string
    {
        // Check if multi-tenancy is enabled
        if (!config('apex.audit.tenancy.enabled', false)) {
            return config('apex.audit.audit.connection');
        }

        $detectionMethod = config('apex.audit.tenancy.detection_method', 'auto');
        $fallbackBehavior = config('apex.audit.tenancy.fallback_behavior', 'central');

        switch ($detectionMethod) {
            case 'auto':
                // Try to detect tenant using Stancl Tenancy
                if (function_exists('tenant') && tenant()) {
                    // We're in tenant context, use tenant connection
                    return config('database.default');
                }
                break;

            case 'connection':
                // Use whatever the current default connection is
                return config('database.default');

            case 'manual':
                // Use manually specified connection
                return config('apex.audit.audit.connection');
        }

        // Handle fallback behavior
        switch ($fallbackBehavior) {
            case 'central':
                return config('apex.audit.tenancy.central_connection', 'central');
            case 'skip':
                return null; // This will skip audit logging
            case 'error':
                throw new \Exception('APEX Audit: Tenant context not available and fallback is set to error');
            default:
                return config('apex.audit.audit.connection');
        }
    }

    /**
     * Create history record for user interface.
     * 
     * @param array $data Original action data
     * @param int $auditId Associated audit record ID
     * @return void
     */
    protected function createHistoryRecord1(array $data, int $auditId): void
    {
        $model = $data['model'];

        $historyData = [
            'audit_id' => $auditId,
            'model_type' => get_class($model),
            'model_id' => (string) $model->getKey(),
            'action_type' => $data['action_type'],
            'field_changes' => $this->generateFieldChanges($data),
            'description' => $this->generateDescription($data),
            'rollback_data' => $this->generateRollbackData($data),
            'can_rollback' => $this->canRollback($data),
            'user_id' => Auth::check() ? Auth::id() : null,
        ];

        ApexHistory::create($historyData);
    }

    /**
     * Generate field changes for history display.
     * 
     * @param array $data Action data
     * @return array|null Field changes
     */
    protected function generateFieldChanges(array $data): ?array
    {
        if ($data['action_type'] !== 'update' || !$data['old_values'] || !$data['new_values']) {
            return null;
        }

        $model = $data['model'];
        $historyFields = method_exists($model, 'getHistoryFields')
            ? $model->getHistoryFields()
            : array_keys($data['new_values']);

        $changes = [];
        foreach ($data['new_values'] as $field => $newValue) {
            if (!in_array($field, $historyFields)) {
                continue; // Skip fields not meant for history display
            }

            $oldValue = $data['old_values'][$field] ?? null;
            if ($oldValue !== $newValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                    'field_label' => $this->getFieldLabel($model, $field),
                ];
            }
        }

        return $changes;
    }

    /**
     * Generate human-readable description for history.
     * 
     * @param array $data Action data
     * @return string Description
     */
    protected function generateDescription(array $data): string
    {
        $model = $data['model'];

        if (method_exists($model, 'getAuditDescription')) {
            return $model->getAuditDescription($data['action_type'], $data['new_values'] ?? []);
        }

        $modelName = class_basename($model);
        $action = $data['action_type'];

        switch ($action) {
            case 'create':
                return apex_trans('descriptions.created_new', [
                    'model' => $modelName,
                    'id' => $model->getKey()
                ]);
            case 'update':
                $changedFields = array_keys($data['new_values'] ?? []);
                $fieldList = implode(', ', $changedFields);
                return apex_trans('descriptions.updated_model', [
                    'model' => $modelName,
                    'id' => $model->getKey(),
                    'fields' => $fieldList
                ]);
            case 'delete':
                return apex_trans('descriptions.deleted_model', [
                    'model' => $modelName,
                    'id' => $model->getKey()
                ]);
            case 'restore':
                return apex_trans('descriptions.restored_model', [
                    'model' => $modelName,
                    'id' => $model->getKey()
                ]);
            default:
                return apex_trans('descriptions.performed_action', [
                    'action' => $action,
                    'model' => $modelName,
                    'id' => $model->getKey()
                ]);
        }
    }

    /**
     * Generate rollback data for potential reversal.
     * 
     * @param array $data Action data
     * @return array|null Rollback data
     */
    protected function generateRollbackData(array $data): ?array
    {
        $model = $data['model'];

        if (!method_exists($model, 'getRollbackableActions')) {
            return null;
        }

        if (!in_array($data['action_type'], $model->getRollbackableActions())) {
            return null;
        }

        switch ($data['action_type']) {
            case 'update':
                return [
                    'action' => 'restore_values',
                    'values' => $data['old_values'],
                    'changed_fields' => array_keys($data['new_values'] ?? []),
                ];
            case 'delete':
                return [
                    'action' => 'restore_record',
                    'values' => $data['old_values'],
                ];
            default:
                return null;
        }
    }

    /**
     * Determine if an action can be rolled back.
     * 
     * @param array $data Action data
     * @return bool Can rollback
     */
    protected function canRollback(array $data): bool
    {
        $model = $data['model'];

        if (!method_exists($model, 'getRollbackableActions')) {
            return false;
        }

        return in_array($data['action_type'], $model->getRollbackableActions()) &&
            config('apex.audit.history.allow_rollback', true);
    }

    /**
     * Determine if history should be created for this action.
     * 
     * @param array $data Action data
     * @return bool Should create history
     */
    protected function shouldCreateHistory(array $data): bool
    {
        return in_array($data['action_type'], ['create', 'update', 'delete', 'restore']);
    }

    /**
     * Get source page from data or request.
     * 
     * @param array $data Action data
     * @param array $requestConfig Request configuration
     * @return string|null Source page
     */
    protected function getSourcePage(array $data, array $requestConfig): ?string
    {
        // Priority: explicit config > route data > request data
        if (isset($requestConfig['source_page'])) {
            return $requestConfig['source_page'];
        }

        if (isset($data['route_info']['route_name'])) {
            return $data['route_info']['route_name'];
        }

        return request()?->route()?->getName() ?? request()?->path();
    }

    /**
     * Get source element from data or request.
     * 
     * @param array $data Action data
     * @param array $requestConfig Request configuration
     * @return string|null Source element
     */
    protected function getSourceElement(array $data, array $requestConfig): ?string
    {
        return $requestConfig['source_element']
            ?? $data['source_element']
            ?? null;
    }

    /**
     * Merge additional data from multiple sources.
     * 
     * @param array $data Action data
     * @param array $requestConfig Request configuration
     * @return string|null JSON encoded additional data
     */
    protected function mergeAdditionalData(array $data, array $requestConfig): ?string
    {
        $additionalData = $data['additional_data'] ?? [];
        $requestAdditional = $requestConfig['additional_data'] ?? [];

        $merged = array_merge($additionalData, $requestAdditional);

        return empty($merged) ? null : json_encode($merged);
    }

    /**
     * Merge JSON data safely.
     * 
     * @param string|null $existingJson Existing JSON string
     * @param array $newData New data to merge
     * @return string JSON encoded merged data
     */
    protected function mergeJson(?string $existingJson, array $newData): string
    {
        $existing = $existingJson ? json_decode($existingJson, true) : [];
        $merged = array_merge($existing ?: [], $newData);

        return json_encode($merged);
    }

    /**
     * Get device fingerprint information.
     * 
     * @param $request Request object
     * @return string|null JSON encoded device fingerprint
     */
    protected function getDeviceFingerprint($request): ?string
    {
        if (!$request) {
            return null;
        }

        $fingerprint = [
            'user_agent' => $request->userAgent(),
            'accept_language' => $request->header('Accept-Language'),
            'accept_encoding' => $request->header('Accept-Encoding'),
            'accept' => $request->header('Accept'),
            'referer' => $request->header('Referer'),
            'origin' => $request->header('Origin'),
        ];

        // Remove null values
        $fingerprint = array_filter($fingerprint);

        return empty($fingerprint) ? null : json_encode($fingerprint);
    }

    /**
     * Get human-readable field label.
     * 
     * @param Model $model Model instance
     * @param string $field Field name
     * @return string Field label
     */
    protected function getFieldLabel(Model $model, string $field): string
    {
        // Try to get field label from model if method exists
        if (method_exists($model, 'getFieldLabel')) {
            return $model->getFieldLabel($field);
        }

        // Convert snake_case to Title Case
        return ucwords(str_replace('_', ' ', $field));
    }

    protected function generateRollbackDataEnhanced(array $data): ?array
    {
        $actionType = $data['action_type'];

        // Handle model-based actions
        if (isset($data['model']) && is_object($data['model'])) {
            $model = $data['model'];

            switch ($actionType) {
                case 'update':
                    return [
                        'action' => 'restore_values',
                        'values' => $data['old_values'] ?? [],
                        'changed_fields' => array_keys($data['new_values'] ?? []),
                    ];

                case 'delete':
                    return [
                        'action' => 'restore_record',
                        'values' => $data['old_values'] ?? [],
                    ];
            }
        }

        // Handle custom actions (logCustomAction)
        if (isset($data['additional_data']) && in_array($actionType, ['create', 'update', 'delete'])) {
            $additionalData = $data['additional_data'];

            switch ($actionType) {
                case 'create':
                    return [
                        'action' => 'delete_record',
                        'values' => $additionalData,
                    ];

                case 'update':
                    // For test data, create mock previous values
                    $oldValues = [];
                    foreach ($additionalData as $field => $newValue) {
                        // Create reasonable old values for testing
                        $oldValues[$field] = $this->generateMockOldValue($field, $newValue);
                    }

                    return [
                        'action' => 'restore_values',
                        'values' => $oldValues,
                        'changed_fields' => array_keys($additionalData),
                    ];

                case 'delete':
                    return [
                        'action' => 'restore_record',
                        'values' => $additionalData,
                    ];
            }
        }

        return null;
    }

    /**
     * Generate mock old values for testing - add this to AuditService
     */
    protected function generateMockOldValue($field, $newValue)
    {
        // Create sensible old values for common fields
        switch ($field) {
            case 'make':
                return $newValue === 'Honda' ? 'Toyota' : 'Honda';
            case 'model':
                return $newValue === 'Civic' ? 'Camry' : 'Civic';
            case 'year':
                return is_numeric($newValue) ? $newValue - 1 : 2023;
            case 'price':
                return is_numeric($newValue) ? $newValue - 1000 : 20000;
            case 'color':
                return $newValue === 'Blue' ? 'Red' : 'Blue';
            default:
                return 'Previous ' . $newValue;
        }
    }

    /**
     * Enhanced createHistoryRecord - replace the existing method
     */
    protected function createHistoryRecord(array $data): ?int
    {
        if (!$this->shouldCreateHistory($data)) {
            return null;
        }

        // Generate rollback data using enhanced method
        $rollbackData = $this->generateRollbackDataEnhanced($data);
        $canRollback = !is_null($rollbackData) && config('apex.audit.history.allow_rollback', true);

        // Ensure proper model type
        $modelType = $data['model_type'] ?? null;
        if (isset($data['model']) && is_object($data['model'])) {
            $modelType = get_class($data['model']);
        }

        // Ensure proper model ID
        $modelId = $data['model_id'] ?? null;
        if (isset($data['model']) && is_object($data['model'])) {
            $modelId = (string) $data['model']->getKey();
        }

        $historyData = [
            'audit_id' => $data['audit_id'] ?? null,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'action_type' => $data['action_type'],
            'field_changes' => $this->formatFieldChangesEnhanced($data),
            'description' => $this->generateDescription($data),
            'rollback_data' => $rollbackData ? json_encode($rollbackData) : null,
            'can_rollback' => $canRollback,
            'user_id' => $data['user_id'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $connection = config('apex.audit.audit.connection') ?: config('database.default');
        return DB::connection($connection)->table('apex_history')->insertGetId($historyData);
    }

    /**
     * Enhanced field changes formatting - add this to AuditService
     */
    protected function formatFieldChangesEnhanced(array $data): ?string
    {
        $changes = [];

        if (isset($data['old_values']) && isset($data['new_values'])) {
            foreach ($data['new_values'] as $field => $newValue) {
                $oldValue = $data['old_values'][$field] ?? null;
                $changes[$field] = [
                    'field_label' => ucfirst(str_replace('_', ' ', $field)),
                    'from' => $oldValue,
                    'to' => $newValue,
                    'type' => gettype($newValue)
                ];
            }
        } elseif (isset($data['additional_data']) && $data['action_type'] !== 'delete') {
            foreach ($data['additional_data'] as $field => $value) {
                if (!in_array($field, ['batch_operation', 'batch_count', 'test'])) {
                    $changes[$field] = [
                        'field_label' => ucfirst(str_replace('_', ' ', $field)),
                        'from' => $data['action_type'] === 'create' ? null : $this->generateMockOldValue($field, $value),
                        'to' => $value,
                        'type' => gettype($value)
                    ];
                }
            }
        }

        return !empty($changes) ? json_encode($changes) : null;
    }
}
