<?php $this->section('content'); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">Call Details</h1>
        <p class="text-muted mb-0">Unique ID: <?= $this->e($cdr['uniqueid']) ?></p>
    </div>
    <a href="/areports/reports/cdr" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back to CDR
    </a>
</div>

<div class="row">
    <!-- Main Call Info -->
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Call Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr>
                                <th width="40%">Date/Time</th>
                                <td><?= $this->formatDateTime($cdr['calldate']) ?></td>
                            </tr>
                            <tr>
                                <th>Caller ID</th>
                                <td><?= $this->e($cdr['clid']) ?></td>
                            </tr>
                            <tr>
                                <th>Source</th>
                                <td><code><?= $this->e($cdr['src']) ?></code></td>
                            </tr>
                            <tr>
                                <th>Destination</th>
                                <td><code><?= $this->e($cdr['dst']) ?></code></td>
                            </tr>
                            <tr>
                                <th>DID</th>
                                <td><?= $this->e($cdr['did'] ?: '-') ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr>
                                <th width="40%">Context</th>
                                <td><code><?= $this->e($cdr['dcontext']) ?></code></td>
                            </tr>
                            <tr>
                                <th>Duration</th>
                                <td><?= $this->formatDuration((int)$cdr['duration']) ?></td>
                            </tr>
                            <tr>
                                <th>Billable</th>
                                <td><?= $this->formatDuration((int)$cdr['billsec']) ?></td>
                            </tr>
                            <tr>
                                <th>Disposition</th>
                                <td>
                                    <span class="badge bg-<?= $cdr['disposition'] === 'ANSWERED' ? 'success' : ($cdr['disposition'] === 'NO ANSWER' ? 'warning' : 'danger') ?>">
                                        <?= $this->e($cdr['disposition']) ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>AMA Flags</th>
                                <td><?= $this->e($cdr['amaflags'] ?? '-') ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Channel Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Channel Information</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <th width="20%">Source Channel</th>
                        <td><code><?= $this->e($cdr['channel']) ?></code></td>
                    </tr>
                    <tr>
                        <th>Dest Channel</th>
                        <td><code><?= $this->e($cdr['dstchannel'] ?: '-') ?></code></td>
                    </tr>
                    <tr>
                        <th>Last Application</th>
                        <td><?= $this->e($cdr['lastapp'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <th>Last Data</th>
                        <td><code><?= $this->e($cdr['lastdata'] ?? '-') ?></code></td>
                    </tr>
                    <tr>
                        <th>Unique ID</th>
                        <td><code><?= $this->e($cdr['uniqueid']) ?></code></td>
                    </tr>
                    <tr>
                        <th>Linked ID</th>
                        <td>
                            <?php if ($cdr['linkedid']): ?>
                            <a href="/areports/reports/cdr/call-flow/<?= $this->e($cdr['linkedid']) ?>">
                                <code><?= $this->e($cdr['linkedid']) ?></code>
                            </a>
                            <?php else: ?>
                            -
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Recording -->
        <?php if (!empty($cdr['recordingfile'])): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Recording</h5>
            </div>
            <div class="card-body">
                <p class="mb-2"><strong>File:</strong> <code><?= $this->e($cdr['recordingfile']) ?></code></p>
                <?php if ($this->can('reports.cdr.listen')): ?>
                <audio controls class="w-100" id="recording-player">
                    <source src="/areports/quality/recordings/<?= $this->e($cdr['uniqueid']) ?>/play" type="audio/wav">
                    Your browser does not support the audio element.
                </audio>
                <div class="mt-2">
                    <a href="/areports/quality/recordings/<?= $this->e($cdr['uniqueid']) ?>/download" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-download me-1"></i> Download Recording
                    </a>
                </div>
                <?php else: ?>
                <p class="text-muted">You don't have permission to listen to recordings.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Side Info -->
    <div class="col-md-4">
        <!-- Related Calls -->
        <?php if (!empty($relatedCalls) && count($relatedCalls) > 1): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Related Calls</h5>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($relatedCalls as $related): ?>
                    <a href="/areports/reports/cdr/<?= $this->e($related['uniqueid']) ?>"
                       class="list-group-item list-group-item-action <?= $related['uniqueid'] === $cdr['uniqueid'] ? 'active' : '' ?>">
                        <div class="d-flex justify-content-between">
                            <span><?= $this->e($related['src']) ?> &rarr; <?= $this->e($related['dst']) ?></span>
                            <span class="badge bg-<?= $related['disposition'] === 'ANSWERED' ? 'success' : 'secondary' ?>">
                                <?= $this->e($related['disposition']) ?>
                            </span>
                        </div>
                        <small class="text-muted"><?= $this->formatDateTime($related['calldate']) ?></small>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Actions</h5>
            </div>
            <div class="card-body">
                <?php if ($cdr['linkedid']): ?>
                <a href="/areports/reports/cdr/call-flow/<?= $this->e($cdr['linkedid']) ?>" class="btn btn-outline-info w-100 mb-2">
                    <i class="fas fa-project-diagram me-1"></i> View Call Flow
                </a>
                <?php endif; ?>
                <a href="/areports/reports/cdr?src=<?= urlencode($cdr['src']) ?>" class="btn btn-outline-secondary w-100 mb-2">
                    <i class="fas fa-phone me-1"></i> Other calls from <?= $this->e($cdr['src']) ?>
                </a>
                <a href="/areports/reports/cdr?dst=<?= urlencode($cdr['dst']) ?>" class="btn btn-outline-secondary w-100">
                    <i class="fas fa-phone-alt me-1"></i> Other calls to <?= $this->e($cdr['dst']) ?>
                </a>
            </div>
        </div>
    </div>
</div>

<?php $this->endSection(); ?>
