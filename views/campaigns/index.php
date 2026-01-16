<?php
/**
 * Campaigns List View
 */
$campaigns = $campaigns ?? [];
$filters = $filters ?? [];
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Campaigns</h1>
        <?php if ($this->can('campaigns.manage')): ?>
        <a href="/areports/campaigns/create" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>New Campaign
        </a>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="draft" <?= ($filters['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="paused" <?= ($filters['status'] ?? '') === 'paused' ? 'selected' : '' ?>>Paused</option>
                        <option value="completed" <?= ($filters['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="archived" <?= ($filters['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Archived</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <option value="inbound" <?= ($filters['type'] ?? '') === 'inbound' ? 'selected' : '' ?>>Inbound</option>
                        <option value="outbound" <?= ($filters['type'] ?? '') === 'outbound' ? 'selected' : '' ?>>Outbound</option>
                        <option value="blended" <?= ($filters['type'] ?? '') === 'blended' ? 'selected' : '' ?>>Blended</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="fas fa-filter me-2"></i>Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Campaigns Table -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($campaigns)): ?>
            <div class="text-center py-5">
                <i class="fas fa-bullhorn fa-3x text-muted mb-3"></i>
                <p class="text-muted">No campaigns found</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Campaign</th>
                            <th>Type</th>
                            <th>Queue</th>
                            <th>Status</th>
                            <th>Leads</th>
                            <th>Calls</th>
                            <th>Dates</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($campaigns as $campaign): ?>
                        <tr>
                            <td>
                                <a href="/areports/campaigns/<?= $campaign['id'] ?>" class="fw-bold text-decoration-none">
                                    <?= htmlspecialchars($campaign['name']) ?>
                                </a>
                                <?php if ($campaign['description']): ?>
                                <br><small class="text-muted"><?= htmlspecialchars(substr($campaign['description'], 0, 50)) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= $campaign['type'] === 'outbound' ? 'warning' : ($campaign['type'] === 'inbound' ? 'info' : 'secondary') ?>">
                                    <?= ucfirst($campaign['type']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($campaign['queue_name'] ?? '-') ?></td>
                            <td>
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
                            </td>
                            <td><?= number_format($campaign['total_leads'] ?? 0) ?></td>
                            <td><?= number_format($campaign['total_calls'] ?? 0) ?></td>
                            <td>
                                <?php if ($campaign['start_date']): ?>
                                <?= date('d/m/Y', strtotime($campaign['start_date'])) ?>
                                <?php if ($campaign['end_date']): ?>
                                - <?= date('d/m/Y', strtotime($campaign['end_date'])) ?>
                                <?php endif; ?>
                                <?php else: ?>
                                -
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="/areports/campaigns/<?= $campaign['id'] ?>" class="btn btn-outline-primary" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($this->can('campaigns.manage')): ?>
                                    <a href="/areports/campaigns/<?= $campaign['id'] ?>/edit" class="btn btn-outline-secondary" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php endif; ?>
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
