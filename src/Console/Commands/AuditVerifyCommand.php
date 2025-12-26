<?php

/*
Copyright EXOR Group ltd 2025 
Version 1.0.0.0 
APEX Laravel Auditing
Description: Console command for verifying audit record signatures and detecting potential tampering. Provides batch verification capabilities with detailed reporting of signature integrity status.
*/

namespace Apex\Audit\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Apex\Audit\Services\AuditSignatureService;

class AuditVerifyCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'apex:audit-verify 
                            {--all : Verify all audit records (may take time)}
                            {--sample= : Verify a random sample of records (percentage: 1-100)}
                            {--since= : Verify records since date (YYYY-MM-DD)}
                            {--days= : Verify records from last N days}
                            {--id= : Verify specific audit record by ID}
                            {--uuid= : Verify specific audit record by UUID}
                            {--batch-size=1000 : Number of records to process in each batch}
                            {--detailed : Show detailed output for each verification}';

    /**
     * The console command description.
     */
    protected $description = 'Verify audit record signatures to detect tampering';

    protected AuditSignatureService $signatureService;

    /**
     * Create a new command instance.
     */
    public function __construct(AuditSignatureService $signatureService)
    {
        parent::__construct();
        $this->signatureService = $signatureService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('APEX Audit Signature Verification Starting...');

        if (!config('apex.audit.audit.signature.enabled')) {
            $this->error('Audit signatures are disabled in configuration');
            return Command::FAILURE;
        }

        try {
            // Determine verification scope
            $query = $this->buildQuery();
            $totalRecords = $query->count();

            if ($totalRecords === 0) {
                $this->info('No audit records found for verification');
                return Command::SUCCESS;
            }

            $this->info("Found {$totalRecords} audit records to verify");

            // Perform verification
            $results = $this->performVerification($query);

            // Display results
            $this->displayResults($results, $totalRecords);

            // Return appropriate exit code
            return $results['failed_count'] > 0 ? Command::FAILURE : Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Verification failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * Build the query based on command options.
     */
    protected function buildQuery()
    {
        $connection = config('apex.audit.audit.connection');
        $db = $connection ? DB::connection($connection) : DB::connection();
        $query = $db->table('apex_audit');

        // Specific record by ID
        if ($this->option('id')) {
            return $query->where('id', $this->option('id'));
        }

        // Specific record by UUID
        if ($this->option('uuid')) {
            return $query->where('audit_uuid', $this->option('uuid'));
        }

        // Date-based filtering
        if ($this->option('since')) {
            $sinceDate = $this->option('since');
            if (!strtotime($sinceDate)) {
                throw new \InvalidArgumentException('Invalid date format for --since option');
            }
            $query->where('created_at', '>=', $sinceDate);
        }

        if ($this->option('days')) {
            $days = (int) $this->option('days');
            $query->where('created_at', '>=', now()->subDays($days));
        }

        // Sample percentage
        if ($this->option('sample')) {
            $samplePercent = (float) $this->option('sample');
            if ($samplePercent < 1 || $samplePercent > 100) {
                throw new \InvalidArgumentException('Sample percentage must be between 1 and 100');
            }

            // Use RAND() for MySQL or RANDOM() for other databases
            $randomFunction = config('database.default') === 'mysql' ? 'RAND()' : 'RANDOM()';
            $query->whereRaw("$randomFunction < ?", [$samplePercent / 100]);
        }

        return $query->orderBy('created_at');
    }

    /**
     * Perform the signature verification.
     */
    protected function performVerification($query): array
    {
        $batchSize = (int) $this->option('batch-size');
        $detailed = $this->option('detailed');

        $totalVerified = 0;
        $validCount = 0;
        $invalidCount = 0;
        $invalidRecords = [];

        $progressBar = $this->output->createProgressBar($query->count());
        $progressBar->start();

        $query->chunk($batchSize, function ($records) use (&$totalVerified, &$validCount, &$invalidCount, &$invalidRecords, $detailed, $progressBar) {
            foreach ($records as $record) {
                $recordArray = (array) $record;
                $storedSignature = $recordArray['signature'];

                // Remove signature from data for verification
                unset($recordArray['signature']);

                $isValid = $this->signatureService->verifySignature($recordArray, $storedSignature);

                $totalVerified++;

                if ($isValid) {
                    $validCount++;
                    if ($detailed) {
                        $this->line("✅ Record {$record->id} ({$record->audit_uuid}): VALID");
                    }
                } else {
                    $invalidCount++;
                    $invalidRecords[] = [
                        'id' => $record->id,
                        'uuid' => $record->audit_uuid,
                        'created_at' => $record->created_at,
                        'event_type' => $record->event_type,
                        'action_type' => $record->action_type,
                    ];

                    if ($detailed) {
                        $this->line("❌ Record {$record->id} ({$record->audit_uuid}): INVALID");
                    }
                }

                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->line('');

        return [
            'total_verified' => $totalVerified,
            'valid_count' => $validCount,
            'invalid_count' => $invalidCount,
            'failed_count' => $invalidCount, // Alias for exit code logic
            'invalid_records' => $invalidRecords,
            'validity_percentage' => $totalVerified > 0 ? round(($validCount / $totalVerified) * 100, 2) : 0,
        ];
    }

    /**
     * Display verification results.
     */
    protected function displayResults(array $results, int $totalRecords): void
    {
        $this->info('');
        $this->info('=== VERIFICATION RESULTS ===');
        $this->info("Total records processed: {$results['total_verified']}");
        $this->info("Valid signatures: {$results['valid_count']}");

        if ($results['invalid_count'] > 0) {
            $this->error("Invalid signatures: {$results['invalid_count']}");
            $this->error("Validity rate: {$results['validity_percentage']}%");

            $this->error('');
            $this->error('🚨 TAMPERING DETECTED! 🚨');
            $this->error('The following records have invalid signatures:');

            $this->table(
                ['ID', 'UUID', 'Created At', 'Event Type', 'Action Type'],
                collect($results['invalid_records'])->map(fn($record) => [
                    $record['id'],
                    substr($record['uuid'], 0, 8) . '...',
                    $record['created_at'],
                    $record['event_type'],
                    $record['action_type'],
                ])->toArray()
            );

            $this->error('');
            $this->error('Recommended actions:');
            $this->error('1. Investigate when and how these records were modified');
            $this->error('2. Check database access logs for unauthorized changes');
            $this->error('3. Notify security team if tampering is confirmed');
            $this->error('4. Consider restoring from backup if integrity is compromised');

            // Log critical security event
            Log::critical('APEX Audit: Signature verification detected tampered records', [
                'invalid_count' => $results['invalid_count'],
                'total_verified' => $results['total_verified'],
                'validity_percentage' => $results['validity_percentage'],
                'invalid_record_ids' => collect($results['invalid_records'])->pluck('id')->toArray(),
                'verification_time' => now()->toISOString(),
            ]);
        } else {
            $this->info("Validity rate: {$results['validity_percentage']}%");
            $this->info('✅ All signatures are valid - no tampering detected');
        }

        $this->info('');
        $this->info('Verification completed');
    }

    /**
     * Get verification statistics without running full verification.
     */
    public function getVerificationStats(): array
    {
        $connection = config('apex.audit.audit.connection');
        $db = $connection ? DB::connection($connection) : DB::connection();

        $totalRecords = $db->table('apex_audit')->count();
        $recordsLast24h = $db->table('apex_audit')
            ->where('created_at', '>=', now()->subDay())
            ->count();
        $recordsLast7days = $db->table('apex_audit')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return [
            'total_audit_records' => $totalRecords,
            'records_last_24h' => $recordsLast24h,
            'records_last_7_days' => $recordsLast7days,
            'signature_enabled' => config('apex.audit.audit.signature.enabled'),
            'last_verification' => $this->getLastVerificationTime(),
        ];
    }

    /**
     * Get the timestamp of the last verification run.
     */
    protected function getLastVerificationTime(): ?string
    {
        // This could be stored in cache or a dedicated table
        // For now, we'll check the log files or return null
        return cache('apex_audit_last_verification');
    }

    /**
     * Quick verification of a single record.
     */
    public function verifyRecord(int $recordId): array
    {
        $connection = config('apex.audit.audit.connection');
        $db = $connection ? DB::connection($connection) : DB::connection();

        $record = $db->table('apex_audit')->where('id', $recordId)->first();

        if (!$record) {
            return ['error' => 'Record not found'];
        }

        $recordArray = (array) $record;
        $storedSignature = $recordArray['signature'];
        unset($recordArray['signature']);

        $isValid = $this->signatureService->verifySignature($recordArray, $storedSignature);

        return [
            'record_id' => $recordId,
            'audit_uuid' => $record->audit_uuid,
            'is_valid' => $isValid,
            'created_at' => $record->created_at,
            'event_type' => $record->event_type,
            'action_type' => $record->action_type,
        ];
    }

    /**
     * Schedule automatic verification.
     */
    public function scheduleVerification(): void
    {
        // Store timestamp of verification
        cache(['apex_audit_last_verification' => now()->toISOString()], now()->addDays(30));

        $frequency = config('apex.audit.security.verification.frequency', 'daily');
        $sampleRate = config('apex.audit.security.verification.sample_rate', 0.1);

        $this->info("Verification scheduled: {$frequency} with {$sampleRate}% sample rate");
    }
}
