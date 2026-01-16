<?php
/**
 * Create API Key View
 */
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Create API Key</h1>
        <a href="/areports/admin/api-keys" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back
        </a>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="/areports/admin/api-keys">
                        <div class="mb-3">
                            <label class="form-label">Key Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required maxlength="100"
                                   placeholder="e.g., Mobile App Key">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2"
                                      placeholder="What is this key used for?"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Permissions</label>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input type="checkbox" name="permissions[]" value="read" class="form-check-input" id="perm_read" checked>
                                        <label class="form-check-label" for="perm_read">Read</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="checkbox" name="permissions[]" value="queues" class="form-check-input" id="perm_queues" checked>
                                        <label class="form-check-label" for="perm_queues">Queue Data</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="checkbox" name="permissions[]" value="agents" class="form-check-input" id="perm_agents" checked>
                                        <label class="form-check-label" for="perm_agents">Agent Data</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input type="checkbox" name="permissions[]" value="cdr" class="form-check-input" id="perm_cdr">
                                        <label class="form-check-label" for="perm_cdr">CDR Data</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="checkbox" name="permissions[]" value="write" class="form-check-input" id="perm_write">
                                        <label class="form-check-label" for="perm_write">Write Actions</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Expires At</label>
                            <input type="datetime-local" name="expires_at" class="form-control">
                            <small class="text-muted">Leave empty for no expiration</small>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="/areports/admin/api-keys" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-key me-2"></i>Generate Key
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>About API Keys</h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted">API keys allow external applications to access the aReports REST API.</p>
                    <ul class="small text-muted">
                        <li>Keys are generated automatically</li>
                        <li>Store keys securely - they cannot be recovered</li>
                        <li>You can create multiple keys for different applications</li>
                        <li>Disable or delete keys that are no longer needed</li>
                    </ul>

                    <h6 class="mt-4">Available Endpoints</h6>
                    <ul class="small text-muted">
                        <li><code>GET /api/v1/queues/status</code> - Real-time queue status</li>
                        <li><code>GET /api/v1/agents/status</code> - Agent status</li>
                        <li><code>GET /api/v1/calls/active</code> - Active calls</li>
                        <li><code>GET /api/v1/cdr</code> - CDR records</li>
                        <li><code>GET /api/v1/stats/today</code> - Today's statistics</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
