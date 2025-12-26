<?php

/*
Copyright EXOR Group ltd 2025 
Version 1.0.0.0 
APEX Laravel Auditing
Description: Core model for APEX audit records. Provides forensic-grade audit trail storage with digital signatures, multi-tenancy support, and comprehensive tracking of all system actions including CRUD operations and UI interactions.
*/

namespace Apex\Audit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use Illuminate\Foundation\Auth\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ApexAudit extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'apex_audit';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'audit_uuid',
        'event_type',
        'action_type',
        'model_type',
        'model_id',
        'table_name',
        'source_page',
        'source_element',
        'user_id',
        'session_id',
        'ip_address',
        'user_agent',
        'device_fingerprint',
        'additional_data',
        'old_values',
        'new_values',
        'signature',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'additional_data' => 'array',
        'old_values' => 'array',
        'new_values' => 'array',
        'device_fingerprint' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * The "booting" method of the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->audit_uuid)) {
                $model->audit_uuid = (string) Str::uuid();
            }
            if (empty($model->created_at)) {
                $model->created_at = now();
            }

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
                // Get the current default connection which should be configured by tenancy
                $currentDefault = config('database.default');

                // If we're already using a tenant connection, use it
                if ($currentDefault !== 'mysql' && $currentDefault !== 'central') {
                    return $currentDefault;
                }

                // Otherwise default to 'tenant' connection
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
                throw new \RuntimeException('APEX Audit: Not in tenant context and fallback is set to error');
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
     * Get the user that performed the audited action.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the related history record if exists.
     */
    public function history()
    {
        return $this->hasOne(ApexHistory::class, 'audit_id', 'id');
    }

    /**
     * Scope a query to only include audit records of a given type.
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('event_type', $type);
    }

    /**
     * Scope a query to only include audit records for a specific model.
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
     * Scope a query to only include audit records by a specific user.
     */
    public function scopeByUser(Builder $query, $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include audit records within a date range.
     */
    public function scopeDateRange(Builder $query, $startDate, $endDate): Builder
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope a query to only include audit records from a specific source page.
     */
    public function scopeFromPage(Builder $query, string $page): Builder
    {
        return $query->where('source_page', $page);
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
     * Get formatted audit data for display.
     */
    public function getFormattedData(): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->audit_uuid,
            'event' => $this->event_type,
            'action' => $this->action_type,
            'model' => $this->model_type ? class_basename($this->model_type) : null,
            'model_id' => $this->model_id,
            'table' => $this->table_name,
            'source' => $this->source_page,
            'element' => $this->source_element,
            'user' => $this->user ? $this->user->name : 'System',
            'user_id' => $this->user_id,
            'ip' => $this->ip_address,
            'changes' => $this->getChangeSummary(),
            'additional' => $this->additional_data,
            'signature' => $this->signature,
            'created_at' => $this->created_at->toDateTimeString(),
            'created_at_human' => $this->created_at->diffForHumans(),
        ];
    }

    /**
     * Get a summary of changes made.
     */
    public function getChangeSummary(): array
    {
        if ($this->event_type !== 'model_crud' || !$this->old_values || !$this->new_values) {
            return [];
        }

        $changes = [];
        foreach ($this->new_values as $field => $newValue) {
            $oldValue = $this->old_values[$field] ?? null;
            if ($oldValue !== $newValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changes;
    }

    /**
     * Verify the audit record signature.
     */
    public function verifySignature(): bool
    {
        $signatureService = app(\Apex\Audit\Services\AuditSignatureService::class);

        $data = [
            'audit_uuid' => $this->audit_uuid,
            'event_type' => $this->event_type,
            'action_type' => $this->action_type,
            'model_type' => $this->model_type,
            'model_id' => $this->model_id,
            'user_id' => $this->user_id,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'created_at' => $this->created_at->toISOString(),
        ];

        $expectedSignature = $signatureService->generateSignature($data);

        return hash_equals($expectedSignature, $this->signature);
    }

    /**
     * Check if this audit record represents a rollback action.
     */
    public function isRollback(): bool
    {
        return $this->event_type === 'rollback_action';
    }

    /**
     * Get the device information from fingerprint.
     */
    public function getDeviceInfo(): array
    {
        $fingerprint = $this->device_fingerprint ?? [];

        return [
            'browser' => $fingerprint['browser'] ?? 'Unknown',
            'platform' => $fingerprint['platform'] ?? 'Unknown',
            'device_type' => $fingerprint['device_type'] ?? 'Unknown',
            'screen_resolution' => $fingerprint['screen_resolution'] ?? 'Unknown',
        ];
    }

    /**
     * Get the IP address (optionally anonymized).
     */
    public function getIpAddress(): string
    {
        if (!$this->ip_address) {
            return 'Unknown';
        }

        if (config('apex.audit.privacy.anonymize_ip', false)) {
            // Anonymize IPv4 by removing last octet
            if (filter_var($this->ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $parts = explode('.', $this->ip_address);
                $parts[3] = '0';
                return implode('.', $parts);
            }

            // Anonymize IPv6 by removing last 64 bits
            if (filter_var($this->ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $parts = explode(':', $this->ip_address);
                for ($i = 4; $i < 8; $i++) {
                    $parts[$i] = '0';
                }
                return implode(':', $parts);
            }
        }

        return $this->ip_address;
    }

    /**
     * Get audit records that are candidates for cleanup.
     */
    public static function getCleanupCandidates(int $retentionDays): Builder
    {
        $cutoffDate = now()->subDays($retentionDays);

        return static::where('created_at', '<', $cutoffDate)
            ->whereNotIn('event_type', ['rollback_action', 'system_event']);
    }

    /**
     * Get statistics for audit records.
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

        return [
            'total_records' => $query->count(),
            'by_event_type' => $query->groupBy('event_type')
                ->selectRaw('event_type, count(*) as count')
                ->pluck('count', 'event_type'),
            'by_action_type' => $query->groupBy('action_type')
                ->selectRaw('action_type, count(*) as count')
                ->pluck('count', 'action_type'),
            'by_model' => $query->whereNotNull('model_type')
                ->groupBy('model_type')
                ->selectRaw('model_type, count(*) as count')
                ->pluck('count', 'model_type')
                ->mapWithKeys(function ($count, $model) {
                    return [class_basename($model) => $count];
                }),
        ];
    }
}
