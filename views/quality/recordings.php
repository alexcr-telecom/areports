<?php $this->section('content'); ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">Call Recordings</h1>
        <p class="text-muted mb-0">Listen to and evaluate recorded calls</p>
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
                <label class="form-label">Search</label>
                <input type="text" class="form-control" name="search" value="<?= $this->e($search ?? '') ?>"
                       placeholder="Phone number, extension...">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-1"></i> Search
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Recordings Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Date/Time</th>
                        <th>Source</th>
                        <th>Destination</th>
                        <th class="text-center">Duration</th>
                        <th class="text-center">Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recordings as $recording): ?>
                    <tr>
                        <td><?= $this->formatDateTime($recording['calldate']) ?></td>
                        <td>
                            <strong><?= $this->e($recording['src']) ?></strong>
                            <?php if (!empty($recording['clid'])): ?>
                            <br><small class="text-muted"><?= $this->e($recording['clid']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= $this->e($recording['dst']) ?></td>
                        <td class="text-center"><?= $this->formatDuration($recording['billsec']) ?></td>
                        <td class="text-center">
                            <?php if ($recording['has_evaluation']): ?>
                            <span class="badge bg-success">Evaluated</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Not Evaluated</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <!-- Audio Player -->
                                <button type="button" class="btn btn-outline-primary play-btn"
                                        data-uniqueid="<?= $this->e($recording['uniqueid']) ?>"
                                        title="Play">
                                    <i class="fas fa-play"></i>
                                </button>

                                <?php if ($this->can('quality.recordings.download')): ?>
                                <a href="/areports/quality/recordings/<?= $this->e($recording['uniqueid']) ?>/download"
                                   class="btn btn-outline-secondary" title="Download">
                                    <i class="fas fa-download"></i>
                                </a>
                                <?php endif; ?>

                                <?php if ($this->can('quality.evaluations.create') && !$recording['has_evaluation']): ?>
                                <a href="/areports/quality/evaluate/<?= $this->e($recording['uniqueid']) ?>"
                                   class="btn btn-outline-success" title="Evaluate">
                                    <i class="fas fa-star"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recordings)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No recordings found</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <?= $this->pagination($page, $totalPages, '/areports/quality/recordings', [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'search' => $search
        ]) ?>
        <?php endif; ?>
    </div>
</div>

<!-- Audio Player Modal -->
<div class="modal fade" id="audioModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Play Recording</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <audio id="audioPlayer" controls class="w-100">
                    Your browser does not support the audio element.
                </audio>
            </div>
        </div>
    </div>
</div>

<?php $this->endSection(); ?>

<?php $this->section('scripts'); ?>
<script>
document.querySelectorAll('.play-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const uniqueid = this.dataset.uniqueid;
        const audio = document.getElementById('audioPlayer');
        audio.src = '/areports/quality/recordings/' + uniqueid + '/play';
        audio.load();

        const modal = new bootstrap.Modal(document.getElementById('audioModal'));
        modal.show();

        audio.play();
    });
});

// Stop audio when modal closes
document.getElementById('audioModal').addEventListener('hidden.bs.modal', function() {
    const audio = document.getElementById('audioPlayer');
    audio.pause();
    audio.src = '';
});
</script>
<?php $this->endSection(); ?>
