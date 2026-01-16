<?php
/**
 * Admin Evaluation Form Controller
 * Manages quality evaluation templates
 */

namespace aReports\Controllers\Admin;

use aReports\Core\Controller;

class EvaluationFormController extends Controller
{
    /**
     * List evaluation forms
     */
    public function index(): void
    {
        $this->requirePermission('admin.forms.view');

        $forms = $this->db->fetchAll(
            "SELECT ef.*, COUNT(ec.id) as criteria_count
             FROM evaluation_forms ef
             LEFT JOIN evaluation_criteria ec ON ef.id = ec.form_id
             GROUP BY ef.id
             ORDER BY ef.name"
        );

        $this->render('admin/evaluation-forms/index', [
            'title' => 'Evaluation Forms',
            'currentPage' => 'admin.forms',
            'forms' => $forms
        ]);
    }

    /**
     * Create form
     */
    public function create(): void
    {
        $this->requirePermission('admin.forms.manage');

        $this->render('admin/evaluation-forms/create', [
            'title' => 'Create Evaluation Form',
            'currentPage' => 'admin.forms'
        ]);
    }

    /**
     * Store evaluation form
     */
    public function store(): void
    {
        $this->requirePermission('admin.forms.manage');

        $data = $this->validate($_POST, [
            'name' => 'required|max:100'
        ]);

        $formId = $this->db->insert('evaluation_forms', [
            'name' => $data['name'],
            'description' => $this->post('description'),
            'is_active' => 1,
            'created_by' => $this->user['id']
        ]);

        // Add criteria
        $criteriaNames = $this->post('criteria_name', []);
        $criteriaWeights = $this->post('criteria_weight', []);
        $criteriaMaxScores = $this->post('criteria_max_score', []);

        foreach ($criteriaNames as $index => $name) {
            if (empty($name)) continue;

            $this->db->insert('evaluation_criteria', [
                'form_id' => $formId,
                'name' => $name,
                'description' => '',
                'weight' => (int) ($criteriaWeights[$index] ?? 100),
                'max_score' => (int) ($criteriaMaxScores[$index] ?? 5),
                'sort_order' => $index
            ]);
        }

        $this->audit('create', 'evaluation_form', $formId);
        $this->redirectWith('/areports/admin/evaluation-forms', 'success', 'Evaluation form created successfully.');
    }

    /**
     * Edit form
     */
    public function edit(int $id): void
    {
        $this->requirePermission('admin.forms.manage');

        $form = $this->db->fetch("SELECT * FROM evaluation_forms WHERE id = ?", [$id]);
        if (!$form) {
            $this->abort(404, 'Form not found');
        }

        $criteria = $this->db->fetchAll(
            "SELECT * FROM evaluation_criteria WHERE form_id = ? ORDER BY sort_order",
            [$id]
        );

        $this->render('admin/evaluation-forms/edit', [
            'title' => 'Edit Evaluation Form',
            'currentPage' => 'admin.forms',
            'form' => $form,
            'criteria' => $criteria
        ]);
    }

    /**
     * Update evaluation form
     */
    public function update(int $id): void
    {
        $this->requirePermission('admin.forms.manage');

        $data = $this->validate($_POST, [
            'name' => 'required|max:100'
        ]);

        $this->db->update('evaluation_forms', [
            'name' => $data['name'],
            'description' => $this->post('description'),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ], ['id' => $id]);

        // Update criteria
        $this->db->delete('evaluation_criteria', ['form_id' => $id]);

        $criteriaNames = $this->post('criteria_name', []);
        $criteriaWeights = $this->post('criteria_weight', []);
        $criteriaMaxScores = $this->post('criteria_max_score', []);

        foreach ($criteriaNames as $index => $name) {
            if (empty($name)) continue;

            $this->db->insert('evaluation_criteria', [
                'form_id' => $id,
                'name' => $name,
                'description' => '',
                'weight' => (int) ($criteriaWeights[$index] ?? 100),
                'max_score' => (int) ($criteriaMaxScores[$index] ?? 5),
                'sort_order' => $index
            ]);
        }

        $this->audit('update', 'evaluation_form', $id);
        $this->redirectWith('/areports/admin/evaluation-forms', 'success', 'Evaluation form updated successfully.');
    }

    /**
     * Delete evaluation form
     */
    public function delete(int $id): void
    {
        $this->requirePermission('admin.forms.manage');

        // Check if form has evaluations
        $evalCount = $this->db->count('call_evaluations', ['form_id' => $id]);
        if ($evalCount > 0) {
            $this->redirectWith('/areports/admin/evaluation-forms', 'error', 'Cannot delete form with existing evaluations.');
            return;
        }

        $this->db->delete('evaluation_criteria', ['form_id' => $id]);
        $this->db->delete('evaluation_forms', ['id' => $id]);

        $this->audit('delete', 'evaluation_form', $id);
        $this->redirectWith('/areports/admin/evaluation-forms', 'success', 'Evaluation form deleted successfully.');
    }
}
