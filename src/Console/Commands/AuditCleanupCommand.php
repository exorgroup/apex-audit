<?php

/*
Copyright EXOR Group ltd 2025 
Version 1.0.0.0 
APEX Laravel Auditing
Description: Console command for cleaning up old audit and history records based on retention policies. Provides safe cleanup operations while maintaining audit trail integrity and compliance requirements.
*/

namespace Apex\Audit\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Apex\Audit\Models\ApexHistory;

class AuditCleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'apex:audit-cleanup 
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--force : Force cleanup without confirmation}
                            {--history-only : Only cleanup history records, not audit records}
                            {--days= : Override retention days from config}';

    /**
     * The console command description.
     */
    protected $description = 'Clean up old audit and history records based on retention policies';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('APEX Audit Cleanup Starting...');

        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $historyOnly = $this->option('history-only');
        $customDays = $this->option('days');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No records will actually be deleted');
        }

        // Cleanup history records
        $historyResults = $this->cleanupHistory($dryRun, $force, $customDays);

        // Cleanup audit records (if not history-only)
        $auditResults = null;
        if (!$historyOnly) {
            $auditResults = $this->cleanupAudit($dryRun, $force, $customDays);
        }

        // Display summary
        $this->displaySummary($historyResults, $auditResults, $dryRun, $historyOnly);

        return Command::SUCCESS;
    }

    /**
     * Cleanup history records based on retention policy.
     */
    protected function cleanupHistory(bool $dryRun, bool $force, ?string $customDays): array
    {
        $this->info('Processing history records cleanup...');

        $retentionDays = $customDays ? (int) $customDays : config('apex.audit.history.retention_days');

        if (!$retentionDays) {
            $this->info('History retention not configured - skipping history cleanup');
            return ['skipped' => true, 'reason' => 'No retention policy configured'];
        }

        $cutoffDate = now()->subDays($retentionDays);
        $this->info("History retention: {$retentionDays} days (cutoff: {$cutoffDate->format('Y-m-d H:i:s')})");

        // Count records to be deleted
        $recordsToDelete = ApexHistory::where('created_at', '<', $cutoffDate)->count();

        if ($recordsToDelete === 0) {
            $this->info('No history records found for cleanup');
            return ['deleted' => 0, 'cutoff_date' => $cutoffDate];
        }

        $this->info("Found {$recordsToDelete} history records for cleanup");

        if ($dryRun) {
            // Show sample records that would be deleted
            $sampleRecords = ApexHistory::where('created_at', '<', $cutoffDate)
                ->orderBy('created_at')
                ->limit(5)
                ->get(['id', 'model_type', 'action_type', 'created_at']);

            $this->table(
                ['ID', 'Model Type', 'Action', 'Created At'],
                $sampleRecords->map(fn($record) => [
                    $record->id,
                    class_basename($record->model_type),
                    $record->action_type,
                    $record->created_at->format('Y-m-d H:i:s')
                ])->toArray()
            );

            if ($recordsToDelete > 5) {
                $this->info("... and " . ($recordsToDelete - 5) . " more records");
            }

            return ['would_delete' => $recordsToDelete, 'cutoff_date' => $cutoffDate];
        }

        // Confirm deletion unless forced
        if (!$force) {
            if (!$this->confirm("Delete {$recordsToDelete} history records older than {$cutoffDate->format('Y-m-d')}?")) {
                $this->info('History cleanup cancelled by user');
                return ['cancelled' => true, 'reason' => 'User cancelled'];
            }
        }

        // Perform deletion
        $deletedCount = ApexHistory::where('created_at', '<', $cutoffDate)->delete();

        $this->info("Successfully deleted {$deletedCount} history records");

        return ['deleted' => $deletedCount, 'cutoff_date' => $cutoffDate];
    }

    /**
     * Cleanup audit records based on retention policy.
     */
    protected function cleanupAudit(bool $dryRun, bool $force, ?string $customDays): array
    {
        $this->info('Processing audit records cleanup...');

        $retentionDays = $customDays ? (int) $customDays : config('apex.audit.audit.retention_days');

        if (!$retentionDays) {
            $this->warn('⚠️  Audit retention not configured - audit records will be kept forever');
            $this->info('This is recommended for compliance, but may affect performance over time');
            return ['skipped' => true, 'reason' => 'No retention policy configured (recommended)'];
        }

        $this->warn('⚠️  CAUTION: Deleting audit records may violate compliance requirements');

        $cutoffDate = now()->subDays($retentionDays);
        $this->info("Audit retention: {$retentionDays} days (cutoff: {$cutoffDate->format('Y-m-d H:i:s')})");

        // Count records to be deleted
        $connection = config('apex.audit.audit.connection');
        $db = $connection ? DB::connection($connection) : DB::connection();

        $recordsToDelete = $db->table('apex_audit')
            ->where('created_at', '<', $cutoffDate)
            ->count();

        if ($recordsToDelete === 0) {
            $this->info('No audit records found for cleanup');
            return ['deleted' => 0, 'cutoff_date' => $cutoffDate];
        }

        $this->info("Found {$recordsToDelete} audit records for cleanup");

        if ($dryRun) {
            // Show sample records that would be deleted
            $sampleRecords = $db->table('apex_audit')
                ->where('created_at', '<', $cutoffDate)
                ->orderBy('created_at')
                ->limit(5)
                ->get(['id', 'event_type', 'action_type', 'model_type', 'created_at']);

            $this->table(
                ['ID', 'Event Type', 'Action', 'Model', 'Created At'],
                collect($sampleRecords)->map(fn($record) => [
                    $record->id,
                    $record->event_type,
                    $record->action_type,
                    $record->model_type ? class_basename($record->model_type) : 'N/A',
                    $record->created_at
                ])->toArray()
            );

            if ($recordsToDelete > 5) {
                $this->info("... and " . ($recordsToDelete - 5) . " more records");
            }

            return ['would_delete' => $recordsToDelete, 'cutoff_date' => $cutoffDate];
        }

        // Extra confirmation for audit records
        if (!$force) {
            $this->error('🚨 WARNING: You are about to permanently delete audit records!');
            $this->error('This action cannot be undone and may violate compliance requirements.');

            if (!$this->confirm("Are you ABSOLUTELY SURE you want to delete {$recordsToDelete} audit records?")) {
                $this->info('Audit cleanup cancelled by user');
                return ['cancelled' => true, 'reason' => 'User cancelled'];
            }

            if (!$this->confirm('Type "DELETE AUDIT RECORDS" to confirm', false)) {
                $this->info('Audit cleanup cancelled - confirmation phrase not entered');
                return ['cancelled' => true, 'reason' => 'Confirmation phrase not entered'];
            }
        }

        // Perform deletion
        $deletedCount = $db->table('apex_audit')
            ->where('created_at', '<', $cutoffDate)
            ->delete();

        $this->info("Successfully deleted {$deletedCount} audit records");

        // Log this critical action
        Log::critical('APEX Audit: Audit records deleted via cleanup command', [
            'deleted_count' => $deletedCount,
            'cutoff_date' => $cutoffDate->toISOString(),
            'retention_days' => $retentionDays,
            'executed_by' => Auth::check() ? Auth::user()?->email ?? 'authenticated_user' : 'console',
            'command_options' => $this->options(),
        ]);

        return ['deleted' => $deletedCount, 'cutoff_date' => $cutoffDate];
    }

    /**
     * Display cleanup summary.
     */
    protected function displaySummary(array $historyResults, ?array $auditResults, bool $dryRun, bool $historyOnly): void
    {
        $this->info('');
        $this->info('=== CLEANUP SUMMARY ===');

        // History summary
        if (isset($historyResults['deleted'])) {
            $action = $dryRun ? 'Would delete' : 'Deleted';
            $this->info("History: {$action} {$historyResults['deleted']} records");
        } elseif (isset($historyResults['would_delete'])) {
            $this->info("History: Would delete {$historyResults['would_delete']} records");
        } elseif (isset($historyResults['skipped'])) {
            $this->info("History: Skipped - {$historyResults['reason']}");
        } elseif (isset($historyResults['cancelled'])) {
            $this->info("History: Cancelled - {$historyResults['reason']}");
        }

        // Audit summary
        if (!$historyOnly && $auditResults) {
            if (isset($auditResults['deleted'])) {
                $action = $dryRun ? 'Would delete' : 'Deleted';
                $this->info("Audit: {$action} {$auditResults['deleted']} records");
            } elseif (isset($auditResults['would_delete'])) {
                $this->info("Audit: Would delete {$auditResults['would_delete']} records");
            } elseif (isset($auditResults['skipped'])) {
                $this->info("Audit: Skipped - {$auditResults['reason']}");
            } elseif (isset($auditResults['cancelled'])) {
                $this->info("Audit: Cancelled - {$auditResults['reason']}");
            }
        }

        if ($dryRun) {
            $this->info('');
            $this->info('To perform actual cleanup, run without --dry-run flag');
        }

        $this->info('Cleanup completed successfully');
    }

    /**
     * Get cleanup statistics without performing deletion.
     */
    public function getCleanupStats(): array
    {
        $historyRetention = config('apex.audit.history.retention_days');
        $auditRetention = config('apex.audit.audit.retention_days');

        $stats = [];

        // History stats
        if ($historyRetention) {
            $historyCutoff = now()->subDays($historyRetention);
            $stats['history'] = [
                'retention_days' => $historyRetention,
                'cutoff_date' => $historyCutoff,
                'records_to_cleanup' => ApexHistory::where('created_at', '<', $historyCutoff)->count(),
            ];
        } else {
            $stats['history'] = ['retention_configured' => false];
        }

        // Audit stats
        if ($auditRetention) {
            $auditCutoff = now()->subDays($auditRetention);
            $connection = config('apex.audit.audit.connection');
            $db = $connection ? DB::connection($connection) : DB::connection();

            $stats['audit'] = [
                'retention_days' => $auditRetention,
                'cutoff_date' => $auditCutoff,
                'records_to_cleanup' => $db->table('apex_audit')->where('created_at', '<', $auditCutoff)->count(),
            ];
        } else {
            $stats['audit'] = ['retention_configured' => false];
        }

        return $stats;
    }
}
