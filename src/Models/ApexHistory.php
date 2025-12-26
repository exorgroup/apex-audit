<?php

/*
Copyright EXOR Group ltd 2025 
Version 1.0.0.0 
APEX Laravel Auditing
Description: Model for user-facing history records. Provides a clean interface for viewing model changes with rollback capabilities and simplified display of CRUD operations.
*/

namespace Apex\Audit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ApexHistory extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'apex_history';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'audit_id',
        'model_type',
        'model_id',
        'action_type',
        'description',
        'field_changes',
        'user_id',
        'user_name',
        'can_rollback',
        'rollback_data',
        'rolled_back_at',
        'rolled_back_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'field_changes' => 'array',
        'rollback_data' => 'array',
        'can_rollback' => 'boolean',
        'rolled_back_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The "booting" method of the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Ensure we're using the correct connection before saving
            $model->setConnection($model->getAuditConnection());
        });
    }

    /**
     * Get the connection name for the model.
     * This ensures the model uses the tenant connection when in tenant context.
     *
     * @return string|null
     */
    public function getConnectionName()
    {
        return $this->getAuditConnection();
    }

    /**
     * Get the appropriate database connection for audit operations.
     *
     * @return string|null
     */
    protected function getAuditConnection(): ?string
    {
        // Check if multi-tenancy is enabled
        if (!config('apex.audit.tenancy.enabled', true)) {
            return config('apex.audit.audit.connection') ?? config('database.default');
        }

        // If we're in a tenant context
        if (function_exists('tenant') && tenant()) {
            $tenant = tenant();

            // First check if tenant has a specific database connection method
            if (method_exists($tenant, 'getTenantConnectionName')) {
                return $tenant->getTenantConnectionName();
            }

            // Check if tenant has database name property
            if (property_exists($tenant, 'tenancy_db_name') || isset($tenant->tenancy_db_name)) {
                // The connection should already be configured by tenancy package
                // Default to 'tenant' connection which should be configured
                return 'tenant';
            }

            // Fallback to current default connection (which should be tenant in tenant context)
            return config('database.default');
        }

        // Not in tenant context - use configured connection or central
        $fallbackBehavior = config('apex.audit.tenancy.fallback_behavior', 'central');

        switch ($fallbackBehavior) {
            case 'central':
                return config('apex.audit.tenancy.central_connection', 'mysql');
            case 'skip':
                return null;
            case 'error':
                throw new \RuntimeException('APEX History: Not in tenant context and fallback is set to error');
            default:
                return config('apex.audit.audit.connection') ?? config('database.default');
        }
    }

    /**
     * Create a new query builder that uses the correct connection.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function newQuery()
    {
        $builder = parent::newQuery();

        // Set the connection on the model instance
        $this->setConnection($this->getAuditConnection());

        return $builder;
    }

    /**
     * Get the related audit record.
     */
    public function audit()
    {
        return $this->belongsTo(ApexAudit::class, 'audit_id');
    }

    /**
     * Get the user that performed the action.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the user that rolled back this action.
     */
    public function rolledBackByUser()
    {
        return $this->belongsTo(User::class, 'rolled_back_by');
    }

    /**
     * Scope a query to only include history for a specific model.
     */
    public function scopeForModel(Builder $query, string $modelType, $modelId = null): Builder
    {
        $query->where('model_type', $modelType);

        if ($modelId !== null) {
            $query->where('model_id', $modelId);
        }

        return $query;
    }

    /**
     * Scope a query to only include history by a specific user.
     */
    public function scopeByUser(Builder $query, $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include specific action types.
     */
    public function scopeWithAction(Builder $query, $actions): Builder
    {
        if (is_array($actions)) {
            return $query->whereIn('action_type', $actions);
        }

        return $query->where('action_type', $actions);
    }

    /**
     * Scope a query to only include rollbackable records.
     */
    public function scopeRollbackable(Builder $query): Builder
    {
        return $query->where('can_rollback', true)
            ->whereNull('rolled_back_at');
    }

    /**
     * Scope a query to only include rolled back records.
     */
    public function scopeRolledBack(Builder $query): Builder
    {
        return $query->whereNotNull('rolled_back_at');
    }

    /**
     * Check if this history record has been rolled back.
     */
    public function isRolledBack(): bool
    {
        return $this->rolled_back_at !== null;
    }

    /**
     * Check if the current user can rollback this history.
     */
    public function userCanRollback(): bool
    {
        if (!$this->can_rollback || $this->isRolledBack()) {
            return false;
        }

        if (!Auth::check()) {
            return false;
        }

        $rollbackConfig = config('apex.audit.history.rollback_permissions', []);

        // Check gate permission
        if (isset($rollbackConfig['gate'])) {
            return Auth::user()->can($rollbackConfig['gate'], $this);
        }

        // Check role-based permission
        if (isset($rollbackConfig['roles'])) {
            if (method_exists(Auth::user(), 'hasAnyRole')) {
                return Auth::user()->hasAnyRole($rollbackConfig['roles']);
            }
        }

        // Default to config setting
        return config('apex.audit.history.allow_rollback', true);
    }

    /**
     * Get formatted history data for display.
     */
    public function getFormattedData(): array
    {
        return [
            'id' => $this->id,
            'action' => $this->getActionLabel(),
            'description' => $this->description,
            'model' => class_basename($this->model_type),
            'model_id' => $this->model_id,
            'changes' => $this->getFormattedChanges(),
            'user' => $this->user_name,
            'user_id' => $this->user_id,
            'can_rollback' => $this->userCanRollback(),
            'is_rolled_back' => $this->isRolledBack(),
            'rolled_back_at' => $this->rolled_back_at?->toDateTimeString(),
            'rolled_back_by' => $this->rolledBackByUser?->name,
            'created_at' => $this->created_at->toDateTimeString(),
            'created_at_human' => $this->created_at->diffForHumans(),
        ];
    }

    /**
     * Get a human-readable label for the action type.
     */
    public function getActionLabel(): string
    {
        $labels = [
            'create' => apex_trans('actions.created'),
            'update' => apex_trans('actions.updated'),
            'delete' => apex_trans('actions.deleted'),
            'restore' => apex_trans('actions.restored'),
            'rollback' => apex_trans('actions.rolled_back'),
        ];

        return $labels[$this->action_type] ?? ucfirst($this->action_type);
    }

    /**
     * Get formatted field changes for display.
     */
    public function getFormattedChanges(): array
    {
        if (!$this->field_changes || $this->action_type !== 'update') {
            return [];
        }

        $formatted = [];
        foreach ($this->field_changes as $field => $change) {
            $formatted[] = [
                'field' => $change['field_label'] ?? $field,
                'old_value' => $this->formatFieldValue($field, $change['old']),
                'new_value' => $this->formatFieldValue($field, $change['new']),
            ];
        }

        return $formatted;
    }

    /**
     * Format a field value for display.
     */
    protected function formatFieldValue(string $field, $value): string
    {
        if ($value === null) {
            return apex_trans('history.empty_value');
        }

        if (is_bool($value)) {
            return $value ? apex_trans('history.yes') : apex_trans('history.no');
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        // Check if this is a date field
        if (str_ends_with($field, '_at') || str_ends_with($field, '_date')) {
            try {
                return \Carbon\Carbon::parse($value)->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                return (string) $value;
            }
        }

        return (string) $value;
    }

    /**
     * Get the model instance this history refers to.
     */
    public function getRelatedModel()
    {
        if (!$this->model_type || !$this->model_id) {
            return null;
        }

        if (!class_exists($this->model_type)) {
            return null;
        }

        // Make sure to use the correct connection for the model
        $modelInstance = new $this->model_type;
        if (method_exists($modelInstance, 'getConnectionName')) {
            $connection = $modelInstance->getConnectionName();
            return $this->model_type::on($connection)->find($this->model_id);
        }

        return $this->model_type::find($this->model_id);
    }

    /**
     * Mark this history as rolled back.
     */
    public function markAsRolledBack(int $userId): void
    {
        $this->update([
            'rolled_back_at' => now(),
            'rolled_back_by' => $userId,
            'can_rollback' => false,
        ]);
    }

    /**
     * Get history records that are candidates for cleanup.
     */
    public static function getCleanupCandidates(int $retentionDays): Builder
    {
        $cutoffDate = now()->subDays($retentionDays);

        return static::where('created_at', '<', $cutoffDate)
            ->where('can_rollback', false);
    }

    // Add these methods to your Apex\Audit\Models\ApexHistory.php

    /**
     * Check if this history record can be rolled back by the current user.
     */
    public function canBeRolledBack(): bool
    {
        // Already rolled back
        if ($this->isRolledBack()) {
            return false;
        }

        // Not configured as rollbackable
        if (!$this->can_rollback) {
            return false;
        }

        // Global rollback disabled
        if (!config('apex.audit.history.allow_rollback', true)) {
            return false;
        }

        // No authentication system - always allow
        return true;
    }

    /**
     * Get the audited model instance if it still exists.
     */
    public function getAuditedModel()
    {
        if (!$this->model_type || !$this->model_id) {
            return null;
        }

        try {
            if (!class_exists($this->model_type)) {
                return null;
            }

            return $this->model_type::find($this->model_id);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get statistics for history records.
     */
    public static function getStatistics(array $filters = []): array
    {
        $query = static::query();

        if (isset($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['model_type'])) {
            $query->where('model_type', $filters['model_type']);
        }

        $totalRecords = $query->count();
        $rollbackableRecords = (clone $query)->where('can_rollback', true)->whereNull('rolled_back_at')->count();
        $rolledBackRecords = (clone $query)->whereNotNull('rolled_back_at')->count();

        return [
            'total_records' => $totalRecords,
            'rollbackable_records' => $rollbackableRecords,
            'rolled_back_records' => $rolledBackRecords,
            'by_action' => $query->groupBy('action_type')
                ->selectRaw('action_type, count(*) as count')
                ->pluck('count', 'action_type'),
            'by_model' => $query->groupBy('model_type')
                ->selectRaw('model_type, count(*) as count')
                ->pluck('count', 'model_type')
                ->mapWithKeys(function ($count, $model) {
                    return [class_basename($model) => $count];
                }),
            'by_user' => $query->groupBy('user_id', 'user_name')
                ->selectRaw('user_id, user_name, count(*) as count')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->user_name => $item->count];
                }),
        ];
    }
}
