<?php $this->section('content'); ?>

<!-- Page Header -->
<div class="mb-4">
    <h1 class="h3 mb-0">System Settings</h1>
    <p class="text-muted mb-0">Configure application settings</p>
</div>

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="fas fa-cog fa-3x text-primary mb-3"></i>
                <h5>General Settings</h5>
                <p class="text-muted">Site name, timezone, date formats</p>
                <a href="/areports/admin/settings/general" class="btn btn-primary">
                    Configure
                </a>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="fas fa-envelope fa-3x text-info mb-3"></i>
                <h5>Email Settings</h5>
                <p class="text-muted">SMTP configuration for notifications</p>
                <a href="/areports/admin/settings/email" class="btn btn-info">
                    Configure
                </a>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="fas fa-phone-alt fa-3x text-success mb-3"></i>
                <h5>AMI Settings</h5>
                <p class="text-muted">Asterisk Manager Interface connection</p>
                <a href="/areports/admin/settings/ami" class="btn btn-success">
                    Configure
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="fab fa-telegram fa-3x text-primary mb-3"></i>
                <h5>Telegram Settings</h5>
                <p class="text-muted">Configure Telegram bot notifications</p>
                <a href="/areports/admin/settings/telegram" class="btn btn-primary">
                    Configure
                </a>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="fas fa-key fa-3x text-warning mb-3"></i>
                <h5>API Keys</h5>
                <p class="text-muted">Manage REST API access keys</p>
                <a href="/areports/admin/api-keys" class="btn btn-warning">
                    Manage
                </a>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="fas fa-tv fa-3x text-dark mb-3"></i>
                <h5>Wallboard Layouts</h5>
                <p class="text-muted">Configure wallboard display layouts</p>
                <a href="/areports/admin/wallboard-layouts" class="btn btn-dark">
                    Manage
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="fas fa-headset fa-3x text-warning mb-3"></i>
                <h5>Queue Settings</h5>
                <p class="text-muted">Configure queue display and SLA</p>
                <a href="/areports/admin/queues" class="btn btn-warning">
                    Configure
                </a>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="fas fa-user-tie fa-3x text-secondary mb-3"></i>
                <h5>Agent Settings</h5>
                <p class="text-muted">Configure agent display names</p>
                <a href="/areports/admin/agents" class="btn btn-secondary">
                    Configure
                </a>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="fas fa-clipboard-check fa-3x text-danger mb-3"></i>
                <h5>Evaluation Forms</h5>
                <p class="text-muted">Quality evaluation templates</p>
                <a href="/areports/admin/evaluation-forms" class="btn btn-danger">
                    Configure
                </a>
            </div>
        </div>
    </div>
</div>

<?php $this->endSection(); ?>
