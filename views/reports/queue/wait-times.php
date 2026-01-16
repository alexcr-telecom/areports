<?php $this->section('content'); ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">Wait Times Analysis</h1>
        <p class="text-muted mb-0">Queue wait time distribution</p>
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

<div class="row">
    <!-- Wait Time Distribution Chart -->
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">Wait Time Distribution</div>
            <div class="card-body">
                <canvas id="distributionChart" height="200"></canvas>
            </div>
        </div>
    </div>

    <!-- Distribution Table -->
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">Distribution Breakdown</div>
            <div class="card-body">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Wait Range</th>
                            <th class="text-center">Calls</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $totalCalls = array_sum(array_column($distribution, 'count'));
                        foreach ($distribution as $range):
                            $percent = $totalCalls > 0 ? round(($range['count'] / $totalCalls) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td><?= $this->e($range['wait_range']) ?></td>
                            <td class="text-center"><?= number_format($range['count']) ?></td>
                            <td>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar" role="progressbar" style="width: <?= $percent ?>%">
                                        <?= $percent ?>%
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($distribution)): ?>
                        <tr>
                            <td colspan="3" class="text-center text-muted">No data available</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Queue Wait Times Summary -->
<div class="card">
    <div class="card-header">Queue Wait Time Summary</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Queue</th>
                        <th class="text-center">Avg Wait</th>
                        <th class="text-center">Min Wait</th>
                        <th class="text-center">Max Wait</th>
                        <th class="text-center">Answered</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($queues as $queue): ?>
                    <tr>
                        <td><strong><?= $this->e($queue['display_name']) ?></strong></td>
                        <td class="text-center">
                            <span class="badge bg-<?= $queue['avg_wait_time'] <= 20 ? 'success' : ($queue['avg_wait_time'] <= 60 ? 'warning' : 'danger') ?>">
                                <?= $this->formatDuration($queue['avg_wait_time']) ?>
                            </span>
                        </td>
                        <td class="text-center"><?= $this->formatDuration($queue['min_wait_time']) ?></td>
                        <td class="text-center"><?= $this->formatDuration($queue['max_wait_time']) ?></td>
                        <td class="text-center"><?= number_format($queue['answered']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($queues)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">No data found</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php $this->endSection(); ?>

<?php $this->section('scripts'); ?>
<script src="/areports/public/vendor/chartjs/chart.min.js"></script>
<script>
const distribution = <?= json_encode($distribution) ?>;
const ctx = document.getElementById('distributionChart').getContext('2d');

new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: distribution.map(d => d.wait_range),
        datasets: [{
            data: distribution.map(d => d.count),
            backgroundColor: [
                'rgba(40, 167, 69, 0.8)',
                'rgba(23, 162, 184, 0.8)',
                'rgba(255, 193, 7, 0.8)',
                'rgba(253, 126, 20, 0.8)',
                'rgba(220, 53, 69, 0.8)',
                'rgba(108, 117, 125, 0.8)'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
</script>
<?php $this->endSection(); ?>
