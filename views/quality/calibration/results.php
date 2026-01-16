<?php
/**
 * Calibration Results View
 */
$session = $session ?? [];
$results = $results ?? [];
$stats = $stats ?? [];
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Calibration Results</h1>
            <p class="text-muted mb-0"><?= htmlspecialchars($session['name']) ?></p>
        </div>
        <a href="/areports/calibration/<?= $session['id'] ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Session
        </a>
    </div>

    <div class="row">
        <div class="col-md-3 mb-4">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h2 class="mb-0"><?= number_format($stats['avg_score'] ?? 0, 1) ?>%</h2>
                    <small>Average Score</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h2 class="mb-0"><?= number_format($stats['max_score'] ?? 0, 1) ?>%</h2>
                    <small>Highest Score</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card bg-danger text-white">
                <div class="card-body text-center">
                    <h2 class="mb-0"><?= number_format($stats['min_score'] ?? 0, 1) ?>%</h2>
                    <small>Lowest Score</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card bg-warning text-dark">
                <div class="card-body text-center">
                    <h2 class="mb-0"><?= number_format($stats['variance'] ?? 0, 1) ?></h2>
                    <small>Variance</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Score Comparison</h6>
                </div>
                <div class="card-body">
                    <canvas id="scoreChart" height="100"></canvas>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Detailed Results</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Evaluator</th>
                                    <th class="text-end">Score</th>
                                    <th class="text-end">Deviation</th>
                                    <th>Submitted</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results as $r): ?>
                                <?php $deviation = ($r['score'] ?? 0) - ($stats['avg_score'] ?? 0); ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['evaluator_name']) ?></td>
                                    <td class="text-end">
                                        <strong><?= number_format($r['score'] ?? 0, 1) ?>%</strong>
                                    </td>
                                    <td class="text-end">
                                        <span class="badge bg-<?= abs($deviation) <= 5 ? 'success' : (abs($deviation) <= 10 ? 'warning' : 'danger') ?>">
                                            <?= $deviation >= 0 ? '+' : '' ?><?= number_format($deviation, 1) ?>
                                        </span>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($r['submitted_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Insights</h6>
                </div>
                <div class="card-body">
                    <?php $variance = $stats['variance'] ?? 0; ?>
                    <?php if ($variance <= 5): ?>
                    <div class="alert alert-success mb-0">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Good Calibration!</strong><br>
                        Low variance indicates consistent scoring across evaluators.
                    </div>
                    <?php elseif ($variance <= 15): ?>
                    <div class="alert alert-warning mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Moderate Variance</strong><br>
                        Some differences in scoring. Consider discussing criteria alignment.
                    </div>
                    <?php else: ?>
                    <div class="alert alert-danger mb-0">
                        <i class="fas fa-times-circle me-2"></i>
                        <strong>High Variance</strong><br>
                        Significant scoring differences. Training may be needed.
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-download me-2"></i>Export</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="/areports/calibration/<?= $session['id'] ?>/export?format=pdf" class="btn btn-outline-danger">
                            <i class="fas fa-file-pdf me-2"></i>Export PDF
                        </a>
                        <a href="/areports/calibration/<?= $session['id'] ?>/export?format=excel" class="btn btn-outline-success">
                            <i class="fas fa-file-excel me-2"></i>Export Excel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="/areports/public/vendor/chartjs/chart.min.js"></script>
<script>
const ctx = document.getElementById('scoreChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($results, 'evaluator_name')) ?>,
        datasets: [{
            label: 'Score',
            data: <?= json_encode(array_column($results, 'score')) ?>,
            backgroundColor: 'rgba(54, 162, 235, 0.5)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }, {
            label: 'Average',
            data: Array(<?= count($results) ?>).fill(<?= $stats['avg_score'] ?? 0 ?>),
            type: 'line',
            borderColor: 'rgba(255, 99, 132, 1)',
            borderDash: [5, 5],
            fill: false,
            pointRadius: 0
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                max: 100
            }
        }
    }
});
</script>
