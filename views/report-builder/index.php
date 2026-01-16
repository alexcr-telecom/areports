<?php
/**
 * Report Builder Index View
 */
$reports = $reports ?? [];
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Report Builder</h1>
        <a href="/areports/report-builder/create" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>New Report
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (empty($reports)): ?>
            <div class="text-center py-5">
                <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                <p class="text-muted">No custom reports created yet</p>
                <a href="/areports/report-builder/create" class="btn btn-primary">
                    Create Your First Report
                </a>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Report Name</th>
                            <th>Data Source</th>
                            <th>Created By</th>
                            <th>Last Run</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $report): ?>
                        <tr>
                            <td>
                                <a href="/areports/report-builder/<?= $report['id'] ?>" class="fw-bold text-decoration-none">
                                    <?= htmlspecialchars($report['name']) ?>
                                </a>
                                <?php if ($report['description']): ?>
                                <br><small class="text-muted"><?= htmlspecialchars(substr($report['description'], 0, 50)) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-info"><?= ucfirst($report['data_source'] ?? 'cdr') ?></span>
                            </td>
                            <td><?= htmlspecialchars(($report['first_name'] ?? '') . ' ' . ($report['last_name'] ?? '')) ?></td>
                            <td>
                                <?= $report['last_run'] ? date('d/m/Y H:i', strtotime($report['last_run'])) : 'Never' ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="/areports/report-builder/<?= $report['id'] ?>" class="btn btn-outline-primary" title="Run">
                                        <i class="fas fa-play"></i>
                                    </a>
                                    <a href="/areports/report-builder/<?= $report['id'] ?>/edit" class="btn btn-outline-secondary" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-outline-danger" title="Delete"
                                            onclick="deleteReport(<?= $report['id'] ?>, '<?= htmlspecialchars($report['name'], ENT_QUOTES) ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
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

<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete "<strong id="deleteReportName"></strong>"?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST" style="display: inline;">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function deleteReport(id, name) {
    document.getElementById('deleteReportName').textContent = name;
    document.getElementById('deleteForm').action = '/areports/report-builder/' + id + '/delete';
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>
