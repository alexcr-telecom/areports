<?php
/**
 * SLA Controller
 * Handles SLA compliance reports
 */

namespace aReports\Controllers;

use aReports\Core\Controller;
use aReports\Services\QueueService;

class SLAController extends Controller
{
    /**
     * SLA reports index
     */
    public function index(): void
    {
        $this->compliance();
    }

    /**
     * SLA compliance report
     */
    public function compliance(): void
    {
        $this->requirePermission('reports.sla.view');

        $dateFrom = $this->get('date_from', date('Y-m-d', strtotime('-30 days')));
        $dateTo = $this->get('date_to', date('Y-m-d'));

        $queueService = new QueueService();
        $slaData = $queueService->getQueueSLA($dateFrom, $dateTo);
        $queueList = $queueService->getQueueList();
        $dailyTrend = $queueService->getDailyTrend($dateFrom, $dateTo);

        // Calculate overall SLA
        $totalAnswered = 0;
        $totalWithinSla = 0;
        foreach ($slaData as $queue) {
            $totalAnswered += $queue['total_answered'];
            $totalWithinSla += $queue['within_sla'];
        }
        $overallSla = $totalAnswered > 0 ? round(($totalWithinSla / $totalAnswered) * 100, 1) : 0;

        $this->render('reports/sla/compliance', [
            'title' => 'SLA Compliance',
            'currentPage' => 'reports.sla',
            'slaData' => $slaData,
            'queueList' => $queueList,
            'dailyTrend' => $dailyTrend,
            'overallSla' => $overallSla,
            'totalAnswered' => $totalAnswered,
            'totalWithinSla' => $totalWithinSla,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo
        ]);
    }
}
