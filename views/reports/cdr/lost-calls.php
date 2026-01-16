<?php
/**
 * Lost Calls Report View
 */
$calls = $calls ?? [];
$filters = $filters ?? [];
$stats = $stats ?? [];
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Lost Calls Report</h1>
        <div class="btn-group">
            <a href="/areports/reports/cdr/lost-calls/export?<?= http_build_query($filters) ?>&format=csv" class="btn btn-outline-secondary">
                <i class="fas fa-file-csv me-2"></i>CSV
            </a>
            <a href="/areports/reports/cdr/lost-calls/export?<?= http_build_query($filters) ?>&format=excel" class="btn btn-outline-secondary">
                <i class="fas fa-file-excel me-2"></i>Excel
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

    <!-- Summary Cards -->
    <div class="row">
        <div class="col-md-3 mb-4">
            <div class="card bg-danger text-white">
                <div class="card-body text-center">
                    <h2 class="mb-0"><?= number_format($stats['total_lost'] ?? 0) ?></h2>
                    <small>Total Lost Calls</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card bg-warning text-dark">
                <div class="card-body text-center">
                    <h2 class="mb-0"><?= number_format($stats['abandoned'] ?? 0) ?></h2>
                    <small>Abandoned</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h2 class="mb-0"><?= number_format($stats['no_answer'] ?? 0) ?></h2>
                    <small>No Answer</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card bg-secondary text-white">
                <div class="card-body text-center">
                    <h2 class="mb-0"><?= gmdate('i:s', $stats['avg_wait_time'] ?? 0) ?></h2>
                    <small>Avg Wait Before Abandon</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Calls Table -->
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0">Lost Calls Details</h6>
        </div>
        <div class="card-body">
            <?php if (empty($calls)): ?>
            <div class="text-center py-5">
                <i class="fas fa-phone-slash fa-3x text-muted mb-3"></i>
                <p class="text-muted">No lost calls found for the selected period</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-sm">
                    <thead>
                        <tr>
                            <th>Date/Time</th>
                            <th>Caller</th>
                            <th>Queue</th>
                            <th>Wait Time</th>
                            <th>Reason</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($calls as $call): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i:s', strtotime($call['calldate'])) ?></td>
                            <td><?= htmlspecialchars($call['src']) ?></td>
                            <td><?= htmlspecialchars($call['queue'] ?? '-') ?></td>
                            <td><?= gmdate('i:s', $call['wait_time'] ?? 0) ?></td>
                            <td>
                                <span class="badge bg-<?= ($call['reason'] ?? '') === 'ABANDON' ? 'warning' : 'danger' ?>">
                                    <?= htmlspecialchars($call['reason'] ?? $call['disposition']) ?>
                                </span>
                            </td>
                            <td>
                                <a href="tel:<?= htmlspecialchars($call['src']) ?>" class="btn btn-sm btn-outline-success" title="Call Back">
                                    <i class="fas fa-phone"></i>
                                </a>
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
