<?php

/*
Copyright EXOR Group ltd 2025 
Version 1.0.0.0 
APEX Laravel Auditing
Description: Main trait for models to enable APEX auditing functionality. Provides granular control over field tracking, CRUD operation filtering, and rollback capabilities with seamless Eloquent integration.
*/

namespace Apex\Audit\Traits;

use Apex\Audit\Observers\ApexAuditObserver;
use Illuminate\Support\Facades\Auth;

trait ApexAuditable
{
    /**
     * Boot the ApexAuditable trait for a model.
     */
    public static function bootApexAuditable(): void
    {
        if (config('apex.audit.audit.enabled')) {
            static::observe(ApexAuditObserver::class);
        }
    }

    /**
     * Get the APEX audit configuration for this model.
     */
    public function getApexAuditConfig(): array
    {
        $modelClass = get_class($this);
        $globalOverrides = config("apex.audit.audit.model_overrides.{$modelClass}", []);
        $modelConfig = $this->apexAuditConfig ?? [];

        // Merge global overrides with model config (global takes precedence)
        return array_merge($modelConfig, $globalOverrides);
    }

    /**
     * Determine if a specific event should be audited for this model.
     */
    public function shouldAuditEvent(string $event): bool
    {
        $config = $this->getApexAuditConfig();
        $auditEvents = $config['audit_events'] ?? ['create', 'update', 'delete', 'restore'];

        return in_array($event, $auditEvents);
    }

    /**
     * Get the fields that should be audited for this model.
     */
    public function getAuditableFields(): array
    {
        $config = $this->getApexAuditConfig();
        $globalExcludes = config('apex.audit.audit.global_excludes', []);

        // If audit_include is specified, use only those fields
        if (!empty($config['audit_include'])) {
            return array_diff($config['audit_include'], $globalExcludes);
        }

        // Otherwise use fillable fields minus excluded ones
        $fields = $this->fillable ?? [];
        $excludeFields = array_merge($globalExcludes, $config['audit_exclude'] ?? []);

        return array_diff($fields, $excludeFields);
    }

    /**
     * Get the fields that should be shown in history UI.
     */
    public function getHistoryFields(): array
    {
        $config = $this->getApexAuditConfig();
        $auditableFields = $this->getAuditableFields();
        $historyExclude = $config['history_exclude'] ?? [];

        return array_diff($auditableFields, $historyExclude);
    }

    /**
     * Get only the auditable attributes from the given attributes array.
     */
    public function getAuditableAttributes(array $attributes = null): array
    {
        $attributes = $attributes ?? $this->getAttributes();
        $auditableFields = $this->getAuditableFields();

        return array_intersect_key($attributes, array_flip($auditableFields));
    }

    /**
     * Determine if a change to a specific field should be tracked.
     */
    public function shouldTrackChange(string $field, $oldValue, $newValue): bool
    {
        $config = $this->getApexAuditConfig();
        $fieldRules = $config['audit_rules'][$field] ?? [];

        // If track_changes_only is true, only log if value actually changed
        if (($fieldRules['track_changes_only'] ?? false) && $oldValue === $newValue) {
            return false;
        }

        // Check minimum change threshold for numeric fields
        if (isset($fieldRules['minimum_change']) && is_numeric($oldValue) && is_numeric($newValue)) {
            $change = abs($newValue - $oldValue);
            if ($change < $fieldRules['minimum_change']) {
                return false;
            }
        }

        // Check custom validation callback
        if (isset($fieldRules['validator']) && is_callable($fieldRules['validator'])) {
            return call_user_func($fieldRules['validator'], $field, $oldValue, $newValue, $this);
        }

        return true;
    }

    /**
     * Get the actions that can be rolled back for this model.
     */
    public function getRollbackableActions(): array
    {
        $config = $this->getApexAuditConfig();
        return $config['rollbackable_actions'] ?? ['update', 'delete'];
    }

    /**
     * Get audit metadata for this model instance.
     */
    public function getApexAuditData(): array
    {
        return [
            'model_type' => get_class($this),
            'model_id' => (string) $this->getKey(),
            'table_name' => $this->getTable(),
            'model_name' => class_basename($this),
        ];
    }

    /**
     * Determine if the current user can rollback changes to this model.
     */
    public function canRollback(): bool
    {
        $config = config('apex.audit.history.rollback_permissions', []);

        if (isset($config['gate']) && Auth::check()) {
            return Auth::user()->can($config['gate'], $this);
        }

        if (isset($config['roles']) && Auth::check()) {
            return Auth::user()->hasAnyRole($config['roles']);
        }

        return config('apex.audit.history.allow_rollback', true);
    }

    /**
     * Get custom audit data for this model.
     * Override this method to add model-specific audit information.
     */
    public function getCustomAuditData(): array
    {
        return [];
    }

    /**
     * Get a human-readable description for an audit action.
     * Override this method to customize audit descriptions.
     */
    public function getAuditDescription(string $action, array $changes = []): string
    {
        $modelName = class_basename($this);
        $modelId = $this->getKey();

        switch ($action) {
            case 'create':
                return "Created new {$modelName} #{$modelId}";
            case 'update':
                $changedFields = array_keys($changes);
                $fieldList = implode(', ', $changedFields);
                return "Updated {$modelName} #{$modelId} - Changed: {$fieldList}";
            case 'delete':
                return "Deleted {$modelName} #{$modelId}";
            case 'restore':
                return "Restored {$modelName} #{$modelId}";
            default:
                return "Performed {$action} on {$modelName} #{$modelId}";
        }
    }

    /**
     * Hook called before audit logging.
     * Override this method to modify audit data before it's saved.
     */
    public function beforeAuditLog(array $auditData): array
    {
        return $auditData;
    }

    /**
     * Hook called after audit logging.
     * Override this method to perform actions after audit is saved.
     */
    public function afterAuditLog(array $auditData): void
    {
        // Override in model if needed
    }
}
