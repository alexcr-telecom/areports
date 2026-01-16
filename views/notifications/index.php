<?php
/**
 * Notifications View
 */
$notifications = $notifications ?? [];
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Notifications</h1>
        <?php if (!empty($notifications)): ?>
        <form method="POST" action="/areports/notifications/mark-all-read">
            <button type="submit" class="btn btn-outline-primary">
                <i class="fas fa-check-double me-2"></i>Mark All Read
            </button>
        </form>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (empty($notifications)): ?>
            <div class="text-center py-5">
                <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                <p class="text-muted">No notifications</p>
            </div>
            <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($notifications as $notification): ?>
                <div class="list-group-item <?= $notification['read_at'] ? '' : 'bg-light' ?>">
                    <div class="d-flex w-100 justify-content-between align-items-start">
                        <div>
                            <div class="d-flex align-items-center mb-1">
                                <?php
                                $icons = [
                                    'alert' => 'exclamation-triangle text-warning',
                                    'info' => 'info-circle text-info',
                                    'success' => 'check-circle text-success',
                                    'error' => 'times-circle text-danger'
                                ];
                                $icon = $icons[$notification['type'] ?? 'info'] ?? 'bell text-secondary';
                                ?>
                                <i class="fas fa-<?= $icon ?> me-2"></i>
                                <strong><?= htmlspecialchars($notification['title']) ?></strong>
                                <?php if (!$notification['read_at']): ?>
                                <span class="badge bg-primary ms-2">New</span>
                                <?php endif; ?>
                            </div>
                            <p class="mb-1 text-muted"><?= htmlspecialchars($notification['message']) ?></p>
                            <small class="text-muted">
                                <i class="fas fa-clock me-1"></i>
                                <?= date('d/m/Y H:i', strtotime($notification['created_at'])) ?>
                            </small>
                        </div>
                        <div class="btn-group btn-group-sm">
                            <?php if (!$notification['read_at']): ?>
                            <form method="POST" action="/areports/notifications/<?= $notification['id'] ?>/read" style="display:inline">
                                <button type="submit" class="btn btn-outline-primary" title="Mark as Read">
                                    <i class="fas fa-check"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                            <?php if ($notification['link']): ?>
                            <a href="<?= htmlspecialchars($notification['link']) ?>" class="btn btn-outline-secondary" title="View">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
