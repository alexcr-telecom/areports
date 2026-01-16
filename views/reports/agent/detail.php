<?php $this->section('content'); ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0"><?= $this->e($agentData['display_name']) ?></h1>
        <p class="text-muted mb-0"><?= $this->e($agentData['agent']) ?></p>
    </div>
    <a href="/areports/reports/agent/performance" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back
    </a>
</div>

<!-- Date Filter -->
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
                    <i class="fas fa-search me-1"></i> Update
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h6 class="card-title">Calls Handled</h6>
                <h2 class="mb-0"><?= number_format($agentData['calls_handled']) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark">
            <div class="card-body">
                <h6 class="card-title">Calls Missed</h6>
                <h2 class="mb-0"><?= number_format($agentData['calls_missed']) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h6 class="card-title">Answer Rate</h6>
                <h2 class="mb-0"><?= $agentData['answer_rate'] ?>%</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h6 class="card-title">Total Talk Time</h6>
                <h2 class="mb-0"><?= $this->formatDuration($agentData['total_talk_time']) ?></h2>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Hourly Activity Chart -->
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">Today's Hourly Activity</div>
            <div class="card-body">
                <canvas id="hourlyChart" height="200"></canvas>
            </div>
        </div>
    </div>

    <!-- Additional Stats -->
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">Performance Metrics</div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <td>Average Talk Time</td>
                        <td class="text-end"><strong><?= $this->formatDuration($agentData['avg_talk_time']) ?></strong></td>
                    </tr>
                    <tr>
                        <td>Total Hold Time</td>
                        <td class="text-end"><strong><?= $this->formatDuration($agentData['total_hold_time'] ?? 0) ?></strong></td>
                    </tr>
                    <tr>
                        <td>Completed by Caller</td>
                        <td class="text-end"><strong><?= number_format($agentData['completed_caller'] ?? 0) ?></strong></td>
                    </tr>
                    <tr>
                        <td>Completed by Agent</td>
                        <td class="text-end"><strong><?= number_format($agentData['completed_agent'] ?? 0) ?></strong></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Daily Trend Chart -->
<div class="card mb-4">
    <div class="card-header">Daily Trend</div>
    <div class="card-body">
        <canvas id="trendChart" height="100"></canvas>
    </div>
</div>

<!-- Recent Activity -->
<div class="card">
    <div class="card-header">Recent Activity</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Queue</th>
                        <th>Event</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($activity, 0, 20) as $event): ?>
                    <tr>
                        <td><?= $this->formatDateTime($event['time']) ?></td>
                        <td><?= $this->e($event['queuename']) ?></td>
                        <td>
                            <?php
                            $eventClass = match($event['event']) {
                                'ADDMEMBER' => 'success',
                                'REMOVEMEMBER' => 'danger',
                                'PAUSE' => 'warning',
                                'UNPAUSE' => 'info',
                                default => 'secondary'
                            };
                            ?>
                            <span class="badge bg-<?= $eventClass ?>"><?= $event['event'] ?></span>
                        </td>
                        <td><?= $this->e($event['data1'] ?: '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($activity)): ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted">No recent activity</td>
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
// Hourly Chart
const hourlyData = <?= json_encode($hourly) ?>;
const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
new Chart(hourlyCtx, {
    type: 'bar',
    data: {
        labels: hourlyData.map(h => h.hour + ':00'),
        datasets: [{
            label: 'Calls Handled',
            data: hourlyData.map(h => h.calls_handled),
            backgroundColor: 'rgba(54, 162, 235, 0.5)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }, {
            label: 'Calls Missed',
            data: hourlyData.map(h => h.calls_missed),
            backgroundColor: 'rgba(255, 193, 7, 0.5)',
            borderColor: 'rgba(255, 193, 7, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: { beginAtZero: true }
        }
    }
});

// Trend Chart
const trendData = <?= json_encode($trend) ?>;
const trendCtx = document.getElementById('trendChart').getContext('2d');
new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: trendData.map(d => d.date),
        datasets: [{
            label: 'Calls Handled',
            data: trendData.map(d => d.calls_handled),
            borderColor: 'rgba(54, 162, 235, 1)',
            tension: 0.1,
            fill: false
        }, {
            label: 'Calls Missed',
            data: trendData.map(d => d.calls_missed),
            borderColor: 'rgba(255, 193, 7, 1)',
            tension: 0.1,
            fill: false
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: { beginAtZero: true }
        }
    }
});
</script>
<?php $this->endSection(); ?>
