<?php
/**
 * Trend Controller
 * Handles trend and historical reports
 */

namespace aReports\Controllers;

use aReports\Core\Controller;
use aReports\Services\CDRService;
use aReports\Services\QueueService;

class TrendController extends Controller
{
    /**
     * Trend reports index
     */
    public function index(): void
    {
        $this->requirePermission('reports.trends.view');

        $this->render('reports/trends/index', [
            'title' => 'Trend Reports',
            'currentPage' => 'reports.trends'
        ]);
    }

    /**
     * Hourly trends
     */
    public function hourly(): void
    {
        $this->requirePermission('reports.trends.view');

        $date = $this->get('date', date('Y-m-d'));

        $cdrService = new CDRService();
        $hourlyData = $cdrService->getHourlyVolume($date);

        $queueService = new QueueService();
        $queueHourly = $queueService->getQueueHourly($date);

        $this->render('reports/trends/hourly', [
            'title' => 'Hourly Trends',
            'currentPage' => 'reports.trends.hourly',
            'date' => $date,
            'hourlyData' => $hourlyData,
            'queueHourly' => $queueHourly
        ]);
    }

    /**
     * Daily trends
     */
    public function daily(): void
    {
        $this->requirePermission('reports.trends.view');

        $dateFrom = $this->get('date_from', date('Y-m-d', strtotime('-30 days')));
        $dateTo = $this->get('date_to', date('Y-m-d'));

        $cdrService = new CDRService();
        $dailyData = $cdrService->getDailyVolume($dateFrom, $dateTo);

        $queueService = new QueueService();
        $queueDaily = $queueService->getDailyTrend($dateFrom, $dateTo);

        $this->render('reports/trends/daily', [
            'title' => 'Daily Trends',
            'currentPage' => 'reports.trends.daily',
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'dailyData' => $dailyData,
            'queueDaily' => $queueDaily
        ]);
    }

    /**
     * Period comparison
     */
    public function comparison(): void
    {
        $this->requirePermission('reports.trends.view');

        $period1From = $this->get('period1_from', date('Y-m-d', strtotime('-14 days')));
        $period1To = $this->get('period1_to', date('Y-m-d', strtotime('-7 days')));
        $period2From = $this->get('period2_from', date('Y-m-d', strtotime('-7 days')));
        $period2To = $this->get('period2_to', date('Y-m-d'));

        $cdrService = new CDRService();

        // Get stats for both periods
        $period1Stats = $this->getPeriodStats($cdrService, $period1From, $period1To);
        $period2Stats = $this->getPeriodStats($cdrService, $period2From, $period2To);

        $this->render('reports/trends/comparison', [
            'title' => 'Period Comparison',
            'currentPage' => 'reports.trends.comparison',
            'period1From' => $period1From,
            'period1To' => $period1To,
            'period2From' => $period2From,
            'period2To' => $period2To,
            'period1Stats' => $period1Stats,
            'period2Stats' => $period2Stats
        ]);
    }

    /**
     * Get statistics for a period
     */
    private function getPeriodStats(CDRService $cdrService, string $dateFrom, string $dateTo): array
    {
        $dailyData = $cdrService->getDailyVolume($dateFrom, $dateTo);

        $totalCalls = 0;
        $answeredCalls = 0;
        $totalDuration = 0;

        foreach ($dailyData as $day) {
            $totalCalls += (int) $day['total'];
            $answeredCalls += (int) $day['answered'];
            $totalDuration += (int) ($day['avg_duration'] ?? 0) * (int) $day['answered'];
        }

        return [
            'total_calls' => $totalCalls,
            'answered_calls' => $answeredCalls,
            'answer_rate' => $totalCalls > 0 ? round(($answeredCalls / $totalCalls) * 100, 1) : 0,
            'avg_duration' => $answeredCalls > 0 ? round($totalDuration / $answeredCalls) : 0,
            'daily_data' => $dailyData
        ];
    }
}
