<?php $this->section('content'); ?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">Call Detail Records</h1>
        <p class="text-muted mb-0">Search and analyze call records</p>
    </div>
    <?php if ($this->can('reports.cdr.export')): ?>
    <div>
        <button type="button" class="btn btn-outline-success" id="exportCsv">
            <i class="fas fa-file-csv me-1"></i> Export CSV
        </button>
    </div>
    <?php endif; ?>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form id="cdrFilters">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Date Range</label>
                    <input type="text" class="form-control daterangepicker-input" id="dateRange"
                           value="<?= $this->e($filters['date_from']) ?> - <?= $this->e($filters['date_to']) ?>">
                    <input type="hidden" name="date_from" id="dateFrom" value="<?= $this->e($filters['date_from']) ?>">
                    <input type="hidden" name="date_to" id="dateTo" value="<?= $this->e($filters['date_to']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Source</label>
                    <input type="text" class="form-control" name="src" placeholder="Caller number"
                           value="<?= $this->e($filters['src']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Destination/DID</label>
                    <input type="text" class="form-control" name="dst" placeholder="Called number"
                           value="<?= $this->e($filters['dst']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="disposition">
                        <option value="">All</option>
                        <option value="ANSWERED" <?= $filters['disposition'] === 'ANSWERED' ? 'selected' : '' ?>>Answered</option>
                        <option value="NO ANSWER" <?= $filters['disposition'] === 'NO ANSWER' ? 'selected' : '' ?>>No Answer</option>
                        <option value="BUSY" <?= $filters['disposition'] === 'BUSY' ? 'selected' : '' ?>>Busy</option>
                        <option value="FAILED" <?= $filters['disposition'] === 'FAILED' ? 'selected' : '' ?>>Failed</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Queue/Context</label>
                    <select class="form-select" name="context">
                        <option value="">All</option>
                        <?php foreach ($queues as $queue): ?>
                        <option value="<?= $this->e($queue['queue_number']) ?>">
                            <?= $this->e($queue['display_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- CDR Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table id="cdrTable" class="table table-hover" style="width:100%">
                <thead>
                    <tr>
                        <th>Date/Time</th>
                        <th>Source</th>
                        <th>Destination</th>
                        <th>DID</th>
                        <th>Duration</th>
                        <th>Talk Time</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Recording Player Modal -->
<div class="modal fade" id="recordingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Call Recording</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <audio id="audioPlayer" controls class="w-100">
                    Your browser does not support the audio element.
                </audio>
                <div id="recordingInfo" class="mt-3 text-muted"></div>
            </div>
        </div>
    </div>
</div>

<?php $this->endSection(); ?>

<?php $this->section('scripts'); ?>
<script>
$(document).ready(function() {
    // Initialize DataTable
    var table = $('#cdrTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '/areports/reports/cdr/data',
            data: function(d) {
                d.date_from = $('#dateFrom').val();
                d.date_to = $('#dateTo').val();
                d.src = $('input[name="src"]').val();
                d.dst = $('input[name="dst"]').val();
                d.disposition = $('select[name="disposition"]').val();
                d.context = $('select[name="context"]').val();
            }
        },
        columns: [
            { data: 'calldate' },
            { data: 'src' },
            { data: 'dst' },
            { data: 'did' },
            { data: 'duration' },
            { data: 'billsec' },
            {
                data: 'disposition',
                render: function(data) {
                    var classes = {
                        'ANSWERED': 'success',
                        'NO ANSWER': 'warning',
                        'BUSY': 'info',
                        'FAILED': 'danger'
                    };
                    return '<span class="badge bg-' + (classes[data] || 'secondary') + '">' + data + '</span>';
                }
            },
            {
                data: 'actions',
                orderable: false,
                searchable: false
            }
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]]
    });

    // Filter form submit
    $('#cdrFilters').on('submit', function(e) {
        e.preventDefault();
        table.ajax.reload();
    });

    // Date range picker
    $('#dateRange').daterangepicker({
        locale: { format: 'YYYY-MM-DD' },
        startDate: $('#dateFrom').val(),
        endDate: $('#dateTo').val()
    }, function(start, end) {
        $('#dateFrom').val(start.format('YYYY-MM-DD'));
        $('#dateTo').val(end.format('YYYY-MM-DD'));
    });

    // Export CSV
    $('#exportCsv').on('click', function() {
        var params = new URLSearchParams({
            format: 'csv',
            date_from: $('#dateFrom').val(),
            date_to: $('#dateTo').val(),
            disposition: $('select[name="disposition"]').val()
        });
        window.location.href = '/areports/reports/cdr/export?' + params.toString();
    });

    // Play recording
    $(document).on('click', '.play-recording', function() {
        var uniqueid = $(this).data('uniqueid');
        $('#audioPlayer').attr('src', '/areports/quality/recordings/' + uniqueid);
        $('#recordingInfo').text('Call ID: ' + uniqueid);
        $('#recordingModal').modal('show');
    });

    // Stop audio when modal closes
    $('#recordingModal').on('hidden.bs.modal', function() {
        $('#audioPlayer')[0].pause();
        $('#audioPlayer')[0].currentTime = 0;
    });
});
</script>
<?php $this->endSection(); ?>
