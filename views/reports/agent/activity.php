<?php $this->section('content'); ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">Agent Activity</h1>
        <p class="text-muted mb-0">Login/logout and pause activity</p>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Date From</label>
                <input type="date" class="form-control" name="date_from" value="<?= $this->e($dateFrom) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Date To</label>
                <input type="date" class="form-control" name="date_to" value="<?= $this->e($dateTo) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Agent</label>
                <select class="form-select" name="agent">
                    <option value="">All Agents</option>
                    <?php foreach ($agentList as $agent): ?>
                    <option value="<?= $this->e($agent['agent']) ?>" <?= $agentFilter === $agent['agent'] ? 'selected' : '' ?>>
                        <?= $this->e($agent['display_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-1"></i> Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Activity Log -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Agent</th>
                        <th>Queue</th>
                        <th>Event</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activities as $activity): ?>
                    <tr>
                        <td><?= $this->formatDateTime($activity['time']) ?></td>
                        <td><?= $this->e($activity['agent']) ?></td>
                        <td><?= $this->e($activity['queuename']) ?></td>
                        <td>
                            <?php
                            $eventClass = match($activity['event']) {
                                'ADDMEMBER' => 'success',
                                'REMOVEMEMBER' => 'danger',
                                'PAUSE' => 'warning',
                                'UNPAUSE' => 'info',
                                default => 'secondary'
                            };
                            $eventLabel = match($activity['event']) {
                                'ADDMEMBER' => 'Login',
                                'REMOVEMEMBER' => 'Logout',
                                'PAUSE' => 'Paused',
                                'UNPAUSE' => 'Unpaused',
                                default => $activity['event']
                            };
                            ?>
                            <span class="badge bg-<?= $eventClass ?>"><?= $eventLabel ?></span>
                        </td>
                        <td><?= $this->e($activity['data1'] ?: '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($activities)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">No activity found for the selected period</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php $this->endSection(); ?>
