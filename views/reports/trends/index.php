<?php $this->section('content'); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">Trend Reports</h1>
        <p class="text-muted mb-0">Analyze call patterns over time</p>
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="display-4 text-primary mb-3">
                    <i class="fas fa-clock"></i>
                </div>
                <h5 class="card-title">Hourly Trends</h5>
                <p class="card-text text-muted">View call volume patterns throughout the day</p>
                <a href="/areports/reports/trends/hourly" class="btn btn-primary">View Report</a>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="display-4 text-success mb-3">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <h5 class="card-title">Daily Trends</h5>
                <p class="card-text text-muted">Track daily call volumes and metrics over time</p>
                <a href="/areports/reports/trends/daily" class="btn btn-success">View Report</a>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="display-4 text-warning mb-3">
                    <i class="fas fa-balance-scale"></i>
                </div>
                <h5 class="card-title">Period Comparison</h5>
                <p class="card-text text-muted">Compare metrics between two time periods</p>
                <a href="/areports/reports/trends/comparison" class="btn btn-warning">View Report</a>
            </div>
        </div>
    </div>
</div>

<?php $this->endSection(); ?>
