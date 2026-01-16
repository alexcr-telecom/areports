<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $this->e($csrfToken) ?>">
    <title><?= $this->e($title ?? 'Login') ?> - aReports</title>

    <!-- Bootstrap CSS -->
    <link href="/areports/public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="/areports/public/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="/areports/public/css/auth.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-5 col-lg-4">
                <!-- Logo -->
                <div class="text-center mb-4">
                    <h1 class="h2 text-primary">
                        <i class="fas fa-chart-line me-2"></i>aReports
                    </h1>
                    <p class="text-muted">Asterisk Reports & Analytics</p>
                </div>

                <!-- Flash Messages -->
                <?php if ($session->hasFlash('success')): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= $this->e($session->getFlash('success')) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if ($session->hasFlash('error')): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= $this->e($session->getFlash('error')) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Content -->
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <?php $this->yield('content'); ?>
                    </div>
                </div>

                <!-- Footer -->
                <div class="text-center mt-4 text-muted small">
                    <p>&copy; <?= date('Y') ?> aReports. All rights reserved.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="/areports/public/vendor/jquery/jquery.min.js"></script>
    <script src="/areports/public/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
