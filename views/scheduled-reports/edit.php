<?php
/**
 * Edit Scheduled Report View
 */
$report = $report ?? [];
$recipients = json_decode($report['recipients'] ?? '[]', true);
$parameters = json_decode($report['parameters'] ?? '{}', true);
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Edit Scheduled Report</h1>
        <a href="/areports/scheduled-reports" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="/areports/scheduled-reports/<?= $report['id'] ?>/update">
                        <div class="mb-3">
                            <label class="form-label">Report Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required maxlength="100"
                                   value="<?= htmlspecialchars($report['name'] ?? '') ?>">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Report Type <span class="text-danger">*</span></label>
                                <select name="report_type" class="form-select" required>
                                    <option value="">Select type...</option>
                                    <option value="cdr" <?= ($report['report_type'] ?? '') === 'cdr' ? 'selected' : '' ?>>CDR Report</option>
                                    <option value="queue" <?= ($report['report_type'] ?? '') === 'queue' ? 'selected' : '' ?>>Queue Report</option>
                                    <option value="agent" <?= ($report['report_type'] ?? '') === 'agent' ? 'selected' : '' ?>>Agent Report</option>
                                    <option value="sla" <?= ($report['report_type'] ?? '') === 'sla' ? 'selected' : '' ?>>SLA Report</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Export Format</label>
                                <select name="export_format" class="form-select">
                                    <option value="pdf" <?= ($report['export_format'] ?? '') === 'pdf' ? 'selected' : '' ?>>PDF</option>
                                    <option value="excel" <?= ($report['export_format'] ?? '') === 'excel' ? 'selected' : '' ?>>Excel</option>
                                    <option value="csv" <?= ($report['export_format'] ?? '') === 'csv' ? 'selected' : '' ?>>CSV</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Schedule Type <span class="text-danger">*</span></label>
                                <select name="schedule_type" class="form-select" required id="scheduleType">
                                    <option value="daily" <?= ($report['schedule_type'] ?? '') === 'daily' ? 'selected' : '' ?>>Daily</option>
                                    <option value="weekly" <?= ($report['schedule_type'] ?? '') === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                                    <option value="monthly" <?= ($report['schedule_type'] ?? '') === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Time</label>
                                <input type="time" name="schedule_time" class="form-control"
                                       value="<?= htmlspecialchars(substr($report['schedule_time'] ?? '08:00:00', 0, 5)) ?>">
                            </div>
                            <div class="col-md-4 mb-3" id="scheduleDayContainer">
                                <label class="form-label">Day</label>
                                <select name="schedule_day" class="form-select" id="scheduleDay">
                                    <!-- Populated by JS -->
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Recipients <span class="text-danger">*</span></label>
                            <input type="email" name="recipients" class="form-control" required
                                   value="<?= htmlspecialchars($recipients[0] ?? '') ?>">
                            <small class="text-muted">Email address to receive the report</small>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" class="form-check-input" id="isActive"
                                       <?= ($report['is_active'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="isActive">Active</label>
                            </div>
                        </div>

                        <hr>

                        <h6 class="mb-3">Report Parameters</h6>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Queue Filter</label>
                                <select name="parameters[queue]" class="form-select">
                                    <option value="">All Queues</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Minimum Duration (seconds)</label>
                                <input type="number" name="parameters[min_duration]" class="form-control" min="0"
                                       value="<?= htmlspecialchars($parameters['min_duration'] ?? 0) ?>">
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="/areports/scheduled-reports" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Report
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-history me-2"></i>Report History</h6>
                </div>
                <div class="card-body">
                    <?php if ($report['last_run']): ?>
                    <p class="small mb-2">
                        <strong>Last Run:</strong><br>
                        <?= date('d/m/Y H:i:s', strtotime($report['last_run'])) ?>
                    </p>
                    <?php endif; ?>
                    <?php if ($report['next_run']): ?>
                    <p class="small mb-0">
                        <strong>Next Run:</strong><br>
                        <?= date('d/m/Y H:i:s', strtotime($report['next_run'])) ?>
                    </p>
                    <?php else: ?>
                    <p class="small text-muted mb-0">No scheduled runs yet</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>About Scheduled Reports</h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted">Scheduled reports are automatically generated and sent to the specified recipients on the configured schedule.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const currentScheduleType = '<?= $report['schedule_type'] ?? 'daily' ?>';
const currentScheduleDay = <?= $report['schedule_day'] ?? 1 ?>;

function updateDaySelect() {
    const dayContainer = document.getElementById('scheduleDayContainer');
    const daySelect = document.getElementById('scheduleDay');
    const scheduleType = document.getElementById('scheduleType').value;

    daySelect.innerHTML = '';

    if (scheduleType === 'weekly') {
        dayContainer.style.display = 'block';
        const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        days.forEach((day, i) => {
            const opt = document.createElement('option');
            opt.value = i + 1;
            opt.textContent = day;
            if (currentScheduleType === 'weekly' && currentScheduleDay === (i + 1)) {
                opt.selected = true;
            }
            daySelect.appendChild(opt);
        });
    } else if (scheduleType === 'monthly') {
        dayContainer.style.display = 'block';
        for (let i = 1; i <= 28; i++) {
            const opt = document.createElement('option');
            opt.value = i;
            opt.textContent = i;
            if (currentScheduleType === 'monthly' && currentScheduleDay === i) {
                opt.selected = true;
            }
            daySelect.appendChild(opt);
        }
    } else {
        dayContainer.style.display = 'none';
    }
}

document.getElementById('scheduleType').addEventListener('change', updateDaySelect);
updateDaySelect();
</script>
