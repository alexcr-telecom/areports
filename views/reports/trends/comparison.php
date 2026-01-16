<?php $this->section('content'); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">Period Comparison</h1>
        <p class="text-muted mb-0">Compare metrics between two time periods</p>
    </div>
    <a href="/areports/reports/trends" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back to Trends
    </a>
</div>

<!-- Period Selector -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET">
            <div class="row g-3">
                <div class="col-md-6">
                    <h6 class="text-primary mb-2">Period 1</h6>
                    <div class="row g-2">
                        <div class="col-6">
                            <input type="date" class="form-control" name="period1_from" value="<?= $this->e($period1From) ?>">
                        </div>
                        <div class="col-6">
                            <input type="date" class="form-control" name="period1_to" value="<?= $this->e($period1To) ?>">
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <h6 class="text-success mb-2">Period 2</h6>
                    <div class="row g-2">
                        <div class="col-6">
                            <input type="date" class="form-control" name="period2_from" value="<?= $this->e($period2From) ?>">
                        </div>
                        <div class="col-6">
                            <input type="date" class="form-control" name="period2_to" value="<?= $this->e($period2To) ?>">
                        </div>
                    </div>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-balance-scale me-1"></i> Compare Periods
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Comparison Cards -->
<div class="row mb-4">
    <!-- Total Calls -->
    <div class="col-md-3 mb-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <h6 class="text-muted mb-3">Total Calls</h6>
                <div class="row">
                    <div class="col-6 border-end">
                        <small class="text-primary">Period 1</small>
                        <h3><?= number_format($period1Stats['total_calls']) ?></h3>
                    </div>
                    <div class="col-6">
                        <small class="text-success">Period 2</small>
                        <h3><?= number_format($period2Stats['total_calls']) ?></h3>
                    </div>
                </div>
                <?php
                $diff = $period1Stats['total_calls'] > 0
                    ? round((($period2Stats['total_calls'] - $period1Stats['total_calls']) / $period1Stats['total_calls']) * 100, 1)
                    : 0;
                ?>
                <div class="mt-2">
                    <span class="badge bg-<?= $diff >= 0 ? 'success' : 'danger' ?>">
                        <i class="fas fa-<?= $diff >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i>
                        <?= abs($diff) ?>%
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Answered Calls -->
    <div class="col-md-3 mb-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <h6 class="text-muted mb-3">Answered</h6>
                <div class="row">
                    <div class="col-6 border-end">
                        <small class="text-primary">Period 1</small>
                        <h3><?= number_format($period1Stats['answered_calls']) ?></h3>
                    </div>
                    <div class="col-6">
                        <small class="text-success">Period 2</small>
                        <h3><?= number_format($period2Stats['answered_calls']) ?></h3>
                    </div>
                </div>
                <?php
                $diff = $period1Stats['answered_calls'] > 0
                    ? round((($period2Stats['answered_calls'] - $period1Stats['answered_calls']) / $period1Stats['answered_calls']) * 100, 1)
                    : 0;
                ?>
                <div class="mt-2">
                    <span class="badge bg-<?= $diff >= 0 ? 'success' : 'danger' ?>">
                        <i class="fas fa-<?= $diff >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i>
                        <?= abs($diff) ?>%
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Answer Rate -->
    <div class="col-md-3 mb-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <h6 class="text-muted mb-3">Answer Rate</h6>
                <div class="row">
                    <div class="col-6 border-end">
                        <small class="text-primary">Period 1</small>
                        <h3><?= $period1Stats['answer_rate'] ?>%</h3>
                    </div>
                    <div class="col-6">
                        <small class="text-success">Period 2</small>
                        <h3><?= $period2Stats['answer_rate'] ?>%</h3>
                    </div>
                </div>
                <?php
                $diff = $period2Stats['answer_rate'] - $period1Stats['answer_rate'];
                ?>
                <div class="mt-2">
                    <span class="badge bg-<?= $diff >= 0 ? 'success' : 'danger' ?>">
                        <i class="fas fa-<?= $diff >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i>
                        <?= abs($diff) ?> pts
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Avg Duration -->
    <div class="col-md-3 mb-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <h6 class="text-muted mb-3">Avg Duration</h6>
                <div class="row">
                    <div class="col-6 border-end">
                        <small class="text-primary">Period 1</small>
                        <h3><?= $this->formatDuration($period1Stats['avg_duration']) ?></h3>
                    </div>
                    <div class="col-6">
                        <small class="text-success">Period 2</small>
                        <h3><?= $this->formatDuration($period2Stats['avg_duration']) ?></h3>
                    </div>
                </div>
                <?php
                $diff = $period1Stats['avg_duration'] > 0
                    ? round((($period2Stats['avg_duration'] - $period1Stats['avg_duration']) / $period1Stats['avg_duration']) * 100, 1)
                    : 0;
                ?>
                <div class="mt-2">
                    <span class="badge bg-<?= $diff >= 0 ? 'success' : 'warning' ?>">
                        <i class="fas fa-<?= $diff >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i>
                        <?= abs($diff) ?>%
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Comparison Chart -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Daily Comparison</h5>
    </div>
    <div class="card-body">
        <canvas id="comparisonChart" height="350"></canvas>
    </div>
</div>

<?php $this->section('scripts'); ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const period1Data = <?= json_encode($period1Stats['daily_data']) ?>;
const period2Data = <?= json_encode($period2Stats['daily_data']) ?>;

// Normalize to day numbers for comparison
const maxDays = Math.max(period1Data.length, period2Data.length);
const labels = Array.from({length: maxDays}, (_, i) => 'Day ' + (i + 1));

new Chart(document.getElementById('comparisonChart'), {
    type: 'line',
    data: {
        labels: labels,
        datasets: [{
            label: 'Period 1 - Total',
            data: period1Data.map(d => parseInt(d.total || 0)),
            borderColor: 'rgba(13, 110, 253, 1)',
            backgroundColor: 'rgba(13, 110, 253, 0.1)',
            fill: false,
            tension: 0.3
        }, {
            label: 'Period 2 - Total',
            data: period2Data.map(d => parseInt(d.total || 0)),
            borderColor: 'rgba(25, 135, 84, 1)',
            backgroundColor: 'rgba(25, 135, 84, 0.1)',
            fill: false,
            tension: 0.3
        }, {
            label: 'Period 1 - Answered',
            data: period1Data.map(d => parseInt(d.answered || 0)),
            borderColor: 'rgba(13, 110, 253, 0.5)',
            borderDash: [5, 5],
            fill: false,
            tension: 0.3
        }, {
            label: 'Period 2 - Answered',
            data: period2Data.map(d => parseInt(d.answered || 0)),
            borderColor: 'rgba(25, 135, 84, 0.5)',
            borderDash: [5, 5],
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
