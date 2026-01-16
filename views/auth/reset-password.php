<?php $this->section('content'); ?>

<h4 class="mb-4 text-center">Reset Password</h4>

<form method="POST" action="/areports/reset-password">
    <?= $this->csrf() ?>
    <input type="hidden" name="token" value="<?= $this->e($token) ?>">

    <div class="mb-3">
        <label for="email" class="form-label">Email Address</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
            <input type="email"
                   class="form-control"
                   id="email"
                   name="email"
                   placeholder="Confirm your email"
                   required
                   autofocus>
        </div>
    </div>

    <div class="mb-3">
        <label for="password" class="form-label">New Password</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-lock"></i></span>
            <input type="password"
                   class="form-control"
                   id="password"
                   name="password"
                   placeholder="Enter new password"
                   minlength="8"
                   required>
        </div>
        <div class="form-text">Minimum 8 characters</div>
    </div>

    <div class="mb-3">
        <label for="password_confirmation" class="form-label">Confirm Password</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-lock"></i></span>
            <input type="password"
                   class="form-control"
                   id="password_confirmation"
                   name="password_confirmation"
                   placeholder="Confirm new password"
                   required>
        </div>
    </div>

    <div class="d-grid">
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-key me-2"></i>Reset Password
        </button>
    </div>

    <div class="text-center mt-3">
        <a href="/areports/login" class="text-muted small">
            <i class="fas fa-arrow-left me-1"></i>Back to Login
        </a>
    </div>
</form>

<?php $this->endSection(); ?>
