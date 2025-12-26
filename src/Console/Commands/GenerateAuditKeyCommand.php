<?php

/*
Copyright EXOR Group ltd 2025 
Version 1.0.0.0 
APEX Laravel Auditing
Description: Artisan command for generating secure secret keys for APEX Audit package. Creates cryptographically secure random keys with options for automatic .env file integration.
*/

namespace Apex\Audit\Console\Commands;

use Illuminate\Console\Command;
use Apex\Audit\Services\AuditSignatureService;

class GenerateAuditKeyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'apex:audit:key-generate 
                            {--length=64 : Length of the key in bytes (default: 64 bytes = 512-bit)}
                            {--show : Display the key instead of writing to .env}
                            {--force : Force overwrite existing key in .env file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a secure secret key for APEX Audit digital signatures';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $length = (int) $this->option('length');
        $show = $this->option('show');
        $force = $this->option('force');

        // Validate key length
        if ($length < 16 || $length > 256) {
            $this->error('Key length must be between 16 and 256 bytes.');
            return self::FAILURE;
        }

        // Generate the secure key
        try {
            $secretKey = AuditSignatureService::generateSecretKey($length);
            
            $this->info("Generated secure audit key ({$length} bytes = " . ($length * 8) . "-bit):");
            
            if ($show) {
                // Just display the key
                $this->line('');
                $this->line('<fg=yellow>' . $secretKey . '</>');
                $this->line('');
                $this->info('Add this key to your .env file:');
                $this->line('<fg=green>APEX_AUDIT_SECRET_KEY=' . $secretKey . '</>');
                return self::SUCCESS;
            }

            // Check if key already exists
            $envPath = base_path('.env');
            if (!file_exists($envPath)) {
                $this->error('.env file not found. Please create one first or use --show option.');
                return self::FAILURE;
            }

            $envContent = file_get_contents($envPath);
            $keyExists = strpos($envContent, 'APEX_AUDIT_SECRET_KEY') !== false;

            if ($keyExists && !$force) {
                if (!$this->confirm('APEX_AUDIT_SECRET_KEY already exists in .env. Overwrite it? (This will invalidate existing audit signatures)')) {
                    $this->info('Key generation cancelled. Use --show to display the key without writing to .env.');
                    return self::SUCCESS;
                }
            }

            // Write or update the key in .env
            if ($this->updateEnvFile($envPath, $secretKey)) {
                $this->info('✅ Secret key has been ' . ($keyExists ? 'updated' : 'added') . ' to .env file.');
                
                if ($keyExists) {
                    $this->warn('⚠️  WARNING: Changing the secret key will invalidate all existing audit signatures!');
                    $this->warn('   Make sure to run signature verification after this change.');
                }

                $this->line('');
                $this->info('Next steps:');
                $this->line('1. Restart your application to load the new key');
                $this->line('2. Test the audit functionality');
                if ($keyExists) {
                    $this->line('3. Run: php artisan apex:audit:verify --all (to verify existing signatures)');
                }
                
                return self::SUCCESS;
            } else {
                $this->error('Failed to update .env file. Please add the key manually:');
                $this->line('<fg=green>APEX_AUDIT_SECRET_KEY=' . $secretKey . '</>');
                return self::FAILURE;
            }

        } catch (\Exception $e) {
            $this->error('Failed to generate secret key: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Update the .env file with the new secret key.
     *
     * @param string $envPath Path to .env file
     * @param string $secretKey Generated secret key
     * @return bool Success status
     */
    protected function updateEnvFile(string $envPath, string $secretKey): bool
    {
        try {
            $envContent = file_get_contents($envPath);
            $keyLine = 'APEX_AUDIT_SECRET_KEY=' . $secretKey;

            // Check if the key already exists
            if (preg_match('/^APEX_AUDIT_SECRET_KEY=.*$/m', $envContent)) {
                // Replace existing key
                $envContent = preg_replace('/^APEX_AUDIT_SECRET_KEY=.*$/m', $keyLine, $envContent);
            } else {
                // Add new key at the end
                $envContent = rtrim($envContent) . "\n\n# APEX Audit Configuration\n" . $keyLine . "\n";
            }

            return file_put_contents($envPath, $envContent) !== false;
        } catch (\Exception $e) {
            return false;
        }
    }
}