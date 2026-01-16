<?php
/**
 * API Keys List View
 */
$apiKeys = $apiKeys ?? [];
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">API Keys</h1>
        <a href="/areports/admin/api-keys/create" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Create API Key
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (empty($apiKeys)): ?>
            <div class="text-center py-5">
                <i class="fas fa-key fa-3x text-muted mb-3"></i>
                <p class="text-muted">No API keys created yet</p>
                <a href="/areports/admin/api-keys/create" class="btn btn-primary">
                    Create Your First API Key
                </a>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Key</th>
                            <th>Created By</th>
                            <th>Last Used</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($apiKeys as $key): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($key['name']) ?></strong>
                                <?php if ($key['description']): ?>
                                <br><small class="text-muted"><?= htmlspecialchars($key['description']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <code><?= htmlspecialchars(substr($key['api_key'], 0, 12)) ?>...</code>
                                <button type="button" class="btn btn-sm btn-link p-0 ms-2" onclick="copyKey('<?= htmlspecialchars($key['api_key']) ?>')" title="Copy">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </td>
                            <td><?= htmlspecialchars(($key['first_name'] ?? '') . ' ' . ($key['last_name'] ?? '')) ?></td>
                            <td>
                                <?= $key['last_used'] ? date('d/m/Y H:i', strtotime($key['last_used'])) : 'Never' ?>
                            </td>
                            <td>
                                <?php if ($key['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <form method="POST" action="/areports/admin/api-keys/<?= $key['id'] ?>/toggle" style="display:inline">
                                        <button type="submit" class="btn btn-outline-<?= $key['is_active'] ? 'warning' : 'success' ?>" title="<?= $key['is_active'] ? 'Disable' : 'Enable' ?>">
                                            <i class="fas fa-<?= $key['is_active'] ? 'pause' : 'play' ?>"></i>
                                        </button>
                                    </form>
                                    <button type="button" class="btn btn-outline-danger" title="Delete"
                                            onclick="deleteKey(<?= $key['id'] ?>, '<?= htmlspecialchars($key['name'], ENT_QUOTES) ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>API Usage</h6>
        </div>
        <div class="card-body">
            <p class="small text-muted">Use API keys to authenticate requests to the REST API. Include the key in the <code>X-API-Key</code> header:</p>
            <pre class="bg-light p-3 rounded"><code>curl -H "X-API-Key: your-api-key" https://your-server/areports/api/v1/queues/status</code></pre>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete API Key</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the API key "<strong id="deleteKeyName"></strong>"?</p>
                <p class="text-danger small">Any applications using this key will stop working.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST" style="display: inline;">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function copyKey(key) {
    navigator.clipboard.writeText(key).then(() => {
        alert('API key copied to clipboard!');
    });
}

function deleteKey(id, name) {
    document.getElementById('deleteKeyName').textContent = name;
    document.getElementById('deleteForm').action = '/areports/admin/api-keys/' + id + '/delete';
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>
