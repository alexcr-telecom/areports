<?php $this->section('content'); ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">AMI Settings</h1>
        <p class="text-muted mb-0">Asterisk Manager Interface configuration</p>
    </div>
    <a href="/areports/admin/settings" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back
    </a>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form action="/areports/admin/settings/ami" method="POST">
                    <?= $this->csrf() ?>

                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label class="form-label">AMI Host</label>
                            <input type="text" class="form-control" name="ami_host"
                                   value="<?= $this->e($settings['ami_host'] ?? '127.0.0.1') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">AMI Port</label>
                            <input type="number" class="form-control" name="ami_port"
                                   value="<?= $this->e($settings['ami_port'] ?? '5038') ?>" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="ami_username"
                                   value="<?= $this->e($settings['ami_username'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Secret</label>
                            <input type="password" class="form-control" name="ami_secret"
                                   placeholder="<?= !empty($settings['ami_secret']) ? '••••••••' : '' ?>">
                            <div class="form-text">Leave blank to keep current secret</div>
                        </div>
                    </div>

                    <hr>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Save Settings
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">Connection Test</div>
            <div class="card-body">
                <p class="text-muted">Test the AMI connection with current settings</p>
                <button type="button" class="btn btn-success w-100" id="testBtn">
                    <i class="fas fa-plug me-1"></i> Test Connection
                </button>
                <div id="testResult" class="mt-3"></div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">Help</div>
            <div class="card-body">
                <p class="small text-muted mb-2">
                    The AMI credentials are typically found in:
                </p>
                <code class="small">/etc/asterisk/manager.conf</code>
                <p class="small text-muted mt-2 mb-0">
                    For FreePBX systems, these settings are managed through the web interface.
                </p>
            </div>
        </div>
    </div>
</div>

<?php $this->endSection(); ?>

<?php $this->section('scripts'); ?>
<script>
document.getElementById('testBtn').addEventListener('click', async function() {
    const resultDiv = document.getElementById('testResult');

    this.disabled = true;
    this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Testing...';

    try {
        const formData = new FormData();
        formData.append('_csrf_token', document.querySelector('[name="_csrf_token"]')?.value || '<?= $this->e($csrfToken) ?>');

        const response = await fetch('/areports/admin/settings/ami/test', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            resultDiv.innerHTML = '<div class="alert alert-success"><i class="fas fa-check me-1"></i> ' + data.message + '</div>';
        } else {
            resultDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-times me-1"></i> ' + data.message + '</div>';
        }
    } catch (error) {
        resultDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-times me-1"></i> Connection test failed</div>';
    }

    this.disabled = false;
    this.innerHTML = '<i class="fas fa-plug me-1"></i> Test Connection';
});
</script>
<?php $this->endSection(); ?>
