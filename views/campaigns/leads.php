<?php
/**
 * Campaign Leads View
 */
$campaign = $campaign ?? [];
$leads = $leads ?? [];
$filters = $filters ?? [];
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Campaign Leads</h1>
            <p class="text-muted mb-0"><?= htmlspecialchars($campaign['name']) ?></p>
        </div>
        <div class="btn-group">
            <a href="/areports/campaigns/<?= $campaign['id'] ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back
            </a>
            <?php if ($this->can('campaigns.manage')): ?>
            <a href="/areports/campaigns/<?= $campaign['id'] ?>/leads/import" class="btn btn-primary">
                <i class="fas fa-upload me-2"></i>Import Leads
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="new" <?= ($filters['status'] ?? '') === 'new' ? 'selected' : '' ?>>New</option>
                        <option value="contacted" <?= ($filters['status'] ?? '') === 'contacted' ? 'selected' : '' ?>>Contacted</option>
                        <option value="callback" <?= ($filters['status'] ?? '') === 'callback' ? 'selected' : '' ?>>Callback</option>
                        <option value="completed" <?= ($filters['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="dnc" <?= ($filters['status'] ?? '') === 'dnc' ? 'selected' : '' ?>>DNC</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Name or phone..."
                           value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="fas fa-filter me-2"></i>Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Leads Table -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($leads)): ?>
            <div class="text-center py-5">
                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                <p class="text-muted">No leads found</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Attempts</th>
                            <th>Last Contact</th>
                            <th>Disposition</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leads as $lead): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($lead['first_name'] . ' ' . $lead['last_name']) ?></strong>
                                <?php if ($lead['company']): ?>
                                <br><small class="text-muted"><?= htmlspecialchars($lead['company']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($lead['phone']) ?></td>
                            <td>
                                <?php
                                $statusColors = [
                                    'new' => 'primary',
                                    'contacted' => 'info',
                                    'callback' => 'warning',
                                    'completed' => 'success',
                                    'dnc' => 'danger'
                                ];
                                ?>
                                <span class="badge bg-<?= $statusColors[$lead['status']] ?? 'secondary' ?>">
                                    <?= ucfirst($lead['status']) ?>
                                </span>
                            </td>
                            <td><?= $lead['call_attempts'] ?? 0 ?></td>
                            <td>
                                <?= $lead['last_contact'] ? date('d/m/Y H:i', strtotime($lead['last_contact'])) : '-' ?>
                            </td>
                            <td><?= htmlspecialchars($lead['disposition'] ?? '-') ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-primary" title="View Details"
                                            onclick="viewLead(<?= $lead['id'] ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if ($this->can('campaigns.manage')): ?>
                                    <button type="button" class="btn btn-outline-secondary" title="Edit"
                                            onclick="editLead(<?= $lead['id'] ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
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
                        <a class="page-link" href="?page=<?= $page - 1 ?>&<?= http_build_query($filters) ?>">Previous</a>
                    </li>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query($filters) ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page + 1 ?>&<?= http_build_query($filters) ?>">Next</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function viewLead(id) {
    window.location.href = '/areports/campaigns/<?= $campaign['id'] ?>/leads/' + id;
}

function editLead(id) {
    window.location.href = '/areports/campaigns/<?= $campaign['id'] ?>/leads/' + id + '/edit';
}
</script>
