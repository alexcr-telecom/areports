<?php $this->section('content'); ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">Create Pause Cause</h1>
        <p class="text-muted mb-0">Add a new agent pause reason</p>
    </div>
    <a href="/areports/admin/pause-causes" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back
    </a>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <form action="/areports/admin/pause-causes" method="POST">
                    <?= $this->csrf() ?>

                    <div class="mb-3">
                        <label class="form-label">Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="code" required
                               maxlength="20" pattern="[a-zA-Z0-9_]+"
                               value="<?= $this->e($this->old('code')) ?>"
                               placeholder="e.g., lunch, break, meeting">
                        <div class="form-text">Unique identifier (letters, numbers, underscore only)</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" required
                               maxlength="100"
                               value="<?= $this->e($this->old('name')) ?>"
                               placeholder="e.g., Lunch Break">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2"
                                  placeholder="Optional description"><?= $this->e($this->old('description')) ?></textarea>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                            <label class="form-check-label" for="is_active">
                                Active (visible to agents)
                            </label>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Create
                        </button>
                        <a href="/areports/admin/pause-causes" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-info-circle me-2"></i>Usage Tips
            </div>
            <div class="card-body">
                <p>Pause causes help track why agents are unavailable for calls.</p>
                <ul class="mb-0">
                    <li><strong>Code</strong> - Used in Asterisk queue logs for reporting</li>
                    <li><strong>Name</strong> - Displayed to agents in the pause menu</li>
                    <li>Common causes: Break, Lunch, Meeting, Training, Administrative work</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php $this->endSection(); ?>
