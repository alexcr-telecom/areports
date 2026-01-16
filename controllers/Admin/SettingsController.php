<?php
/**
 * Admin Settings Controller
 * Manages system settings
 */

namespace aReports\Controllers\Admin;

use aReports\Core\Controller;
use aReports\Services\AMIService;

class SettingsController extends Controller
{
    /**
     * Settings index
     */
    public function index(): void
    {
        $this->requirePermission('admin.settings.view');

        $this->render('admin/settings/index', [
            'title' => 'System Settings',
            'currentPage' => 'admin.settings'
        ]);
    }

    /**
     * General settings
     */
    public function general(): void
    {
        $this->requirePermission('admin.settings.view');

        $settings = $this->getSettings([
            'site_name',
            'timezone',
            'date_format',
            'time_format',
            'default_page_size',
            'session_timeout'
        ]);

        $this->render('admin/settings/general', [
            'title' => 'General Settings',
            'currentPage' => 'admin.settings.general',
            'settings' => $settings
        ]);
    }

    /**
     * Update general settings
     */
    public function updateGeneral(): void
    {
        $this->requirePermission('admin.settings.edit');

        $data = $this->validate($_POST, [
            'site_name' => 'required|max:100',
            'timezone' => 'required',
            'date_format' => 'required',
            'time_format' => 'required',
            'default_page_size' => 'required|numeric|min:10|max:100',
            'session_timeout' => 'required|numeric|min:5|max:480'
        ]);

        foreach ($data as $key => $value) {
            $this->saveSetting($key, $value);
        }

        $this->audit('update', 'settings', null, null, ['category' => 'general']);

        $this->redirectWith('/areports/admin/settings/general', 'success', 'Settings updated successfully.');
    }

    /**
     * Email settings
     */
    public function email(): void
    {
        $this->requirePermission('admin.settings.view');

        $settings = $this->getSettings([
            'smtp_host',
            'smtp_port',
            'smtp_username',
            'smtp_password',
            'smtp_encryption',
            'mail_from_address',
            'mail_from_name'
        ]);

        $this->render('admin/settings/email', [
            'title' => 'Email Settings',
            'currentPage' => 'admin.settings.email',
            'settings' => $settings
        ]);
    }

    /**
     * Update email settings
     */
    public function updateEmail(): void
    {
        $this->requirePermission('admin.settings.edit');

        $data = $this->validate($_POST, [
            'smtp_host' => 'required|max:255',
            'smtp_port' => 'required|numeric|min:1|max:65535',
            'smtp_username' => 'max:255',
            'smtp_encryption' => 'in:none,tls,ssl',
            'mail_from_address' => 'required|email',
            'mail_from_name' => 'required|max:100'
        ]);

        foreach ($data as $key => $value) {
            $this->saveSetting($key, $value);
        }

        // Handle password separately (only update if provided)
        if (!empty($_POST['smtp_password'])) {
            $this->saveSetting('smtp_password', $_POST['smtp_password']);
        }

        $this->audit('update', 'settings', null, null, ['category' => 'email']);

        $this->redirectWith('/areports/admin/settings/email', 'success', 'Email settings updated successfully.');
    }

    /**
     * Test email configuration
     */
    public function testEmail(): void
    {
        $this->requirePermission('admin.settings.edit');

        $testAddress = $this->post('test_address');
        if (!$testAddress || !filter_var($testAddress, FILTER_VALIDATE_EMAIL)) {
            $this->json(['success' => false, 'message' => 'Invalid email address']);
            return;
        }

        try {
            // Simple mail test using PHP mail function or your mail service
            $subject = 'aReports - Test Email';
            $message = 'This is a test email from aReports to verify your email configuration.';

            $headers = 'From: ' . $this->app->getSetting('mail_from_name') .
                       ' <' . $this->app->getSetting('mail_from_address') . ">\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

            $sent = mail($testAddress, $subject, $message, $headers);

            $this->json([
                'success' => $sent,
                'message' => $sent ? 'Test email sent successfully' : 'Failed to send test email'
            ]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * AMI settings
     */
    public function ami(): void
    {
        $this->requirePermission('admin.settings.view');

        $settings = $this->getSettings([
            'ami_host',
            'ami_port',
            'ami_username',
            'ami_secret'
        ]);

        $this->render('admin/settings/ami', [
            'title' => 'AMI Settings',
            'currentPage' => 'admin.settings.ami',
            'settings' => $settings
        ]);
    }

    /**
     * Update AMI settings
     */
    public function updateAmi(): void
    {
        $this->requirePermission('admin.settings.edit');

        $data = $this->validate($_POST, [
            'ami_host' => 'required|max:255',
            'ami_port' => 'required|numeric|min:1|max:65535',
            'ami_username' => 'required|max:255'
        ]);

        foreach ($data as $key => $value) {
            $this->saveSetting($key, $value);
        }

        // Handle secret separately
        if (!empty($_POST['ami_secret'])) {
            $this->saveSetting('ami_secret', $_POST['ami_secret']);
        }

        $this->audit('update', 'settings', null, null, ['category' => 'ami']);

        $this->redirectWith('/areports/admin/settings/ami', 'success', 'AMI settings updated successfully.');
    }

    /**
     * Test AMI connection
     */
    public function testAmi(): void
    {
        $this->requirePermission('admin.settings.edit');

        try {
            $ami = new AMIService();
            $connected = $ami->connect();

            if ($connected) {
                $ami->disconnect();
                $this->json(['success' => true, 'message' => 'AMI connection successful']);
            } else {
                $this->json(['success' => false, 'message' => 'Failed to authenticate with AMI']);
            }
        } catch (\Exception $e) {
            $this->json(['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Telegram settings
     */
    public function telegram(): void
    {
        $this->requirePermission('admin.settings.view');

        $settings = $this->getSettings([
            'telegram_enabled',
            'telegram_bot_token',
            'telegram_default_chat',
            'telegram_alert_chat',
            'telegram_notify_alerts',
            'telegram_notify_reports',
            'telegram_notify_daily',
            'telegram_daily_time'
        ]);

        $this->render('admin/settings/telegram', [
            'title' => 'Telegram Settings',
            'currentPage' => 'admin.settings.telegram',
            'settings' => $settings
        ]);
    }

    /**
     * Update Telegram settings
     */
    public function updateTelegram(): void
    {
        $this->requirePermission('admin.settings.edit');

        $data = [
            'telegram_enabled' => $this->post('telegram_enabled') ? '1' : '0',
            'telegram_default_chat' => $this->post('telegram_default_chat', ''),
            'telegram_alert_chat' => $this->post('telegram_alert_chat', ''),
            'telegram_notify_alerts' => $this->post('telegram_notify_alerts') ? '1' : '0',
            'telegram_notify_reports' => $this->post('telegram_notify_reports') ? '1' : '0',
            'telegram_notify_daily' => $this->post('telegram_notify_daily') ? '1' : '0',
            'telegram_daily_time' => $this->post('telegram_daily_time', '18:00'),
        ];

        foreach ($data as $key => $value) {
            $this->saveSetting($key, $value);
        }

        // Handle bot token separately (only update if provided)
        if (!empty($_POST['telegram_bot_token'])) {
            $this->saveSetting('telegram_bot_token', $_POST['telegram_bot_token']);
        }

        $this->audit('update', 'settings', null, null, ['category' => 'telegram']);

        $this->redirectWith('/areports/admin/settings/telegram', 'success', 'Telegram settings updated successfully.');
    }

    /**
     * Test Telegram connection
     */
    public function testTelegram(): void
    {
        $this->requirePermission('admin.settings.edit');

        $input = json_decode(file_get_contents('php://input'), true);
        $botToken = $input['bot_token'] ?? '';
        $chatId = $input['chat_id'] ?? '';

        if (empty($botToken) || empty($chatId)) {
            $this->json(['success' => false, 'message' => 'Bot token and chat ID are required']);
            return;
        }

        try {
            // Temporarily save the token for testing
            $this->saveSetting('telegram_bot_token', $botToken);

            $telegram = new \aReports\Services\TelegramService();

            // First test getMe
            $meResult = $telegram->getMe();
            if (!$meResult['success']) {
                $this->json(['success' => false, 'message' => 'Invalid bot token: ' . ($meResult['message'] ?? 'Unknown error')]);
                return;
            }

            // Then send test message
            $message = "✅ <b>aReports Test Message</b>\n\n";
            $message .= "Your Telegram integration is working correctly!\n";
            $message .= "Bot: @" . ($meResult['data']['username'] ?? 'unknown') . "\n";
            $message .= "Time: " . date('d/m/Y H:i:s');

            $result = $telegram->sendMessage($chatId, $message);

            if ($result['success']) {
                $this->json(['success' => true, 'message' => 'Test message sent successfully!']);
            } else {
                $this->json(['success' => false, 'message' => 'Failed to send message: ' . ($result['message'] ?? 'Unknown error')]);
            }
        } catch (\Exception $e) {
            $this->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    /**
     * Get multiple settings
     */
    private function getSettings(array $keys): array
    {
        $settings = [];
        foreach ($keys as $key) {
            $settings[$key] = $this->app->getSetting($key) ?? '';
        }
        return $settings;
    }

    /**
     * Save a setting
     */
    private function saveSetting(string $key, string $value): void
    {
        $existing = $this->db->fetch("SELECT id FROM settings WHERE setting_key = ?", [$key]);

        if ($existing) {
            $this->db->update('settings', [
                'setting_value' => $value,
                'updated_at' => date('Y-m-d H:i:s')
            ], ['id' => $existing['id']]);
        } else {
            $this->db->insert('settings', [
                'setting_key' => $key,
                'setting_value' => $value
            ]);
        }
    }
}
