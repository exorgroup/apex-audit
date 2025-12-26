<?php

/*
Copyright EXOR Group ltd 2025 
Version 1.0.0.0 
APEX Laravel Auditing
Description: Custom exception class for APEX auditing rollback operations. Provides specific error handling for rollback failures with detailed error messages and context preservation.
*/

namespace Apex\Audit\Exceptions;

use Exception;
use Throwable;
use Illuminate\Support\Facades\Auth;

class RollbackException extends Exception
{
    /**
     * Additional context data for the exception.
     */
    protected array $context = [];

    /**
     * The history record ID that failed to rollback.
     */
    protected ?int $historyId = null;

    /**
     * The type of rollback operation that failed.
     */
    protected ?string $rollbackType = null;

    /**
     * The model information involved in the rollback.
     */
    protected array $modelInfo = [];

    /**
     * Create a new rollback exception instance.
     *
     * @param string $message The exception message
     * @param int $code The exception code
     * @param Throwable|null $previous Previous exception
     * @param array $context Additional context data
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * Create a new rollback exception for permission denied.
     */
    public static function permissionDenied(int $historyId, ?int $userId = null): self
    {
        $userId = $userId ?? (Auth::check() ? Auth::id() : null);

        return new self(
            'You do not have permission to rollback this action.',
            403,
            null,
            [
                'type' => 'permission_denied',
                'history_id' => $historyId,
                'user_id' => $userId,
                'timestamp' => now()->toISOString(),
            ]
        );
    }

    /**
     * Create a new rollback exception for already rolled back record.
     */
    public static function alreadyRolledBack(int $historyId, string $rolledBackAt): self
    {
        return new self(
            'This action has already been rolled back.',
            409,
            null,
            [
                'type' => 'already_rolled_back',
                'history_id' => $historyId,
                'rolled_back_at' => $rolledBackAt,
                'timestamp' => now()->toISOString(),
            ]
        );
    }

    /**
     * Create a new rollback exception for non-rollbackable action.
     */
    public static function notRollbackable(int $historyId, string $actionType): self
    {
        return new self(
            "This {$actionType} action cannot be rolled back.",
            422,
            null,
            [
                'type' => 'not_rollbackable',
                'history_id' => $historyId,
                'action_type' => $actionType,
                'timestamp' => now()->toISOString(),
            ]
        );
    }

    /**
     * Create a new rollback exception for missing rollback data.
     */
    public static function missingRollbackData(int $historyId): self
    {
        return new self(
            'No rollback data available for this action.',
            422,
            null,
            [
                'type' => 'missing_rollback_data',
                'history_id' => $historyId,
                'timestamp' => now()->toISOString(),
            ]
        );
    }

    /**
     * Create a new rollback exception for model not found.
     */
    public static function modelNotFound(int $historyId, string $modelType, string $modelId): self
    {
        return new self(
            'The original record no longer exists and cannot be updated.',
            404,
            null,
            [
                'type' => 'model_not_found',
                'history_id' => $historyId,
                'model_type' => $modelType,
                'model_id' => $modelId,
                'timestamp' => now()->toISOString(),
            ]
        );
    }

    /**
     * Create a new rollback exception for model save failure.
     */
    public static function modelSaveFailed(int $historyId, string $modelType, string $modelId, ?Throwable $previous = null): self
    {
        return new self(
            'Failed to save model during rollback.',
            500,
            $previous,
            [
                'type' => 'model_save_failed',
                'history_id' => $historyId,
                'model_type' => $modelType,
                'model_id' => $modelId,
                'timestamp' => now()->toISOString(),
            ]
        );
    }

    /**
     * Create a new rollback exception for record already exists.
     */
    public static function recordAlreadyExists(int $historyId, string $modelType, string $modelId): self
    {
        return new self(
            'Record already exists and cannot be restored.',
            409,
            null,
            [
                'type' => 'record_already_exists',
                'history_id' => $historyId,
                'model_type' => $modelType,
                'model_id' => $modelId,
                'timestamp' => now()->toISOString(),
            ]
        );
    }

    /**
     * Create a new rollback exception for validation failure.
     */
    public static function validationFailed(int $historyId, array $validationErrors): self
    {
        $errorMessages = collect($validationErrors)->flatten()->implode(', ');

        return new self(
            "Rollback validation failed: {$errorMessages}",
            422,
            null,
            [
                'type' => 'validation_failed',
                'history_id' => $historyId,
                'validation_errors' => $validationErrors,
                'timestamp' => now()->toISOString(),
            ]
        );
    }

    /**
     * Create a new rollback exception for disabled functionality.
     */
    public static function functionalityDisabled(): self
    {
        return new self(
            'Rollback functionality is disabled.',
            403,
            null,
            [
                'type' => 'functionality_disabled',
                'timestamp' => now()->toISOString(),
            ]
        );
    }

    /**
     * Create a new rollback exception for database transaction failure.
     */
    public static function transactionFailed(int $historyId, ?Throwable $previous = null): self
    {
        return new self(
            'Database transaction failed during rollback.',
            500,
            $previous,
            [
                'type' => 'transaction_failed',
                'history_id' => $historyId,
                'timestamp' => now()->toISOString(),
            ]
        );
    }

    /**
     * Create a new rollback exception for field permission failure.
     */
    public static function fieldPermissionDenied(int $historyId, array $invalidFields): self
    {
        $fieldList = implode(', ', $invalidFields);

        return new self(
            "Cannot rollback non-auditable fields: {$fieldList}",
            403,
            null,
            [
                'type' => 'field_permission_denied',
                'history_id' => $historyId,
                'invalid_fields' => $invalidFields,
                'timestamp' => now()->toISOString(),
            ]
        );
    }

    /**
     * Get the additional context data.
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Set the history ID for this exception.
     */
    public function setHistoryId(int $historyId): self
    {
        $this->historyId = $historyId;
        $this->context['history_id'] = $historyId;
        return $this;
    }

    /**
     * Get the history ID associated with this exception.
     */
    public function getHistoryId(): ?int
    {
        return $this->historyId;
    }

    /**
     * Set the rollback type for this exception.
     */
    public function setRollbackType(string $rollbackType): self
    {
        $this->rollbackType = $rollbackType;
        $this->context['rollback_type'] = $rollbackType;
        return $this;
    }

    /**
     * Get the rollback type associated with this exception.
     */
    public function getRollbackType(): ?string
    {
        return $this->rollbackType;
    }

    /**
     * Set the model information for this exception.
     */
    public function setModelInfo(string $modelType, string $modelId): self
    {
        $this->modelInfo = [
            'model_type' => $modelType,
            'model_id' => $modelId,
        ];
        $this->context['model_info'] = $this->modelInfo;
        return $this;
    }

    /**
     * Get the model information associated with this exception.
     */
    public function getModelInfo(): array
    {
        return $this->modelInfo;
    }

    /**
     * Get the exception type from context.
     */
    public function getType(): ?string
    {
        return $this->context['type'] ?? null;
    }

    /**
     * Check if this is a specific type of rollback exception.
     */
    public function isType(string $type): bool
    {
        return $this->getType() === $type;
    }

    /**
     * Check if this is a permission-related exception.
     */
    public function isPermissionError(): bool
    {
        return in_array($this->getType(), ['permission_denied', 'field_permission_denied', 'functionality_disabled']);
    }

    /**
     * Check if this is a validation-related exception.
     */
    public function isValidationError(): bool
    {
        return in_array($this->getType(), ['validation_failed', 'not_rollbackable', 'already_rolled_back']);
    }

    /**
     * Check if this is a system/technical error.
     */
    public function isSystemError(): bool
    {
        return in_array($this->getType(), ['model_save_failed', 'transaction_failed', 'model_not_found']);
    }

    /**
     * Get a user-friendly error message.
     */
    public function getUserMessage(): string
    {
        return match ($this->getType()) {
            'permission_denied', 'field_permission_denied' => 'You do not have permission to perform this rollback.',
            'functionality_disabled' => 'Rollback functionality is currently disabled.',
            'already_rolled_back' => 'This action has already been rolled back.',
            'not_rollbackable' => 'This type of action cannot be rolled back.',
            'missing_rollback_data' => 'Rollback data is not available for this action.',
            'model_not_found' => 'The original record no longer exists.',
            'record_already_exists' => 'The record already exists and cannot be restored.',
            'validation_failed' => 'The rollback data failed validation checks.',
            'model_save_failed', 'transaction_failed' => 'A technical error occurred during rollback. Please try again.',
            default => 'An unexpected error occurred during rollback.',
        };
    }

    /**
     * Get data suitable for API response.
     */
    public function toArray(): array
    {
        return [
            'error' => 'rollback_failed',
            'message' => $this->getMessage(),
            'user_message' => $this->getUserMessage(),
            'type' => $this->getType(),
            'code' => $this->getCode(),
            'context' => $this->getContext(),
        ];
    }

    /**
     * Convert the exception to JSON.
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Render the exception for HTTP response.
     */
    public function render(): array
    {
        return [
            'success' => false,
            'error' => $this->toArray(),
        ];
    }
}
