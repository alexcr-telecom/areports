<?php $this->section('content'); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">Hourly Trends</h1>
        <p class="text-muted mb-0">Call volume patterns throughout the day</p>
    </div>
    <a href="/areports/reports/trends" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back to Trends
    </a>
</div>

<!-- Date Selector -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Select Date</label>
                <input type="date" class="form-control" name="date" value="<?= $this->e($date) ?>">
            </div>
            <div class="col-md-6 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-1"></i> View Data
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Chart -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Call Volume by Hour - <?= $this->formatDate($date) ?></h5>
    </div>
    <div class="card-body">
        <canvas id="hourlyChart" height="300"></canvas>
    </div>
</div>

<!-- Queue Hourly Chart -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Queue Activity by Hour</h5>
    </div>
    <div class="card-body">
        <canvas id="queueHourlyChart" height="300"></canvas>
    </div>
</div>

<!-- Hourly Data Table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Hourly Breakdown</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead>
                    <tr>
                        <th>Hour</th>
                        <th class="text-center">Total Calls</th>
                        <th class="text-center">Answered</th>
                        <th class="text-center">Answer Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($h = 0; $h < 24; $h++): ?>
                    <?php
                    $hourData = $hourlyData[$h] ?? ['total' => 0, 'answered' => 0];
                    $rate = $hourData['total'] > 0 ? round(($hourData['answered'] / $hourData['total']) * 100, 1) : 0;
                    ?>
                    <tr>
                        <td><?= sprintf('%02d:00 - %02d:59', $h, $h) ?></td>
                        <td class="text-center"><?= number_format($hourData['total']) ?></td>
                        <td class="text-center text-success"><?= number_format($hourData['answered']) ?></td>
                        <td class="text-center">
                            <?php if ($hourData['total'] > 0): ?>
                            <span class="badge bg-<?= $rate >= 80 ? 'success' : ($rate >= 60 ? 'warning' : 'danger') ?>">
                                <?= $rate ?>%
                            </span>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php $this->section('scripts'); ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const hourlyData = <?= json_encode($hourlyData) ?>;
const queueHourly = <?= json_encode($queueHourly) ?>;

const hourLabels = Array.from({length: 24}, (_, i) => i.toString().padStart(2, '0') + ':00');

// Main hourly chart
new Chart(document.getElementById('hourlyChart'), {
    type: 'bar',
    data: {
        labels: hourLabels,
        datasets: [{
            label: 'Total Calls',
            data: hourlyData.map(h => h.total || 0),
            backgroundColor: 'rgba(13, 110, 253, 0.7)',
            borderColor: '#0d6efd',
            borderWidth: 1
        }, {
            label: 'Answered',
            data: hourlyData.map(h => h.answered || 0),
            backgroundColor: 'rgba(25, 135, 84, 0.7)',
            borderColor: '#198754',
            borderWidth: 1
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

// Queue hourly chart
new Chart(document.getElementById('queueHourlyChart'), {
    type: 'line',
    data: {
        labels: hourLabels,
        datasets: [{
            label: 'Queue Calls',
            data: queueHourly.map(h => h.total_calls || 0),
            borderColor: '#0d6efd',
            backgroundColor: 'rgba(13, 110, 253, 0.1)',
            fill: true,
            tension: 0.3
        }, {
            label: 'Answered',
            data: queueHourly.map(h => h.answered || 0),
            borderColor: '#198754',
            fill: false,
            tension: 0.3
        }, {
            label: 'Abandoned',
            data: queueHourly.map(h => h.abandoned || 0),
            borderColor: '#dc3545',
            fill: false,
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
</script>
<?php $this->endSection(); ?>

<?php $this->endSection(); ?>
