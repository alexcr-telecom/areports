<?php $this->section('content'); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">Queue Settings</h1>
        <p class="text-muted mb-0">Configure queue display and SLA thresholds</p>
    </div>
    <?php if ($this->can('admin.queues.manage')): ?>
    <form action="/areports/admin/queues/sync" method="POST" class="d-inline">
        <?= $this->csrf() ?>
        <button type="submit" class="btn btn-success">
            <i class="fas fa-sync me-1"></i> Sync Queues
        </button>
    </form>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Queue</th>
                        <th>Display Name</th>
                        <th class="text-center">SLA Threshold</th>
                        <th class="text-center">SLA Warning</th>
                        <th class="text-center">Monitored</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($queues as $queue): ?>
                    <tr>
                        <td><code><?= $this->e($queue['queue_number']) ?></code></td>
                        <td><strong><?= $this->e($queue['display_name']) ?></strong></td>
                        <td class="text-center"><?= $queue['sla_threshold_seconds'] ?>s</td>
                        <td class="text-center"><?= $queue['warning_threshold_seconds'] ?>s</td>
                        <td class="text-center">
                            <?php if ($queue['is_monitored']): ?>
                            <span class="badge bg-success">Yes</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">No</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($this->can('admin.queues.manage')): ?>
                            <a href="/areports/admin/queues/<?= $queue['id'] ?>/edit" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($queues)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No queues configured. Click "Sync Queues" to import from Asterisk.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php $this->endSection(); ?>
