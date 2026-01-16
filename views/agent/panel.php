<?php $this->section('content'); ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">Agent Panel</h1>
        <p class="text-muted mb-0">Extension: <strong><?= $this->e($extension) ?></strong></p>
    </div>
    <div class="btn-group">
        <button type="button" class="btn btn-success" id="btn-login-all" title="Login to all queues">
            <i class="fas fa-sign-in-alt me-1"></i> Login All
        </button>
        <button type="button" class="btn btn-danger" id="btn-logout-all" title="Logout from all queues">
            <i class="fas fa-sign-out-alt me-1"></i> Logout All
        </button>
        <button type="button" class="btn btn-warning" id="btn-pause-all" data-bs-toggle="modal" data-bs-target="#pauseModal" data-queue="" title="Pause in all queues">
            <i class="fas fa-pause me-1"></i> Pause All
        </button>
        <button type="button" class="btn btn-info" id="btn-unpause-all" title="Unpause in all queues">
            <i class="fas fa-play me-1"></i> Unpause All
        </button>
    </div>
</div>

<div class="row">
    <!-- Today's Stats -->
    <div class="col-lg-8">
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h3 class="mb-0"><?= number_format($todayStats['total_calls'] ?? 0) ?></h3>
                        <small>Total Calls Today</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h3 class="mb-0"><?= number_format($todayStats['answered'] ?? 0) ?></h3>
                        <small>Answered</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body text-center">
                        <h3 class="mb-0"><?= number_format($todayStats['missed'] ?? 0) ?></h3>
                        <small>Missed</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h3 class="mb-0"><?= $this->formatDuration($todayStats['talk_time'] ?? 0) ?></h3>
                        <small>Talk Time</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Calls -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-phone-alt me-2"></i>Recent Calls</span>
                <a href="/areports/reports/cdr?extension=<?= urlencode($extension) ?>" class="btn btn-sm btn-outline-primary">
                    View All
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Time</th>
                                <th>Direction</th>
                                <th>Number</th>
                                <th>Duration</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentCalls)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    No calls today
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach (array_slice($recentCalls, 0, 15) as $call): ?>
                            <?php
                                $isInbound = $call['dst'] === $extension || $call['cnum'] === $extension;
                                $direction = $isInbound ? 'inbound' : 'outbound';
                                $otherParty = $isInbound ? ($call['src'] ?: $call['cnum']) : $call['dst'];
                            ?>
                            <tr>
                                <td><?= date('H:i:s', strtotime($call['calldate'])) ?></td>
                                <td>
                                    <?php if ($direction === 'inbound'): ?>
                                    <span class="badge bg-success"><i class="fas fa-arrow-down"></i> In</span>
                                    <?php else: ?>
                                    <span class="badge bg-primary"><i class="fas fa-arrow-up"></i> Out</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $this->e($otherParty) ?></td>
                                <td><?= $this->formatDuration($call['duration']) ?></td>
                                <td>
                                    <?php
                                    $statusClass = match($call['disposition']) {
                                        'ANSWERED' => 'success',
                                        'NO ANSWER' => 'warning',
                                        'BUSY' => 'info',
                                        default => 'danger'
                                    };
                                    ?>
                                    <span class="badge bg-<?= $statusClass ?>"><?= $call['disposition'] ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Queue Status Panel -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-headset me-2"></i>Queue Status</span>
                <button class="btn btn-sm btn-outline-secondary" id="btn-refresh-status" title="Refresh">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
            <div class="card-body p-0">
                <div id="queue-status-container">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 mb-0 text-muted">Loading queue status...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card mt-3">
            <div class="card-header">
                <i class="fas fa-bolt me-2"></i>Quick Actions
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="/areports/reports/agent/my" class="btn btn-outline-primary">
                        <i class="fas fa-chart-bar me-2"></i>My Performance Report
                    </a>
                    <a href="/areports/quality/recordings?extension=<?= urlencode($extension) ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-microphone me-2"></i>My Recordings
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Pause Reason Modal -->
<div class="modal fade" id="pauseModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Select Pause Reason</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="pause-queue" value="">
                <div class="list-group">
                    <?php foreach ($pauseCauses as $cause): ?>
                    <button type="button" class="list-group-item list-group-item-action pause-reason-btn"
                            data-reason="<?= $this->e($cause['code']) ?>">
                        <strong><?= $this->e($cause['name']) ?></strong>
                        <?php if ($cause['description']): ?>
                        <br><small class="text-muted"><?= $this->e($cause['description']) ?></small>
                        <?php endif; ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $this->endSection(); ?>

<?php $this->section('scripts'); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const statusContainer = document.getElementById('queue-status-container');
    const pauseModal = new bootstrap.Modal(document.getElementById('pauseModal'));
    let refreshInterval;

    // Load queue status
    function loadQueueStatus() {
        fetch('/areports/agent/status')
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    statusContainer.innerHTML = `<div class="alert alert-danger m-3">${data.error}</div>`;
                    return;
                }
                renderQueueStatus(data.queues);
            })
            .catch(error => {
                statusContainer.innerHTML = `<div class="alert alert-danger m-3">Failed to load status</div>`;
            });
    }

    // Render queue status
    function renderQueueStatus(queues) {
        if (!queues || queues.length === 0) {
            statusContainer.innerHTML = '<div class="text-center py-4 text-muted">No queues configured</div>';
            return;
        }

        let html = '<ul class="list-group list-group-flush">';
        queues.forEach(queue => {
            let statusBadge = '';
            let statusIcon = '';
            let btnClass = '';

            if (!queue.in_queue) {
                statusBadge = '<span class="badge bg-secondary">Logged Out</span>';
                statusIcon = 'fa-sign-out-alt text-secondary';
            } else if (queue.paused) {
                statusBadge = `<span class="badge bg-warning">Paused</span>`;
                if (queue.paused_reason) {
                    statusBadge += ` <small class="text-muted">(${queue.paused_reason})</small>`;
                }
                statusIcon = 'fa-pause text-warning';
            } else if (queue.status === 'available') {
                statusBadge = '<span class="badge bg-success">Available</span>';
                statusIcon = 'fa-check-circle text-success';
            } else if (queue.status === 'busy' || queue.status === 'ringing' || queue.status === 'ringinuse') {
                statusBadge = '<span class="badge bg-danger">Busy</span>';
                statusIcon = 'fa-phone text-danger';
            } else {
                statusBadge = '<span class="badge bg-info">Ready</span>';
                statusIcon = 'fa-headset text-info';
            }

            html += `
                <li class="list-group-item">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <i class="fas ${statusIcon} me-2"></i>
                            <strong>${queue.queue}</strong>
                            ${queue.calls_waiting > 0 ? `<span class="badge bg-danger ms-2">${queue.calls_waiting} waiting</span>` : ''}
                            <br>
                            <small>${statusBadge}</small>
                        </div>
                        <div class="btn-group btn-group-sm">
                            ${!queue.in_queue ? `
                                <button class="btn btn-outline-success queue-login-btn" data-queue="${queue.queue}" title="Login">
                                    <i class="fas fa-sign-in-alt"></i>
                                </button>
                            ` : `
                                <button class="btn btn-outline-danger queue-logout-btn" data-queue="${queue.queue}" title="Logout">
                                    <i class="fas fa-sign-out-alt"></i>
                                </button>
                                ${queue.paused ? `
                                    <button class="btn btn-outline-info queue-unpause-btn" data-queue="${queue.queue}" title="Unpause">
                                        <i class="fas fa-play"></i>
                                    </button>
                                ` : `
                                    <button class="btn btn-outline-warning queue-pause-btn" data-queue="${queue.queue}" title="Pause">
                                        <i class="fas fa-pause"></i>
                                    </button>
                                `}
                            `}
                        </div>
                    </div>
                </li>
            `;
        });
        html += '</ul>';
        statusContainer.innerHTML = html;

        // Attach event handlers
        attachQueueButtonHandlers();
    }

    // Attach handlers to queue buttons
    function attachQueueButtonHandlers() {
        document.querySelectorAll('.queue-login-btn').forEach(btn => {
            btn.addEventListener('click', () => queueAction('login', btn.dataset.queue));
        });
        document.querySelectorAll('.queue-logout-btn').forEach(btn => {
            btn.addEventListener('click', () => queueAction('logout', btn.dataset.queue));
        });
        document.querySelectorAll('.queue-pause-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.getElementById('pause-queue').value = btn.dataset.queue;
                pauseModal.show();
            });
        });
        document.querySelectorAll('.queue-unpause-btn').forEach(btn => {
            btn.addEventListener('click', () => queueAction('unpause', btn.dataset.queue));
        });
    }

    // Queue action
    function queueAction(action, queue, reason = '') {
        const formData = new FormData();
        formData.append('queue', queue);
        if (reason) formData.append('reason', reason);

        fetch(`/areports/agent/queue-${action}`, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                toastr.error(data.error);
            } else {
                toastr.success(data.message);
                loadQueueStatus();
            }
        })
        .catch(() => toastr.error('Action failed'));
    }

    // Pause reason selection
    document.querySelectorAll('.pause-reason-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const queue = document.getElementById('pause-queue').value;
            const reason = btn.dataset.reason;
            pauseModal.hide();
            queueAction('pause', queue, reason);
        });
    });

    // Login all
    document.getElementById('btn-login-all').addEventListener('click', function() {
        this.disabled = true;
        fetch('/areports/agent/login-all', { method: 'POST' })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    toastr.error(data.error);
                } else {
                    toastr.success(data.message);
                    loadQueueStatus();
                }
            })
            .finally(() => this.disabled = false);
    });

    // Logout all
    document.getElementById('btn-logout-all').addEventListener('click', function() {
        if (!confirm('Are you sure you want to logout from all queues?')) return;
        this.disabled = true;
        fetch('/areports/agent/logout-all', { method: 'POST' })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    toastr.error(data.error);
                } else {
                    toastr.success(data.message);
                    loadQueueStatus();
                }
            })
            .finally(() => this.disabled = false);
    });

    // Pause all
    document.getElementById('btn-pause-all').addEventListener('click', function() {
        document.getElementById('pause-queue').value = '';
    });

    // Unpause all
    document.getElementById('btn-unpause-all').addEventListener('click', function() {
        this.disabled = true;
        queueAction('unpause', '');
        this.disabled = false;
    });

    // Refresh button
    document.getElementById('btn-refresh-status').addEventListener('click', loadQueueStatus);

    // Initial load and auto-refresh
    loadQueueStatus();
    refreshInterval = setInterval(loadQueueStatus, 10000); // Refresh every 10 seconds
});
</script>
<?php $this->endSection(); ?>
