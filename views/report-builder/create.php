<?php
/**
 * Create Custom Report View
 */
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Create Custom Report</h1>
        <a href="/areports/report-builder" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back
        </a>
    </div>

    <form method="POST" action="/areports/report-builder" id="reportForm">
        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">Report Details</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Report Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required maxlength="200">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Data Source <span class="text-danger">*</span></label>
                            <select name="data_source" class="form-select" required id="dataSource">
                                <option value="cdr">CDR (Call Detail Records)</option>
                                <option value="queuelog">Queue Log</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">Columns</h6>
                    </div>
                    <div class="card-body">
                        <div class="row" id="cdrColumns">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input type="checkbox" name="columns[]" value="calldate" class="form-check-input" id="col_calldate" checked>
                                    <label class="form-check-label" for="col_calldate">Call Date</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" name="columns[]" value="src" class="form-check-input" id="col_src" checked>
                                    <label class="form-check-label" for="col_src">Source</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" name="columns[]" value="dst" class="form-check-input" id="col_dst" checked>
                                    <label class="form-check-label" for="col_dst">Destination</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input type="checkbox" name="columns[]" value="duration" class="form-check-input" id="col_duration" checked>
                                    <label class="form-check-label" for="col_duration">Duration</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" name="columns[]" value="billsec" class="form-check-input" id="col_billsec">
                                    <label class="form-check-label" for="col_billsec">Billable Seconds</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" name="columns[]" value="disposition" class="form-check-input" id="col_disposition" checked>
                                    <label class="form-check-label" for="col_disposition">Disposition</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input type="checkbox" name="columns[]" value="channel" class="form-check-input" id="col_channel">
                                    <label class="form-check-label" for="col_channel">Channel</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" name="columns[]" value="dcontext" class="form-check-input" id="col_dcontext">
                                    <label class="form-check-label" for="col_dcontext">Context</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" name="columns[]" value="uniqueid" class="form-check-input" id="col_uniqueid">
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
                                    <option value="ANSWERED">Answered</option>
                                    <option value="NO ANSWER">No Answer</option>
                                    <option value="BUSY">Busy</option>
                                    <option value="FAILED">Failed</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Min Duration (seconds)</label>
                                <input type="number" name="filters[min_duration]" class="form-control" min="0">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Source Contains</label>
                                <input type="text" name="filters[src_contains]" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Destination Contains</label>
                                <input type="text" name="filters[dst_contains]" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Grouping & Sorting</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Group By</label>
                                <select name="group_by" class="form-select">
                                    <option value="">No Grouping</option>
                                    <option value="date">Date</option>
                                    <option value="hour">Hour</option>
                                    <option value="src">Source</option>
                                    <option value="dst">Destination</option>
                                    <option value="disposition">Disposition</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Sort By</label>
                                <select name="sort_by" class="form-select">
                                    <option value="calldate">Call Date</option>
                                    <option value="duration">Duration</option>
                                    <option value="src">Source</option>
                                </select>
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
                            <input type="checkbox" name="is_public" class="form-check-input" id="isPublic">
                            <label class="form-check-label" for="isPublic">Make report public</label>
                            <small class="text-muted d-block">Allow other users to run this report</small>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Report
                            </button>
                            <a href="/areports/report-builder" class="btn btn-secondary">Cancel</a>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Help</h6>
                    </div>
                    <div class="card-body small text-muted">
                        <p>Build custom reports by selecting:</p>
                        <ul>
                            <li><strong>Data Source:</strong> CDR or Queue Log</li>
                            <li><strong>Columns:</strong> Fields to include</li>
                            <li><strong>Filters:</strong> Narrow down results</li>
                            <li><strong>Grouping:</strong> Aggregate data</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
