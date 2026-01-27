<?php $this->section('content'); ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">Create User</h1>
        <p class="text-muted mb-0">Add a new system user</p>
    </div>
    <a href="/areports/admin/users" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back
    </a>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form action="/areports/admin/users" method="POST">
                    <?= $this->csrf() ?>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="first_name" required
                                   value="<?= $this->e($this->old('first_name')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="last_name" required
                                   value="<?= $this->e($this->old('last_name')) ?>">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="username" required
                                   minlength="3" maxlength="50"
                                   value="<?= $this->e($this->old('username')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email"
                                   value="<?= $this->e($this->old('email')) ?>">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="password" required minlength="8">
                            <div class="form-text">Minimum 8 characters</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="password_confirmation" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select" name="role_id" required id="role_id">
                                <option value="">Select Role</option>
                                <?php foreach ($roles as $role): ?>
                                <option value="<?= $role['id'] ?>"><?= $this->e($role['display_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Extension (FreePBX)</label>
                            <select class="form-select" name="extension">
                                <option value="">-- No Extension --</option>
                                <?php foreach ($extensions as $ext): ?>
                                <option value="<?= $this->e($ext['extension']) ?>"
                                    <?= $this->old('extension') === $ext['extension'] ? 'selected' : '' ?>>
                                    <?= $this->e($ext['extension']) ?> - <?= $this->e($ext['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Link to FreePBX extension for agent tracking</div>
                        </div>
                    </div>

                    <!-- Queue Assignment (for Agents) -->
                    <div class="mb-3" id="queue-assignment-section">
                        <label class="form-label">Assigned Queues</label>
                        <div class="form-text mb-2">Select which queues this agent can login to</div>
                        <div class="row">
                            <?php foreach ($queues as $queue): ?>
                            <div class="col-md-4 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="queues[]"
                                           value="<?= $this->e($queue['extension']) ?>"
                                           id="queue_<?= $this->e($queue['extension']) ?>">
                                    <label class="form-check-label" for="queue_<?= $this->e($queue['extension']) ?>">
                                        <?= $this->e($queue['extension']) ?> - <?= $this->e($queue['name']) ?>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (empty($queues)): ?>
                        <p class="text-muted">No queues found in FreePBX</p>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Create User
                        </button>
                        <a href="/areports/admin/users" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php $this->endSection(); ?>
