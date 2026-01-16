<?php $this->section('content'); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">Daily Trends</h1>
        <p class="text-muted mb-0">Call volume and metrics over time</p>
    </div>
    <a href="/areports/reports/trends" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back to Trends
    </a>
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
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-1"></i> Apply Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Charts -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Daily Call Volume</h5>
            </div>
            <div class="card-body">
                <canvas id="dailyChart" height="300"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">CDR Trends</h5>
            </div>
            <div class="card-body">
                <canvas id="cdrChart" height="250"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">Queue Trends</h5>
            </div>
            <div class="card-body">
                <canvas id="queueChart" height="250"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Data Table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Daily Data</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th class="text-center">Total Calls</th>
                        <th class="text-center">Answered</th>
                        <th class="text-center">Missed</th>
                        <th class="text-center">Answer Rate</th>
                        <th class="text-center">Avg Duration</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dailyData as $day): ?>
                    <tr>
                        <td><?= $this->formatDate($day['date']) ?></td>
                        <td class="text-center"><?= number_format($day['total'] ?? 0) ?></td>
                        <td class="text-center text-success"><?= number_format($day['answered'] ?? 0) ?></td>
                        <td class="text-center text-danger"><?= number_format(($day['total'] ?? 0) - ($day['answered'] ?? 0)) ?></td>
                        <td class="text-center">
                            <?php
                            $rate = ($day['total'] ?? 0) > 0 ? round(($day['answered'] / $day['total']) * 100, 1) : 0;
                            ?>
                            <span class="badge bg-<?= $rate >= 80 ? 'success' : ($rate >= 60 ? 'warning' : 'danger') ?>">
                                <?= $rate ?>%
                            </span>
                        </td>
                        <td class="text-center"><?= $this->formatDuration((int)($day['avg_duration'] ?? 0)) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($dailyData)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No data for selected period</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php $this->section('scripts'); ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const dailyData = <?= json_encode($dailyData) ?>;
const queueDaily = <?= json_encode($queueDaily) ?>;

// Labels (dates)
const labels = dailyData.map(d => d.date);

// Daily Call Volume Chart
new Chart(document.getElementById('dailyChart'), {
    type: 'line',
    data: {
        labels: labels,
        datasets: [{
            label: 'Total Calls',
            data: dailyData.map(d => parseInt(d.total || 0)),
            borderColor: '#0d6efd',
            backgroundColor: 'rgba(13, 110, 253, 0.1)',
            fill: true,
            tension: 0.3
        }, {
            label: 'Answered',
            data: dailyData.map(d => parseInt(d.answered || 0)),
            borderColor: '#198754',
            backgroundColor: 'rgba(25, 135, 84, 0.1)',
            fill: true,
            tension: 0.3
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top' }
        },
        scales: {
            y: { beginAtZero: true }
        }
    }
});

// CDR Chart
new Chart(document.getElementById('cdrChart'), {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [{
            label: 'Answered',
            data: dailyData.map(d => parseInt(d.answered || 0)),
            backgroundColor: '#198754'
        }, {
            label: 'Not Answered',
            data: dailyData.map(d => parseInt(d.no_answer || 0)),
            backgroundColor: '#dc3545'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top' }
        },
        scales: {
            x: { stacked: true },
            y: { stacked: true, beginAtZero: true }
        }
    }
});

// Queue Chart
const queueLabels = queueDaily.map(d => d.date);
new Chart(document.getElementById('queueChart'), {
    type: 'bar',
    data: {
        labels: queueLabels,
        datasets: [{
            label: 'Answered',
            data: queueDaily.map(d => parseInt(d.answered || 0)),
            backgroundColor: '#198754'
        }, {
            label: 'Abandoned',
            data: queueDaily.map(d => parseInt(d.abandoned || 0)),
            backgroundColor: '#ffc107'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top' }
        },
        scales: {
            x: { stacked: true },
            y: { stacked: true, beginAtZero: true }
        }
    }
});
</script>
<?php $this->endSection(); ?>

<?php $this->endSection(); ?>
