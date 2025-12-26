<?php

/*
Copyright EXOR Group ltd 2025 
Version 1.0.0.0 
APEX Laravel Auditing
Description: Test controller for APEX Audit functionality within the APEX framework. Provides comprehensive testing endpoints for audit logging, history management, language support, and widget integration.
*/

namespace Apex\Audit\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Apex\Audit\Services\AuditService;
use Apex\Audit\Services\HistoryService;
use Apex\Audit\Services\RollbackService;
use Apex\Audit\Services\ApexAuditLanguageService;
use Apex\Audit\Exceptions\RollbackException;
use Illuminate\Support\Facades\Artisan;

class ApexAuditTestController extends Controller
{
    protected AuditService $auditService;
    protected HistoryService $historyService;
    protected RollbackService $rollbackService;
    protected ApexAuditLanguageService $languageService;

    public function __construct(
        AuditService $auditService,
        HistoryService $historyService,
        RollbackService $rollbackService,
        ApexAuditLanguageService $languageService
    ) {
        $this->auditService = $auditService;
        $this->historyService = $historyService;
        $this->rollbackService = $rollbackService;
        $this->languageService = $languageService;
    }

    /**
     * Dashboard with available test endpoints.
     */
    public function dashboard(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'APEX Audit Testing Dashboard',
            'system_status' => [
                'audit_enabled' => config('apex.audit.audit.enabled'),
                'history_enabled' => config('apex.audit.history.enabled'),
                'signatures_enabled' => config('apex.audit.audit.signature.enabled'),
                'queue_enabled' => config('apex.audit.audit.queue.enabled'),
                'current_language' => apex_lang(),
                'supported_languages' => count(apex_supported_languages()),
            ],
            'available_tests' => [
                'basic_logging' => 'Test basic audit logging functionality',
                'ui_actions' => 'Test UI action tracking',
                'history_management' => 'Test history viewing and filtering',
                'rollback_system' => 'Test rollback functionality',
                'language_support' => 'Test multi-language features',
                'signature_verification' => 'Test digital signature integrity',
                'widget_integration' => 'Test APEX widget configurations',
                'middleware_configs' => 'Test route-level configurations',
            ],
            'quick_links' => [
                'create_test_data' => '/apex/audit/test/create-test-data',
                'view_history' => '/apex/audit/history/list',
                'system_stats' => '/apex/audit/admin/statistics',
                'language_test' => '/apex/audit/language/test-translations',
            ],
        ]);
    }

    /**
     * Create test data for audit testing.
     */
    public function createTestData(): JsonResponse
    {
        $results = [];

        // Create various test scenarios
        $testScenarios = [
            ['action' => 'create', 'model' => 'Car', 'data' => ['make' => 'Toyota', 'model' => 'Camry']],
            ['action' => 'update', 'model' => 'Car', 'data' => ['price' => 25000]],
            ['action' => 'delete', 'model' => 'Car', 'data' => ['id' => 1]],
            ['action' => 'custom', 'model' => 'System', 'data' => ['test_run' => now()]],
        ];

        foreach ($testScenarios as $scenario) {
            $this->auditService->logCustomAction([
                'event_type' => $scenario['action'] === 'custom' ? 'custom' : 'model_crud',
                'action_type' => $scenario['action'],
                'model_type' => "TestModel{$scenario['model']}",
                'model_id' => rand(1, 100),
                'table_name' => strtolower($scenario['model']),
                'additional_data' => array_merge($scenario['data'], [
                    'test_scenario' => true,
                    'created_at' => now()->toISOString(),
                ]),
                'source_element' => 'test-data-generator',
            ]);

            $results[] = apex_trans('descriptions.performed_action', [
                'action' => $scenario['action'],
                'model' => $scenario['model'],
                'id' => 'test'
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => apex_trans('success.data_exported'),
            'results' => $results,
            'count' => count($results),
        ]);
    }

    /**
     * Test audit logging functionality.
     */
    public function testAuditLog(Request $request): JsonResponse
    {
        $customData = $request->get('data', []);

        $this->auditService->logCustomAction([
            'event_type' => 'custom',
            'action_type' => 'manual_test',
            'model_type' => 'TestModel',
            'model_id' => '999',
            'table_name' => 'test_table',
            'additional_data' => array_merge([
                'test_type' => 'manual_audit_test',
                'user_input' => $customData,
                'timestamp' => now()->toISOString(),
                'language' => apex_lang(),
            ], $customData),
            'source_element' => 'manual-test-form',
        ]);

        return response()->json([
            'success' => true,
            'message' => apex_trans('success.audit_created'),
            'language' => apex_lang(),
            'test_data' => $customData,
        ]);
    }

    /**
     * Test UI action logging.
     */
    public function testUIAction(Request $request): JsonResponse
    {
        $actionType = $request->get('action_type', 'button_click');
        $element = $request->get('element', 'test-button');

        $this->auditService->logUIAction([
            'action_type' => $actionType,
            'source_element' => $element,
            'additional_data' => [
                'page_context' => 'audit_testing',
                'user_agent' => $request->userAgent(),
                'test_mode' => true,
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'UI action logged successfully',
            'action_type' => $actionType,
            'element' => $element,
        ]);
    }

    /**
     * Get audit history with filtering.
     */
    public function getHistory(Request $request): JsonResponse
    {
        $filters = $request->only(['action_type', 'user_id', 'search', 'date_from', 'date_to']);
        $perPage = $request->get('per_page', 20);

        $history = $this->historyService->getRecentActivity(30, $perPage, $filters);
        $summary = $this->historyService->getHistorySummary();

        return response()->json([
            'success' => true,
            'data' => $history,
            'summary' => $summary,
            'filters_applied' => $filters,
            'language' => apex_lang(),
        ]);
    }

    /**
     * Preview rollback operation.
     */
    public function previewRollback(int $historyId): JsonResponse
    {
        try {
            $preview = $this->rollbackService->previewRollback($historyId);

            return response()->json([
                'success' => true,
                'preview' => $preview,
                'title' => apex_trans('rollback.preview_title'),
                'description' => apex_trans('rollback.preview_description'),
            ]);
        } catch (RollbackException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getUserMessage(),
                'details' => $e->toArray(),
            ], 400);
        }
    }

    /**
     * Perform rollback operation.
     */
    public function performRollback(int $historyId): JsonResponse
    {
        try {
            $result = $this->rollbackService->rollback($historyId);

            return response()->json([
                'success' => true,
                'message' => apex_trans('rollback.success'),
                'rolled_back' => $result,
            ]);
        } catch (RollbackException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getUserMessage(),
                'details' => $e->toArray(),
            ], 400);
        }
    }

    /**
     * Export history data.
     */
    public function exportHistory(Request $request): JsonResponse
    {
        $filters = $request->only(['action_type', 'user_id', 'date_from', 'date_to']);
        $data = $this->historyService->exportHistory(null, null, $filters);

        return response()->json([
            'success' => true,
            'data' => $data,
            'count' => count($data),
            'exported_at' => now()->toISOString(),
            'filters' => $filters,
        ]);
    }

    /**
     * Get current language information.
     */
    public function getCurrentLanguage(): JsonResponse
    {
        return response()->json([
            'current_language' => $this->languageService->getCurrentLanguage(),
            'supported_languages' => $this->languageService->getSupportedLanguages(),
            'detection_method' => config('apex.audit.language.detection_method'),
            'direction' => $this->languageService->getLanguageDirection(),
            'cache_enabled' => config('apex.audit.language.cache.enabled'),
        ]);
    }

    /**
     * Set language for testing.
     */
    public function setLanguage(string $language): JsonResponse
    {
        if (!$this->languageService->isLanguageSupported($language)) {
            return response()->json([
                'success' => false,
                'error' => 'Language not supported',
                'supported' => array_keys($this->languageService->getSupportedLanguages()),
            ], 400);
        }

        $this->languageService->setLanguage($language);

        return response()->json([
            'success' => true,
            'message' => 'Language set successfully',
            'language' => $language,
            'test_translations' => [
                'title' => apex_trans('history.title'),
                'success' => apex_trans('rollback.success'),
                'created' => apex_trans('actions.create'),
            ],
        ]);
    }

    /**
     * Test translations in current language.
     */
    public function testTranslations(): JsonResponse
    {
        return response()->json([
            'current_language' => apex_lang(),
            'language_name' => $this->languageService->getSupportedLanguages()[apex_lang()] ?? 'Unknown',
            'sample_translations' => [
                'actions' => [
                    'create' => apex_trans('actions.create'),
                    'update' => apex_trans('actions.update'),
                    'delete' => apex_trans('actions.delete'),
                    'restore' => apex_trans('actions.restore'),
                ],
                'messages' => [
                    'history_title' => apex_trans('history.title'),
                    'rollback_success' => apex_trans('rollback.success'),
                    'no_records' => apex_trans('history.no_records'),
                ],
                'dynamic_examples' => [
                    'created_new' => apex_trans('descriptions.created_new', [
                        'model' => 'Car',
                        'id' => '123'
                    ]),
                    'updated_model' => apex_trans('descriptions.updated_model', [
                        'model' => 'User',
                        'id' => '456',
                        'fields' => 'name, email'
                    ]),
                ],
            ],
        ]);
    }

    /**
     * Run signature verification test.
     */
    public function verifySignatures(Request $request): JsonResponse
    {
        $sample = $request->get('sample', 10);

        Artisan::call('apex:audit-verify', [
            '--sample' => $sample,
        ]);

        return response()->json([
            'success' => true,
            'output' => Artisan::output(),
            'message' => apex_trans('verification.verification_completed'),
            'sample_size' => $sample,
        ]);
    }

    /**
     * Test cleanup functionality (dry run).
     */
    public function testCleanup(Request $request): JsonResponse
    {
        $days = $request->get('days', 30);

        Artisan::call('apex:audit-cleanup', [
            '--dry-run' => true,
            '--days' => $days,
        ]);

        return response()->json([
            'success' => true,
            'output' => Artisan::output(),
            'message' => 'Cleanup test completed (dry run)',
            'retention_days' => $days,
        ]);
    }

    /**
     * Get comprehensive system statistics.
     */
    public function getStatistics(): JsonResponse
    {
        return response()->json([
            'audit_system' => [
                'history_summary' => $this->historyService->getHistorySummary(),
                'rollback_stats' => $this->rollbackService->getRollbackStatistics(),
                'recent_activity' => $this->historyService->getRecentActivity(7, 5),
            ],
            'configuration' => [
                'audit_enabled' => config('apex.audit.audit.enabled'),
                'history_enabled' => config('apex.audit.history.enabled'),
                'signatures_enabled' => config('apex.audit.audit.signature.enabled'),
                'queue_enabled' => config('apex.audit.audit.queue.enabled'),
                'language_method' => config('apex.audit.language.detection_method'),
                'current_language' => apex_lang(),
            ],
            'performance' => [
                'cache_enabled' => config('apex.audit.language.cache.enabled'),
                'compression_enabled' => config('apex.audit.audit.performance.compress_large_data'),
                'batch_processing' => config('apex.audit.audit.batch.enabled'),
            ],
            'generated_at' => now()->toISOString(),
        ]);
    }

    /**
     * Get history widget configuration.
     */
    public function getHistoryWidget(Request $request, string $modelType = null, string $modelId = null): JsonResponse
    {
        $options = $request->get('options', []);
        $config = $this->historyService->getWidgetConfig($modelType, $modelId, $options);

        return response()->json([
            'success' => true,
            'widget_config' => $config,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'language' => apex_lang(),
        ]);
    }

    /**
     * Get summary widget data.
     */
    public function getSummaryWidget(): JsonResponse
    {
        $summary = $this->historyService->getHistorySummary();
        $timeline = $this->historyService->getHistoryTimeline(null, null, 30);

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'timeline' => $timeline,
            'widget_title' => apex_trans('widgets.audit_summary'),
            'language' => apex_lang(),
        ]);
    }

    /**
     * Test middleware configuration capture.
     */
    public function testMiddlewareConfig(Request $request): JsonResponse
    {
        $config = app('apex.audit.request.config', []);

        return response()->json([
            'success' => true,
            'message' => 'Middleware configuration captured',
            'captured_config' => $config,
            'request_info' => [
                'method' => $request->method(),
                'path' => $request->path(),
                'route' => $request->route()?->getName(),
            ],
        ]);
    }

    /**
     * Run comprehensive test suite.
     */
    public function runTestSuite(): JsonResponse
    {
        $results = [];

        // Test 1: Basic audit logging
        try {
            $this->auditService->logCustomAction([
                'event_type' => 'test_suite',
                'action_type' => 'basic_test',
                'model_type' => 'TestSuite',
                'model_id' => '1',
                'additional_data' => ['test' => 'basic_logging'],
            ]);
            $results['basic_logging'] = ['status' => 'passed', 'message' => 'Basic audit logging works'];
        } catch (\Exception $e) {
            $results['basic_logging'] = ['status' => 'failed', 'message' => $e->getMessage()];
        }

        // Test 2: Language system
        try {
            $currentLang = apex_lang();
            $translation = apex_trans('history.title');
            $results['language_system'] = [
                'status' => 'passed',
                'current_language' => $currentLang,
                'sample_translation' => $translation
            ];
        } catch (\Exception $e) {
            $results['language_system'] = ['status' => 'failed', 'message' => $e->getMessage()];
        }

        // Test 3: History service
        try {
            $summary = $this->historyService->getHistorySummary();
            $results['history_service'] = [
                'status' => 'passed',
                'total_records' => $summary['total_records'] ?? 0
            ];
        } catch (\Exception $e) {
            $results['history_service'] = ['status' => 'failed', 'message' => $e->getMessage()];
        }

        // Test 4: Configuration
        $configTests = [
            'audit_enabled' => config('apex.audit.audit.enabled'),
            'signatures_enabled' => config('apex.audit.audit.signature.enabled'),
            'language_supported' => count(apex_supported_languages()) > 0,
        ];

        $results['configuration'] = [
            'status' => array_reduce($configTests, fn($carry, $test) => $carry && $test, true) ? 'passed' : 'failed',
            'tests' => $configTests
        ];

        return response()->json([
            'success' => true,
            'message' => 'Test suite completed',
            'results' => $results,
            'overall_status' => !in_array('failed', array_column($results, 'status')) ? 'passed' : 'failed',
            'executed_at' => now()->toISOString(),
        ]);
    }
}
