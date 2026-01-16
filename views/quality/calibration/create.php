<?php
/**
 * Create Calibration Session View
 */
$forms = $forms ?? [];
$users = $users ?? [];
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Create Calibration Session</h1>
        <a href="/areports/calibration" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="/areports/calibration">
                        <div class="mb-3">
                            <label class="form-label">Session Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required maxlength="200"
                                   placeholder="e.g., Weekly Calibration - Week 3">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2"
                                      placeholder="Optional description"></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Evaluation Form <span class="text-danger">*</span></label>
                                <select name="form_id" class="form-select" required>
                                    <option value="">Select form...</option>
                                    <?php foreach ($forms as $form): ?>
                                    <option value="<?= $form['id'] ?>"><?= htmlspecialchars($form['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Call UniqueID <span class="text-danger">*</span></label>
                                <input type="text" name="uniqueid" class="form-control" required
                                       placeholder="e.g., 1234567890.12345">
                                <small class="text-muted">The call recording ID to calibrate</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Participants <span class="text-danger">*</span></label>
                            <select name="participants[]" class="form-select" multiple size="5" required>
                                <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>">
                                    <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Hold Ctrl/Cmd to select multiple participants</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Scheduled Date</label>
                                <input type="datetime-local" name="scheduled_at" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Due Date</label>
                                <input type="datetime-local" name="due_at" class="form-control">
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="/areports/calibration" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Create Session
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>About Calibration</h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted">Calibration sessions help ensure consistency in quality evaluations across your team.</p>
                    <ul class="small text-muted">
                        <li>All participants evaluate the same call</li>
                        <li>Results are compared for consistency</li>
                        <li>Helps identify scoring discrepancies</li>
                        <li>Improves evaluation accuracy</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
