<?php
/**
 * Email Service
 * Handles email sending via SMTP
 */

namespace aReports\Services;

class EmailService
{
    private string $smtpHost;
    private int $smtpPort;
    private string $smtpUsername;
    private string $smtpPassword;
    private string $smtpEncryption;
    private string $fromAddress;
    private string $fromName;
    private bool $enabled = false;
    private string $logFile;
    private $socket = null;

    public function __construct()
    {
        $app = \aReports\Core\App::getInstance();

        $this->smtpHost = $app->getSetting('smtp_host', '');
        $this->smtpPort = (int) $app->getSetting('smtp_port', 587);
        $this->smtpUsername = $app->getSetting('smtp_username', '');
        $this->smtpPassword = $app->getSetting('smtp_password', '');
        $this->smtpEncryption = $app->getSetting('smtp_encryption', 'tls');
        $this->fromAddress = $app->getSetting('mail_from_address', '');
        $this->fromName = $app->getSetting('mail_from_name', 'aReports');
        $this->enabled = !empty($this->smtpHost) && !empty($this->fromAddress);
        $this->logFile = dirname(__DIR__) . '/storage/logs/email.log';
    }

    /**
     * Log message
     */
    private function log(string $message, string $type = 'INFO'): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$type}] {$message}\n";

        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }

    /**
     * Check if email is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Send an email
     */
    public function send(string $to, string $subject, string $body, array $options = []): array
    {
        if (!$this->enabled) {
            return ['success' => false, 'message' => 'Email is not configured'];
        }

        $this->log("Sending email to: {$to}, Subject: {$subject}");

        try {
            // Try SMTP first
            if ($this->connectSmtp()) {
                $result = $this->sendViaSmtp($to, $subject, $body, $options);
                $this->disconnectSmtp();
                return $result;
            }

            // Fallback to PHP mail()
            return $this->sendViaMail($to, $subject, $body, $options);
        } catch (\Exception $e) {
            $this->log("Error: " . $e->getMessage(), 'ERROR');
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Send email to multiple recipients
     */
    public function sendToMultiple(array $recipients, string $subject, string $body, array $options = []): array
    {
        $results = [];
        foreach ($recipients as $to) {
            $results[$to] = $this->send($to, $subject, $body, $options);
        }
        return $results;
    }

    /**
     * Send alert notification email
     */
    public function sendAlert(string $to, array $alert): array
    {
        $subject = "[aReports Alert] {$alert['name']}";

        $body = $this->buildAlertEmail($alert);

        return $this->send($to, $subject, $body, ['html' => true]);
    }

    /**
     * Send scheduled report email
     */
    public function sendReport(string $to, string $reportName, string $summary, ?string $attachmentPath = null): array
    {
        $subject = "[aReports] Scheduled Report: {$reportName}";

        $body = $this->buildReportEmail($reportName, $summary);

        $options = ['html' => true];
        if ($attachmentPath && file_exists($attachmentPath)) {
            $options['attachment'] = $attachmentPath;
        }

        return $this->send($to, $subject, $body, $options);
    }

    /**
     * Send password reset email
     */
    public function sendPasswordReset(string $to, string $resetLink, string $userName): array
    {
        $subject = "[aReports] Password Reset Request";

        $body = $this->buildPasswordResetEmail($resetLink, $userName);

        return $this->send($to, $subject, $body, ['html' => true]);
    }

    /**
     * Test SMTP connection
     */
    public function testConnection(): array
    {
        if (!$this->enabled) {
            return ['success' => false, 'message' => 'Email is not configured'];
        }

        try {
            if ($this->connectSmtp()) {
                $this->disconnectSmtp();
                return ['success' => true, 'message' => 'SMTP connection successful'];
            }
            return ['success' => false, 'message' => 'Failed to connect to SMTP server'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Connect to SMTP server
     */
    private function connectSmtp(): bool
    {
        $host = $this->smtpEncryption === 'ssl' ? "ssl://{$this->smtpHost}" : $this->smtpHost;

        $this->socket = @fsockopen($host, $this->smtpPort, $errno, $errstr, 30);

        if (!$this->socket) {
            $this->log("SMTP Connection failed: {$errstr}", 'ERROR');
            return false;
        }

        stream_set_timeout($this->socket, 30);

        $response = $this->readSmtp();
        if (strpos($response, '220') !== 0) {
            $this->log("SMTP Unexpected welcome: {$response}", 'ERROR');
            return false;
        }

        // EHLO
        $this->writeSmtp("EHLO " . gethostname());
        $response = $this->readSmtp();

        // STARTTLS if needed
        if ($this->smtpEncryption === 'tls' && strpos($response, 'STARTTLS') !== false) {
            $this->writeSmtp("STARTTLS");
            $response = $this->readSmtp();

            if (strpos($response, '220') === 0) {
                stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);

                $this->writeSmtp("EHLO " . gethostname());
                $this->readSmtp();
            }
        }

        // AUTH LOGIN
        if (!empty($this->smtpUsername)) {
            $this->writeSmtp("AUTH LOGIN");
            $response = $this->readSmtp();

            if (strpos($response, '334') === 0) {
                $this->writeSmtp(base64_encode($this->smtpUsername));
                $this->readSmtp();

                $this->writeSmtp(base64_encode($this->smtpPassword));
                $response = $this->readSmtp();

                if (strpos($response, '235') !== 0) {
                    $this->log("SMTP Auth failed: {$response}", 'ERROR');
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Send email via SMTP
     */
    private function sendViaSmtp(string $to, string $subject, string $body, array $options = []): array
    {
        // MAIL FROM
        $this->writeSmtp("MAIL FROM:<{$this->fromAddress}>");
        $response = $this->readSmtp();
        if (strpos($response, '250') !== 0) {
            return ['success' => false, 'message' => "MAIL FROM failed: {$response}"];
        }

        // RCPT TO
        $this->writeSmtp("RCPT TO:<{$to}>");
        $response = $this->readSmtp();
        if (strpos($response, '250') !== 0) {
            return ['success' => false, 'message' => "RCPT TO failed: {$response}"];
        }

        // DATA
        $this->writeSmtp("DATA");
        $response = $this->readSmtp();
        if (strpos($response, '354') !== 0) {
            return ['success' => false, 'message' => "DATA failed: {$response}"];
        }

        // Build message
        $message = $this->buildMessage($to, $subject, $body, $options);
        $this->writeSmtp($message . "\r\n.");

        $response = $this->readSmtp();
        if (strpos($response, '250') !== 0) {
            return ['success' => false, 'message' => "Send failed: {$response}"];
        }

        $this->log("Email sent successfully to {$to}");
        return ['success' => true, 'message' => 'Email sent successfully'];
    }

    /**
     * Disconnect from SMTP
     */
    private function disconnectSmtp(): void
    {
        if ($this->socket) {
            $this->writeSmtp("QUIT");
            fclose($this->socket);
            $this->socket = null;
        }
    }

    /**
     * Write to SMTP socket
     */
    private function writeSmtp(string $data): void
    {
        fwrite($this->socket, $data . "\r\n");
        $this->log("SMTP >>> " . substr($data, 0, 100));
    }

    /**
     * Read from SMTP socket
     */
    private function readSmtp(): string
    {
        $response = '';
        while ($line = fgets($this->socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }
        $this->log("SMTP <<< " . trim($response));
        return $response;
    }

    /**
     * Send via PHP mail() function
     */
    private function sendViaMail(string $to, string $subject, string $body, array $options = []): array
    {
        $headers = [];
        $headers[] = "From: {$this->fromName} <{$this->fromAddress}>";
        $headers[] = "Reply-To: {$this->fromAddress}";
        $headers[] = "MIME-Version: 1.0";

        if ($options['html'] ?? false) {
            $headers[] = "Content-Type: text/html; charset=UTF-8";
        } else {
            $headers[] = "Content-Type: text/plain; charset=UTF-8";
        }

        $success = mail($to, $subject, $body, implode("\r\n", $headers));

        return [
            'success' => $success,
            'message' => $success ? 'Email sent successfully' : 'Failed to send email'
        ];
    }

    /**
     * Build SMTP message
     */
    private function buildMessage(string $to, string $subject, string $body, array $options = []): string
    {
        $boundary = md5(uniqid(time()));
        $isHtml = $options['html'] ?? false;
        $attachment = $options['attachment'] ?? null;

        $message = "Date: " . date('r') . "\r\n";
        $message .= "From: {$this->fromName} <{$this->fromAddress}>\r\n";
        $message .= "To: {$to}\r\n";
        $message .= "Subject: {$subject}\r\n";
        $message .= "MIME-Version: 1.0\r\n";

        if ($attachment && file_exists($attachment)) {
            $message .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n\r\n";
            $message .= "--{$boundary}\r\n";

            if ($isHtml) {
                $message .= "Content-Type: text/html; charset=UTF-8\r\n";
            } else {
                $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
            }
            $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $message .= $body . "\r\n\r\n";

            // Add attachment
            $message .= "--{$boundary}\r\n";
            $message .= "Content-Type: application/octet-stream; name=\"" . basename($attachment) . "\"\r\n";
            $message .= "Content-Transfer-Encoding: base64\r\n";
            $message .= "Content-Disposition: attachment; filename=\"" . basename($attachment) . "\"\r\n\r\n";
            $message .= chunk_split(base64_encode(file_get_contents($attachment))) . "\r\n";
            $message .= "--{$boundary}--";
        } else {
            if ($isHtml) {
                $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
            } else {
                $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
            }
            $message .= $body;
        }

        return $message;
    }

    /**
     * Build alert email HTML
     */
    private function buildAlertEmail(array $alert): string
    {
        $severityColor = match ($alert['severity'] ?? 'warning') {
            'critical' => '#dc3545',
            'high' => '#fd7e14',
            'warning' => '#ffc107',
            default => '#17a2b8',
        };

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: {$severityColor}; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .metric { margin: 10px 0; padding: 10px; background: white; border-left: 4px solid {$severityColor}; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚠️ Alert: {$alert['name']}</h1>
        </div>
        <div class="content">
            <div class="metric">
                <strong>Metric:</strong> {$alert['metric']}
            </div>
            <div class="metric">
                <strong>Current Value:</strong> {$alert['current_value']}
            </div>
            <div class="metric">
                <strong>Threshold:</strong> {$alert['threshold']}
            </div>
            <div class="metric">
                <strong>Queue:</strong> {$alert['queue']}
            </div>
            <div class="metric">
                <strong>Time:</strong> {$alert['time']}
            </div>
        </div>
        <div class="footer">
            This alert was generated by aReports Call Center Analytics
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Build report email HTML
     */
    private function buildReportEmail(string $reportName, string $summary): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #3498db; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 {$reportName}</h1>
        </div>
        <div class="content">
            {$summary}
        </div>
        <div class="footer">
            This report was generated by aReports Call Center Analytics<br>
            Generated at: {date('d/m/Y H:i:s')}
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Build password reset email HTML
     */
    private function buildPasswordResetEmail(string $resetLink, string $userName): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #3498db; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .button { display: inline-block; padding: 12px 24px; background: #3498db; color: white; text-decoration: none; border-radius: 4px; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Password Reset</h1>
        </div>
        <div class="content">
            <p>Hello {$userName},</p>
            <p>You have requested to reset your password. Click the button below to proceed:</p>
            <p style="text-align: center;">
                <a href="{$resetLink}" class="button">Reset Password</a>
            </p>
            <p>If you did not request this, please ignore this email.</p>
            <p>This link will expire in 1 hour.</p>
        </div>
        <div class="footer">
            aReports Call Center Analytics
        </div>
    </div>
</body>
</html>
HTML;
    }
}
