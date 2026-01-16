<?php
/**
 * Telegram Settings View
 */
$settings = $settings ?? [];
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fab fa-telegram me-2"></i>Telegram Settings
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="/areports/admin/settings/telegram" id="telegramForm">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="telegram_enabled"
                                               name="telegram_enabled" value="1"
                                               <?= ($settings['telegram_enabled'] ?? '') ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="telegram_enabled">
                                            Enable Telegram Notifications
                                        </label>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="telegram_bot_token" class="form-label">Bot Token</label>
                                    <input type="password" class="form-control" id="telegram_bot_token"
                                           name="telegram_bot_token"
                                           value="<?= htmlspecialchars($settings['telegram_bot_token'] ?? '') ?>"
                                           placeholder="Enter your Telegram bot token">
                                    <div class="form-text">
                                        Get your bot token from <a href="https://t.me/BotFather" target="_blank">@BotFather</a>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="telegram_default_chat" class="form-label">Default Chat ID</label>
                                    <input type="text" class="form-control" id="telegram_default_chat"
                                           name="telegram_default_chat"
                                           value="<?= htmlspecialchars($settings['telegram_default_chat'] ?? '') ?>"
                                           placeholder="Enter default chat ID for notifications">
                                    <div class="form-text">
                                        This is the default chat where alerts will be sent. Can be a user ID or group ID.
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="telegram_alert_chat" class="form-label">Alert Chat ID (Optional)</label>
                                    <input type="text" class="form-control" id="telegram_alert_chat"
                                           name="telegram_alert_chat"
                                           value="<?= htmlspecialchars($settings['telegram_alert_chat'] ?? '') ?>"
                                           placeholder="Separate chat for alerts (optional)">
                                    <div class="form-text">
                                        Optionally send alerts to a separate chat/group.
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <i class="fas fa-info-circle me-2"></i>Setup Instructions
                                        </h6>
                                        <ol class="mb-0">
                                            <li>Open Telegram and search for <code>@BotFather</code></li>
                                            <li>Send <code>/newbot</code> and follow the instructions</li>
                                            <li>Copy the bot token and paste it above</li>
                                            <li>Start a chat with your bot or add it to a group</li>
                                            <li>To get your chat ID:
                                                <ul>
                                                    <li>Send a message to your bot</li>
                                                    <li>Visit: <code>https://api.telegram.org/bot&lt;TOKEN&gt;/getUpdates</code></li>
                                                    <li>Look for <code>"chat":{"id":XXXXXXX}</code></li>
                                                </ul>
                                            </li>
                                            <li>For groups, the chat ID will be negative (e.g., -1001234567890)</li>
                                        </ol>
                                    </div>
                                </div>

                                <div class="card mt-3">
                                    <div class="card-body">
                                        <h6 class="card-title">Test Connection</h6>
                                        <button type="button" class="btn btn-outline-primary" id="testTelegram">
                                            <i class="fas fa-paper-plane me-2"></i>Send Test Message
                                        </button>
                                        <div id="testResult" class="mt-2"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <h6 class="mb-3">Notification Settings</h6>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="telegram_notify_alerts"
                                           name="telegram_notify_alerts" value="1"
                                           <?= ($settings['telegram_notify_alerts'] ?? '1') ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="telegram_notify_alerts">
                                        Send Alert Notifications
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="telegram_notify_reports"
                                           name="telegram_notify_reports" value="1"
                                           <?= ($settings['telegram_notify_reports'] ?? '') ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="telegram_notify_reports">
                                        Send Scheduled Report Notifications
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="telegram_notify_daily"
                                           name="telegram_notify_daily" value="1"
                                           <?= ($settings['telegram_notify_daily'] ?? '') ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="telegram_notify_daily">
                                        Send Daily Summary
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-6">
                                <label for="telegram_daily_time" class="form-label">Daily Summary Time</label>
                                <input type="time" class="form-control" id="telegram_daily_time"
                                       name="telegram_daily_time"
                                       value="<?= htmlspecialchars($settings['telegram_daily_time'] ?? '18:00') ?>">
                            </div>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="/areports/admin/settings" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('testTelegram').addEventListener('click', function() {
    const btn = this;
    const resultDiv = document.getElementById('testResult');

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';

    fetch('/areports/admin/settings/telegram/test', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            csrf_token: document.querySelector('[name="csrf_token"]').value,
            bot_token: document.getElementById('telegram_bot_token').value,
            chat_id: document.getElementById('telegram_default_chat').value
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            resultDiv.innerHTML = '<div class="alert alert-success mb-0"><i class="fas fa-check me-2"></i>' + data.message + '</div>';
        } else {
            resultDiv.innerHTML = '<div class="alert alert-danger mb-0"><i class="fas fa-times me-2"></i>' + data.message + '</div>';
        }
    })
    .catch(error => {
        resultDiv.innerHTML = '<div class="alert alert-danger mb-0"><i class="fas fa-times me-2"></i>Error: ' + error.message + '</div>';
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Send Test Message';
    });
});
</script>
