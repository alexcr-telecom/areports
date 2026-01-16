<?php
/**
 * Edit Custom Report View
 */
$report = $report ?? [];
$selectedColumns = json_decode($report['columns'] ?? '[]', true);
$reportFilters = json_decode($report['filters'] ?? '{}', true);
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Edit Report</h1>
        <a href="/areports/report-builder" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back
        </a>
    </div>

    <form method="POST" action="/areports/report-builder/<?= $report['id'] ?>/update">
        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">Report Details</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Report Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required maxlength="200"
                                   value="<?= htmlspecialchars($report['name'] ?? '') ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($report['description'] ?? '') ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Data Source <span class="text-danger">*</span></label>
                            <select name="data_source" class="form-select" required>
                                <option value="cdr" <?= ($report['data_source'] ?? '') === 'cdr' ? 'selected' : '' ?>>CDR (Call Detail Records)</option>
                                <option value="queuelog" <?= ($report['data_source'] ?? '') === 'queuelog' ? 'selected' : '' ?>>Queue Log</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">Columns</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input type="checkbox" name="columns[]" value="calldate" class="form-check-input" id="col_calldate"
                                           <?= in_array('calldate', $selectedColumns) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="col_calldate">Call Date</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" name="columns[]" value="src" class="form-check-input" id="col_src"
                                           <?= in_array('src', $selectedColumns) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="col_src">Source</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" name="columns[]" value="dst" class="form-check-input" id="col_dst"
                                           <?= in_array('dst', $selectedColumns) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="col_dst">Destination</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input type="checkbox" name="columns[]" value="duration" class="form-check-input" id="col_duration"
                                           <?= in_array('duration', $selectedColumns) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="col_duration">Duration</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" name="columns[]" value="billsec" class="form-check-input" id="col_billsec"
                                           <?= in_array('billsec', $selectedColumns) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="col_billsec">Billable Seconds</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" name="columns[]" value="disposition" class="form-check-input" id="col_disposition"
                                           <?= in_array('disposition', $selectedColumns) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="col_disposition">Disposition</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input type="checkbox" name="columns[]" value="channel" class="form-check-input" id="col_channel"
                                           <?= in_array('channel', $selectedColumns) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="col_channel">Channel</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" name="columns[]" value="dcontext" class="form-check-input" id="col_dcontext"
                                           <?= in_array('dcontext', $selectedColumns) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="col_dcontext">Context</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" name="columns[]" value="uniqueid" class="form-check-input" id="col_uniqueid"
                                           <?= in_array('uniqueid', $selectedColumns) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="col_uniqueid">Unique ID</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">Filters</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Disposition</label>
                                <select name="filters[disposition]" class="form-select">
                                    <option value="">All</option>
                                    <option value="ANSWERED" <?= ($reportFilters['disposition'] ?? '') === 'ANSWERED' ? 'selected' : '' ?>>Answered</option>
                                    <option value="NO ANSWER" <?= ($reportFilters['disposition'] ?? '') === 'NO ANSWER' ? 'selected' : '' ?>>No Answer</option>
                                    <option value="BUSY" <?= ($reportFilters['disposition'] ?? '') === 'BUSY' ? 'selected' : '' ?>>Busy</option>
                                    <option value="FAILED" <?= ($reportFilters['disposition'] ?? '') === 'FAILED' ? 'selected' : '' ?>>Failed</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Min Duration (seconds)</label>
                                <input type="number" name="filters[min_duration]" class="form-control" min="0"
                                       value="<?= htmlspecialchars($reportFilters['min_duration'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">Save Options</h6>
                    </div>
                    <div class="card-body">
                        <div class="form-check mb-3">
                            <input type="checkbox" name="is_public" class="form-check-input" id="isPublic"
                                   <?= ($report['is_public'] ?? 0) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="isPublic">Make report public</label>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Report
                            </button>
                            <a href="/areports/report-builder/<?= $report['id'] ?>" class="btn btn-outline-primary">
                                <i class="fas fa-play me-2"></i>Run Report
                            </a>
                            <a href="/areports/report-builder" class="btn btn-secondary">Cancel</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
