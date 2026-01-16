<?php
/**
 * Agent Report Controller
 * Handles agent performance reports
 */

namespace aReports\Controllers;

use aReports\Core\App;
use aReports\Core\Controller;
use aReports\Services\AgentService;

class AgentReportController extends Controller
{
    private AgentService $agentService;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->agentService = new AgentService();
    }

    /**
     * Agent performance report
     */
    public function performance(): void
    {
        $this->requirePermission('reports.agent.view');

        $dateFrom = $this->get('date_from', date('Y-m-d', strtotime('-7 days')));
        $dateTo = $this->get('date_to', date('Y-m-d'));
        $agentFilter = $this->get('agent');

        $agents = $this->agentService->getAgentPerformance($dateFrom, $dateTo, $agentFilter);
        $agentList = $this->agentService->getAgentList();

        // Calculate totals
        $totals = [
            'calls_handled' => 0,
            'calls_missed' => 0,
            'total_talk_time' => 0,
            'total_hold_time' => 0
        ];

        foreach ($agents as $agent) {
            $totals['calls_handled'] += $agent['calls_handled'];
            $totals['calls_missed'] += $agent['calls_missed'];
            $totals['total_talk_time'] += $agent['total_talk_time'];
            $totals['total_hold_time'] += $agent['total_hold_time'];
        }

        $totals['answer_rate'] = ($totals['calls_handled'] + $totals['calls_missed']) > 0
            ? round(($totals['calls_handled'] / ($totals['calls_handled'] + $totals['calls_missed'])) * 100, 1)
            : 0;

        $this->render('reports/agent/performance', [
            'title' => 'Agent Performance',
            'currentPage' => 'reports.agent.performance',
            'agents' => $agents,
            'agentList' => $agentList,
            'totals' => $totals,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'agentFilter' => $agentFilter
        ]);
    }

    /**
     * Agent activity log
     */
    public function activity(): void
    {
        $this->requirePermission('reports.agent.view');

        $dateFrom = $this->get('date_from', date('Y-m-d'));
        $dateTo = $this->get('date_to', date('Y-m-d'));
        $agentFilter = $this->get('agent');

        $activities = $this->agentService->getAgentActivity($dateFrom, $dateTo, $agentFilter);
        $agentList = $this->agentService->getAgentList();

        $this->render('reports/agent/activity', [
            'title' => 'Agent Activity',
            'currentPage' => 'reports.agent.activity',
            'activities' => $activities,
            'agentList' => $agentList,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'agentFilter' => $agentFilter
        ]);
    }

    /**
     * Agent efficiency report
     */
    public function efficiency(): void
    {
        $this->requirePermission('reports.agent.view');

        $dateFrom = $this->get('date_from', date('Y-m-d', strtotime('-7 days')));
        $dateTo = $this->get('date_to', date('Y-m-d'));
        $agentFilter = $this->get('agent');

        $efficiency = $this->agentService->getAgentEfficiency($dateFrom, $dateTo, $agentFilter);
        $agentList = $this->agentService->getAgentList();

        // Group by agent
        $groupedData = [];
        foreach ($efficiency as $row) {
            $agent = $row['agent'];
            if (!isset($groupedData[$agent])) {
                $groupedData[$agent] = [
                    'agent' => $agent,
                    'queues' => [],
                    'total_calls' => 0,
                    'total_talk_time' => 0
                ];
            }
            $groupedData[$agent]['queues'][] = $row;
            $groupedData[$agent]['total_calls'] += $row['calls_handled'];
            $groupedData[$agent]['total_talk_time'] += $row['talk_time'];
        }

        $this->render('reports/agent/efficiency', [
            'title' => 'Agent Efficiency',
            'currentPage' => 'reports.agent.efficiency',
            'groupedData' => $groupedData,
            'agentList' => $agentList,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'agentFilter' => $agentFilter
        ]);
    }

    /**
     * Individual agent detail
     */
    public function detail(string $agent): void
    {
        $this->requirePermission('reports.agent.view');

        $dateFrom = $this->get('date_from', date('Y-m-d'));
        $dateTo = $this->get('date_to', date('Y-m-d'));

        $performance = $this->agentService->getAgentPerformance($dateFrom, $dateTo, $agent);
        $hourly = $this->agentService->getAgentHourly(date('Y-m-d'), $agent);
        $trend = $this->agentService->getAgentDailyTrend($dateFrom, $dateTo, $agent);
        $activity = $this->agentService->getAgentActivity($dateFrom, $dateTo, $agent);

        $agentData = $performance[0] ?? [
            'agent' => $agent,
            'display_name' => $agent,
            'calls_handled' => 0,
            'calls_missed' => 0,
            'answer_rate' => 0,
            'total_talk_time' => 0,
            'avg_talk_time' => 0
        ];

        $this->render('reports/agent/detail', [
            'title' => 'Agent Detail - ' . $agentData['display_name'],
            'currentPage' => 'reports.agent.performance',
            'agentData' => $agentData,
            'hourly' => $hourly,
            'trend' => $trend,
            'activity' => $activity,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo
        ]);
    }

    /**
     * Export agent report
     */
    public function export(): void
    {
        $this->requirePermission('reports.agent.export');

        $dateFrom = $this->get('date_from', date('Y-m-d', strtotime('-7 days')));
        $dateTo = $this->get('date_to', date('Y-m-d'));
        $agentFilter = $this->get('agent');

        $csv = $this->agentService->exportToCSV($dateFrom, $dateTo, $agentFilter);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="agent_report_' . $dateFrom . '_' . $dateTo . '.csv"');
        echo $csv;
        exit;
    }
}
