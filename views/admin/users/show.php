<?php
/**
 * User Detail View
 */
$user = $user ?? [];
$activity = $activity ?? [];
$stats = $stats ?? [];
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h1>
            <p class="text-muted mb-0"><?= htmlspecialchars($user['email']) ?></p>
        </div>
        <div class="btn-group">
            <a href="/areports/admin/users" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back
            </a>
            <a href="/areports/admin/users/<?= $user['id'] ?>/edit" class="btn btn-outline-primary">
                <i class="fas fa-edit me-2"></i>Edit
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-body text-center">
                    <div class="avatar-lg bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px; font-size: 2rem;">
                        <?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?>
                    </div>
                    <h5><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h5>
                    <p class="text-muted"><?= htmlspecialchars($user['role_name'] ?? 'No Role') ?></p>
                    <span class="badge bg-<?= $user['is_active'] ? 'success' : 'secondary' ?>">
                        <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">User Details</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-sm-5">Username</dt>
                        <dd class="col-sm-7"><?= htmlspecialchars($user['username']) ?></dd>

                        <dt class="col-sm-5">Email</dt>
                        <dd class="col-sm-7"><?= htmlspecialchars($user['email']) ?></dd>

                        <dt class="col-sm-5">Extension</dt>
                        <dd class="col-sm-7"><?= htmlspecialchars($user['extension'] ?? '-') ?></dd>

                        <dt class="col-sm-5">Created</dt>
                        <dd class="col-sm-7"><?= date('d/m/Y', strtotime($user['created_at'])) ?></dd>

                        <dt class="col-sm-5">Last Login</dt>
                        <dd class="col-sm-7"><?= $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Never' ?></dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h3 class="mb-0"><?= number_format($stats['logins'] ?? 0) ?></h3>
                            <small>Total Logins</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h3 class="mb-0"><?= number_format($stats['evaluations'] ?? 0) ?></h3>
                            <small>Evaluations</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h3 class="mb-0"><?= number_format($stats['reports'] ?? 0) ?></h3>
                            <small>Reports Generated</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Recent Activity</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($activity)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-history fa-2x text-muted mb-2"></i>
                        <p class="text-muted mb-0">No recent activity</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Action</th>
                                    <th>Details</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activity as $item): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($item['action']) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($item['details'] ?? '-') ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($item['created_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
