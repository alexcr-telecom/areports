<?php $this->section('content'); ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">Create Role</h1>
        <p class="text-muted mb-0">Add a new user role</p>
    </div>
    <a href="/areports/admin/roles" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back
    </a>
</div>

<form action="/areports/admin/roles" method="POST">
    <?= $this->csrf() ?>

    <div class="row">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">Role Details</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" required
                               maxlength="50" pattern="[a-z_]+"
                               value="<?= $this->e($this->old('name')) ?>"
                               placeholder="e.g., team_lead">
                        <div class="form-text">Lowercase letters and underscores only</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Display Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="display_name" required
                               maxlength="100"
                               value="<?= $this->e($this->old('display_name')) ?>"
                               placeholder="e.g., Team Lead">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"
                                  placeholder="Role description"><?= $this->e($this->old('description')) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Permissions</span>
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="select-all">Select All</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="deselect-all">Deselect All</button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php foreach ($groupedPermissions as $category => $permissions): ?>
                    <div class="mb-4">
                        <h6 class="text-uppercase text-muted mb-3">
                            <i class="fas fa-folder me-2"></i><?= $this->e(ucfirst($category)) ?>
                        </h6>
                        <div class="row">
                            <?php foreach ($permissions as $permission): ?>
                            <div class="col-md-6 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input permission-checkbox" type="checkbox"
                                           name="permissions[]" value="<?= $permission['id'] ?>"
                                           id="perm_<?= $permission['id'] ?>">
                                    <label class="form-check-label" for="perm_<?= $permission['id'] ?>">
                                        <strong><?= $this->e($permission['name']) ?></strong>
                                        <?php if ($permission['description']): ?>
                                        <br><small class="text-muted"><?= $this->e($permission['description']) ?></small>
                                        <?php endif; ?>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-3">
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-1"></i> Create Role
        </button>
        <a href="/areports/admin/roles" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<?php $this->endSection(); ?>

<?php $this->section('scripts'); ?>
<script>
document.getElementById('select-all').addEventListener('click', function() {
    document.querySelectorAll('.permission-checkbox').forEach(cb => cb.checked = true);
});
document.getElementById('deselect-all').addEventListener('click', function() {
    document.querySelectorAll('.permission-checkbox').forEach(cb => cb.checked = false);
});
</script>
<?php $this->endSection(); ?>
