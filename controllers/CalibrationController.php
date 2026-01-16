<?php
/**
 * Calibration Controller
 * QA calibration sessions for reviewer consistency
 */

namespace aReports\Controllers;

use aReports\Core\Controller;

class CalibrationController extends Controller
{
    /**
     * List calibration sessions
     */
    public function index(): void
    {
        $this->requirePermission('quality.calibration');

        $status = $this->get('status');

        $sql = "SELECT cs.*, ef.name as form_name, u.first_name, u.last_name,
                       (SELECT COUNT(*) FROM calibration_participants cp WHERE cp.session_id = cs.id) as participant_count,
                       (SELECT COUNT(*) FROM calibration_participants cp WHERE cp.session_id = cs.id AND cp.status = 'completed') as completed_count
                FROM calibration_sessions cs
                LEFT JOIN evaluation_forms ef ON cs.form_id = ef.id
                LEFT JOIN users u ON cs.created_by = u.id
                WHERE 1=1";

        $params = [];

        if ($status) {
            $sql .= " AND cs.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY cs.scheduled_at DESC";

        $sessions = $this->db->fetchAll($sql, $params);

        $this->render('quality/calibration/index', [
            'title' => 'Calibration Sessions',
            'currentPage' => 'quality.calibration',
            'sessions' => $sessions,
            'filters' => ['status' => $status]
        ]);
    }

    /**
     * Create session form
     */
    public function create(): void
    {
        $this->requirePermission('quality.calibration');

        // Get evaluation forms
        $forms = $this->db->fetchAll(
            "SELECT * FROM evaluation_forms WHERE is_active = 1 ORDER BY name"
        );

        // Get evaluators (supervisors and admins)
        $evaluators = $this->db->fetchAll(
            "SELECT u.* FROM users u
             JOIN roles r ON u.role_id = r.id
             WHERE u.is_active = 1 AND r.name IN ('admin', 'supervisor')
             ORDER BY u.first_name"
        );

        // Get recent calls with recordings for selection
        $recentCalls = $this->cdrDb->fetchAll(
            "SELECT uniqueid, calldate, src, dst, duration, disposition
             FROM cdr
             WHERE disposition = 'ANSWERED' AND duration > 60
             ORDER BY calldate DESC
             LIMIT 50"
        );

        $this->render('quality/calibration/create', [
            'title' => 'Create Calibration Session',
            'currentPage' => 'quality.calibration',
            'forms' => $forms,
            'evaluators' => $evaluators,
            'recentCalls' => $recentCalls
        ]);
    }

    /**
     * Store session
     */
    public function store(): void
    {
        $this->requirePermission('quality.calibration');

        $data = $this->validate($_POST, [
            'name' => 'required|max:100',
            'uniqueid' => 'required',
            'form_id' => 'required|numeric',
            'scheduled_at' => 'required|date',
        ]);

        $sessionId = $this->db->insert('calibration_sessions', [
            'name' => $data['name'],
            'description' => $this->post('description'),
            'uniqueid' => $data['uniqueid'],
            'form_id' => $data['form_id'],
            'status' => 'scheduled',
            'scheduled_at' => $data['scheduled_at'],
            'created_by' => $this->user['id'],
        ]);

        // Add participants
        $participants = $this->post('participants', []);
        foreach ($participants as $userId) {
            $this->db->insert('calibration_participants', [
                'session_id' => $sessionId,
                'user_id' => $userId,
                'status' => 'invited',
            ]);
        }

        $this->audit('create', 'calibration_session', $sessionId);
        $this->redirectWith('/areports/quality/calibration/' . $sessionId, 'success', 'Calibration session created successfully.');
    }

    /**
     * Show session details
     */
    public function show(int $id): void
    {
        $this->requirePermission('quality.calibration');

        $session = $this->getSession($id);

        // Get participants with their evaluations
        $participants = $this->db->fetchAll(
            "SELECT cp.*, u.first_name, u.last_name, u.email,
                    ce.total_score, ce.percentage
             FROM calibration_participants cp
             JOIN users u ON cp.user_id = u.id
             LEFT JOIN call_evaluations ce ON cp.evaluation_id = ce.id
             WHERE cp.session_id = ?
             ORDER BY u.first_name",
            [$id]
        );

        // Get call details
        $call = $this->cdrDb->fetch(
            "SELECT * FROM cdr WHERE uniqueid = ?",
            [$session['uniqueid']]
        );

        // Check if current user is a participant
        $isParticipant = false;
        $userParticipant = null;
        foreach ($participants as $p) {
            if ($p['user_id'] == $this->user['id']) {
                $isParticipant = true;
                $userParticipant = $p;
                break;
            }
        }

        $this->render('quality/calibration/show', [
            'title' => $session['name'],
            'currentPage' => 'quality.calibration',
            'session' => $session,
            'participants' => $participants,
            'call' => $call,
            'isParticipant' => $isParticipant,
            'userParticipant' => $userParticipant,
        ]);
    }

    /**
     * Start calibration session
     */
    public function start(int $id): void
    {
        $this->requirePermission('quality.calibration');

        $session = $this->getSession($id);

        if ($session['status'] !== 'scheduled') {
            $this->redirectWith('/areports/quality/calibration/' . $id, 'error', 'Session cannot be started.');
            return;
        }

        $this->db->update('calibration_sessions', [
            'status' => 'in_progress'
        ], ['id' => $id]);

        // Update participants to joined
        $this->db->query(
            "UPDATE calibration_participants SET status = 'joined' WHERE session_id = ? AND status = 'invited'",
            [$id]
        );

        $this->audit('start', 'calibration_session', $id);
        $this->redirectWith('/areports/quality/calibration/' . $id, 'success', 'Calibration session started.');
    }

    /**
     * Complete calibration session
     */
    public function complete(int $id): void
    {
        $this->requirePermission('quality.calibration');

        $session = $this->getSession($id);

        if ($session['status'] !== 'in_progress') {
            $this->redirectWith('/areports/quality/calibration/' . $id, 'error', 'Session is not in progress.');
            return;
        }

        $this->db->update('calibration_sessions', [
            'status' => 'completed',
            'completed_at' => date('Y-m-d H:i:s'),
        ], ['id' => $id]);

        $this->audit('complete', 'calibration_session', $id);
        $this->redirectWith('/areports/quality/calibration/' . $id . '/results', 'success', 'Calibration session completed.');
    }

    /**
     * Show calibration results
     */
    public function results(int $id): void
    {
        $this->requirePermission('quality.calibration');

        $session = $this->getSession($id);

        // Get all evaluations for this session
        $evaluations = $this->db->fetchAll(
            "SELECT cp.user_id, u.first_name, u.last_name,
                    ce.id as evaluation_id, ce.total_score, ce.max_possible_score, ce.percentage, ce.notes
             FROM calibration_participants cp
             JOIN users u ON cp.user_id = u.id
             LEFT JOIN call_evaluations ce ON cp.evaluation_id = ce.id
             WHERE cp.session_id = ?",
            [$id]
        );

        // Get detailed scores by criteria
        $criteriaScores = [];
        foreach ($evaluations as $eval) {
            if ($eval['evaluation_id']) {
                $scores = $this->db->fetchAll(
                    "SELECT es.*, ec.name as criteria_name, ec.category, ec.max_score
                     FROM evaluation_scores es
                     JOIN evaluation_criteria ec ON es.criteria_id = ec.id
                     WHERE es.evaluation_id = ?",
                    [$eval['evaluation_id']]
                );
                $criteriaScores[$eval['user_id']] = $scores;
            }
        }

        // Calculate statistics
        $scores = array_filter(array_column($evaluations, 'percentage'));
        $stats = [
            'average' => !empty($scores) ? round(array_sum($scores) / count($scores), 2) : 0,
            'min' => !empty($scores) ? min($scores) : 0,
            'max' => !empty($scores) ? max($scores) : 0,
            'range' => !empty($scores) ? max($scores) - min($scores) : 0,
            'std_dev' => $this->calculateStdDev($scores),
        ];

        // Get form criteria for comparison
        $criteria = $this->db->fetchAll(
            "SELECT * FROM evaluation_criteria WHERE form_id = ? ORDER BY category, sort_order",
            [$session['form_id']]
        );

        $this->render('quality/calibration/results', [
            'title' => $session['name'] . ' - Results',
            'currentPage' => 'quality.calibration',
            'session' => $session,
            'evaluations' => $evaluations,
            'criteriaScores' => $criteriaScores,
            'criteria' => $criteria,
            'stats' => $stats,
        ]);
    }

    /**
     * Get session or 404
     */
    private function getSession(int $id): array
    {
        $session = $this->db->fetch(
            "SELECT cs.*, ef.name as form_name
             FROM calibration_sessions cs
             LEFT JOIN evaluation_forms ef ON cs.form_id = ef.id
             WHERE cs.id = ?",
            [$id]
        );

        if (!$session) {
            $this->abort(404, 'Calibration session not found');
        }

        return $session;
    }

    /**
     * Calculate standard deviation
     */
    private function calculateStdDev(array $values): float
    {
        if (count($values) < 2) {
            return 0;
        }

        $mean = array_sum($values) / count($values);
        $squaredDiffs = array_map(fn($v) => pow($v - $mean, 2), $values);

        return round(sqrt(array_sum($squaredDiffs) / count($values)), 2);
    }
}
