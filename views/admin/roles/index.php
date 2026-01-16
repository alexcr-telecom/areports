<?php $this->section('content'); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">Role Management</h1>
        <p class="text-muted mb-0">Manage user roles and permissions</p>
    </div>
    <?php if ($this->can('admin.roles.manage')): ?>
    <a href="/areports/admin/roles/create" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i> Add Role
    </a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Role</th>
                        <th>Description</th>
                        <th class="text-center">Users</th>
                        <th class="text-center">System</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roles as $role): ?>
                    <tr>
                        <td><strong><?= $this->e($role['display_name']) ?></strong></td>
                        <td><?= $this->e($role['description'] ?? '-') ?></td>
                        <td class="text-center">
                            <span class="badge bg-info"><?= $role['user_count'] ?></span>
                        </td>
                        <td class="text-center">
                            <?php if ($role['is_system']): ?>
                            <span class="badge bg-warning">System</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Custom</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($this->can('admin.roles.manage')): ?>
                            <a href="/areports/admin/roles/<?= $role['id'] ?>/edit" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php $this->endSection(); ?>
