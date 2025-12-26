<?php

/*
Copyright EXOR Group ltd 2025 
Version 1.0.0.0 
APEX Laravel Auditing
Description: Queue job for asynchronous audit record creation to improve application performance. Handles audit logging in background with proper error handling and retry mechanisms.
*/

namespace Apex\Audit\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateAuditRecord implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The audit data to be inserted.
     */
    protected array $auditData;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 60;

    /**
     * Delete the job if its models no longer exist.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     */
    public function __construct(array $auditData)
    {
        $this->auditData = $auditData;

        // Set queue configuration from config
        $this->onConnection(config('apex.audit.audit.queue.connection'));
        $this->onQueue(config('apex.audit.audit.queue.queue', 'audit'));
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Validate audit data
            $this->validateAuditData();

            // Get database connection
            $connection = config('apex.audit.audit.connection');
            $db = $connection ? DB::connection($connection) : DB::connection();

            // Insert audit record
            $auditId = $db->table('apex_audit')->insertGetId($this->auditData);

            if (!$auditId) {
                throw new \RuntimeException('Failed to insert audit record - no ID returned');
            }

            Log::debug('APEX Audit: Async audit record created', [
                'audit_id' => $auditId,
                'audit_uuid' => $this->auditData['audit_uuid'] ?? 'unknown',
                'event_type' => $this->auditData['event_type'] ?? 'unknown',
                'action_type' => $this->auditData['action_type'] ?? 'unknown',
            ]);
        } catch (\Exception $e) {
            Log::error('APEX Audit: Failed to create async audit record', [
                'error' => $e->getMessage(),
                'audit_data' => $this->sanitizeAuditDataForLogging(),
                'attempt' => $this->attempts(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('APEX Audit: Async audit record creation failed permanently', [
            'error' => $exception->getMessage(),
            'audit_data' => $this->sanitizeAuditDataForLogging(),
            'attempts' => $this->attempts(),
            'exception_class' => get_class($exception),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Optionally, try to create a fallback audit record
        $this->createFallbackAuditRecord($exception);
    }

    /**
     * Validate the audit data before processing.
     */
    protected function validateAuditData(): void
    {
        $requiredFields = [
            'audit_uuid',
            'event_type',
            'action_type',
            'signature',
            'created_at',
        ];

        foreach ($requiredFields as $field) {
            if (!isset($this->auditData[$field]) || $this->auditData[$field] === null) {
                throw new \InvalidArgumentException("Required audit field '{$field}' is missing or null");
            }
        }

        // Validate UUID format
        if (!$this->isValidUuid($this->auditData['audit_uuid'])) {
            throw new \InvalidArgumentException("Invalid UUID format for audit_uuid");
        }

        // Validate event type
        $validEventTypes = ['model_crud', 'ui_action', 'system_event', 'custom', 'batch_operation', 'rollback_action'];
        if (!in_array($this->auditData['event_type'], $validEventTypes)) {
            throw new \InvalidArgumentException("Invalid event_type: {$this->auditData['event_type']}");
        }

        // Validate JSON fields
        $jsonFields = ['device_fingerprint', 'additional_data', 'old_values', 'new_values'];
        foreach ($jsonFields as $field) {
            if (isset($this->auditData[$field]) && $this->auditData[$field] !== null) {
                if (!$this->isValidJson($this->auditData[$field])) {
                    throw new \InvalidArgumentException("Invalid JSON format for field '{$field}'");
                }
            }
        }

        // Validate signature length
        if (strlen($this->auditData['signature']) > 128) {
            throw new \InvalidArgumentException("Signature too long (max 128 characters)");
        }
    }

    /**
     * Check if a string is a valid UUID.
     */
    protected function isValidUuid(string $uuid): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid) === 1;
    }

    /**
     * Check if a string is valid JSON.
     */
    protected function isValidJson(string $json): bool
    {
        json_decode($json);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Sanitize audit data for logging (remove sensitive information).
     */
    protected function sanitizeAuditDataForLogging(): array
    {
        $sanitized = $this->auditData;

        // Remove potentially sensitive fields
        $sensitiveFields = ['old_values', 'new_values', 'additional_data'];
        foreach ($sensitiveFields as $field) {
            if (isset($sanitized[$field])) {
                $sanitized[$field] = '[REDACTED]';
            }
        }

        return $sanitized;
    }

    /**
     * Create a fallback audit record when async processing fails.
     */
    protected function createFallbackAuditRecord(\Throwable $exception): void
    {
        try {
            // Create a simplified audit record about the failure
            $fallbackData = [
                'audit_uuid' => \Illuminate\Support\Str::uuid()->toString(),
                'event_type' => 'system_event',
                'action_type' => 'audit_failure',
                'model_type' => null,
                'model_id' => null,
                'table_name' => null,
                'source_page' => null,
                'source_element' => 'async_audit_job',
                'user_id' => null,
                'session_id' => null,
                'ip_address' => null,
                'user_agent' => null,
                'device_fingerprint' => null,
                'additional_data' => json_encode([
                    'failed_audit_uuid' => $this->auditData['audit_uuid'] ?? 'unknown',
                    'failure_reason' => $exception->getMessage(),
                    'failure_class' => get_class($exception),
                    'original_event_type' => $this->auditData['event_type'] ?? 'unknown',
                    'original_action_type' => $this->auditData['action_type'] ?? 'unknown',
                ]),
                'old_values' => null,
                'new_values' => null,
                'signature' => 'fallback_record',
                'created_at' => now()->toDateTimeString(),
            ];

            $connection = config('apex.audit.audit.connection');
            $db = $connection ? DB::connection($connection) : DB::connection();

            $db->table('apex_audit')->insert($fallbackData);

            Log::info('APEX Audit: Fallback audit record created for failed async audit', [
                'fallback_uuid' => $fallbackData['audit_uuid'],
                'original_uuid' => $this->auditData['audit_uuid'] ?? 'unknown',
            ]);
        } catch (\Exception $fallbackException) {
            Log::emergency('APEX Audit: Failed to create fallback audit record', [
                'original_error' => $exception->getMessage(),
                'fallback_error' => $fallbackException->getMessage(),
                'audit_uuid' => $this->auditData['audit_uuid'] ?? 'unknown',
            ]);
        }
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        // Exponential backoff: 1 second, 4 seconds, 16 seconds
        return [1, 4, 16];
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(5);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'apex-audit',
            'audit-' . ($this->auditData['event_type'] ?? 'unknown'),
            'action-' . ($this->auditData['action_type'] ?? 'unknown'),
        ];
    }

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        // Add rate limiting if needed
        return [];
    }

    /**
     * Get the job's display name for monitoring.
     */
    public function displayName(): string
    {
        $eventType = $this->auditData['event_type'] ?? 'unknown';
        $actionType = $this->auditData['action_type'] ?? 'unknown';

        return "CreateAuditRecord: {$eventType}.{$actionType}";
    }

    /**
     * Prepare the instance for serialization.
     */
    public function __serialize(): array
    {
        return [
            'auditData' => $this->auditData,
        ];
    }

    /**
     * Restore the instance after deserialization.
     */
    public function __unserialize(array $data): void
    {
        $this->auditData = $data['auditData'];
    }
}
