<?php
/**
 * Call Flow Report View
 */
$flowData = $flowData ?? [];
$filters = $filters ?? [];
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Call Flow Analysis</h1>
        <a href="/areports/reports/cdr" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to CDR
        </a>
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
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>Analyze
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        <!-- Hourly Distribution -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Hourly Call Distribution</h6>
                </div>
                <div class="card-body">
                    <canvas id="hourlyChart" height="150"></canvas>
                </div>
            </div>
        </div>

        <!-- Call Types -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Call Types</h6>
                </div>
                <div class="card-body">
                    <canvas id="typeChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Flow Summary -->
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0">Call Flow Summary</h6>
        </div>
        <div class="card-body">
            <div class="row text-center">
                <div class="col">
                    <div class="p-3 bg-light rounded">
                        <h3 class="text-primary"><?= number_format($flowData['total_incoming'] ?? 0) ?></h3>
                        <small class="text-muted">Incoming</small>
                    </div>
                </div>
                <div class="col d-flex align-items-center justify-content-center">
                    <i class="fas fa-arrow-right fa-2x text-muted"></i>
                </div>
                <div class="col">
                    <div class="p-3 bg-light rounded">
                        <h3 class="text-success"><?= number_format($flowData['answered'] ?? 0) ?></h3>
                        <small class="text-muted">Answered</small>
                    </div>
                </div>
                <div class="col d-flex align-items-center justify-content-center">
                    <i class="fas fa-code-branch fa-2x text-muted"></i>
                </div>
                <div class="col">
                    <div class="p-3 bg-light rounded">
                        <h3 class="text-info"><?= number_format($flowData['transferred'] ?? 0) ?></h3>
                        <small class="text-muted">Transferred</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Context Flow -->
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0">Context Flow</h6>
        </div>
        <div class="card-body">
            <?php if (empty($flowData['contexts'])): ?>
            <div class="text-center py-5">
                <i class="fas fa-sitemap fa-3x text-muted mb-3"></i>
                <p class="text-muted">No call flow data available</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Context</th>
                            <th class="text-end">Calls</th>
                            <th class="text-end">Answered</th>
                            <th class="text-end">Avg Duration</th>
                            <th>Distribution</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $maxCalls = max(array_column($flowData['contexts'], 'calls') ?: [1]);
                        foreach ($flowData['contexts'] as $ctx): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($ctx['context']) ?></code></td>
                            <td class="text-end"><?= number_format($ctx['calls']) ?></td>
                            <td class="text-end"><?= number_format($ctx['answered']) ?></td>
                            <td class="text-end"><?= gmdate('i:s', $ctx['avg_duration'] ?? 0) ?></td>
                            <td>
                                <div class="progress" style="height: 10px; width: 150px;">
                                    <div class="progress-bar" style="width: <?= ($ctx['calls'] / $maxCalls) * 100 ?>%"></div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="/areports/public/vendor/chartjs/chart.min.js"></script>
<script>
// Hourly chart
const hourlyData = <?= json_encode($flowData['hourly'] ?? []) ?>;
if (hourlyData.length > 0) {
    const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
    new Chart(hourlyCtx, {
        type: 'bar',
        data: {
            labels: hourlyData.map(d => d.hour + ':00'),
            datasets: [{
                label: 'Calls',
                data: hourlyData.map(d => d.count),
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
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
}

// Type chart
const typeData = <?= json_encode($flowData['types'] ?? ['Incoming' => 0, 'Outgoing' => 0, 'Internal' => 0]) ?>;
const typeCtx = document.getElementById('typeChart').getContext('2d');
new Chart(typeCtx, {
    type: 'doughnut',
    data: {
        labels: Object.keys(typeData),
        datasets: [{
            data: Object.values(typeData),
            backgroundColor: ['#007bff', '#28a745', '#ffc107']
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});
</script>
