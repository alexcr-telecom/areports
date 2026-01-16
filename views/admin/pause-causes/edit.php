<?php $this->section('content'); ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">Edit Pause Cause</h1>
        <p class="text-muted mb-0">Modify pause reason</p>
    </div>
    <a href="/areports/admin/pause-causes" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back
    </a>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <form action="/areports/admin/pause-causes/<?= $cause['id'] ?>" method="POST">
                    <?= $this->csrf() ?>

                    <div class="mb-3">
                        <label class="form-label">Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="code" required
                               maxlength="20" pattern="[a-zA-Z0-9_]+"
                               value="<?= $this->e($cause['code']) ?>">
                        <div class="form-text">Unique identifier (letters, numbers, underscore only)</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" required
                               maxlength="100"
                               value="<?= $this->e($cause['name']) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2"><?= $this->e($cause['description']) ?></textarea>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                                   <?= $cause['is_active'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_active">
                                Active (visible to agents)
                            </label>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Save Changes
                        </button>
                        <a href="/areports/admin/pause-causes" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">Info</div>
            <div class="card-body">
                <p><strong>Created:</strong><br><?= $this->formatDateTime($cause['created_at']) ?></p>
                <p class="mb-0"><strong>Last Updated:</strong><br><?= $this->formatDateTime($cause['updated_at']) ?></p>
            </div>
        </div>
    </div>
</div>

<?php $this->endSection(); ?>
