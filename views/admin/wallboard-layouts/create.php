<?php
/**
 * Create Wallboard Layout View
 */
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Create Wallboard Layout</h1>
        <a href="/areports/admin/wallboard-layouts" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back
        </a>
    </div>

    <form method="POST" action="/areports/admin/wallboard-layouts" id="layoutForm">
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
                                   placeholder="e.g., Main Dashboard">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2"
                                      placeholder="Optional description"></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Refresh Interval (seconds)</label>
                                <input type="number" name="refresh_interval" class="form-control" value="30" min="5" max="300">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Theme</label>
                                <select name="theme" class="form-select">
                                    <option value="light">Light</option>
                                    <option value="dark">Dark</option>
                                </select>
                            </div>
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
                        <div id="widgetsContainer">
                            <div class="text-center text-muted py-4" id="noWidgetsMessage">
                                No widgets added yet. Click "Add Widget" to start.
                            </div>
                        </div>
                    </div>
                </div>

                <input type="hidden" name="widgets" id="widgetsInput" value="[]">
            </div>

            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">Available Widget Types</h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled small">
                            <li class="mb-2"><strong>queue_stats</strong> - Queue statistics</li>
                            <li class="mb-2"><strong>agent_status</strong> - Agent status panel</li>
                            <li class="mb-2"><strong>calls_today</strong> - Today's call summary</li>
                            <li class="mb-2"><strong>sla_gauge</strong> - SLA percentage gauge</li>
                            <li class="mb-2"><strong>calls_chart</strong> - Calls over time chart</li>
                            <li class="mb-2"><strong>active_calls</strong> - Active calls list</li>
                        </ul>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Create Layout
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
                    <input type="text" class="form-control" id="widgetTitle" placeholder="Widget title">
                </div>
                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label">Width (cols)</label>
                        <select class="form-select" id="widgetWidth">
                            <option value="3">3 (quarter)</option>
                            <option value="4">4 (third)</option>
                            <option value="6" selected>6 (half)</option>
                            <option value="12">12 (full)</option>
                        </select>
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label">Height</label>
                        <select class="form-select" id="widgetHeight">
                            <option value="small">Small</option>
                            <option value="medium" selected>Medium</option>
                            <option value="large">Large</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveWidget()">Add Widget</button>
            </div>
        </div>
    </div>
</div>

<script>
let widgets = [];

function addWidget() {
    document.getElementById('widgetType').value = 'queue_stats';
    document.getElementById('widgetTitle').value = '';
    document.getElementById('widgetWidth').value = '6';
    document.getElementById('widgetHeight').value = 'medium';
    new bootstrap.Modal(document.getElementById('widgetModal')).show();
}

function saveWidget() {
    const widget = {
        type: document.getElementById('widgetType').value,
        title: document.getElementById('widgetTitle').value || document.getElementById('widgetType').options[document.getElementById('widgetType').selectedIndex].text,
        width: document.getElementById('widgetWidth').value,
        height: document.getElementById('widgetHeight').value
    };
    widgets.push(widget);
    updateWidgetsDisplay();
    bootstrap.Modal.getInstance(document.getElementById('widgetModal')).hide();
}

function removeWidget(index) {
    widgets.splice(index, 1);
    updateWidgetsDisplay();
}

function updateWidgetsDisplay() {
    const container = document.getElementById('widgetsContainer');
    const noMsg = document.getElementById('noWidgetsMessage');

    if (widgets.length === 0) {
        container.innerHTML = '<div class="text-center text-muted py-4" id="noWidgetsMessage">No widgets added yet. Click "Add Widget" to start.</div>';
    } else {
        let html = '<div class="row">';
        widgets.forEach((w, i) => {
            html += `
                <div class="col-md-6 mb-3">
                    <div class="card bg-light">
                        <div class="card-body py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>${w.title}</strong>
                                    <br><small class="text-muted">${w.type} | ${w.width} cols | ${w.height}</small>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeWidget(${i})">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        container.innerHTML = html;
    }

    document.getElementById('widgetsInput').value = JSON.stringify(widgets);
}
</script>
