<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $this->e($csrfToken) ?>">
    <title>Wallboard - aReports</title>

    <!-- Bootstrap CSS -->
    <link href="/areports/public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="/areports/public/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="/areports/public/css/wallboard.css" rel="stylesheet">
</head>
<body class="wallboard-body">
    <div class="wallboard-container">
        <!-- Header -->
        <header class="wallboard-header">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i>Call Center Wallboard
                </h1>
                <div class="d-flex align-items-center">
                    <span class="wallboard-time me-4" id="current-time"></span>
                    <a href="/areports/dashboard" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="wallboard-main">
            <?php $this->yield('content'); ?>
        </main>
    </div>

    <!-- Scripts -->
    <script src="/areports/public/vendor/jquery/jquery.min.js"></script>
    <script src="/areports/public/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="/areports/public/vendor/chartjs/chart.min.js"></script>
    <script src="/areports/public/js/wallboard.js"></script>

    <?php if ($this->hasSection('scripts')): ?>
    <?php $this->yield('scripts'); ?>
    <?php endif; ?>
</body>
</html>
