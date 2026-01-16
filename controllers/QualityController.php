<?php
/**
 * Quality Controller
 * Handles call recordings and quality evaluations
 */

namespace aReports\Controllers;

use aReports\Core\App;
use aReports\Core\Controller;
use aReports\Services\CDRService;

class QualityController extends Controller
{
    private CDRService $cdrService;
    private string $recordingsPath;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->cdrService = new CDRService();
        // Default FreePBX recording path
        $this->recordingsPath = '/var/spool/asterisk/monitor';
    }

    /**
     * List recordings
     */
    public function recordings(): void
    {
        $this->requirePermission('quality.recordings.view');

        $dateFrom = $this->get('date_from', date('Y-m-d'));
        $dateTo = $this->get('date_to', date('Y-m-d'));
        $search = $this->get('search');
        $page = (int) $this->get('page', 1);
        $perPage = 50;

        // Get CDR records with recordings
        $filters = [
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ];

        if ($search) {
            $filters['search'] = $search;
        }

        $result = $this->cdrService->getCDRList($filters, $perPage, ($page - 1) * $perPage);

        // Filter to only those with recording files
        $recordings = [];
        foreach ($result['records'] as $record) {
            $recordingFile = $this->findRecordingFile($record);
            if ($recordingFile) {
                $record['recording_file'] = $recordingFile;
                $record['has_evaluation'] = $this->hasEvaluation($record['uniqueid']);
                $recordings[] = $record;
            }
        }

        $totalPages = ceil($result['total'] / $perPage);

        $this->render('quality/recordings', [
            'title' => 'Call Recordings',
            'currentPage' => 'quality.recordings',
            'recordings' => $recordings,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'search' => $search,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $result['total']
        ]);
    }

    /**
     * Play/stream recording
     */
    public function playRecording(string $uniqueid): void
    {
        $this->requirePermission('quality.recordings.listen');

        $cdr = $this->cdrService->getCDRByUniqueId($uniqueid);
        if (!$cdr) {
            $this->abort(404, 'Recording not found');
        }

        $recordingFile = $this->findRecordingFile($cdr);
        if (!$recordingFile || !file_exists($recordingFile)) {
            $this->abort(404, 'Recording file not found');
        }

        // Determine content type
        $extension = pathinfo($recordingFile, PATHINFO_EXTENSION);
        $contentType = match ($extension) {
            'wav' => 'audio/wav',
            'mp3' => 'audio/mpeg',
            'gsm' => 'audio/gsm',
            'ogg' => 'audio/ogg',
            default => 'application/octet-stream'
        };

        // Stream the file
        header('Content-Type: ' . $contentType);
        header('Content-Length: ' . filesize($recordingFile));
        header('Content-Disposition: inline; filename="' . basename($recordingFile) . '"');
        header('Accept-Ranges: bytes');

        readfile($recordingFile);
        exit;
    }

    /**
     * Download recording
     */
    public function downloadRecording(string $uniqueid): void
    {
        $this->requirePermission('quality.recordings.download');

        $cdr = $this->cdrService->getCDRByUniqueId($uniqueid);
        if (!$cdr) {
            $this->abort(404, 'Recording not found');
        }

        $recordingFile = $this->findRecordingFile($cdr);
        if (!$recordingFile || !file_exists($recordingFile)) {
            $this->abort(404, 'Recording file not found');
        }

        header('Content-Type: application/octet-stream');
        header('Content-Length: ' . filesize($recordingFile));
        header('Content-Disposition: attachment; filename="' . basename($recordingFile) . '"');

        readfile($recordingFile);
        exit;
    }

    /**
     * List evaluations
     */
    public function evaluations(): void
    {
        $this->requirePermission('quality.evaluations.view');

        $dateFrom = $this->get('date_from', date('Y-m-d', strtotime('-30 days')));
        $dateTo = $this->get('date_to', date('Y-m-d'));
        $page = (int) $this->get('page', 1);
        $perPage = 50;

        $evaluations = $this->db->fetchAll(
            "SELECT ce.*, u.first_name, u.last_name, ef.name as form_name
             FROM call_evaluations ce
             JOIN users u ON ce.evaluator_id = u.id
             JOIN evaluation_forms ef ON ce.form_id = ef.id
             WHERE DATE(ce.created_at) BETWEEN ? AND ?
             ORDER BY ce.created_at DESC
             LIMIT ? OFFSET ?",
            [$dateFrom, $dateTo, $perPage, ($page - 1) * $perPage]
        );

        $totalCount = $this->db->count('call_evaluations');
        $totalPages = ceil($totalCount / $perPage);

        // Get average scores
        $avgScore = $this->db->fetch(
            "SELECT AVG(total_score) as avg_score FROM call_evaluations WHERE DATE(created_at) BETWEEN ? AND ?",
            [$dateFrom, $dateTo]
        );

        $this->render('quality/evaluations', [
            'title' => 'Call Evaluations',
            'currentPage' => 'quality.evaluations',
            'evaluations' => $evaluations,
            'avgScore' => round($avgScore['avg_score'] ?? 0, 1),
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $totalCount
        ]);
    }

    /**
     * Show evaluation form
     */
    public function showEvaluate(string $uniqueid): void
    {
        $this->requirePermission('quality.evaluations.create');

        $cdr = $this->cdrService->getCDRByUniqueId($uniqueid);
        if (!$cdr) {
            $this->abort(404, 'Call not found');
        }

        $recordingFile = $this->findRecordingFile($cdr);

        // Get available evaluation forms
        $forms = $this->db->fetchAll(
            "SELECT * FROM evaluation_forms WHERE is_active = 1 ORDER BY name"
        );

        // Get criteria for each form
        foreach ($forms as &$form) {
            $form['criteria'] = $this->db->fetchAll(
                "SELECT * FROM evaluation_criteria WHERE form_id = ? ORDER BY sort_order",
                [$form['id']]
            );
        }

        $this->render('quality/evaluate', [
            'title' => 'Evaluate Call',
            'currentPage' => 'quality.evaluations',
            'cdr' => $cdr,
            'recordingFile' => $recordingFile ? true : false,
            'forms' => $forms
        ]);
    }

    /**
     * Store evaluation
     */
    public function storeEvaluation(): void
    {
        $this->requirePermission('quality.evaluations.create');

        $data = $this->validate($_POST, [
            'uniqueid' => 'required',
            'form_id' => 'required|exists:evaluation_forms,id',
            'scores' => 'required|array',
            'comments' => 'max:5000'
        ]);

        // Get form and criteria
        $form = $this->db->fetch("SELECT * FROM evaluation_forms WHERE id = ?", [$data['form_id']]);
        $criteria = $this->db->fetchAll(
            "SELECT * FROM evaluation_criteria WHERE form_id = ?",
            [$data['form_id']]
        );

        // Calculate total score
        $totalScore = 0;
        $maxScore = 0;
        $scores = $data['scores'];

        foreach ($criteria as $criterion) {
            $score = (int) ($scores[$criterion['id']] ?? 0);
            $weightedScore = $score * ($criterion['weight'] / 100);
            $totalScore += $weightedScore;
            $maxScore += $criterion['max_score'] * ($criterion['weight'] / 100);
        }

        $percentScore = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 1) : 0;

        // Insert evaluation
        $evaluationId = $this->db->insert('call_evaluations', [
            'uniqueid' => $data['uniqueid'],
            'form_id' => $data['form_id'],
            'evaluator_id' => $this->user['id'],
            'total_score' => $percentScore,
            'scores_json' => json_encode($scores),
            'comments' => $data['comments'] ?? null
        ]);

        $this->audit('create', 'call_evaluation', $evaluationId, null, [
            'uniqueid' => $data['uniqueid'],
            'score' => $percentScore
        ]);

        $this->redirectWith('/areports/quality/evaluations', 'success', 'Evaluation saved successfully. Score: ' . $percentScore . '%');
    }

    /**
     * Find recording file for a CDR record
     */
    private function findRecordingFile(array $cdr): ?string
    {
        // Check if recordingfile field exists and has value
        if (!empty($cdr['recordingfile'])) {
            // Try direct path
            if (file_exists($cdr['recordingfile'])) {
                return $cdr['recordingfile'];
            }

            // Try in recordings path
            $fullPath = $this->recordingsPath . '/' . $cdr['recordingfile'];
            if (file_exists($fullPath)) {
                return $fullPath;
            }
        }

        // Try to construct filename from CDR data
        $calldate = $cdr['calldate'] ?? '';
        $uniqueid = $cdr['uniqueid'] ?? '';

        if (!$calldate || !$uniqueid) {
            return null;
        }

        $date = date('Y/m/d', strtotime($calldate));
        $basePath = $this->recordingsPath . '/' . $date;

        // Common recording filename patterns
        $patterns = [
            $basePath . '/' . $uniqueid . '.*',
            $basePath . '/*' . $uniqueid . '*.*',
            $this->recordingsPath . '/' . date('Ymd', strtotime($calldate)) . '/*' . $uniqueid . '*.*'
        ];

        foreach ($patterns as $pattern) {
            $files = glob($pattern);
            if (!empty($files)) {
                return $files[0];
            }
        }

        return null;
    }

    /**
     * Check if call has been evaluated
     */
    private function hasEvaluation(string $uniqueid): bool
    {
        return $this->db->count('call_evaluations', ['uniqueid' => $uniqueid]) > 0;
    }
}
