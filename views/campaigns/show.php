<?php
/**
 * Campaign Detail View
 */
$campaign = $campaign ?? [];
$stats = $stats ?? [];
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><?= htmlspecialchars($campaign['name']) ?></h1>
            <p class="text-muted mb-0"><?= htmlspecialchars($campaign['description'] ?? '') ?></p>
        </div>
        <div class="btn-group">
            <a href="/areports/campaigns" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back
            </a>
            <?php if ($this->can('campaigns.manage')): ?>
            <a href="/areports/campaigns/<?= $campaign['id'] ?>/edit" class="btn btn-outline-primary">
                <i class="fas fa-edit me-2"></i>Edit
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <div class="col-md-3 mb-4">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h2 class="mb-0"><?= number_format($stats['total_leads'] ?? 0) ?></h2>
                    <small>Total Leads</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h2 class="mb-0"><?= number_format($stats['total_calls'] ?? 0) ?></h2>
                    <small>Calls Made</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h2 class="mb-0"><?= number_format($stats['contacted'] ?? 0) ?></h2>
                    <small>Contacted</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card bg-warning text-dark">
                <div class="card-body text-center">
                    <?php $contactRate = ($stats['total_leads'] ?? 0) > 0 ? round(($stats['contacted'] ?? 0) / ($stats['total_leads'] ?? 1) * 100, 1) : 0; ?>
                    <h2 class="mb-0"><?= $contactRate ?>%</h2>
                    <small>Contact Rate</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Campaign Details</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-3">Status</dt>
                        <dd class="col-sm-9">
                            <?php
                            $statusColors = [
                                'draft' => 'secondary',
                                'active' => 'success',
                                'paused' => 'warning',
                                'completed' => 'info',
                                'archived' => 'dark',
                            ];
                            ?>
                            <span class="badge bg-<?= $statusColors[$campaign['status']] ?? 'secondary' ?>">
                                <?= ucfirst($campaign['status']) ?>
                            </span>
                        </dd>

                        <dt class="col-sm-3">Type</dt>
                        <dd class="col-sm-9"><?= ucfirst($campaign['type']) ?></dd>

                        <dt class="col-sm-3">Queue</dt>
                        <dd class="col-sm-9"><?= htmlspecialchars($campaign['queue_name'] ?? '-') ?></dd>

                        <dt class="col-sm-3">Start Date</dt>
                        <dd class="col-sm-9"><?= $campaign['start_date'] ? date('d/m/Y', strtotime($campaign['start_date'])) : '-' ?></dd>

                        <dt class="col-sm-3">End Date</dt>
                        <dd class="col-sm-9"><?= $campaign['end_date'] ? date('d/m/Y', strtotime($campaign['end_date'])) : '-' ?></dd>

                        <dt class="col-sm-3">Created</dt>
                        <dd class="col-sm-9"><?= date('d/m/Y H:i', strtotime($campaign['created_at'])) ?></dd>
                    </dl>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Disposition Summary</h6>
                    <a href="/areports/campaigns/<?= $campaign['id'] ?>/dispositions" class="btn btn-sm btn-outline-primary">
                        View All
                    </a>
                </div>
                <div class="card-body">
                    <canvas id="dispositionChart" height="100"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="/areports/campaigns/<?= $campaign['id'] ?>/leads" class="btn btn-outline-primary">
                            <i class="fas fa-users me-2"></i>Manage Leads
                        </a>
                        <a href="/areports/campaigns/<?= $campaign['id'] ?>/dispositions" class="btn btn-outline-info">
                            <i class="fas fa-chart-pie me-2"></i>Disposition Report
                        </a>
                        <a href="/areports/campaigns/<?= $campaign['id'] ?>/export" class="btn btn-outline-success">
                            <i class="fas fa-download me-2"></i>Export Data
                        </a>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Progress</h6>
                </div>
                <div class="card-body">
                    <?php
                    $total = $stats['total_leads'] ?? 0;
                    $contacted = $stats['contacted'] ?? 0;
                    $progress = $total > 0 ? round(($contacted / $total) * 100) : 0;
                    ?>
                    <div class="text-center mb-3">
                        <h3><?= $progress ?>%</h3>
                        <small class="text-muted">Campaign Progress</small>
                    </div>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar bg-success" style="width: <?= $progress ?>%"></div>
                    </div>
                    <div class="mt-2 small text-muted text-center">
                        <?= number_format($contacted) ?> of <?= number_format($total) ?> leads contacted
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="/areports/public/vendor/chartjs/chart.min.js"></script>
<script>
const dispositions = <?= json_encode($stats['dispositions'] ?? []) ?>;
if (Object.keys(dispositions).length > 0) {
    const ctx = document.getElementById('dispositionChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: Object.keys(dispositions),
            datasets: [{
                data: Object.values(dispositions),
                backgroundColor: ['#28a745', '#ffc107', '#17a2b8', '#dc3545', '#6c757d']
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
