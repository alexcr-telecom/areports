<?php $this->section('content'); ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">Abandonment Report</h1>
        <p class="text-muted mb-0">Abandoned queue calls</p>
    </div>
    <span class="badge bg-danger fs-6"><?= number_format($total) ?> abandoned calls</span>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Date From</label>
                <input type="date" class="form-control" name="date_from" value="<?= $this->e($dateFrom) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Date To</label>
                <input type="date" class="form-control" name="date_to" value="<?= $this->e($dateTo) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Queue</label>
                <select class="form-select" name="queue">
                    <option value="">All Queues</option>
                    <?php foreach ($queueList as $queue): ?>
                    <option value="<?= $this->e($queue['name']) ?>" <?= $queueFilter === $queue['name'] ? 'selected' : '' ?>>
                        <?= $this->e($queue['display_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-1"></i> Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Abandoned Calls Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Queue</th>
                        <th>Call ID</th>
                        <th class="text-center">Wait Time</th>
                        <th class="text-center">Position</th>
                        <th class="text-center">Original Position</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $record): ?>
                    <tr>
                        <td><?= $this->formatDateTime($record['time']) ?></td>
                        <td><?= $this->e($record['queuename']) ?></td>
                        <td><small class="text-muted"><?= $this->e($record['callid']) ?></small></td>
                        <td class="text-center">
                            <span class="badge bg-<?= ($record['wait_time'] ?? 0) > 60 ? 'danger' : 'warning' ?>">
                                <?= $this->formatDuration($record['wait_time'] ?? 0) ?>
                            </span>
                        </td>
                        <td class="text-center"><?= $record['wait_position'] ?? '-' ?></td>
                        <td class="text-center"><?= $record['original_position'] ?? '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($records)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No abandoned calls found</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <?= $this->pagination($page, $totalPages, '/areports/reports/queue/abandonment', [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'queue' => $queueFilter
        ]) ?>
        <?php endif; ?>
    </div>
</div>

<?php $this->endSection(); ?>
