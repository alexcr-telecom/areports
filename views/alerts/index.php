<?php $this->section('content'); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">Alerts</h1>
        <p class="text-muted mb-0">Configure and manage alerts</p>
    </div>
    <?php if ($this->can('alerts.manage')): ?>
    <a href="/areports/alerts/create" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i> Create Alert
    </a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Metric</th>
                        <th>Condition</th>
                        <th>Threshold</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alerts as $alert): ?>
                    <tr>
                        <td><strong><?= $this->e($alert['name']) ?></strong></td>
                        <td><?= $this->e($alert['metric']) ?></td>
                        <td><?= $this->e($alert['condition']) ?></td>
                        <td><?= $this->e($alert['threshold']) ?></td>
                        <td>
                            <?php if ($alert['is_active']): ?>
                            <span class="badge bg-success">Active</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($this->can('alerts.manage')): ?>
                            <a href="/areports/alerts/<?= $alert['id'] ?>/edit" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($alerts)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No alerts configured</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php $this->endSection(); ?>
