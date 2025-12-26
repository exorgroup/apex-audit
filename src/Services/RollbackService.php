<?php

/*
Copyright EXOR Group ltd 2025 
Version 1.0.0.0 
APEX Laravel Auditing
Description: Service for handling rollback operations in APEX auditing system. Provides safe restoration of previous model states with validation, permission checking, and audit trail creation for rollback actions.
*/

namespace Apex\Audit\Services;

use Apex\Audit\Models\ApexHistory;
use Apex\Audit\Exceptions\RollbackException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class RollbackService
{
    protected AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Rollback a history record with comprehensive error handling.
     * 
     * @param int $historyId History record ID to rollback
     * @return array Result with success flag and error details
     */
    public function rollback(int $historyId): array
    {
        Log::info('=== rollback entry ===');

        try {
            $history = ApexHistory::findOrFail($historyId);

            // Validate rollback is possible
            $this->validateRollback($history);

            $success = DB::transaction(function () use ($history) {
                // Perform the actual rollback
                $success = $this->performRollback($history);
                Log::info('=== rollback entry a ===');
                if ($success) {
                    // Mark as rolled back
                    Log::info('=== rollback entry b ===');
                    $this->markAsRolledBack($history);

                    Log::info('=== rollback entry c===');
                    // Log the rollback action
                    $this->logRollbackAction($history);
                }
                Log::info('=== rollback exit ===');

                return $success;
            });

            return [
                'success' => $success,
                'error' => null,
                'error_type' => null,
                'user_message' => null,
                'technical_details' => null
            ];
        } catch (RollbackException $e) {
            Log::error('APEX Audit: Rollback failed (RollbackException)', [
                'history_id' => $historyId,
                'error_type' => $e->getType(),
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
            ]);

            return [
                'success' => false,
                'error' => $e->getUserMessage(),
                'error_type' => $e->getType() ?? 'rollback_exception',
                'user_message' => $e->getUserMessage(),
                'technical_details' => $e->getMessage()
            ];
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('APEX Audit: Rollback failed (Database Error)', [
                'history_id' => $historyId,
                'error' => $e->getMessage(),
                'sql' => $e->getSql(),
                'bindings' => $e->getBindings(),
            ]);

            $userMessage = $this->getDatabaseErrorMessage($e);

            return [
                'success' => false,
                'error' => $userMessage,
                'error_type' => 'database_error',
                'user_message' => $userMessage,
                'technical_details' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            Log::error('APEX Audit: Rollback failed (General Exception)', [
                'history_id' => $historyId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'An unexpected error occurred during rollback',
                'error_type' => 'system_error',
                'user_message' => 'A technical error occurred. Please try again or contact support.',
                'technical_details' => $e->getMessage()
            ];
        }
    }

    /**
     * Attempt rollback with detailed error reporting.
     * 
     * @param int $historyId
     * @return array
     */
    public function attemptRollback(int $historyId): array
    {
        $result = $this->rollback($historyId);

        // Add additional context for UI display
        if (!$result['success']) {
            $result['display_message'] = $this->getDisplayMessage($result['error_type'], $result['user_message']);
            $result['can_retry'] = $this->canRetryRollback($result['error_type']);
            $result['suggested_action'] = $this->getSuggestedAction($result['error_type']);
        }

        return $result;
    }

    /**
     * Get user-friendly database error messages.
     * 
     * @param \Illuminate\Database\QueryException $e
     * @return string
     */
    protected function getDatabaseErrorMessage(\Illuminate\Database\QueryException $e): string
    {
        $errorMessage = $e->getMessage();

        // Handle common database errors
        if (str_contains($errorMessage, 'Duplicate entry')) {
            if (str_contains($errorMessage, 'vin_unique')) {
                return 'Cannot restore: A car with this VIN already exists';
            }
            return 'Cannot restore: A record with this unique identifier already exists';
        }

        if (str_contains($errorMessage, 'foreign key constraint')) {
            return 'Cannot rollback: This action would violate data relationships';
        }

        if (str_contains($errorMessage, "doesn't exist")) {
            return 'Cannot rollback: The target table or record no longer exists';
        }

        if (str_contains($errorMessage, 'Data too long')) {
            return 'Cannot rollback: Some data values are too large for the database fields';
        }

        // Generic database error
        return 'Database error occurred during rollback. Please check the data and try again.';
    }

    /**
     * Get display message for UI.
     * 
     * @param string $errorType
     * @param string $userMessage
     * @return string
     */
    protected function getDisplayMessage(string $errorType, string $userMessage): string
    {
        return match ($errorType) {
            'database_error' => "❌ {$userMessage}",
            'rollback_exception' => "⚠️ {$userMessage}",
            'system_error' => "🔧 {$userMessage}",
            default => "❌ {$userMessage}"
        };
    }

    /**
     * Check if rollback can be retried.
     * 
     * @param string $errorType
     * @return bool
     */
    protected function canRetryRollback(string $errorType): bool
    {
        return match ($errorType) {
            'database_error' => false, // Usually permanent issues
            'rollback_exception' => false, // Business logic issues
            'system_error' => true, // Might be temporary
            default => false
        };
    }

    /**
     * Get suggested action for error type.
     * 
     * @param string $errorType
     * @return string
     */
    protected function getSuggestedAction(string $errorType): string
    {
        return match ($errorType) {
            'database_error' => 'Check for duplicate records or data conflicts and resolve them first.',
            'rollback_exception' => 'This action cannot be rolled back due to business rules.',
            'system_error' => 'Please try again. If the problem persists, contact support.',
            default => 'Please contact support for assistance.'
        };
    }

    /**
     * Legacy rollback method for backward compatibility.
     * 
     * @param int $historyId History record ID to rollback
     * @return bool True if rollback was successful
     * @throws RollbackException If rollback fails
     */
    public function rollbackLegacy(int $historyId): bool
    {
        $result = $this->rollback($historyId);

        if (!$result['success']) {
            throw new RollbackException($result['technical_details'] ?? $result['error']);
        }

        return $result['success'];
    }

    /**
     * Validate that a rollback can be performed.
     * 
     * @param ApexHistory $history
     * @throws RollbackException
     */
    protected function validateRollback(ApexHistory $history): void
    {
        Log::info('=== validateRollback entry ===');

        Log::info('=== validateRollback 1 ===');
        // Check if already rolled back
        if ($history->isRolledBack()) {
            throw new RollbackException('This action has already been rolled back.');
        }

        Log::info('=== validateRollback 2 ===');
        // Check if rollback is allowed for this record
        if (!$history->can_rollback) {
            throw new RollbackException('This action cannot be rolled back.');
        }

        Log::info('=== validateRollback 3 ===');
        // Check if rollback is globally enabled
        if (!config('apex.audit.history.allow_rollback', true)) {
            throw new RollbackException('Rollback functionality is disabled.');
        }

        Log::info('=== validateRollback 4 ===');
        // Check user permissions
        try {
            if (!$history->canBeRolledBack()) {
                Log::info('=== validateRollback 4a ===');
                throw new RollbackException('You do not have permission to rollback this action.');
                Log::info('=== validateRollback 4b ===');
            }
        } catch (\Exception $e) {
            Log::info('=== validateRollback 4 exception ===');
            Log::info($e->getMessage());
            Log::info('=== e ===');
            Log::info($e);
        }

        Log::info('=== validateRollback 5 ===');
        // Check if rollback data exists
        if (!$history->rollback_data) {
            throw new RollbackException('No rollback data available for this action.');
        }

        try {
            Log::info('=== validateRollback 6 ===');
            // Check if model still exists (for updates)
            Log::info('=== validateRollback 6 a ===');
            if ($history->action_type === 'update') {
                Log::info('=== validateRollback 6 b ===');
                $model = $history->getAuditedModel();
                Log::info('=== validateRollback 6 c===');
                if (!$model) {
                    throw new RollbackException('The original record no longer exists and cannot be updated.');
                }
            }
        } catch (\Exception $e) {
            Log::info('=== validateRollback 6 exception ===');
            Log::info($e->getMessage());
        }
        Log::info('=== validateRollback exit ===');
    }

    /**
     * Perform the actual rollback operation.
     * 
     * @param ApexHistory $history
     * @return bool
     * @throws RollbackException
     */
    protected function performRollback(ApexHistory $history): bool
    {
        Log::info('=== performRollback entry ===');
        $rollbackData = $history->rollback_data;

        switch ($rollbackData['action']) {
            case 'restore_values':
                Log::info('=== performRollback 1 exit ===');
                return $this->rollbackUpdate($history, $rollbackData);

            case 'restore_record':
                Log::info('=== performRollback 2 exit ===');
                return $this->rollbackDelete($history, $rollbackData);

            default:
                Log::info('=== performRollback 3 exit ===');
                throw new RollbackException("Unknown rollback action: {$rollbackData['action']}");
        }
    }

    /**
     * Rollback an update operation by restoring previous values.
     * 
     * @param ApexHistory $history
     * @param array $rollbackData
     * @return bool
     * @throws RollbackException
     */
    protected function rollbackUpdate(ApexHistory $history, array $rollbackData): bool
    {
        Log::info('=== rollbackUpdate entry ===');
        $model = $history->getAuditedModel();

        if (!$model) {
            throw new RollbackException('Model not found for rollback update.');
        }

        $previousValues = $rollbackData['values'];
        $changedFields = $rollbackData['changed_fields'] ?? array_keys($previousValues);

        // Validate that we have permission to update these fields
        if (method_exists($model, 'getAuditableFields')) {
            $auditableFields = $model->getAuditableFields();
            $invalidFields = array_diff($changedFields, $auditableFields);

            if (!empty($invalidFields)) {
                throw new RollbackException('Cannot rollback non-auditable fields: ' . implode(', ', $invalidFields));
            }
        }

        // Store current values before rollback
        $currentValues = $model->only($changedFields);

        // Apply the rollback values
        foreach ($previousValues as $field => $value) {
            if (in_array($field, $changedFields)) {
                $model->$field = $value;
            }
        }

        // Save the model (this will trigger audit observers)
        $saved = $model->save();

        if (!$saved) {
            throw new RollbackException('Failed to save model during rollback.');
        }
        Log::info('=== rollbackUpdate exit ===');

        return true;
    }

    /**
     * Rollback a delete operation by restoring the record.
     * 
     * @param ApexHistory $history
     * @param array $rollbackData
     * @return bool
     * @throws RollbackException
     */
    protected function rollbackDelete(ApexHistory $history, array $rollbackData): bool
    {
        Log::info('=== rollbackDelete entry ===');
        $modelClass = $history->model_type;
        $modelId = $history->model_id;
        $restoredValues = $rollbackData['values'];

        if (!class_exists($modelClass)) {
            throw new RollbackException("Model class {$modelClass} does not exist.");
        }

        // Check if record already exists (might have been restored manually)
        $existingModel = $modelClass::find($modelId);
        if ($existingModel) {
            throw new RollbackException('Record already exists and cannot be restored.');
        }

        // Try soft delete restore first if the model supports it
        if (method_exists($modelClass, 'withTrashed')) {
            $trashedModel = $modelClass::withTrashed()->find($modelId);
            if ($trashedModel && $trashedModel->trashed()) {
                $restored = $trashedModel->restore();
                if ($restored) {
                    return true;
                }
            }
        }

        // Hard restore by recreating the record
        try {
            $newModel = new $modelClass();

            // Set the primary key if it was provided
            if (isset($restoredValues[$newModel->getKeyName()])) {
                $newModel->{$newModel->getKeyName()} = $restoredValues[$newModel->getKeyName()];
            }

            // Fill with restored values
            $fillableValues = array_intersect_key($restoredValues, array_flip($newModel->getFillable()));
            $newModel->fill($fillableValues);

            // Set timestamps if they exist
            if ($newModel->usesTimestamps()) {
                $newModel->created_at = $restoredValues['created_at'] ?? now();
                $newModel->updated_at = $restoredValues['updated_at'] ?? now();
            }

            $saved = $newModel->save();

            if (!$saved) {
                throw new RollbackException('Failed to save restored model.');
            }
            Log::info('=== rollbackDelete exit ===');

            return true;
        } catch (\Exception $e) {
            throw new RollbackException("Failed to restore deleted record: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Mark history record as rolled back.
     * 
     * @param ApexHistory $history
     */
    protected function markAsRolledBack(ApexHistory $history): void
    {
        Log::info('=== markAsRolledBack entry ===');

        Log::info('$history');
        Log::info($history);

        $history->update([
            'can_rollback' => false,
            'rolled_back_at' => now(),
            'rolled_back_by' => Auth::check() ? Auth::id() : null,
        ]);

        Log::info('$history 2');
        Log::info($history);
        Log::info('=== markAsRolledBack exit ===');
    }

    /**
     * Log the rollback action itself.
     * 
     * @param ApexHistory $history
     */
    protected function logRollbackAction(ApexHistory $history): void
    {
        Log::info('=== logRollbackAction entry ===');
        $this->auditService->logCustomAction([
            'event_type' => 'rollback_action',
            'action_type' => 'rollback',
            'model_type' => $history->model_type,
            'model_id' => $history->model_id,
            'table_name' => null,
            'additional_data' => [
                'original_history_id' => $history->id,
                'original_action' => $history->action_type,
                'rollback_timestamp' => now()->toISOString(),
                'rollback_user_id' => Auth::check() ? Auth::id() : null,
            ],
            'source_element' => 'rollback-action',
        ]);
        Log::info('=== logRollbackAction exit ===');
    }

    /**
     * Batch rollback multiple history records.
     * 
     * @param array $historyIds Array of history record IDs
     * @return array Results with success/failure for each ID
     */
    public function batchRollback(array $historyIds): array
    {
        Log::info('=== batchRollback entry ===');
        $results = [];

        foreach ($historyIds as $historyId) {
            $result = $this->rollback($historyId);
            $results[$historyId] = $result;
        }
        Log::info('=== batchRollback exit ===');

        return $results;
    }

    /**
     * Preview what would happen during a rollback without actually performing it.
     * 
     * @param int $historyId
     * @return array Preview information
     * @throws RollbackException
     */
    public function previewRollback(int $historyId): array
    {
        Log::info('=== previewRollback entry ===');
        $history = ApexHistory::findOrFail($historyId);

        // Validate rollback is possible
        $this->validateRollback($history);

        $rollbackData = $history->rollback_data;
        $preview = [
            'history_id' => $historyId,
            'action_type' => $history->action_type,
            'model_type' => $history->model_type,
            'model_id' => $history->model_id,
            'rollback_action' => $rollbackData['action'],
            'description' => $this->getRollbackDescription($history),
        ];

        switch ($rollbackData['action']) {
            case 'restore_values':
                $model = $history->getAuditedModel();
                if ($model) {
                    $currentValues = $model->only(array_keys($rollbackData['values']));
                    $preview['changes'] = [
                        'current_values' => $currentValues,
                        'will_restore_to' => $rollbackData['values'],
                        'affected_fields' => array_keys($rollbackData['values']),
                    ];
                }
                break;

            case 'restore_record':
                $preview['restoration'] = [
                    'will_restore' => true,
                    'restored_values' => $rollbackData['values'],
                    'restoration_method' => $this->getRestorationMethod($history),
                ];
                break;
        }
        Log::info('=== previewRollback exit ===');

        return $preview;
    }

    /**
     * Get a human-readable description of what the rollback will do.
     * 
     * @param ApexHistory $history
     * @return string
     */
    protected function getRollbackDescription(ApexHistory $history): string
    {
        Log::info('=== getRollbackDescription entry ===');
        $modelName = class_basename($history->model_type);

        switch ($history->action_type) {
            case 'update':
                Log::info('=== getRollbackDescription update exit ===');
                $fieldCount = count($history->rollback_data['values'] ?? []);
                return "Restore {$fieldCount} field(s) of {$modelName} #{$history->model_id} to previous values";

            case 'delete':
                Log::info('=== getRollbackDescription delete exit ===');
                return "Restore deleted {$modelName} #{$history->model_id}";

            default:
                Log::info('=== getRollbackDescription default exit ===');
                return "Rollback {$history->action_type} action on {$modelName} #{$history->model_id}";
        }
    }

    /**
     * Determine the restoration method for deleted records.
     * 
     * @param ApexHistory $history
     * @return string
     */
    protected function getRestorationMethod(ApexHistory $history): string
    {
        Log::info('=== getRestorationMethod entry ===');
        $modelClass = $history->model_type;

        if (!class_exists($modelClass)) {
            return 'unknown';
        }

        // Check if soft deleted record exists
        if (method_exists($modelClass, 'withTrashed')) {
            $trashedModel = $modelClass::withTrashed()->find($history->model_id);
            if ($trashedModel && $trashedModel->trashed()) {
                return 'soft_delete_restore';
            }
        }
        Log::info('=== getRestorationMethod exit ===');

        return 'hard_restore';
    }

    /**
     * Get rollback statistics.
     * 
     * @param string|null $modelType Filter by model type
     * @param int|null $days Number of days to look back
     * @return array
     */
    public function getRollbackStatistics(?string $modelType = null, ?int $days = null): array
    {
        Log::info('=== getRollbackStatistics entry ===');
        $query = ApexHistory::query();

        if ($modelType) {
            $query->where('model_type', $modelType);
        }

        if ($days) {
            $query->where('created_at', '>=', now()->subDays($days));
        }

        $total = $query->count();
        $rollbackable = $query->where('can_rollback', true)->count();
        $rolledBack = $query->whereNotNull('rolled_back_at')->count();

        Log::info('=== getRollbackStatistics exit ===');
        return [
            'total_actions' => $total,
            'rollbackable_actions' => $rollbackable,
            'rolled_back_actions' => $rolledBack,
            'rollback_rate' => $rollbackable > 0 ? round(($rolledBack / $rollbackable) * 100, 2) : 0,
            'by_action_type' => $query->select('action_type', DB::raw('count(*) as count'))
                ->groupBy('action_type')
                ->pluck('count', 'action_type')
                ->toArray(),
        ];
    }
}
