<?php $this->section('content'); ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">Pause Causes</h1>
        <p class="text-muted mb-0">Manage agent pause reasons</p>
    </div>
    <a href="/areports/admin/pause-causes/create" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i> Add Cause
    </a>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($causes)): ?>
        <div class="text-center py-4">
            <i class="fas fa-pause-circle fa-3x text-muted mb-3"></i>
            <p class="text-muted">No pause causes configured</p>
            <a href="/areports/admin/pause-causes/create" class="btn btn-primary">Add First Cause</a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle" id="causes-table">
                <thead class="table-light">
                    <tr>
                        <th style="width: 40px;"></th>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="sortable-causes">
                    <?php foreach ($causes as $cause): ?>
                    <tr data-id="<?= $cause['id'] ?>">
                        <td class="drag-handle text-center" style="cursor: move;">
                            <i class="fas fa-grip-vertical text-muted"></i>
                        </td>
                        <td><code><?= $this->e($cause['code']) ?></code></td>
                        <td><strong><?= $this->e($cause['name']) ?></strong></td>
                        <td class="text-muted"><?= $this->e($cause['description']) ?: '-' ?></td>
                        <td class="text-center">
                            <div class="form-check form-switch d-inline-block">
                                <input class="form-check-input toggle-active" type="checkbox"
                                       data-id="<?= $cause['id'] ?>"
                                       <?= $cause['is_active'] ? 'checked' : '' ?>>
                            </div>
                        </td>
                        <td class="text-end">
                            <a href="/areports/admin/pause-causes/<?= $cause['id'] ?>/edit"
                               class="btn btn-sm btn-outline-primary" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form action="/areports/admin/pause-causes/<?= $cause['id'] ?>/delete"
                                  method="POST" class="d-inline"
                                  onsubmit="return confirm('Delete this pause cause?');">
                                <?= $this->csrf() ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <small class="text-muted">
            <i class="fas fa-info-circle me-1"></i>
            Drag rows to reorder. Order affects how causes appear in the agent pause menu.
        </small>
        <?php endif; ?>
    </div>
</div>

<?php $this->endSection(); ?>

<?php $this->section('scripts'); ?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sortable for reordering
    const sortable = new Sortable(document.getElementById('sortable-causes'), {
        handle: '.drag-handle',
        animation: 150,
        onEnd: function() {
            const order = [];
            document.querySelectorAll('#sortable-causes tr').forEach(row => {
                order.push(row.dataset.id);
            });

            const formData = new FormData();
            formData.append('order', JSON.stringify(order));

            fetch('/areports/admin/pause-causes/reorder', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    toastr.success('Order saved');
                } else {
                    toastr.error('Failed to save order');
                }
            });
        }
    });

    // Toggle active status
    document.querySelectorAll('.toggle-active').forEach(toggle => {
        toggle.addEventListener('change', function() {
            const id = this.dataset.id;
            fetch(`/areports/admin/pause-causes/${id}/toggle`, {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    toastr.success(data.is_active ? 'Activated' : 'Deactivated');
                } else {
                    toastr.error('Failed to update status');
                    this.checked = !this.checked;
                }
            });
        });
    });
});
</script>
<?php $this->endSection(); ?>
