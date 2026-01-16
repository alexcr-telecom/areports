<?php
/**
 * Create Scheduled Report View
 */
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Create Scheduled Report</h1>
        <a href="/areports/scheduled-reports" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="/areports/scheduled-reports">
                        <div class="mb-3">
                            <label class="form-label">Report Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required maxlength="100"
                                   placeholder="e.g., Daily CDR Summary">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Report Type <span class="text-danger">*</span></label>
                                <select name="report_type" class="form-select" required>
                                    <option value="">Select type...</option>
                                    <option value="cdr">CDR Report</option>
                                    <option value="queue">Queue Report</option>
                                    <option value="agent">Agent Report</option>
                                    <option value="sla">SLA Report</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Export Format</label>
                                <select name="export_format" class="form-select">
                                    <option value="pdf">PDF</option>
                                    <option value="excel">Excel</option>
                                    <option value="csv">CSV</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Schedule Type <span class="text-danger">*</span></label>
                                <select name="schedule_type" class="form-select" required id="scheduleType">
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Time</label>
                                <input type="time" name="schedule_time" class="form-control" value="08:00">
                            </div>
                            <div class="col-md-4 mb-3" id="scheduleDayContainer" style="display: none;">
                                <label class="form-label">Day</label>
                                <select name="schedule_day" class="form-select" id="scheduleDay">
                                    <!-- Populated by JS -->
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Recipients <span class="text-danger">*</span></label>
                            <input type="email" name="recipients" class="form-control" required
                                   placeholder="email@example.com">
                            <small class="text-muted">Email address to receive the report</small>
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
                                <input type="number" name="parameters[min_duration]" class="form-control" min="0" value="0">
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="/areports/scheduled-reports" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Create Report
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>About Scheduled Reports</h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted">Scheduled reports are automatically generated and sent to the specified recipients on the configured schedule.</p>
                    <ul class="small text-muted">
                        <li><strong>Daily:</strong> Report for the previous day, sent at the specified time</li>
                        <li><strong>Weekly:</strong> Report for the previous week, sent on the selected day</li>
                        <li><strong>Monthly:</strong> Report for the previous month, sent on the selected day</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('scheduleType').addEventListener('change', function() {
    const dayContainer = document.getElementById('scheduleDayContainer');
    const daySelect = document.getElementById('scheduleDay');

    daySelect.innerHTML = '';

    if (this.value === 'weekly') {
        dayContainer.style.display = 'block';
        const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        days.forEach((day, i) => {
            const opt = document.createElement('option');
            opt.value = i + 1;
            opt.textContent = day;
            daySelect.appendChild(opt);
        });
    } else if (this.value === 'monthly') {
        dayContainer.style.display = 'block';
        for (let i = 1; i <= 28; i++) {
            const opt = document.createElement('option');
            opt.value = i;
            opt.textContent = i;
            daySelect.appendChild(opt);
        }
    } else {
        dayContainer.style.display = 'none';
    }
});
</script>
