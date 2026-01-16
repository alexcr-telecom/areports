<?php $this->section('content'); ?>

<!-- Page Header -->
<div class="mb-4">
    <h1 class="h3 mb-0">My Profile</h1>
    <p class="text-muted mb-0">Manage your account settings</p>
</div>

<div class="row">
    <!-- Profile Information -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">Profile Information</div>
            <div class="card-body">
                <form action="/areports/profile" method="POST">
                    <?= $this->csrf() ?>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control" name="first_name"
                                   value="<?= $this->e($user['first_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" name="last_name"
                                   value="<?= $this->e($user['last_name']) ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email"
                               value="<?= $this->e($user['email']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" value="<?= $this->e($user['username']) ?>" disabled>
                        <div class="form-text">Username cannot be changed</div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Update Profile
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Change Password -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">Change Password</div>
            <div class="card-body">
                <form action="/areports/profile/password" method="POST">
                    <?= $this->csrf() ?>

                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" class="form-control" name="current_password" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" class="form-control" name="password" required minlength="8">
                        <div class="form-text">Minimum 8 characters</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" name="password_confirmation" required>
                    </div>

                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-key me-1"></i> Change Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Account Info -->
<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">Account Information</div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr>
                        <td class="text-muted">Role</td>
                        <td><strong><?= $this->e($user['role_name'] ?? 'Unknown') ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Extension</td>
                        <td><strong><?= $this->e($user['extension'] ?? '-') ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Last Login</td>
                        <td><strong><?= $user['last_login'] ? $this->formatDateTime($user['last_login']) : 'N/A' ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Account Created</td>
                        <td><strong><?= $this->formatDateTime($user['created_at']) ?></strong></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?php $this->endSection(); ?>
