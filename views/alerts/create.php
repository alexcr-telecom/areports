<?php
/**
 * Create Alert View
 */
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Create Alert</h1>
        <a href="/areports/alerts" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="/areports/alerts">
                        <div class="mb-3">
                            <label class="form-label">Alert Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required maxlength="100"
                                   placeholder="e.g., High Wait Time Alert">
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Metric <span class="text-danger">*</span></label>
                                <select name="metric" class="form-select" required>
                                    <option value="">Select metric...</option>
                                    <optgroup label="Queue Metrics">
                                        <option value="calls_waiting">Calls Waiting</option>
                                        <option value="longest_wait">Longest Wait Time</option>
                                        <option value="abandoned_rate">Abandon Rate %</option>
                                        <option value="sla_percentage">SLA %</option>
                                        <option value="avg_wait_time">Average Wait Time</option>
                                    </optgroup>
                                    <optgroup label="Agent Metrics">
                                        <option value="agents_available">Agents Available</option>
                                        <option value="agents_busy">Agents Busy</option>
                                        <option value="agents_paused">Agents Paused</option>
                                    </optgroup>
                                    <optgroup label="Call Metrics">
                                        <option value="total_calls">Total Calls (hourly)</option>
                                        <option value="answered_calls">Answered Calls</option>
                                        <option value="missed_calls">Missed Calls</option>
                                    </optgroup>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Condition <span class="text-danger">*</span></label>
                                <select name="condition" class="form-select" required>
                                    <option value="gt">Greater than (&gt;)</option>
                                    <option value="gte">Greater than or equal (&gt;=)</option>
                                    <option value="lt">Less than (&lt;)</option>
                                    <option value="lte">Less than or equal (&lt;=)</option>
                                    <option value="eq">Equals (=)</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Threshold <span class="text-danger">*</span></label>
                                <input type="number" name="threshold" class="form-control" required step="0.01"
                                       placeholder="e.g., 60">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Queue Filter</label>
                            <select name="queue_filter" class="form-select">
                                <option value="">All Queues</option>
                            </select>
                            <small class="text-muted">Leave empty to monitor all queues</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Notification Email</label>
                            <input type="email" name="notify_email" class="form-control"
                                   placeholder="alert@example.com">
                            <small class="text-muted">Email address to receive alert notifications</small>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="/areports/alerts" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Create Alert
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>About Alerts</h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted">Alerts monitor your call center metrics in real-time and notify you when thresholds are exceeded.</p>
                    <ul class="small text-muted">
                        <li>Alerts are checked every minute</li>
                        <li>Notifications are sent via email and/or Telegram</li>
                        <li>You can set up escalation rules in settings</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
