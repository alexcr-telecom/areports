<?php $this->section('content'); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">SLA Compliance</h1>
        <p class="text-muted mb-0">Service Level Agreement performance</p>
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
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-1"></i> Apply Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Overall SLA -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-<?= $overallSla >= 80 ? 'success' : ($overallSla >= 60 ? 'warning' : 'danger') ?> text-white">
            <div class="card-body text-center">
                <h2 class="display-4"><?= $overallSla ?>%</h2>
                <p class="mb-0">Overall SLA</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <h2 class="display-4 text-success"><?= number_format($totalWithinSla) ?></h2>
                <p class="mb-0">Within SLA</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <h2 class="display-4"><?= number_format($totalAnswered) ?></h2>
                <p class="mb-0">Total Answered</p>
            </div>
        </div>
    </div>
</div>

<!-- Queue SLA Table -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">SLA by Queue</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Queue</th>
                        <th class="text-center">Threshold</th>
                        <th class="text-center">Answered</th>
                        <th class="text-center">Within SLA</th>
                        <th class="text-center">Outside SLA</th>
                        <th class="text-center">SLA %</th>
                        <th>Performance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($slaData as $queue): ?>
                    <tr>
                        <td><strong><?= $this->e($queue['display_name']) ?></strong></td>
                        <td class="text-center"><?= $queue['sla_threshold'] ?>s</td>
                        <td class="text-center"><?= number_format($queue['total_answered']) ?></td>
                        <td class="text-center text-success"><?= number_format($queue['within_sla']) ?></td>
                        <td class="text-center text-danger"><?= number_format($queue['outside_sla']) ?></td>
                        <td class="text-center">
                            <span class="badge bg-<?= $queue['sla_percentage'] >= 80 ? 'success' : ($queue['sla_percentage'] >= 60 ? 'warning' : 'danger') ?>">
                                <?= $queue['sla_percentage'] ?>%
                            </span>
                        </td>
                        <td>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-<?= $queue['sla_percentage'] >= 80 ? 'success' : ($queue['sla_percentage'] >= 60 ? 'warning' : 'danger') ?>"
                                     role="progressbar"
                                     style="width: <?= $queue['sla_percentage'] ?>%">
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($slaData)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No SLA data found for the selected period</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Daily Trend Chart -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Daily SLA Trend</h5>
    </div>
    <div class="card-body">
        <canvas id="slaChart" height="300"></canvas>
    </div>
</div>

<?php $this->section('scripts'); ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const dailyData = <?= json_encode($dailyTrend) ?>;

const labels = dailyData.map(d => d.date);
const totalData = dailyData.map(d => parseInt(d.total_calls || 0));
const answeredData = dailyData.map(d => parseInt(d.answered || 0));

new Chart(document.getElementById('slaChart'), {
    type: 'line',
    data: {
        labels: labels,
        datasets: [{
            label: 'Total Calls',
            data: totalData,
            borderColor: '#0d6efd',
            backgroundColor: 'rgba(13, 110, 253, 0.1)',
            fill: true
        }, {
            label: 'Answered',
            data: answeredData,
            borderColor: '#198754',
            backgroundColor: 'rgba(25, 135, 84, 0.1)',
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top'
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
</script>
<?php $this->endSection(); ?>

<?php $this->endSection(); ?>
