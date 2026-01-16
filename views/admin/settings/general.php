<?php $this->section('content'); ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">General Settings</h1>
        <p class="text-muted mb-0">Basic application configuration</p>
    </div>
    <a href="/areports/admin/settings" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form action="/areports/admin/settings/general" method="POST">
            <?= $this->csrf() ?>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Site Name</label>
                    <input type="text" class="form-control" name="site_name"
                           value="<?= $this->e($settings['site_name'] ?? 'aReports') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Timezone</label>
                    <select class="form-select" name="timezone" required>
                        <?php
                        $timezones = DateTimeZone::listIdentifiers();
                        $currentTz = $settings['timezone'] ?? 'UTC';
                        foreach ($timezones as $tz):
                        ?>
                        <option value="<?= $tz ?>" <?= $currentTz === $tz ? 'selected' : '' ?>><?= $tz ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Date Format</label>
                    <select class="form-select" name="date_format" required>
                        <option value="d/m/Y" <?= ($settings['date_format'] ?? '') === 'd/m/Y' ? 'selected' : '' ?>>DD/MM/YYYY (31/12/2024)</option>
                        <option value="m/d/Y" <?= ($settings['date_format'] ?? '') === 'm/d/Y' ? 'selected' : '' ?>>MM/DD/YYYY (12/31/2024)</option>
                        <option value="Y-m-d" <?= ($settings['date_format'] ?? '') === 'Y-m-d' ? 'selected' : '' ?>>YYYY-MM-DD (2024-12-31)</option>
                        <option value="d.m.Y" <?= ($settings['date_format'] ?? '') === 'd.m.Y' ? 'selected' : '' ?>>DD.MM.YYYY (31.12.2024)</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Time Format</label>
                    <select class="form-select" name="time_format" required>
                        <option value="H:i:s" <?= ($settings['time_format'] ?? '') === 'H:i:s' ? 'selected' : '' ?>>24-hour (14:30:00)</option>
                        <option value="h:i:s A" <?= ($settings['time_format'] ?? '') === 'h:i:s A' ? 'selected' : '' ?>>12-hour (02:30:00 PM)</option>
                        <option value="H:i" <?= ($settings['time_format'] ?? '') === 'H:i' ? 'selected' : '' ?>>24-hour short (14:30)</option>
                        <option value="h:i A" <?= ($settings['time_format'] ?? '') === 'h:i A' ? 'selected' : '' ?>>12-hour short (02:30 PM)</option>
                    </select>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Default Page Size</label>
                    <select class="form-select" name="default_page_size" required>
                        <option value="10" <?= ($settings['default_page_size'] ?? '') == '10' ? 'selected' : '' ?>>10 items</option>
                        <option value="25" <?= ($settings['default_page_size'] ?? '') == '25' ? 'selected' : '' ?>>25 items</option>
                        <option value="50" <?= ($settings['default_page_size'] ?? '') == '50' ? 'selected' : '' ?>>50 items</option>
                        <option value="100" <?= ($settings['default_page_size'] ?? '') == '100' ? 'selected' : '' ?>>100 items</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Session Timeout (minutes)</label>
                    <input type="number" class="form-control" name="session_timeout"
                           value="<?= $this->e($settings['session_timeout'] ?? '60') ?>"
                           min="5" max="480" required>
                    <div class="form-text">User will be logged out after this period of inactivity</div>
                </div>
            </div>

            <hr>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-1"></i> Save Settings
            </button>
        </form>
    </div>
</div>

<?php $this->endSection(); ?>
