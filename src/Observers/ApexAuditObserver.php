<?php

/*
Copyright EXOR Group ltd 2025 
Version 1.0.0.0 
APEX Laravel Auditing
Description: Eloquent observer for APEX auditing that automatically tracks model CRUD operations. Provides intelligent filtering of auditable events and fields with seamless integration into Laravel's model lifecycle.
*/

namespace Apex\Audit\Observers;

use Apex\Audit\Services\AuditService;
use Illuminate\Database\Eloquent\Model;

class ApexAuditObserver
{
    protected AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Handle the model "created" event.
     * 
     * @param Model $model
     * @return void
     */
    public function created(Model $model): void
    {
        if (!$this->shouldAudit($model, 'create')) {
            return;
        }

        $this->auditService->logModelAction([
            'event_type' => 'model_crud',
            'action_type' => 'create',
            'model' => $model,
            'old_values' => null,
            'new_values' => $model->getAuditableAttributes(),
            'route_info' => $this->getRouteInfo(),
        ]);

        // Call after audit hook if available
        if (method_exists($model, 'afterAuditLog')) {
            $model->afterAuditLog([
                'action_type' => 'create',
                'new_values' => $model->getAuditableAttributes(),
            ]);
        }
    }

    /**
     * Handle the model "updated" event.
     * 
     * @param Model $model
     * @return void
     */
    public function updated(Model $model): void
    {
        if (!$this->shouldAudit($model, 'update')) {
            return;
        }

        $original = $model->getAuditableAttributes($model->getOriginal());
        $dirty = $model->getAuditableAttributes($model->getDirty());

        // Filter out changes that shouldn't be tracked
        $filteredChanges = $this->filterChanges($model, $original, $dirty);

        if (empty($filteredChanges)) {
            return; // No trackable changes
        }

        $this->auditService->logModelAction([
            'event_type' => 'model_crud',
            'action_type' => 'update',
            'model' => $model,
            'old_values' => array_intersect_key($original, $filteredChanges),
            'new_values' => $filteredChanges,
            'route_info' => $this->getRouteInfo(),
        ]);

        // Call after audit hook if available
        if (method_exists($model, 'afterAuditLog')) {
            $model->afterAuditLog([
                'action_type' => 'update',
                'old_values' => array_intersect_key($original, $filteredChanges),
                'new_values' => $filteredChanges,
            ]);
        }
    }

    /**
     * Handle the model "deleted" event.
     * 
     * @param Model $model
     * @return void
     */
    public function deleted(Model $model): void
    {
        if (!$this->shouldAudit($model, 'delete')) {
            return;
        }

        $this->auditService->logModelAction([
            'event_type' => 'model_crud',
            'action_type' => 'delete',
            'model' => $model,
            'old_values' => $model->getAuditableAttributes($model->getOriginal()),
            'new_values' => null,
            'route_info' => $this->getRouteInfo(),
        ]);

        // Call after audit hook if available
        if (method_exists($model, 'afterAuditLog')) {
            $model->afterAuditLog([
                'action_type' => 'delete',
                'old_values' => $model->getAuditableAttributes($model->getOriginal()),
            ]);
        }
    }

    /**
     * Handle the model "restored" event (for soft deletes).
     * 
     * @param Model $model
     * @return void
     */
    public function restored(Model $model): void
    {
        if (!$this->shouldAudit($model, 'restore')) {
            return;
        }

        $this->auditService->logModelAction([
            'event_type' => 'model_crud',
            'action_type' => 'restore',
            'model' => $model,
            'old_values' => null,
            'new_values' => $model->getAuditableAttributes(),
            'route_info' => $this->getRouteInfo(),
        ]);

        // Call after audit hook if available
        if (method_exists($model, 'afterAuditLog')) {
            $model->afterAuditLog([
                'action_type' => 'restore',
                'new_values' => $model->getAuditableAttributes(),
            ]);
        }
    }

    /**
     * Handle the model "force deleted" event.
     * 
     * @param Model $model
     * @return void
     */
    public function forceDeleted(Model $model): void
    {
        if (!$this->shouldAudit($model, 'force_delete')) {
            return;
        }

        $this->auditService->logModelAction([
            'event_type' => 'model_crud',
            'action_type' => 'force_delete',
            'model' => $model,
            'old_values' => $model->getAuditableAttributes($model->getOriginal()),
            'new_values' => null,
            'route_info' => $this->getRouteInfo(),
            'additional_data' => ['permanent_deletion' => true],
        ]);

        // Call after audit hook if available
        if (method_exists($model, 'afterAuditLog')) {
            $model->afterAuditLog([
                'action_type' => 'force_delete',
                'old_values' => $model->getAuditableAttributes($model->getOriginal()),
            ]);
        }
    }

    /**
     * Handle the model "retrieved" event.
     * Note: This is typically too verbose for most applications.
     * 
     * @param Model $model
     * @return void
     */
    public function retrieved(Model $model): void
    {
        // Only log retrieval if specifically configured to do so
        if (!$this->shouldAudit($model, 'retrieve') || !config('apex.audit.audit.track_retrievals', false)) {
            return;
        }

        $this->auditService->logModelAction([
            'event_type' => 'model_crud',
            'action_type' => 'retrieve',
            'model' => $model,
            'old_values' => null,
            'new_values' => null,
            'route_info' => $this->getRouteInfo(),
            'additional_data' => [
                'accessed_fields' => array_keys($model->getAttributes()),
            ],
        ]);
    }

    /**
     * Determine if the model should be audited for a specific event.
     * 
     * @param Model $model
     * @param string $event
     * @return bool
     */
    protected function shouldAudit(Model $model, string $event): bool
    {
        // Check if auditing is globally enabled
        if (!config('apex.audit.audit.enabled')) {
            return false;
        }

        // Check if model supports auditing
        if (!method_exists($model, 'shouldAuditEvent')) {
            return true; // Default to auditing if method doesn't exist
        }

        return $model->shouldAuditEvent($event);
    }

    /**
     * Filter changes to determine which should be tracked.
     * 
     * @param Model $model
     * @param array $original
     * @param array $dirty
     * @return array
     */
    protected function filterChanges(Model $model, array $original, array $dirty): array
    {
        $filtered = [];

        foreach ($dirty as $field => $newValue) {
            $oldValue = $original[$field] ?? null;

            // Use model's shouldTrackChange method if available
            if (method_exists($model, 'shouldTrackChange')) {
                if ($model->shouldTrackChange($field, $oldValue, $newValue)) {
                    $filtered[$field] = $newValue;
                }
            } else {
                // Default behavior: track all changes
                $filtered[$field] = $newValue;
            }
        }

        return $filtered;
    }

    /**
     * Get route information for the current request.
     * 
     * @return array
     */
    protected function getRouteInfo(): array
    {
        $request = request();
        $route = $request?->route();

        if (!$route) {
            return [
                'route_name' => null,
                'route_uri' => null,
                'method' => $request?->method(),
                'controller' => null,
                'middleware' => [],
                'parameters' => [],
            ];
        }

        return [
            'route_name' => $route->getName(),
            'route_uri' => $route->uri(),
            'method' => $request->method(),
            'controller' => $route->getActionName(),
            'middleware' => $route->gatherMiddleware() ?? [],
            'parameters' => $this->sanitizeRouteParameters($route->parameters()),
        ];
    }

    /**
     * Sanitize route parameters for audit logging.
     * Remove sensitive parameters that shouldn't be logged.
     * 
     * @param array $parameters
     * @return array
     */
    protected function sanitizeRouteParameters(array $parameters): array
    {
        $sensitiveParams = ['password', 'token', 'key', 'secret'];
        $sanitized = [];

        foreach ($parameters as $key => $value) {
            // Skip sensitive parameters
            if (in_array(strtolower($key), $sensitiveParams)) {
                $sanitized[$key] = '[REDACTED]';
                continue;
            }

            // Log model instances as their identifier
            if ($value instanceof Model) {
                $sanitized[$key] = [
                    'model_type' => get_class($value),
                    'model_id' => $value->getKey(),
                ];
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Handle batch operations (if supported by the model).
     * This method can be called manually for bulk operations.
     * 
     * @param string $action
     * @param array $models
     * @param array $additionalData
     * @return void
     */
    public function logBatchOperation(string $action, array $models, array $additionalData = []): void
    {
        if (empty($models) || !config('apex.audit.audit.enabled')) {
            return;
        }

        $firstModel = $models[0];
        $modelType = get_class($firstModel);
        $modelIds = array_map(fn($model) => $model->getKey(), $models);

        $this->auditService->logCustomAction([
            'event_type' => 'batch_operation',
            'action_type' => $action,
            'model_type' => $modelType,
            'table_name' => $firstModel->getTable(),
            'additional_data' => array_merge([
                'batch_count' => count($models),
                'model_ids' => $modelIds,
                'batch_operation' => true,
            ], $additionalData),
            'route_info' => $this->getRouteInfo(),
        ]);
    }

    /**
     * Log a custom model event.
     * This method can be called manually for custom model operations.
     * 
     * @param Model $model
     * @param string $action
     * @param array $additionalData
     * @return void
     */
    public function logCustomModelEvent(Model $model, string $action, array $additionalData = []): void
    {
        if (!$this->shouldAudit($model, $action)) {
            return;
        }

        $this->auditService->logModelAction([
            'event_type' => 'custom_model_event',
            'action_type' => $action,
            'model' => $model,
            'old_values' => null,
            'new_values' => null,
            'route_info' => $this->getRouteInfo(),
            'additional_data' => $additionalData,
        ]);
    }
}
