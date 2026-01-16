<?php
/**
 * Campaign Dispositions View
 */
$campaign = $campaign ?? [];
$dispositions = $dispositions ?? [];
$filters = $filters ?? [];
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Disposition Report</h1>
            <p class="text-muted mb-0"><?= htmlspecialchars($campaign['name']) ?></p>
        </div>
        <div class="btn-group">
            <a href="/areports/campaigns/<?= $campaign['id'] ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back
            </a>
            <a href="/areports/campaigns/<?= $campaign['id'] ?>/dispositions/export?format=csv" class="btn btn-outline-success">
                <i class="fas fa-file-csv me-2"></i>Export
            </a>
        </div>
    </div>

    <!-- Date Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="<?= $filters['date_from'] ?? '' ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="<?= $filters['date_to'] ?? '' ?>">
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
        <!-- Chart -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0">Disposition Distribution</h6>
                </div>
                <div class="card-body">
                    <canvas id="dispositionChart" height="250"></canvas>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0">Disposition Summary</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($dispositions)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-chart-pie fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No disposition data available</p>
                    </div>
                    <?php else: ?>
                    <?php $total = array_sum(array_column($dispositions, 'count')); ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Disposition</th>
                                    <th class="text-end">Count</th>
                                    <th class="text-end">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dispositions as $disp): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($disp['disposition']) ?></span>
                                    </td>
                                    <td class="text-end"><?= number_format($disp['count']) ?></td>
                                    <td class="text-end">
                                        <?= $total > 0 ? number_format(($disp['count'] / $total) * 100, 1) : 0 ?>%
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-light">
                                    <th>Total</th>
                                    <th class="text-end"><?= number_format($total) ?></th>
                                    <th class="text-end">100%</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Agent Breakdown -->
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0">Agent Breakdown</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Agent</th>
                            <th class="text-end">Total</th>
                            <?php
                            $dispNames = array_unique(array_column($dispositions, 'disposition'));
                            foreach ($dispNames as $name): ?>
                            <th class="text-end"><?= htmlspecialchars($name) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="<?= 2 + count($dispNames) ?>" class="text-center text-muted">
                                Agent breakdown data not available
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="/areports/public/vendor/chartjs/chart.min.js"></script>
<script>
const dispositions = <?= json_encode($dispositions) ?>;
if (dispositions.length > 0) {
    const ctx = document.getElementById('dispositionChart').getContext('2d');
    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: dispositions.map(d => d.disposition),
            datasets: [{
                data: dispositions.map(d => d.count),
                backgroundColor: [
                    '#28a745', '#ffc107', '#17a2b8', '#dc3545', '#6c757d',
                    '#007bff', '#6f42c1', '#e83e8c', '#fd7e14', '#20c997'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
}
</script>
