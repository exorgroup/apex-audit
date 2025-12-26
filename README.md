# APEX Audit - Laravel Enterprise Audit Trail Package

[![Latest Version on Packagist](https://img.shields.io/packagist/v/apex/audit.svg?style=flat-square)](https://packagist.org/packages/apex/audit)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/apex/audit/run-tests?label=tests)](https://github.com/apex/audit/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/apex/audit.svg?style=flat-square)](https://packagist.org/packages/apex/audit)

APEX Audit is an enterprise-grade audit trail package for Laravel applications that provides forensic-level integrity and comprehensive tracking of all system actions including CRUD operations, UI interactions, and custom events.

## Features

- 🔒 **Forensic-Grade Audit Trails** - Digital signatures ensure tamper-proof audit records
- 📊 **Comprehensive Tracking** - CRUD operations, UI actions, custom events, and batch operations
- 🔄 **Rollback Capabilities** - Safely revert changes with permission-based controls
- 🌍 **Multi-Language Support** - Built-in support for 10+ languages including English, Spanish, French, German, etc.
- 🏢 **Multi-Tenancy Ready** - Full support for Stancl Tenancy package
- ⚡ **Performance Optimized** - Queue support, batch processing, and intelligent caching
- 🔧 **Highly Configurable** - Extensive configuration options for every use case
- 📱 **Laravel Integration** - Service provider auto-discovery and Artisan commands
- 🛡️ **Security Features** - Data anonymization, IP tracking, and tamper detection

## Requirements

- PHP 8.2+
- Laravel 11.0+ or 12.0+

## Installation

### 1. Install the Package

```bash
composer require apex/audit
```

### 2. Publish Configuration

```bash
php artisan vendor:publish --tag=apex-audit-config
```

### 3. Publish and Run Migrations

The package automatically detects your application architecture:

```bash
# Auto-detects and publishes to correct location
php artisan vendor:publish --tag=apex-audit-migrations
php artisan migrate
```

**Multi-tenancy auto-detection:**
- ✅ Detects existing `migrations/tenant/` folder
- ✅ Detects Stancl Tenancy package installation  
- ✅ Can be overridden with `APEX_AUDIT_TENANCY_ENABLED=true/false`
- ✅ Defaults to single-tenancy if detection is inconclusive

### 4. Generate Secret Key

```bash
php artisan apex:audit:key-generate
```

This will generate a cryptographically secure secret key and add it to your `.env` file automatically.

Alternatively, you can generate the key manually:
```bash
# Generate 512-bit key (recommended)
php -r "echo base64_encode(random_bytes(64));"

# Generate 256-bit key (minimum)
php -r "echo base64_encode(random_bytes(32));"
```

### 5. Publish Language Files (Optional)

```bash
php artisan vendor:publish --tag=apex-audit-lang
```

## Configuration

### Environment Variables

Add these to your `.env` file:

```env
# Core Audit Settings
APEX_AUDIT_ENABLED=true
APEX_AUDIT_UI_ACTIONS=true
APEX_AUDIT_TRACK_RETRIEVALS=false

# Digital Signature Settings
APEX_AUDIT_SIGNATURE_ENABLED=true
APEX_AUDIT_SECRET_KEY=your-secret-key-here

# Multi-Tenancy Support (optional - auto-detected by default)
APEX_AUDIT_TENANCY_ENABLED=auto  # auto (default), true, or false
APEX_AUDIT_TENANCY_METHOD=auto

# Performance Settings
APEX_AUDIT_QUEUE_ENABLED=false
APEX_AUDIT_CACHE_SIGNATURES=true
APEX_AUDIT_COMPRESS_DATA=true
```

### Architecture Auto-Detection

APEX Audit automatically detects your application architecture using this priority order:

1. **Explicit Configuration** - If `APEX_AUDIT_TENANCY_ENABLED` is set to `true` or `false`
2. **Tenant Migrations Folder** - If `database/migrations/tenant/` exists
3. **Stancl Tenancy Package** - If Stancl Tenancy is installed
4. **Default Fallback** - Defaults to single-tenancy mode

**Detection Results:**
- **Multi-tenant detected**: Migrations publish to `database/migrations/tenant/`
- **Single-tenant detected**: Migrations publish to `database/migrations/`

**Override Detection:**
```env
# Force multi-tenancy
APEX_AUDIT_TENANCY_ENABLED=true

# Force single-tenancy  
APEX_AUDIT_TENANCY_ENABLED=false

# Use auto-detection (default)
APEX_AUDIT_TENANCY_ENABLED=auto
```

### Configuration File

The package publishes its configuration to `config/apex/audit.php`. Key configuration sections include:

- **Audit Settings** - Enable/disable tracking, signature settings, retention policies
- **History Settings** - User-facing history display and rollback permissions
- **Security Settings** - Data anonymization, IP tracking, and tamper detection
- **Multi-Language** - Language detection and formatting options
- **Multi-Tenancy** - Tenant-aware audit trails with auto-detection
- **Performance** - Queue processing, caching, and optimization settings

## Usage

### Basic Model Auditing

Add the `ApexAuditable` trait to any Eloquent model you want to audit:

```php
<?php

use Illuminate\Database\Eloquent\Model;
use Apex\Audit\Traits\ApexAuditable;

class User extends Model
{
    use ApexAuditable;

    // Optional: Customize audit behavior
    protected $auditEvents = ['created', 'updated', 'deleted'];
    protected $auditExclude = ['password', 'remember_token'];
    protected $rollbackableActions = ['updated', 'deleted'];
}
```

### Manual Audit Logging

```php
use Apex\Audit\Services\AuditService;

class UserController extends Controller
{
    public function login(AuditService $auditService)
    {
        // Custom audit event
        $auditService->logCustomAction([
            'action_type' => 'user_login',
            'description' => 'User logged in successfully',
            'metadata' => [
                'user_id' => auth()->id(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]
        ]);
    }
}
```

### History Display

```php
use Apex\Audit\Services\HistoryService;

class HistoryController extends Controller
{
    public function show($id, HistoryService $historyService)
    {
        $model = User::find($id);
        $history = $historyService->getModelHistory($model, [
            'per_page' => 20,
            'include_rollback' => true
        ]);
        
        return view('history.show', compact('history'));
    }
}
```

### Rollback Operations

```php
use Apex\Audit\Services\RollbackService;

class RollbackController extends Controller
{
    public function rollback($historyId, RollbackService $rollbackService)
    {
        try {
            $result = $rollbackService->rollback($historyId);
            return response()->json(['success' => true, 'message' => 'Rollback successful']);
        } catch (\Apex\Audit\Exceptions\RollbackException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
```

## Advanced Features

### Digital Signatures

All audit records are automatically signed with a cryptographic signature to ensure integrity:

```php
use Apex\Audit\Services\AuditSignatureService;

$signatureService = app(AuditSignatureService::class);

// Verify audit record integrity
$isValid = $signatureService->verifySignature($auditRecord);

// Verify all signatures (scheduled task)
php artisan apex:audit:verify
```

### Multi-Language Support

The package supports multiple languages with automatic detection:

```php
// Manual language setting
app()->setLocale('es'); // Spanish

// Helper functions
echo audit_trans('audit.actions.created'); // "creado" in Spanish
echo audit_format_date($date, 'es'); // Spanish date format
```

### Batch Operations

For bulk operations, use batch tracking to maintain performance:

```php
use Apex\Audit\Services\AuditService;

$auditService = app(AuditService::class);

$auditService->logBatchOperation([
    'action_type' => 'bulk_update',
    'description' => 'Updated 1000 user records',
    'record_count' => 1000,
    'table' => 'users',
    'filters' => ['active' => true]
]);
```

## Artisan Commands

### Key Generation

Generate a secure secret key for audit signatures:

```bash
# Generate and add key to .env automatically (recommended)
php artisan apex:audit:key-generate

# Generate custom length key (in bytes)
php artisan apex:audit:key-generate --length=32

# Display key without writing to .env
php artisan apex:audit:key-generate --show

# Force overwrite existing key in .env
php artisan apex:audit:key-generate --force
```

### Audit Verification

Verify the integrity of audit records:

```bash
# Verify all records
php artisan apex:audit:verify

# Verify specific date range
php artisan apex:audit:verify --from=2025-01-01 --to=2025-01-31

# Verify specific model
php artisan apex:audit:verify --model=User
```

### Audit Cleanup

Clean up old audit records based on retention policies:

```bash
# Clean up based on config settings
php artisan apex:audit:cleanup

# Clean up records older than 90 days
php artisan apex:audit:cleanup --days=90

# Preview cleanup (dry run)
php artisan apex:audit:cleanup --dry-run
```

## Multi-Tenancy Support

APEX Audit integrates seamlessly with Stancl Tenancy:

```php
// Automatic tenant detection
// Audit records are automatically stored in tenant database

// Manual tenant switching
$auditService->setTenant($tenant);
$auditService->logCustomAction($data);
```

## Security Considerations

### Secret Key Management

Store your audit secret key securely:

```bash
# Recommended: Use the built-in command
php artisan apex:audit:key-generate

# Or generate manually
php -r "echo base64_encode(random_bytes(64));"

# Add to .env (if not using the command)
APEX_AUDIT_SECRET_KEY=your-generated-key-here
```

### Data Anonymization

Configure sensitive field anonymization:

```php
// In config/apex/audit.php
'security' => [
    'anonymization' => [
        'enabled' => true,
        'fields' => [
            'email' => 'partial', // abc***@***.com
            'phone' => 'partial',
            'ssn' => 'hash',
        ]
    ]
]
```

## Performance Optimization

### Queue Processing

Enable queue processing for high-traffic applications:

```env
APEX_AUDIT_QUEUE_ENABLED=true
APEX_AUDIT_QUEUE_CONNECTION=redis
APEX_AUDIT_QUEUE_NAME=audit
```

### Caching

Enable caching for better performance:

```env
APEX_AUDIT_CACHE_SIGNATURES=true
APEX_AUDIT_COMPRESS_DATA=true
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [EXOR Group](https://github.com/exorgroup)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Support

For support, please contact [support@exorgroup.com](mailto:support@exorgroup.com).