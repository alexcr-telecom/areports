<?php
/**
 * PDF Export Service
 * Generates PDF reports using native PHP (no external library required)
 */

namespace aReports\Services;

class PDFService
{
    private string $exportPath;
    private array $config;
    private string $content = '';
    private array $styles = [];

    // Page dimensions (A4 in points: 595.28 x 841.89)
    private float $pageWidth = 595.28;
    private float $pageHeight = 841.89;
    private float $margin = 40;

    public function __construct()
    {
        $app = \aReports\Core\App::getInstance();
        $this->exportPath = $app->getConfig()['exports_path'] ?? dirname(__DIR__) . '/storage/exports';
        $this->config = [
            'title' => 'aReports',
            'author' => 'aReports System',
            'creator' => 'aReports PDF Generator',
        ];

        if (!is_dir($this->exportPath)) {
            mkdir($this->exportPath, 0755, true);
        }
    }

    /**
     * Generate PDF from HTML content
     */
    public function generateFromHtml(string $html, string $filename, array $options = []): array
    {
        $filepath = $this->exportPath . '/' . $filename;

        // For complex HTML, we'll generate a simplified PDF
        // In production, integrate with TCPDF, mPDF, or wkhtmltopdf

        try {
            $pdf = $this->createPdf($html, $options);
            file_put_contents($filepath, $pdf);

            return [
                'success' => true,
                'filepath' => $filepath,
                'filename' => $filename,
                'size' => filesize($filepath)
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate report PDF
     */
    public function generateReport(string $title, array $data, array $columns, array $options = []): array
    {
        $filename = $options['filename'] ?? 'report_' . date('Y-m-d_His') . '.pdf';
        $filepath = $this->exportPath . '/' . $filename;

        try {
            $html = $this->buildReportHtml($title, $data, $columns, $options);
            $pdf = $this->createPdf($html, $options);
            file_put_contents($filepath, $pdf);

            return [
                'success' => true,
                'filepath' => $filepath,
                'filename' => $filename,
                'size' => filesize($filepath)
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate CDR report PDF
     */
    public function generateCdrReport(array $calls, array $options = []): array
    {
        $columns = [
            'calldate' => 'Date/Time',
            'src' => 'Source',
            'dst' => 'Destination',
            'duration' => 'Duration',
            'billsec' => 'Bill Sec',
            'disposition' => 'Disposition',
        ];

        return $this->generateReport('Call Detail Records', $calls, $columns, $options);
    }

    /**
     * Generate Agent report PDF
     */
    public function generateAgentReport(array $agents, array $options = []): array
    {
        $columns = [
            'agent_name' => 'Agent',
            'calls_handled' => 'Calls Handled',
            'calls_missed' => 'Calls Missed',
            'total_talk_time' => 'Talk Time',
            'avg_talk_time' => 'Avg Talk Time',
            'answer_rate' => 'Answer Rate %',
        ];

        return $this->generateReport('Agent Performance Report', $agents, $columns, $options);
    }

    /**
     * Generate Queue report PDF
     */
    public function generateQueueReport(array $queues, array $options = []): array
    {
        $columns = [
            'queue_name' => 'Queue',
            'total_calls' => 'Total Calls',
            'answered' => 'Answered',
            'abandoned' => 'Abandoned',
            'sla_percentage' => 'SLA %',
            'avg_wait_time' => 'Avg Wait',
        ];

        return $this->generateReport('Queue Performance Report', $queues, $columns, $options);
    }

    /**
     * Build report HTML
     */
    private function buildReportHtml(string $title, array $data, array $columns, array $options = []): string
    {
        $dateRange = $options['date_range'] ?? date('d/m/Y');
        $generatedAt = date('d/m/Y H:i:s');

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{$title}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10pt; color: #333; margin: 20px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
        .header h1 { color: #3498db; margin: 0; font-size: 18pt; }
        .header .meta { color: #666; font-size: 9pt; margin-top: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { background: #3498db; color: white; padding: 8px 5px; text-align: left; font-size: 9pt; }
        td { padding: 6px 5px; border-bottom: 1px solid #ddd; font-size: 9pt; }
        tr:nth-child(even) { background: #f9f9f9; }
        tr:hover { background: #f0f0f0; }
        .footer { margin-top: 20px; text-align: center; font-size: 8pt; color: #666; }
        .summary { background: #f5f5f5; padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .summary-item { display: inline-block; margin-right: 20px; }
        .summary-label { font-weight: bold; color: #666; }
        .summary-value { color: #3498db; font-size: 12pt; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{$title}</h1>
        <div class="meta">
            Period: {$dateRange} | Generated: {$generatedAt}
        </div>
    </div>
HTML;

        // Add summary if provided
        if (!empty($options['summary'])) {
            $html .= '<div class="summary">';
            foreach ($options['summary'] as $label => $value) {
                $html .= "<div class='summary-item'><span class='summary-label'>{$label}:</span> <span class='summary-value'>{$value}</span></div>";
            }
            $html .= '</div>';
        }

        // Build table
        $html .= '<table><thead><tr>';
        foreach ($columns as $key => $label) {
            $html .= "<th>{$label}</th>";
        }
        $html .= '</tr></thead><tbody>';

        foreach ($data as $row) {
            $html .= '<tr>';
            foreach ($columns as $key => $label) {
                $value = $row[$key] ?? '';
                // Format duration columns
                if (strpos($key, 'time') !== false && is_numeric($value)) {
                    $value = $this->formatDuration((int)$value);
                }
                $html .= "<td>" . htmlspecialchars($value) . "</td>";
            }
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        $html .= <<<HTML
    <div class="footer">
        aReports Call Center Analytics | Page 1 of 1 | Total Records: {count($data)}
    </div>
</body>
</html>
HTML;

        return $html;
    }

    /**
     * Create PDF from HTML (simplified version)
     * For production, integrate with TCPDF, mPDF, or wkhtmltopdf
     */
    private function createPdf(string $html, array $options = []): string
    {
        // Try to use wkhtmltopdf if available
        if ($this->hasWkhtmltopdf()) {
            return $this->createWithWkhtmltopdf($html, $options);
        }

        // Fallback: Create a simple PDF structure
        return $this->createSimplePdf($html, $options);
    }

    /**
     * Check if wkhtmltopdf is available
     */
    private function hasWkhtmltopdf(): bool
    {
        $output = [];
        $returnVar = 0;
        @exec('which wkhtmltopdf 2>/dev/null', $output, $returnVar);
        return $returnVar === 0 && !empty($output);
    }

    /**
     * Create PDF using wkhtmltopdf
     */
    private function createWithWkhtmltopdf(string $html, array $options = []): string
    {
        $tempHtml = tempnam(sys_get_temp_dir(), 'pdf_html_');
        $tempPdf = tempnam(sys_get_temp_dir(), 'pdf_out_');

        file_put_contents($tempHtml, $html);

        $orientation = $options['orientation'] ?? 'Portrait';
        $pageSize = $options['page_size'] ?? 'A4';

        $cmd = sprintf(
            'wkhtmltopdf --quiet --page-size %s --orientation %s --margin-top 10mm --margin-bottom 10mm --margin-left 10mm --margin-right 10mm %s %s 2>/dev/null',
            escapeshellarg($pageSize),
            escapeshellarg($orientation),
            escapeshellarg($tempHtml),
            escapeshellarg($tempPdf)
        );

        exec($cmd);

        $pdf = file_get_contents($tempPdf);

        unlink($tempHtml);
        unlink($tempPdf);

        if (empty($pdf)) {
            throw new \Exception('Failed to generate PDF with wkhtmltopdf');
        }

        return $pdf;
    }

    /**
     * Create simple PDF structure (fallback)
     */
    private function createSimplePdf(string $html, array $options = []): string
    {
        // Strip HTML and create basic text PDF
        $text = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</tr>', '</p>'], "\n", $html));
        $text = html_entity_decode($text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = wordwrap($text, 80, "\n", true);

        $title = $options['title'] ?? 'aReports Report';
        $creator = 'aReports PDF Generator';
        $date = date('D:YmdHis');

        // Basic PDF structure
        $pdf = "%PDF-1.4\n";

        // Objects
        $objects = [];

        // Catalog
        $objects[1] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";

        // Pages
        $objects[2] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";

        // Page
        $objects[3] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n";

        // Content stream
        $stream = "BT\n/F1 12 Tf\n50 800 Td\n";
        $stream .= "({$title}) Tj\n";
        $stream .= "0 -30 Td\n/F1 10 Tf\n";

        $lines = explode("\n", $text);
        $y = 0;
        foreach (array_slice($lines, 0, 60) as $line) {
            $line = str_replace(['(', ')', '\\'], ['\\(', '\\)', '\\\\'], trim($line));
            if (!empty($line)) {
                $stream .= "({$line}) Tj\n";
            }
            $stream .= "0 -14 Td\n";
        }
        $stream .= "ET";

        $objects[4] = "4 0 obj\n<< /Length " . strlen($stream) . " >>\nstream\n{$stream}\nendstream\nendobj\n";

        // Font
        $objects[5] = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

        // Info
        $objects[6] = "6 0 obj\n<< /Title ({$title}) /Creator ({$creator}) /CreationDate ({$date}) >>\nendobj\n";

        // Build PDF
        $body = '';
        $xref = [];
        foreach ($objects as $num => $obj) {
            $xref[$num] = strlen($pdf) + strlen($body);
            $body .= $obj;
        }

        $pdf .= $body;

        // XRef table
        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $xref[$i]);
        }

        // Trailer
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R /Info 6 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    /**
     * Format duration in seconds to HH:MM:SS
     */
    private function formatDuration(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        }
        return sprintf('%d:%02d', $minutes, $secs);
    }

    /**
     * Get export path
     */
    public function getExportPath(): string
    {
        return $this->exportPath;
    }

    /**
     * Clean old exports
     */
    public function cleanOldExports(int $daysOld = 7): int
    {
        $count = 0;
        $threshold = time() - ($daysOld * 86400);

        $files = glob($this->exportPath . '/*.pdf');
        foreach ($files as $file) {
            if (filemtime($file) < $threshold) {
                unlink($file);
                $count++;
            }
        }

        return $count;
    }
}
