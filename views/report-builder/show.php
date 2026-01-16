<?php
/**
 * Run Custom Report View
 */
$report = $report ?? [];
$data = $data ?? [];
$columns = json_decode($report['columns'] ?? '[]', true);
$filters = $filters ?? [];
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><?= htmlspecialchars($report['name']) ?></h1>
            <?php if ($report['description']): ?>
            <p class="text-muted mb-0"><?= htmlspecialchars($report['description']) ?></p>
            <?php endif; ?>
        </div>
        <div class="btn-group">
            <a href="/areports/report-builder" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back
            </a>
            <a href="/areports/report-builder/<?= $report['id'] ?>/export?format=csv&<?= http_build_query($filters) ?>" class="btn btn-outline-success">
                <i class="fas fa-file-csv me-2"></i>CSV
            </a>
            <a href="/areports/report-builder/<?= $report['id'] ?>/export?format=excel&<?= http_build_query($filters) ?>" class="btn btn-outline-success">
                <i class="fas fa-file-excel me-2"></i>Excel
            </a>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="<?= $filters['date_from'] ?? date('Y-m-d') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="<?= $filters['date_to'] ?? date('Y-m-d') ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-play me-2"></i>Run Report
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Results -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">Results</h6>
            <span class="badge bg-info"><?= number_format(count($data)) ?> records</span>
        </div>
        <div class="card-body">
            <?php if (empty($data)): ?>
            <div class="text-center py-5">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <p class="text-muted">No data found for the selected criteria</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-sm">
                    <thead>
                        <tr>
                            <?php foreach ($columns as $col): ?>
                            <th><?= htmlspecialchars(ucwords(str_replace('_', ' ', $col))) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $row): ?>
                        <tr>
                            <?php foreach ($columns as $col): ?>
                            <td>
                                <?php
                                $value = $row[$col] ?? '-';
                                if ($col === 'calldate' && $value !== '-') {
                                    echo date('d/m/Y H:i:s', strtotime($value));
                                } elseif (in_array($col, ['duration', 'billsec']) && is_numeric($value)) {
                                    echo gmdate('i:s', $value);
                                } elseif ($col === 'disposition') {
                                    $colors = ['ANSWERED' => 'success', 'NO ANSWER' => 'warning', 'BUSY' => 'info', 'FAILED' => 'danger'];
                                    echo '<span class="badge bg-' . ($colors[$value] ?? 'secondary') . '">' . htmlspecialchars($value) . '</span>';
                                } else {
                                    echo htmlspecialchars($value);
                                }
                                ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
