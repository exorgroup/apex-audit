<?php

/*
Copyright EXOR Group ltd 2025 
Version 1.0.0.0 
APEX Laravel Auditing
Description: Service for generating and verifying SHA512 digital signatures for audit records. Ensures complete audit trail integrity by including all audit data in tamper-proof signatures with cryptographic verification.
*/

namespace Apex\Audit\Services;

use Illuminate\Support\Facades\Log;

class AuditSignatureService
{
    /**
     * Generate a digital signature for audit data.
     * 
     * @param array $auditData Complete audit record data
     * @return string SHA512 signature
     */
    public function generateSignature(array $auditData): string
    {
        if (!config('apex.audit.audit.signature.enabled')) {
            return '';
        }

        // Include ALL audit table fields in signature for complete integrity
        $signaturePayload = [
            'audit_uuid' => $auditData['audit_uuid'] ?? '',
            'event_type' => $auditData['event_type'] ?? '',
            'action_type' => $auditData['action_type'] ?? '',
            'model_type' => $auditData['model_type'] ?? '',
            'model_id' => $auditData['model_id'] ?? '',
            'table_name' => $auditData['table_name'] ?? '',
            'source_page' => $auditData['source_page'] ?? '',
            'source_element' => $auditData['source_element'] ?? '',
            'user_id' => $auditData['user_id'] ?? '',
            'session_id' => $auditData['session_id'] ?? '',
            'ip_address' => $auditData['ip_address'] ?? '',
            'user_agent' => $auditData['user_agent'] ?? '',
            'device_fingerprint' => $auditData['device_fingerprint'] ?? '',
            'additional_data' => $auditData['additional_data'] ?? '',
            'old_values' => $auditData['old_values'] ?? '',
            'new_values' => $auditData['new_values'] ?? '',
            'created_at' => $auditData['created_at'] ?? '',
            // Add secret key for additional security
            'secret_key' => $this->getSecretKey(),
            // Add algorithm version for future compatibility
            'signature_version' => '1.0',
        ];

        // Sort keys for consistent signature generation
        ksort($signaturePayload);

        // Generate JSON with consistent formatting
        $jsonPayload = json_encode($signaturePayload, JSON_UNESCAPED_UNICODE);

        // Generate signature using configured algorithm
        $algorithm = config('apex.audit.audit.signature.algorithm', 'sha512');

        return hash($algorithm, $jsonPayload);
    }

    /**
     * Verify a stored signature against audit data.
     * 
     * @param array $auditData Audit record data to verify
     * @param string $storedSignature Signature to verify against
     * @return bool True if signature is valid
     */
    public function verifySignature(array $auditData, string $storedSignature): bool
    {
        if (!config('apex.audit.audit.signature.enabled')) {
            return true; // Consider valid if signatures are disabled
        }

        if (empty($storedSignature)) {
            Log::warning('APEX Audit: Empty signature found during verification', [
                'audit_uuid' => $auditData['audit_uuid'] ?? 'unknown'
            ]);
            return false;
        }

        try {
            $calculatedSignature = $this->generateSignature($auditData);
            $isValid = hash_equals($storedSignature, $calculatedSignature);

            if (!$isValid) {
                Log::critical('APEX Audit: Signature verification failed - possible tampering detected', [
                    'audit_uuid' => $auditData['audit_uuid'] ?? 'unknown',
                    'stored_signature' => $storedSignature,
                    'calculated_signature' => $calculatedSignature,
                    'audit_data_keys' => array_keys($auditData),
                ]);
            }

            return $isValid;
        } catch (\Exception $e) {
            Log::error('APEX Audit: Signature verification error', [
                'audit_uuid' => $auditData['audit_uuid'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Batch verify multiple audit records.
     * 
     * @param array $auditRecords Array of audit records with signatures
     * @return array Results with verification status for each record
     */
    public function batchVerifySignatures(array $auditRecords): array
    {
        $results = [];

        foreach ($auditRecords as $index => $record) {
            $signature = $record['signature'] ?? '';
            unset($record['signature']); // Remove signature from data to verify

            $results[$index] = [
                'audit_uuid' => $record['audit_uuid'] ?? "record_{$index}",
                'is_valid' => $this->verifySignature($record, $signature),
                'signature' => $signature,
            ];
        }

        return $results;
    }

    /**
     * Get statistics about signature verification results.
     * 
     * @param array $verificationResults Results from batchVerifySignatures
     * @return array Statistics summary
     */
    public function getVerificationStats(array $verificationResults): array
    {
        $total = count($verificationResults);
        $valid = count(array_filter($verificationResults, fn($result) => $result['is_valid']));
        $invalid = $total - $valid;

        return [
            'total_verified' => $total,
            'valid_signatures' => $valid,
            'invalid_signatures' => $invalid,
            'validity_percentage' => $total > 0 ? round(($valid / $total) * 100, 2) : 0,
            'has_tampering' => $invalid > 0,
        ];
    }

    /**
     * Generate a signature for arbitrary data (not audit records).
     * Useful for custom audit events or external data verification.
     * 
     * @param array $data Data to sign
     * @param string $context Context identifier for the signature
     * @return string Generated signature
     */
    public function signData(array $data, string $context = 'custom'): string
    {
        $signaturePayload = [
            'context' => $context,
            'data' => $data,
            'timestamp' => now()->toISOString(),
            'secret_key' => $this->getSecretKey(),
        ];

        ksort($signaturePayload);
        $jsonPayload = json_encode($signaturePayload, JSON_UNESCAPED_UNICODE);

        $algorithm = config('apex.audit.audit.signature.algorithm', 'sha512');
        return hash($algorithm, $jsonPayload);
    }

    /**
     * Verify a custom data signature.
     * 
     * @param array $data Original data that was signed
     * @param string $signature Signature to verify
     * @param string $context Context used during signing
     * @param string $timestamp Timestamp used during signing
     * @return bool True if signature is valid
     */
    public function verifyDataSignature(array $data, string $signature, string $context = 'custom', string $timestamp = null): bool
    {
        if (!$timestamp) {
            // If no timestamp provided, we can't verify accurately
            return false;
        }

        $signaturePayload = [
            'context' => $context,
            'data' => $data,
            'timestamp' => $timestamp,
            'secret_key' => $this->getSecretKey(),
        ];

        ksort($signaturePayload);
        $jsonPayload = json_encode($signaturePayload, JSON_UNESCAPED_UNICODE);

        $algorithm = config('apex.audit.audit.signature.algorithm', 'sha512');
        $calculatedSignature = hash($algorithm, $jsonPayload);

        return hash_equals($signature, $calculatedSignature);
    }

    /**
     * Get the secret key for signature generation.
     * 
     * @return string Secret key
     * @throws \Exception If secret key is not configured
     */
    protected function getSecretKey(): string
    {
        $secretKey = config('apex.audit.audit.signature.secret_key');

        if (empty($secretKey)) {
            throw new \Exception(
                'APEX Audit: Secret key not configured. Set APEX_AUDIT_SECRET_KEY in your environment file.'
            );
        }

        return $secretKey;
    }

    /**
     * Generate a secure random secret key.
     * 
     * This method creates a cryptographically secure random key suitable for
     * digital signature generation. The key is generated using PHP's random_bytes()
     * function which uses a cryptographically secure pseudo-random number generator.
     * 
     * Security considerations:
     * - Default 64 bytes (512-bit) provides optimal security for SHA-512 algorithm
     * - Minimum recommended length is 32 bytes (256-bit) for production use
     * - The generated key should be stored securely and never changed in production
     * - Changing the key will invalidate all existing audit signatures
     * 
     * Usage:
     * ```php
     * // Generate default 512-bit key
     * $key = AuditSignatureService::generateSecretKey();
     * 
     * // Generate custom length key
     * $key = AuditSignatureService::generateSecretKey(32); // 256-bit
     * 
     * // Use in environment file
     * // APEX_AUDIT_SECRET_KEY={generated_key}
     * ```
     * 
     * @param int $length Length of the key in bytes (default: 64 = 512-bit key)
     * @return string Base64 encoded secret key ready for use in .env file
     * @throws \Exception If random_bytes() fails to generate random data
     */
    public static function generateSecretKey(int $length = 64): string
    {
        return base64_encode(random_bytes($length));
    }
}
