<?php $this->section('content'); ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">Evaluate Call</h1>
        <p class="text-muted mb-0">Quality assessment for call <?= $this->e($cdr['uniqueid']) ?></p>
    </div>
    <a href="/areports/quality/recordings" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back
    </a>
</div>

<div class="row">
    <!-- Call Details -->
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">Call Details</div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <td class="text-muted">Date/Time</td>
                        <td><strong><?= $this->formatDateTime($cdr['calldate']) ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Source</td>
                        <td><strong><?= $this->e($cdr['src']) ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Destination</td>
                        <td><strong><?= $this->e($cdr['dst']) ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Duration</td>
                        <td><strong><?= $this->formatDuration($cdr['billsec']) ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Disposition</td>
                        <td>
                            <span class="badge bg-<?= $cdr['disposition'] === 'ANSWERED' ? 'success' : 'danger' ?>">
                                <?= $this->e($cdr['disposition']) ?>
                            </span>
                        </td>
                    </tr>
                </table>

                <?php if ($recordingFile): ?>
                <hr>
                <h6>Recording</h6>
                <audio controls class="w-100">
                    <source src="/areports/quality/recordings/<?= $this->e($cdr['uniqueid']) ?>">
                    Your browser does not support the audio element.
                </audio>
                <?php else: ?>
                <div class="alert alert-warning mb-0">
                    <i class="fas fa-exclamation-triangle me-1"></i> No recording available
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Evaluation Form -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">Evaluation Form</div>
            <div class="card-body">
                <?php if (empty($forms)): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    No evaluation forms available. Please create one in the admin settings.
                </div>
                <?php else: ?>
                <form action="/areports/quality/evaluate" method="POST">
                    <?= $this->csrf() ?>
                    <input type="hidden" name="uniqueid" value="<?= $this->e($cdr['uniqueid']) ?>">

                    <!-- Form Selection -->
                    <div class="mb-4">
                        <label class="form-label">Select Evaluation Form</label>
                        <select class="form-select" name="form_id" id="formSelect" required>
                            <option value="">Choose a form...</option>
                            <?php foreach ($forms as $form): ?>
                            <option value="<?= $form['id'] ?>" data-criteria='<?= json_encode($form['criteria']) ?>'>
                                <?= $this->e($form['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Criteria Section (dynamic) -->
                    <div id="criteriaSection" class="mb-4" style="display: none;">
                        <h5 class="mb-3">Scoring Criteria</h5>
                        <div id="criteriaList">
                            <!-- Criteria will be populated dynamically -->
                        </div>
                    </div>

                    <!-- Comments -->
                    <div class="mb-4">
                        <label class="form-label">Comments</label>
                        <textarea class="form-control" name="comments" rows="4"
                                  placeholder="Optional comments about the call quality..."></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                            <i class="fas fa-save me-1"></i> Save Evaluation
                        </button>
                        <a href="/areports/quality/recordings" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php $this->endSection(); ?>

<?php $this->section('scripts'); ?>
<script>
document.getElementById('formSelect')?.addEventListener('change', function() {
    const criteriaSection = document.getElementById('criteriaSection');
    const criteriaList = document.getElementById('criteriaList');
    const submitBtn = document.getElementById('submitBtn');

    if (!this.value) {
        criteriaSection.style.display = 'none';
        submitBtn.disabled = true;
        return;
    }

    const criteria = JSON.parse(this.selectedOptions[0].dataset.criteria);
    criteriaList.innerHTML = '';

    criteria.forEach((c, index) => {
        const html = `
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h6 class="mb-1">${escapeHtml(c.name)}</h6>
                            <small class="text-muted">${escapeHtml(c.description || '')}</small>
                        </div>
                        <span class="badge bg-secondary">Weight: ${c.weight}%</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <input type="range" class="form-range flex-grow-1" name="scores[${c.id}]"
                               min="0" max="${c.max_score}" value="0"
                               oninput="this.nextElementSibling.textContent = this.value + '/${c.max_score}'">
                        <span class="badge bg-primary" style="min-width: 50px;">0/${c.max_score}</span>
                    </div>
                </div>
            </div>
        `;
        criteriaList.innerHTML += html;
    });

    criteriaSection.style.display = 'block';
    submitBtn.disabled = false;
});

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>
<?php $this->endSection(); ?>
