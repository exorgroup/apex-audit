<?php

/*
Copyright EXOR Group ltd 2025 
Version 1.0.0.0 
APEX Laravel Auditing
Description: Service for managing user-facing history display and filtering. Provides clean interfaces for viewing model change history with appropriate field filtering and user permission integration.
*/

namespace Apex\Audit\Services;

use Apex\Audit\Models\ApexHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HistoryService
{
    /**
     * Get paginated history for a specific model instance.
     */
    public function getModelHistory(
        Model $model,
        int $perPage = null,
        array $filters = []
    ): LengthAwarePaginator {
        $perPage = $perPage ?? config('apex.audit.history.ui.items_per_page', 20);

        $query = ApexHistory::forModelInstance($model)
            ->with(['user', 'rolledBackBy'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        $this->applyFilters($query, $filters);

        return $query->paginate($perPage);
    }

    /**
     * Get history for a model type (all instances).
     */
    public function getModelTypeHistory(
        string $modelType,
        int $perPage = null,
        array $filters = []
    ): LengthAwarePaginator {
        $perPage = $perPage ?? config('apex.audit.history.ui.items_per_page', 20);

        $query = ApexHistory::forModel($modelType)
            ->with(['user', 'rolledBackBy'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        $this->applyFilters($query, $filters);

        return $query->paginate($perPage);
    }

    /**
     * Get history for a specific user.
     */
    public function getUserHistory(
        int $userId,
        int $perPage = null,
        array $filters = []
    ): LengthAwarePaginator {
        $perPage = $perPage ?? config('apex.audit.history.ui.items_per_page', 20);

        $query = ApexHistory::byUser($userId)
            ->with(['user', 'rolledBackBy'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        $this->applyFilters($query, $filters);

        return $query->paginate($perPage);
    }

    /**
     * Get recent activity across all models.
     */
    public function getRecentActivity(
        int $days = 7,
        int $perPage = null,
        array $filters = []
    ): LengthAwarePaginator {
        $perPage = $perPage ?? config('apex.audit.history.ui.items_per_page', 20);

        $query = ApexHistory::recent($days)
            ->with(['user', 'rolledBackBy'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        $this->applyFilters($query, $filters);

        return $query->paginate($perPage);
    }

    /**
     * Apply filters to history query.
     */
    protected function applyFilters($query, array $filters): void
    {
        // Action type filter
        if (!empty($filters['action_type'])) {
            if (is_array($filters['action_type'])) {
                $query->whereIn('action_type', $filters['action_type']);
            } else {
                $query->where('action_type', $filters['action_type']);
            }
        }

        // User filter
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        // Date range filter
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        // Rollback status filter
        if (isset($filters['rollback_status'])) {
            switch ($filters['rollback_status']) {
                case 'can_rollback':
                    $query->where('can_rollback', true)->whereNull('rolled_back_at');
                    break;
                case 'rolled_back':
                    $query->whereNotNull('rolled_back_at');
                    break;
                case 'not_rollbackable':
                    $query->where('can_rollback', false);
                    break;
            }
        }

        // Search in description
        if (!empty($filters['search'])) {
            $query->where('description', 'like', '%' . $filters['search'] . '%');
        }

        // Model type filter
        if (!empty($filters['model_type'])) {
            $query->where('model_type', $filters['model_type']);
        }
    }

    /**
     * Get history summary statistics.
     */
    public function getHistorySummary(string $modelType = null, $modelId = null): array
    {
        $query = ApexHistory::query();

        if ($modelType) {
            $query->forModel($modelType, $modelId);
        }

        $summary = [
            'total_records' => $query->count(),
            'by_action' => $query->select('action_type', DB::raw('count(*) as count'))
                ->groupBy('action_type')
                ->pluck('count', 'action_type')
                ->toArray(),
            'rollbackable_count' => $query->where('can_rollback', true)
                ->whereNull('rolled_back_at')
                ->count(),
            'rolled_back_count' => $query->whereNotNull('rolled_back_at')->count(),
            'recent_activity' => $query->where('created_at', '>=', now()->subDays(7))->count(),
        ];

        // Add user breakdown for specific models
        if ($modelType && $modelId) {
            $summary['by_user'] = $query->select('user_id', DB::raw('count(*) as count'))
                ->whereNotNull('user_id')
                ->groupBy('user_id')
                ->with('user:id,name,email')
                ->get()
                ->pluck('count', 'user.name')
                ->toArray();
        }

        return $summary;
    }

    /**
     * Get field change analysis for a model.
     */
    public function getFieldChangeAnalysis(string $modelType, $modelId = null): array
    {
        $query = ApexHistory::forModel($modelType, $modelId)
            ->where('action_type', 'update')
            ->whereNotNull('field_changes');

        $fieldStats = [];

        $query->chunk(100, function ($records) use (&$fieldStats) {
            foreach ($records as $record) {
                if ($record->field_changes) {
                    foreach (array_keys($record->field_changes) as $field) {
                        if (!isset($fieldStats[$field])) {
                            $fieldStats[$field] = [
                                'change_count' => 0,
                                'last_changed' => null,
                                'users_who_changed' => [],
                            ];
                        }

                        $fieldStats[$field]['change_count']++;

                        if (
                            !$fieldStats[$field]['last_changed'] ||
                            $record->created_at > $fieldStats[$field]['last_changed']
                        ) {
                            $fieldStats[$field]['last_changed'] = $record->created_at;
                        }

                        if ($record->user_id && !in_array($record->user_id, $fieldStats[$field]['users_who_changed'])) {
                            $fieldStats[$field]['users_who_changed'][] = $record->user_id;
                        }
                    }
                }
            }
        });

        // Sort by change count
        uasort($fieldStats, fn($a, $b) => $b['change_count'] <=> $a['change_count']);

        return $fieldStats;
    }

    /**
     * Get rollback candidates (records that can be rolled back).
     */
    public function getRollbackCandidates(
        string $modelType = null,
        $modelId = null,
        int $limit = 50
    ): Collection {
        $query = ApexHistory::rollbackable()
            ->with(['user'])
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        if ($modelType) {
            $query->forModel($modelType, $modelId);
        }

        return $query->get();
    }

    /**
     * Get history timeline for visualization.
     */
    public function getHistoryTimeline(
        ?string $modelType = null,
        ?string $modelId = null,
        int $days = 30
    ): array {
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        $query = ApexHistory::whereBetween('created_at', [$startDate, $endDate]);

        if ($modelType) {
            $query->forModel($modelType, $modelId);
        }

        $query->orderBy('created_at');

        $timeline = [];
        $currentDate = $startDate->copy();

        // Initialize timeline with empty days
        while ($currentDate <= $endDate) {
            $timeline[$currentDate->format('Y-m-d')] = [
                'date' => $currentDate->format('Y-m-d'),
                'total' => 0,
                'by_action' => [],
            ];
            $currentDate->addDay();
        }

        // Populate timeline with actual data
        $records = $query->get()->groupBy(function ($record) {
            return $record->created_at->format('Y-m-d');
        });

        foreach ($records as $date => $dayRecords) {
            if (isset($timeline[$date])) {
                $timeline[$date]['total'] = $dayRecords->count();
                $timeline[$date]['by_action'] = $dayRecords->groupBy('action_type')
                    ->map->count()
                    ->toArray();
            }
        }

        return array_values($timeline);
    }

    /**
     * Export history data to array format.
     */
    public function exportHistory(
        ?string $modelType = null,
        ?string $modelId = null,
        array $filters = []
    ): array {
        $query = ApexHistory::query();

        if ($modelType) {
            $query->forModel($modelType, $modelId);
        }

        $query->with(['user'])->orderBy('created_at', 'desc');

        $this->applyFilters($query, $filters);

        return $query->get()->map(function ($record) {
            return [
                'id' => $record->id,
                'audit_id' => $record->audit_id,
                'model_type' => $record->model_type,
                'model_id' => $record->model_id,
                'action_type' => $record->action_type,
                'description' => $record->description,
                'field_changes' => $record->field_changes,
                'user_name' => $record->user?->name,
                'user_email' => $record->user?->email,
                'created_at' => $record->created_at->toISOString(),
                'can_rollback' => $record->can_rollback,
                'rolled_back_at' => $record->rolled_back_at?->toISOString(),
                'rolled_back_by_name' => $record->rolledBackBy?->name,
            ];
        })->toArray();
    }

    /**
     * Get widget configuration for APEX framework.
     */
    public function getWidgetConfig(
        ?string $modelType = null,
        ?string $modelId = null,
        array $options = []
    ): array {
        $config = ApexHistory::getWidgetSchema($modelType ?: '', $modelId);

        if (!empty($options['title'])) {
            $config['title'] = $options['title'];
        }

        if (!empty($options['columns'])) {
            $config['columns'] = $options['columns'];
        }

        if (!empty($options['actions'])) {
            $config['actions'] = array_merge($config['actions'], $options['actions']);
        }

        if (!empty($options['filters'])) {
            $config['filters'] = array_merge($config['filters'], $options['filters']);
        }

        $config['summary'] = $this->getHistorySummary($modelType, $modelId);

        return $config;
    }

    /**
     * Clean up old history records.
     */
    public function cleanup(): int
    {
        return ApexHistory::cleanup();
    }

    /**
     * Get user activity summary.
     */
    public function getUserActivitySummary(int $userId, int $days = 30): array
    {
        $startDate = now()->subDays($days);

        $query = ApexHistory::byUser($userId)
            ->where('created_at', '>=', $startDate);

        $totalActions = $query->count();
        $byAction = $query->select('action_type', DB::raw('count(*) as count'))
            ->groupBy('action_type')
            ->pluck('count', 'action_type')
            ->toArray();

        $byModel = $query->select('model_type', DB::raw('count(*) as count'))
            ->groupBy('model_type')
            ->pluck('count', 'model_type')
            ->mapWithKeys(function ($count, $modelType) {
                return [class_basename($modelType) => $count];
            })
            ->toArray();

        $rollbacksPerformed = ApexHistory::where('rolled_back_by', $userId)
            ->where('created_at', '>=', $startDate)
            ->count();

        return [
            'total_actions' => $totalActions,
            'by_action_type' => $byAction,
            'by_model_type' => $byModel,
            'rollbacks_performed' => $rollbacksPerformed,
            'most_active_day' => $this->getMostActiveDay($userId, $days),
            'avg_actions_per_day' => round($totalActions / $days, 1),
        ];
    }

    /**
     * Get the most active day for a user.
     */
    protected function getMostActiveDay(int $userId, int $days): ?string
    {
        $startDate = now()->subDays($days);

        $dailyActivity = ApexHistory::byUser($userId)
            ->where('created_at', '>=', $startDate)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->orderBy('count', 'desc')
            ->first();

        return $dailyActivity?->date;
    }

    /**
     * Get comparison between two time periods.
     */
    public function getActivityComparison(
        string $modelType = null,
        $modelId = null,
        int $currentPeriodDays = 30,
        int $previousPeriodDays = 30
    ): array {
        $currentStart = now()->subDays($currentPeriodDays);
        $previousStart = now()->subDays($currentPeriodDays + $previousPeriodDays);
        $previousEnd = now()->subDays($currentPeriodDays);

        $currentQuery = ApexHistory::where('created_at', '>=', $currentStart);
        $previousQuery = ApexHistory::whereBetween('created_at', [$previousStart, $previousEnd]);

        if ($modelType) {
            $currentQuery->forModel($modelType, $modelId);
            $previousQuery->forModel($modelType, $modelId);
        }

        $currentCount = $currentQuery->count();
        $previousCount = $previousQuery->count();

        $percentageChange = $previousCount > 0
            ? round((($currentCount - $previousCount) / $previousCount) * 100, 1)
            : ($currentCount > 0 ? 100 : 0);

        return [
            'current_period' => [
                'total' => $currentCount,
                'period_days' => $currentPeriodDays,
                'avg_per_day' => round($currentCount / $currentPeriodDays, 1),
            ],
            'previous_period' => [
                'total' => $previousCount,
                'period_days' => $previousPeriodDays,
                'avg_per_day' => round($previousCount / $previousPeriodDays, 1),
            ],
            'comparison' => [
                'change_count' => $currentCount - $previousCount,
                'percentage_change' => $percentageChange,
                'trend' => $percentageChange > 0 ? 'increasing' : ($percentageChange < 0 ? 'decreasing' : 'stable'),
            ],
        ];
    }
}
