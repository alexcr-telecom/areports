<?php
/**
 * Edit Alert View
 */
$alert = $alert ?? [];
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Edit Alert</h1>
        <a href="/areports/alerts" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="/areports/alerts/<?= $alert['id'] ?>/update">
                        <div class="mb-3">
                            <label class="form-label">Alert Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required maxlength="100"
                                   value="<?= htmlspecialchars($alert['name'] ?? '') ?>">
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Metric <span class="text-danger">*</span></label>
                                <select name="metric" class="form-select" required>
                                    <option value="">Select metric...</option>
                                    <optgroup label="Queue Metrics">
                                        <option value="calls_waiting" <?= ($alert['metric'] ?? '') === 'calls_waiting' ? 'selected' : '' ?>>Calls Waiting</option>
                                        <option value="longest_wait" <?= ($alert['metric'] ?? '') === 'longest_wait' ? 'selected' : '' ?>>Longest Wait Time</option>
                                        <option value="abandoned_rate" <?= ($alert['metric'] ?? '') === 'abandoned_rate' ? 'selected' : '' ?>>Abandon Rate %</option>
                                        <option value="sla_percentage" <?= ($alert['metric'] ?? '') === 'sla_percentage' ? 'selected' : '' ?>>SLA %</option>
                                        <option value="avg_wait_time" <?= ($alert['metric'] ?? '') === 'avg_wait_time' ? 'selected' : '' ?>>Average Wait Time</option>
                                    </optgroup>
                                    <optgroup label="Agent Metrics">
                                        <option value="agents_available" <?= ($alert['metric'] ?? '') === 'agents_available' ? 'selected' : '' ?>>Agents Available</option>
                                        <option value="agents_busy" <?= ($alert['metric'] ?? '') === 'agents_busy' ? 'selected' : '' ?>>Agents Busy</option>
                                        <option value="agents_paused" <?= ($alert['metric'] ?? '') === 'agents_paused' ? 'selected' : '' ?>>Agents Paused</option>
                                    </optgroup>
                                    <optgroup label="Call Metrics">
                                        <option value="total_calls" <?= ($alert['metric'] ?? '') === 'total_calls' ? 'selected' : '' ?>>Total Calls (hourly)</option>
                                        <option value="answered_calls" <?= ($alert['metric'] ?? '') === 'answered_calls' ? 'selected' : '' ?>>Answered Calls</option>
                                        <option value="missed_calls" <?= ($alert['metric'] ?? '') === 'missed_calls' ? 'selected' : '' ?>>Missed Calls</option>
                                    </optgroup>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Condition <span class="text-danger">*</span></label>
                                <select name="condition" class="form-select" required>
                                    <option value="gt" <?= ($alert['condition'] ?? '') === 'gt' ? 'selected' : '' ?>>Greater than (&gt;)</option>
                                    <option value="gte" <?= ($alert['condition'] ?? '') === 'gte' ? 'selected' : '' ?>>Greater than or equal (&gt;=)</option>
                                    <option value="lt" <?= ($alert['condition'] ?? '') === 'lt' ? 'selected' : '' ?>>Less than (&lt;)</option>
                                    <option value="lte" <?= ($alert['condition'] ?? '') === 'lte' ? 'selected' : '' ?>>Less than or equal (&lt;=)</option>
                                    <option value="eq" <?= ($alert['condition'] ?? '') === 'eq' ? 'selected' : '' ?>>Equals (=)</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Threshold <span class="text-danger">*</span></label>
                                <input type="number" name="threshold" class="form-control" required step="0.01"
                                       value="<?= htmlspecialchars($alert['threshold'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Queue Filter</label>
                            <select name="queue_filter" class="form-select">
                                <option value="">All Queues</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Notification Email</label>
                            <input type="email" name="notify_email" class="form-control"
                                   value="<?= htmlspecialchars($alert['notify_email'] ?? '') ?>">
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="/areports/alerts" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Alert
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
