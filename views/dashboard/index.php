<?php $this->section('content'); ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">Dashboard</h1>
        <p class="text-muted mb-0">Call center overview for today</p>
    </div>
    <div>
        <button type="button" class="btn btn-outline-primary" id="refreshDashboard">
            <i class="fas fa-sync-alt me-1"></i> Refresh
        </button>
        <?php if ($this->can('wallboard.view')): ?>
        <a href="/areports/wallboard" class="btn btn-primary ms-2" target="_blank">
            <i class="fas fa-tv me-1"></i> Wallboard
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stat-card primary">
            <i class="fas fa-phone stat-icon"></i>
            <div class="stat-value" id="stat-total"><?= number_format($stats['total_calls']) ?></div>
            <div class="stat-label">Total Calls</div>
            <div class="stat-change">
                <?php if ($stats['calls_change'] >= 0): ?>
                <i class="fas fa-arrow-up"></i> <?= $stats['calls_change'] ?>%
                <?php else: ?>
                <i class="fas fa-arrow-down"></i> <?= abs($stats['calls_change']) ?>%
                <?php endif; ?>
                vs yesterday
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card success">
            <i class="fas fa-phone-alt stat-icon"></i>
            <div class="stat-value" id="stat-answered"><?= number_format($stats['answered_calls']) ?></div>
            <div class="stat-label">Answered</div>
            <div class="stat-change">
                <?= $stats['answer_rate'] ?>% answer rate
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card warning">
            <i class="fas fa-phone-slash stat-icon"></i>
            <div class="stat-value" id="stat-abandoned"><?= number_format($stats['abandoned_calls']) ?></div>
            <div class="stat-label">Unanswered</div>
            <div class="stat-change">
                <?= 100 - $stats['answer_rate'] ?>% of total
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card info">
            <i class="fas fa-clock stat-icon"></i>
            <div class="stat-value" id="stat-avgtime"><?= $this->formatDuration($stats['avg_talk_time']) ?></div>
            <div class="stat-label">Avg Talk Time</div>
            <div class="stat-change">
                <?= $this->formatDuration($stats['avg_duration']) ?> total duration
            </div>
        </div>
    </div>
</div>

<!-- Charts and Tables Row -->
<div class="row">
    <!-- Call Volume Chart -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-chart-area me-2"></i>Call Volume Today</span>
                <small class="text-muted">Hourly distribution</small>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="callVolumeChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Queue Status -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-layer-group me-2"></i>Queue Status
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Queue</th>
                                <th class="text-center">Calls</th>
                                <th class="text-center">SLA</th>
                            </tr>
                        </thead>
                        <tbody id="queue-status-body">
                            <?php foreach ($queues as $queue): ?>
                            <tr>
                                <td>
                                    <span class="badge" style="background-color: <?= $this->e($queue['color_code']) ?>">
                                        <?= $this->e($queue['queue_number']) ?>
                                    </span>
                                    <small class="d-block text-muted"><?= $this->e($queue['display_name']) ?></small>
                                </td>
                                <td class="text-center">
                                    <span class="text-success"><?= $queue['answered'] ?></span>
                                    /
                                    <span class="text-danger"><?= $queue['abandoned'] ?></span>
                                </td>
                                <td class="text-center">
                                    <?php
                                    $slaClass = 'success';
                                    if ($queue['sla_percentage'] < 80) $slaClass = 'warning';
                                    if ($queue['sla_percentage'] < 60) $slaClass = 'danger';
                                    ?>
                                    <span class="badge bg-<?= $slaClass ?>"><?= $queue['sla_percentage'] ?>%</span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($queues)): ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">No queue data available</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Calls -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-history me-2"></i>Recent Calls</span>
                <a href="/areports/reports/cdr" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>From</th>
                                <th>To/DID</th>
                                <th>Duration</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="recent-calls-body">
                            <?php foreach ($recentCalls as $call): ?>
                            <tr>
                                <td>
                                    <span class="fw-medium"><?= $this->e($call['time']) ?></span>
                                </td>
                                <td><?= $this->e($call['src']) ?></td>
                                <td>
                                    <?= $this->e($call['did'] ?: $call['dst']) ?>
                                    <small class="d-block text-muted"><?= $this->e($call['context']) ?></small>
                                </td>
                                <td><?= $this->e($call['talk_time']) ?></td>
                                <td>
                                    <?php
                                    $statusClass = match($call['disposition']) {
                                        'ANSWERED' => 'success',
                                        'NO ANSWER' => 'warning',
                                        'BUSY' => 'info',
                                        default => 'danger'
                                    };
                                    ?>
                                    <span class="badge bg-<?= $statusClass ?>"><?= $this->e($call['disposition']) ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recentCalls)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No recent calls</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $this->endSection(); ?>

<?php $this->section('scripts'); ?>
<script>
// Hourly volume data
var hourlyData = <?= json_encode($hourlyVolume) ?>;

// Initialize Call Volume Chart
var ctx = document.getElementById('callVolumeChart').getContext('2d');
var callVolumeChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: hourlyData.map(function(h) { return h.hour + ':00'; }),
        datasets: [{
            label: 'Total Calls',
            data: hourlyData.map(function(h) { return h.total; }),
            borderColor: '#3498db',
            backgroundColor: 'rgba(52, 152, 219, 0.1)',
            fill: true,
            tension: 0.4
        }, {
            label: 'Answered',
            data: hourlyData.map(function(h) { return h.answered; }),
            borderColor: '#27ae60',
            backgroundColor: 'rgba(39, 174, 96, 0.1)',
            fill: true,
            tension: 0.4
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
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});

// Refresh dashboard
$('#refreshDashboard').on('click', function() {
    var $btn = $(this);
    $btn.prop('disabled', true).find('i').addClass('fa-spin');

    $.get('/areports/dashboard/data', function(data) {
        // Update stats
        $('#stat-total').text(data.stats.total_calls.toLocaleString());
        $('#stat-answered').text(data.stats.answered_calls.toLocaleString());
        $('#stat-abandoned').text(data.stats.abandoned_calls.toLocaleString());

        // Update chart
        callVolumeChart.data.datasets[0].data = data.hourlyVolume.map(function(h) { return h.total; });
        callVolumeChart.data.datasets[1].data = data.hourlyVolume.map(function(h) { return h.answered; });
        callVolumeChart.update();

        aReports.toast('Dashboard updated', 'success');
    }).fail(function() {
        aReports.toast('Failed to refresh dashboard', 'error');
    }).always(function() {
        $btn.prop('disabled', false).find('i').removeClass('fa-spin');
    });
});

// Auto-refresh every 60 seconds
setInterval(function() {
    $('#refreshDashboard').click();
}, 60000);
</script>
<?php $this->endSection(); ?>
