<?php $this->section('content'); ?>

<div class="mb-4">
    <h1 class="h3 mb-0">Audit Log</h1>
    <p class="text-muted mb-0">System activity history</p>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Entity Type</label>
                <select class="form-select" name="entity_type">
                    <option value="">All Types</option>
                    <?php foreach ($entityTypes as $type): ?>
                    <option value="<?= $this->e($type['entity_type']) ?>" <?= $filters['entity_type'] === $type['entity_type'] ? 'selected' : '' ?>>
                        <?= $this->e($type['entity_type']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Action</label>
                <select class="form-select" name="action">
                    <option value="">All Actions</option>
                    <?php foreach ($actions as $act): ?>
                    <option value="<?= $this->e($act['action']) ?>" <?= $filters['action'] === $act['action'] ? 'selected' : '' ?>>
                        <?= $this->e($act['action']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">User</label>
                <select class="form-select" name="user_id">
                    <option value="">All Users</option>
                    <?php foreach ($users as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= $filters['user_id'] == $u['id'] ? 'selected' : '' ?>>
                        <?= $this->e($u['first_name'] . ' ' . $u['last_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-1"></i> Filter
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-sm">
                <thead>
                    <tr>
                        <th>Date/Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Entity</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= $this->formatDateTime($log['created_at']) ?></td>
                        <td>
                            <?php if ($log['username']): ?>
                            <?= $this->e($log['first_name'] . ' ' . $log['last_name']) ?>
                            <?php else: ?>
                            <span class="text-muted">System</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= match($log['action']) {
                                'create' => 'success',
                                'update' => 'primary',
                                'delete' => 'danger',
                                default => 'secondary'
                            } ?>"><?= $this->e($log['action']) ?></span>
                        </td>
                        <td>
                            <?= $this->e($log['entity_type']) ?>
                            <?php if ($log['entity_id']): ?>
                            <small class="text-muted">#<?= $log['entity_id'] ?></small>
                            <?php endif; ?>
                        </td>
                        <td><small><?= $this->e($log['ip_address']) ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">No audit records found</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <?= $this->pagination($page, $totalPages, '/areports/admin/audit-log', $filters) ?>
        <?php endif; ?>
    </div>
</div>

<?php $this->endSection(); ?>
