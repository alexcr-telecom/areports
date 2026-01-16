<?php
/**
 * Notification Controller
 * Handles browser notifications
 */

namespace aReports\Controllers;

use aReports\Core\Controller;
use aReports\Services\NotificationService;

class NotificationController extends Controller
{
    private NotificationService $notificationService;

    public function __construct(\aReports\Core\App $app)
    {
        parent::__construct($app);
        $this->notificationService = new NotificationService();
    }

    /**
     * List all notifications
     */
    public function index(): void
    {
        $page = (int) $this->get('page', 1);
        $perPage = 50;

        $notifications = $this->db->fetchAll(
            "SELECT * FROM browser_notifications
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?",
            [$this->user['id'], $perPage, ($page - 1) * $perPage]
        );

        $total = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM browser_notifications WHERE user_id = ?",
            [$this->user['id']]
        );

        if ($this->isAjax()) {
            $this->json([
                'notifications' => $notifications,
                'total' => $total,
                'page' => $page,
                'pages' => ceil($total / $perPage),
            ]);
        } else {
            $this->render('notifications/index', [
                'title' => 'Notifications',
                'currentPage' => 'notifications',
                'notifications' => $notifications,
                'page' => $page,
                'totalPages' => ceil($total / $perPage),
            ]);
        }
    }

    /**
     * Get unread notifications (for navbar polling)
     */
    public function unread(): void
    {
        $notifications = $this->notificationService->getUnreadNotifications($this->user['id']);

        $this->json([
            'notifications' => $notifications,
            'count' => count($notifications),
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markRead(int $id): void
    {
        $result = $this->notificationService->markAsRead($id, $this->user['id']);

        $this->json([
            'success' => $result,
            'message' => $result ? 'Notification marked as read' : 'Failed to update notification',
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllRead(): void
    {
        $result = $this->notificationService->markAllAsRead($this->user['id']);

        if ($this->isAjax()) {
            $this->json([
                'success' => $result,
                'message' => $result ? 'All notifications marked as read' : 'Failed to update notifications',
            ]);
        } else {
            $this->redirectWith('/areports/notifications', 'success', 'All notifications marked as read.');
        }
    }
}
