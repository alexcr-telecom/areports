<?php
/**
 * Calibration Session Detail View
 */
$session = $session ?? [];
$participants = $participants ?? [];
$call = $call ?? null;
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><?= htmlspecialchars($session['name']) ?></h1>
            <p class="text-muted mb-0">Calibration Session</p>
        </div>
        <div class="btn-group">
            <a href="/areports/calibration" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back
            </a>
            <?php if ($session['status'] === 'completed'): ?>
            <a href="/areports/calibration/<?= $session['id'] ?>/results" class="btn btn-success">
                <i class="fas fa-chart-bar me-2"></i>View Results
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Session Details</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-3">Status</dt>
                        <dd class="col-sm-9">
                            <?php
                            $statusColors = [
                                'pending' => 'warning',
                                'in_progress' => 'info',
                                'completed' => 'success',
                                'cancelled' => 'secondary'
                            ];
                            ?>
                            <span class="badge bg-<?= $statusColors[$session['status']] ?? 'secondary' ?>">
                                <?= ucfirst($session['status']) ?>
                            </span>
                        </dd>

                        <dt class="col-sm-3">Evaluation Form</dt>
                        <dd class="col-sm-9"><?= htmlspecialchars($session['form_name'] ?? '-') ?></dd>

                        <dt class="col-sm-3">Call ID</dt>
                        <dd class="col-sm-9"><code><?= htmlspecialchars($session['uniqueid'] ?? '-') ?></code></dd>

                        <?php if ($session['description']): ?>
                        <dt class="col-sm-3">Description</dt>
                        <dd class="col-sm-9"><?= htmlspecialchars($session['description']) ?></dd>
                        <?php endif; ?>

                        <dt class="col-sm-3">Created</dt>
                        <dd class="col-sm-9"><?= date('d/m/Y H:i', strtotime($session['created_at'])) ?></dd>
                    </dl>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Participants</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Participant</th>
                                    <th>Status</th>
                                    <th>Score</th>
                                    <th>Submitted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($participants as $p): ?>
                                <tr>
                                    <td><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></td>
                                    <td>
                                        <?php if ($p['submitted_at']): ?>
                                        <span class="badge bg-success">Completed</span>
                                        <?php else: ?>
                                        <span class="badge bg-warning">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($p['submitted_at'] && isset($p['score'])): ?>
                                        <strong><?= number_format($p['score'], 1) ?>%</strong>
                                        <?php else: ?>
                                        -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= $p['submitted_at'] ? date('d/m/Y H:i', strtotime($p['submitted_at'])) : '-' ?>
                                    </td>
                                    <td>
                                        <?php if (!$p['submitted_at'] && $p['user_id'] == ($this->user['id'] ?? 0)): ?>
                                        <a href="/areports/calibration/<?= $session['id'] ?>/evaluate" class="btn btn-sm btn-primary">
                                            Evaluate
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <?php if ($call): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-phone me-2"></i>Call Information</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-sm-4">Date</dt>
                        <dd class="col-sm-8"><?= date('d/m/Y H:i', strtotime($call['calldate'])) ?></dd>

                        <dt class="col-sm-4">From</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($call['src']) ?></dd>

                        <dt class="col-sm-4">To</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($call['dst']) ?></dd>

                        <dt class="col-sm-4">Duration</dt>
                        <dd class="col-sm-8"><?= gmdate('i:s', $call['duration']) ?></dd>
                    </dl>

                    <?php if (isset($call['recordingfile'])): ?>
                    <hr>
                    <audio controls class="w-100">
                        <source src="/areports/recordings/<?= htmlspecialchars($call['recordingfile']) ?>">
                    </audio>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Progress</h6>
                </div>
                <div class="card-body">
                    <?php
                    $total = count($participants);
                    $completed = count(array_filter($participants, fn($p) => $p['submitted_at']));
                    $percentage = $total > 0 ? round(($completed / $total) * 100) : 0;
                    ?>
                    <div class="text-center mb-3">
                        <h2 class="mb-0"><?= $completed ?>/<?= $total ?></h2>
                        <small class="text-muted">Evaluations Completed</small>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-success" style="width: <?= $percentage ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
