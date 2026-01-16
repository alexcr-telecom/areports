<?php
/**
 * Disposition Analysis Report View
 */
$dispositions = $dispositions ?? [];
$hourlyData = $hourlyData ?? [];
$agentDispositions = $agentDispositions ?? [];
$queues = $queues ?? [];
$filters = $filters ?? [];
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Disposition Analysis</h1>
        <div class="btn-group">
            <a href="/areports/reports/disposition/export?<?= http_build_query($filters) ?>&format=csv" class="btn btn-outline-secondary">
                <i class="fas fa-file-csv me-2"></i>CSV
            </a>
            <a href="/areports/reports/disposition/export?<?= http_build_query($filters) ?>&format=excel" class="btn btn-outline-secondary">
                <i class="fas fa-file-excel me-2"></i>Excel
            </a>
            <a href="/areports/reports/disposition/export?<?= http_build_query($filters) ?>&format=pdf" class="btn btn-outline-secondary">
                <i class="fas fa-file-pdf me-2"></i>PDF
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="<?= $filters['date_from'] ?? date('Y-m-d') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="<?= $filters['date_to'] ?? date('Y-m-d') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Queue</label>
                    <select name="queue" class="form-select">
                        <option value="">All Queues</option>
                        <?php foreach ($queues as $queue): ?>
                        <option value="<?= $queue['queue_number'] ?>" <?= ($filters['queue'] ?? '') === $queue['queue_number'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($queue['display_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>Apply
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        <!-- Disposition Summary -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Disposition Summary</h5>
                </div>
                <div class="card-body">
                    <canvas id="dispositionPieChart" height="300"></canvas>
                </div>
            </div>
        </div>

        <!-- Disposition Table -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Disposition Breakdown</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Disposition</th>
                                    <th class="text-end">Calls</th>
                                    <th class="text-end">%</th>
                                    <th class="text-end">Avg Duration</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dispositions as $disp): ?>
                                <?php
                                $color = match ($disp['disposition']) {
                                    'ANSWERED' => 'success',
                                    'NO ANSWER' => 'warning',
                                    'BUSY' => 'info',
                                    'FAILED' => 'danger',
                                    default => 'secondary',
                                };
                                ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-<?= $color ?>"><?= htmlspecialchars($disp['disposition']) ?></span>
                                    </td>
                                    <td class="text-end"><?= number_format($disp['total_calls']) ?></td>
                                    <td class="text-end"><?= $disp['percentage'] ?>%</td>
                                    <td class="text-end"><?= gmdate('i:s', $disp['avg_duration']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hourly Distribution -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Hourly Disposition Distribution</h5>
        </div>
        <div class="card-body">
            <canvas id="hourlyChart" height="100"></canvas>
        </div>
    </div>

    <!-- Agent Breakdown -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Agent Disposition Breakdown</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Agent</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">ANSWERED</th>
                            <th class="text-end">NO ANSWER</th>
                            <th class="text-end">BUSY</th>
                            <th class="text-end">FAILED</th>
                            <th class="text-end">Answer Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($agentDispositions as $agent): ?>
                        <?php
                        $answered = $agent['dispositions']['ANSWERED'] ?? 0;
                        $total = $agent['total'];
                        $rate = $total > 0 ? round(($answered / $total) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($agent['agent']) ?></td>
                            <td class="text-end"><?= number_format($total) ?></td>
                            <td class="text-end"><?= number_format($answered) ?></td>
                            <td class="text-end"><?= number_format($agent['dispositions']['NO ANSWER'] ?? 0) ?></td>
                            <td class="text-end"><?= number_format($agent['dispositions']['BUSY'] ?? 0) ?></td>
                            <td class="text-end"><?= number_format($agent['dispositions']['FAILED'] ?? 0) ?></td>
                            <td class="text-end">
                                <span class="badge bg-<?= $rate >= 80 ? 'success' : ($rate >= 60 ? 'warning' : 'danger') ?>">
                                    <?= $rate ?>%
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="/areports/public/vendor/chartjs/chart.min.js"></script>
<script>
// Disposition Pie Chart
const pieCtx = document.getElementById('dispositionPieChart').getContext('2d');
new Chart(pieCtx, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($dispositions, 'disposition')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($dispositions, 'total_calls')) ?>,
            backgroundColor: ['#28a745', '#ffc107', '#17a2b8', '#dc3545', '#6c757d'],
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'right' }
        }
    }
});

// Hourly Chart
const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
const hourlyData = <?= json_encode($hourlyData) ?>;
new Chart(hourlyCtx, {
    type: 'bar',
    data: {
        labels: hourlyData.map(d => d.hour + ':00'),
        datasets: [
            {
                label: 'ANSWERED',
                data: hourlyData.map(d => d.ANSWERED),
                backgroundColor: '#28a745',
            },
            {
                label: 'NO ANSWER',
                data: hourlyData.map(d => d['NO ANSWER']),
                backgroundColor: '#ffc107',
            },
            {
                label: 'BUSY',
                data: hourlyData.map(d => d.BUSY),
                backgroundColor: '#17a2b8',
            },
            {
                label: 'FAILED',
                data: hourlyData.map(d => d.FAILED),
                backgroundColor: '#dc3545',
            }
        ]
    },
    options: {
        responsive: true,
        scales: {
            x: { stacked: true },
            y: { stacked: true }
        }
    }
});
</script>
