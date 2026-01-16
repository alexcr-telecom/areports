<?php $this->section('content'); ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">Call Evaluations</h1>
        <p class="text-muted mb-0">Quality assurance scores</p>
    </div>
    <div class="text-end">
        <span class="h4 mb-0">Avg Score: <span class="text-<?= $avgScore >= 80 ? 'success' : ($avgScore >= 60 ? 'warning' : 'danger') ?>"><?= $avgScore ?>%</span></span>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Date From</label>
                <input type="date" class="form-control" name="date_from" value="<?= $this->e($dateFrom) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Date To</label>
                <input type="date" class="form-control" name="date_to" value="<?= $this->e($dateTo) ?>">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search me-1"></i> Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Evaluations Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Call ID</th>
                        <th>Evaluator</th>
                        <th>Form</th>
                        <th class="text-center">Score</th>
                        <th>Comments</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($evaluations as $eval): ?>
                    <tr>
                        <td><?= $this->formatDateTime($eval['created_at']) ?></td>
                        <td><small><?= $this->e($eval['uniqueid']) ?></small></td>
                        <td><?= $this->e($eval['first_name'] . ' ' . $eval['last_name']) ?></td>
                        <td><?= $this->e($eval['form_name']) ?></td>
                        <td class="text-center">
                            <?php
                            $scoreClass = $eval['total_score'] >= 80 ? 'success' : ($eval['total_score'] >= 60 ? 'warning' : 'danger');
                            ?>
                            <span class="badge bg-<?= $scoreClass ?> fs-6"><?= $eval['total_score'] ?>%</span>
                        </td>
                        <td>
                            <?php if (!empty($eval['comments'])): ?>
                            <span class="text-truncate d-inline-block" style="max-width: 200px;" title="<?= $this->e($eval['comments']) ?>">
                                <?= $this->e(substr($eval['comments'], 0, 50)) ?>...
                            </span>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($evaluations)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No evaluations found</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <?= $this->pagination($page, $totalPages, '/areports/quality/evaluations', [
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ]) ?>
        <?php endif; ?>
    </div>
</div>

<?php $this->endSection(); ?>
