<?php $this->section('content'); ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">Agent Efficiency</h1>
        <p class="text-muted mb-0">Performance breakdown by queue</p>
    </div>
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
                <label class="form-label">Agent</label>
                <select class="form-select" name="agent">
                    <option value="">All Agents</option>
                    <?php foreach ($agentList as $agent): ?>
                    <option value="<?= $this->e($agent['agent']) ?>" <?= $agentFilter === $agent['agent'] ? 'selected' : '' ?>>
                        <?= $this->e($agent['display_name']) ?>
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

<!-- Agent Cards -->
<?php foreach ($groupedData as $agentKey => $data): ?>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <strong><?= $this->e($data['agent']) ?></strong>
        </div>
        <div>
            <span class="badge bg-primary me-2"><?= number_format($data['total_calls']) ?> calls</span>
            <span class="badge bg-info"><?= $this->formatDuration($data['total_talk_time']) ?> talk time</span>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th>Queue</th>
                        <th class="text-center">Calls Handled</th>
                        <th class="text-center">Talk Time</th>
                        <th class="text-center">Avg Ring Time</th>
                        <th>% of Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['queues'] as $queue): ?>
                    <tr>
                        <td><?= $this->e($queue['queuename']) ?></td>
                        <td class="text-center"><?= number_format($queue['calls_handled']) ?></td>
                        <td class="text-center"><?= $this->formatDuration($queue['talk_time']) ?></td>
                        <td class="text-center"><?= round($queue['avg_ring_time'] ?? 0) ?>s</td>
                        <td>
                            <?php $percent = $data['total_calls'] > 0 ? round(($queue['calls_handled'] / $data['total_calls']) * 100, 1) : 0; ?>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar" role="progressbar" style="width: <?= $percent ?>%">
                                    <?= $percent ?>%
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php if (empty($groupedData)): ?>
<div class="card">
    <div class="card-body text-center text-muted py-4">
        No data found for the selected period
    </div>
</div>
<?php endif; ?>

<?php $this->endSection(); ?>
