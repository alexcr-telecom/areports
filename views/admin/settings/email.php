<?php $this->section('content'); ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">Email Settings</h1>
        <p class="text-muted mb-0">SMTP configuration for notifications</p>
    </div>
    <a href="/areports/admin/settings" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back
    </a>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form action="/areports/admin/settings/email" method="POST">
                    <?= $this->csrf() ?>

                    <h5 class="mb-3">SMTP Server</h5>

                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label class="form-label">SMTP Host</label>
                            <input type="text" class="form-control" name="smtp_host"
                                   value="<?= $this->e($settings['smtp_host'] ?? 'localhost') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">SMTP Port</label>
                            <input type="number" class="form-control" name="smtp_port"
                                   value="<?= $this->e($settings['smtp_port'] ?? '25') ?>" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">SMTP Username</label>
                            <input type="text" class="form-control" name="smtp_username"
                                   value="<?= $this->e($settings['smtp_username'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">SMTP Password</label>
                            <input type="password" class="form-control" name="smtp_password"
                                   placeholder="<?= !empty($settings['smtp_password']) ? '••••••••' : '' ?>">
                            <div class="form-text">Leave blank to keep current password</div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Encryption</label>
                        <select class="form-select" name="smtp_encryption">
                            <option value="none" <?= ($settings['smtp_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>None</option>
                            <option value="tls" <?= ($settings['smtp_encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS</option>
                            <option value="ssl" <?= ($settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                        </select>
                    </div>

                    <hr>
                    <h5 class="mb-3">Sender Information</h5>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">From Address</label>
                            <input type="email" class="form-control" name="mail_from_address"
                                   value="<?= $this->e($settings['mail_from_address'] ?? 'noreply@example.com') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">From Name</label>
                            <input type="text" class="form-control" name="mail_from_name"
                                   value="<?= $this->e($settings['mail_from_name'] ?? 'aReports') ?>" required>
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
            <div class="card-header">Test Email</div>
            <div class="card-body">
                <p class="text-muted">Send a test email to verify configuration</p>
                <div class="mb-3">
                    <label class="form-label">Test Address</label>
                    <input type="email" class="form-control" id="testEmail" placeholder="test@example.com">
                </div>
                <button type="button" class="btn btn-info w-100" id="testBtn">
                    <i class="fas fa-paper-plane me-1"></i> Send Test
                </button>
                <div id="testResult" class="mt-3"></div>
            </div>
        </div>
    </div>
</div>

<?php $this->endSection(); ?>

<?php $this->section('scripts'); ?>
<script>
document.getElementById('testBtn').addEventListener('click', async function() {
    const email = document.getElementById('testEmail').value;
    const resultDiv = document.getElementById('testResult');

    if (!email) {
        resultDiv.innerHTML = '<div class="alert alert-warning">Please enter an email address</div>';
        return;
    }

    this.disabled = true;
    this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Sending...';

    try {
        const formData = new FormData();
        formData.append('test_address', email);
        formData.append('_csrf_token', document.querySelector('[name="_csrf_token"]')?.value || '<?= $this->e($csrfToken) ?>');

        const response = await fetch('/areports/admin/settings/email/test', {
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
        resultDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-times me-1"></i> Failed to send test</div>';
    }

    this.disabled = false;
    this.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Send Test';
});
</script>
<?php $this->endSection(); ?>
