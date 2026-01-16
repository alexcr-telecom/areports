<?php
/**
 * Alert History View
 */
$history = $history ?? [];
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Alert History</h1>
        <a href="/areports/alerts" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Alerts
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (empty($history)): ?>
            <div class="text-center py-5">
                <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                <p class="text-muted">No alerts have been triggered yet</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Alert</th>
                            <th>Triggered At</th>
                            <th>Value</th>
                            <th>Message</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $item): ?>
                        <tr class="<?= $item['acknowledged_at'] ? '' : 'table-warning' ?>">
                            <td>
                                <strong><?= htmlspecialchars($item['alert_name']) ?></strong>
                            </td>
                            <td>
                                <?= date('d/m/Y H:i:s', strtotime($item['triggered_at'])) ?>
                            </td>
                            <td>
                                <span class="badge bg-danger"><?= htmlspecialchars($item['trigger_value'] ?? '-') ?></span>
                            </td>
                            <td>
                                <?= htmlspecialchars($item['message'] ?? '-') ?>
                            </td>
                            <td>
                                <?php if ($item['acknowledged_at']): ?>
                                <span class="badge bg-success">Acknowledged</span>
                                <br><small class="text-muted"><?= date('d/m/Y H:i', strtotime($item['acknowledged_at'])) ?></small>
                                <?php else: ?>
                                <span class="badge bg-warning text-dark">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$item['acknowledged_at']): ?>
                                <form method="POST" action="/areports/alerts/<?= $item['id'] ?>/acknowledge" style="display:inline">
                                    <button type="submit" class="btn btn-sm btn-success" title="Acknowledge">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page - 1 ?>">Previous</a>
                    </li>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page + 1 ?>">Next</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
