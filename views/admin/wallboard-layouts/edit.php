<?php
/**
 * Edit Wallboard Layout View
 */
$layout = $layout ?? [];
$existingWidgets = json_decode($layout['widgets'] ?? '[]', true);
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Edit Wallboard Layout</h1>
        <a href="/areports/admin/wallboard-layouts" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back
        </a>
    </div>

    <form method="POST" action="/areports/admin/wallboard-layouts/<?= $layout['id'] ?>/update" id="layoutForm">
        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">Layout Details</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Layout Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required maxlength="100"
                                   value="<?= htmlspecialchars($layout['name'] ?? '') ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($layout['description'] ?? '') ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Refresh Interval (seconds)</label>
                                <input type="number" name="refresh_interval" class="form-control"
                                       value="<?= htmlspecialchars($layout['refresh_interval'] ?? 30) ?>" min="5" max="300">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Theme</label>
                                <select name="theme" class="form-select">
                                    <option value="light" <?= ($layout['theme'] ?? '') === 'light' ? 'selected' : '' ?>>Light</option>
                                    <option value="dark" <?= ($layout['theme'] ?? '') === 'dark' ? 'selected' : '' ?>>Dark</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-check">
                            <input type="checkbox" name="is_active" class="form-check-input" id="isActive"
                                   <?= ($layout['is_active'] ?? 0) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="isActive">Active</label>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Widgets</h6>
                        <button type="button" class="btn btn-sm btn-primary" onclick="addWidget()">
                            <i class="fas fa-plus me-1"></i>Add Widget
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="widgetsContainer"></div>
                    </div>
                </div>

                <input type="hidden" name="widgets" id="widgetsInput" value="<?= htmlspecialchars(json_encode($existingWidgets)) ?>">
            </div>

            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">Preview</h6>
                    </div>
                    <div class="card-body">
                        <a href="/areports/wallboard?layout=<?= $layout['id'] ?>" class="btn btn-outline-primary w-100" target="_blank">
                            <i class="fas fa-external-link-alt me-2"></i>Open Wallboard
                        </a>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Update Layout
                    </button>
                    <a href="/areports/admin/wallboard-layouts" class="btn btn-secondary">Cancel</a>
                </div>
            </div>
        </div>
    </form>
</div>

<div class="modal fade" id="widgetModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Widget</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Widget Type</label>
                    <select class="form-select" id="widgetType">
                        <option value="queue_stats">Queue Statistics</option>
                        <option value="agent_status">Agent Status</option>
                        <option value="calls_today">Today's Calls</option>
                        <option value="sla_gauge">SLA Gauge</option>
                        <option value="calls_chart">Calls Chart</option>
                        <option value="active_calls">Active Calls</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Title</label>
                    <input type="text" class="form-control" id="widgetTitle">
                </div>
                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label">Width</label>
                        <select class="form-select" id="widgetWidth">
                            <option value="3">3 cols</option>
                            <option value="4">4 cols</option>
                            <option value="6">6 cols</option>
                            <option value="12">12 cols</option>
                        </select>
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label">Height</label>
                        <select class="form-select" id="widgetHeight">
                            <option value="small">Small</option>
                            <option value="medium">Medium</option>
                            <option value="large">Large</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveWidget()">Add</button>
            </div>
        </div>
    </div>
</div>

<script>
let widgets = <?= json_encode($existingWidgets) ?>;

function addWidget() {
    new bootstrap.Modal(document.getElementById('widgetModal')).show();
}

function saveWidget() {
    widgets.push({
        type: document.getElementById('widgetType').value,
        title: document.getElementById('widgetTitle').value || document.getElementById('widgetType').options[document.getElementById('widgetType').selectedIndex].text,
        width: document.getElementById('widgetWidth').value,
        height: document.getElementById('widgetHeight').value
    });
    updateWidgetsDisplay();
    bootstrap.Modal.getInstance(document.getElementById('widgetModal')).hide();
}

function removeWidget(index) {
    widgets.splice(index, 1);
    updateWidgetsDisplay();
}

function updateWidgetsDisplay() {
    const container = document.getElementById('widgetsContainer');
    if (widgets.length === 0) {
        container.innerHTML = '<div class="text-center text-muted py-4">No widgets added yet.</div>';
    } else {
        let html = '<div class="row">';
        widgets.forEach((w, i) => {
            html += `<div class="col-md-6 mb-3"><div class="card bg-light"><div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div><strong>${w.title}</strong><br><small class="text-muted">${w.type} | ${w.width} cols</small></div>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeWidget(${i})"><i class="fas fa-times"></i></button>
                </div></div></div></div>`;
        });
        html += '</div>';
        container.innerHTML = html;
    }
    document.getElementById('widgetsInput').value = JSON.stringify(widgets);
}

updateWidgetsDisplay();
</script>
