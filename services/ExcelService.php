<?php
/**
 * Excel Export Service
 * Generates Excel files using native PHP (XLSX format)
 */

namespace aReports\Services;

class ExcelService
{
    private string $exportPath;
    private array $sheets = [];
    private string $currentSheet = 'Sheet1';

    public function __construct()
    {
        $app = \aReports\Core\App::getInstance();
        $this->exportPath = $app->getConfig()['exports_path'] ?? dirname(__DIR__) . '/storage/exports';

        if (!is_dir($this->exportPath)) {
            mkdir($this->exportPath, 0755, true);
        }

        $this->sheets[$this->currentSheet] = [];
    }

    /**
     * Create a new sheet
     */
    public function createSheet(string $name): self
    {
        $this->sheets[$name] = [];
        $this->currentSheet = $name;
        return $this;
    }

    /**
     * Set active sheet
     */
    public function setActiveSheet(string $name): self
    {
        if (!isset($this->sheets[$name])) {
            $this->sheets[$name] = [];
        }
        $this->currentSheet = $name;
        return $this;
    }

    /**
     * Set cell value
     */
    public function setCellValue(string $cell, mixed $value): self
    {
        $this->sheets[$this->currentSheet][$cell] = $value;
        return $this;
    }

    /**
     * Set row data
     */
    public function setRowData(int $row, array $data): self
    {
        $col = 'A';
        foreach ($data as $value) {
            $this->sheets[$this->currentSheet][$col . $row] = $value;
            $col++;
        }
        return $this;
    }

    /**
     * Generate report Excel
     */
    public function generateReport(string $title, array $data, array $columns, array $options = []): array
    {
        $filename = $options['filename'] ?? 'report_' . date('Y-m-d_His') . '.xlsx';
        $filepath = $this->exportPath . '/' . $filename;

        try {
            $this->sheets = ['Report' => []];
            $this->currentSheet = 'Report';

            // Title row
            $this->setCellValue('A1', $title);

            // Date range
            $dateRange = $options['date_range'] ?? date('d/m/Y');
            $this->setCellValue('A2', "Period: {$dateRange}");
            $this->setCellValue('A3', "Generated: " . date('d/m/Y H:i:s'));

            // Headers (row 5)
            $col = 'A';
            foreach ($columns as $key => $label) {
                $this->setCellValue($col . '5', $label);
                $col++;
            }

            // Data rows (starting row 6)
            $row = 6;
            foreach ($data as $record) {
                $col = 'A';
                foreach ($columns as $key => $label) {
                    $value = $record[$key] ?? '';
                    // Format duration columns
                    if (strpos($key, 'time') !== false && is_numeric($value)) {
                        $value = $this->formatDuration((int)$value);
                    }
                    $this->setCellValue($col . $row, $value);
                    $col++;
                }
                $row++;
            }

            // Summary row
            if (!empty($options['summary'])) {
                $row++;
                $this->setCellValue('A' . $row, 'Summary:');
                $row++;
                foreach ($options['summary'] as $label => $value) {
                    $this->setCellValue('A' . $row, $label);
                    $this->setCellValue('B' . $row, $value);
                    $row++;
                }
            }

            // Save file
            $this->save($filepath);

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
     * Generate CDR report Excel
     */
    public function generateCdrReport(array $calls, array $options = []): array
    {
        $columns = [
            'calldate' => 'Date/Time',
            'src' => 'Source',
            'dst' => 'Destination',
            'duration' => 'Duration (sec)',
            'billsec' => 'Bill Sec',
            'disposition' => 'Disposition',
            'queue' => 'Queue',
            'agent' => 'Agent',
        ];

        return $this->generateReport('Call Detail Records', $calls, $columns, $options);
    }

    /**
     * Generate Agent report Excel
     */
    public function generateAgentReport(array $agents, array $options = []): array
    {
        $columns = [
            'agent_name' => 'Agent',
            'extension' => 'Extension',
            'calls_handled' => 'Calls Handled',
            'calls_missed' => 'Calls Missed',
            'total_talk_time' => 'Talk Time',
            'total_hold_time' => 'Hold Time',
            'avg_talk_time' => 'Avg Talk Time',
            'answer_rate' => 'Answer Rate %',
        ];

        return $this->generateReport('Agent Performance Report', $agents, $columns, $options);
    }

    /**
     * Generate Queue report Excel
     */
    public function generateQueueReport(array $queues, array $options = []): array
    {
        $columns = [
            'queue_name' => 'Queue',
            'total_calls' => 'Total Calls',
            'answered' => 'Answered',
            'abandoned' => 'Abandoned',
            'abandon_rate' => 'Abandon Rate %',
            'sla_percentage' => 'SLA %',
            'avg_wait_time' => 'Avg Wait Time',
            'avg_talk_time' => 'Avg Talk Time',
        ];

        return $this->generateReport('Queue Performance Report', $queues, $columns, $options);
    }

    /**
     * Save to XLSX file
     */
    public function save(string $filepath): bool
    {
        $zip = new \ZipArchive();

        if ($zip->open($filepath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \Exception("Cannot create Excel file: {$filepath}");
        }

        // Add required files
        $zip->addFromString('[Content_Types].xml', $this->getContentTypes());
        $zip->addFromString('_rels/.rels', $this->getRels());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->getWorkbookRels());
        $zip->addFromString('xl/workbook.xml', $this->getWorkbook());
        $zip->addFromString('xl/styles.xml', $this->getStyles());
        $zip->addFromString('xl/sharedStrings.xml', $this->getSharedStrings());

        // Add sheets
        $sheetIndex = 1;
        foreach ($this->sheets as $name => $data) {
            $zip->addFromString("xl/worksheets/sheet{$sheetIndex}.xml", $this->getSheetXml($data));
            $sheetIndex++;
        }

        $zip->close();

        return true;
    }

    /**
     * Export to CSV (simpler alternative)
     */
    public function exportToCsv(array $data, array $columns, string $filename = null): array
    {
        $filename = $filename ?? 'export_' . date('Y-m-d_His') . '.csv';
        $filepath = $this->exportPath . '/' . $filename;

        try {
            $fp = fopen($filepath, 'w');

            // BOM for Excel UTF-8 compatibility
            fwrite($fp, "\xEF\xBB\xBF");

            // Headers
            fputcsv($fp, array_values($columns));

            // Data
            foreach ($data as $row) {
                $csvRow = [];
                foreach ($columns as $key => $label) {
                    $value = $row[$key] ?? '';
                    if (strpos($key, 'time') !== false && is_numeric($value)) {
                        $value = $this->formatDuration((int)$value);
                    }
                    $csvRow[] = $value;
                }
                fputcsv($fp, $csvRow);
            }

            fclose($fp);

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
     * Get Content Types XML
     */
    private function getContentTypes(): string
    {
        $sheets = '';
        $index = 1;
        foreach ($this->sheets as $name => $data) {
            $sheets .= '<Override PartName="/xl/worksheets/sheet' . $index . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $index++;
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
    <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
    ' . $sheets . '
</Types>';
    }

    /**
     * Get Rels XML
     */
    private function getRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
    }

    /**
     * Get Workbook Rels XML
     */
    private function getWorkbookRels(): string
    {
        $sheets = '';
        $index = 1;
        foreach ($this->sheets as $name => $data) {
            $sheets .= '<Relationship Id="rId' . ($index + 2) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $index . '.xml"/>';
            $index++;
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
    ' . $sheets . '
</Relationships>';
    }

    /**
     * Get Workbook XML
     */
    private function getWorkbook(): string
    {
        $sheets = '';
        $index = 1;
        foreach ($this->sheets as $name => $data) {
            $sheets .= '<sheet name="' . htmlspecialchars($name) . '" sheetId="' . $index . '" r:id="rId' . ($index + 2) . '"/>';
            $index++;
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        ' . $sheets . '
    </sheets>
</workbook>';
    }

    /**
     * Get Styles XML
     */
    private function getStyles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <fonts count="2">
        <font><sz val="11"/><name val="Calibri"/></font>
        <font><b/><sz val="11"/><name val="Calibri"/></font>
    </fonts>
    <fills count="3">
        <fill><patternFill patternType="none"/></fill>
        <fill><patternFill patternType="gray125"/></fill>
        <fill><patternFill patternType="solid"><fgColor rgb="FF4472C4"/></patternFill></fill>
    </fills>
    <borders count="1">
        <border><left/><right/><top/><bottom/><diagonal/></border>
    </borders>
    <cellStyleXfs count="1">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
    </cellStyleXfs>
    <cellXfs count="3">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
        <xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>
        <xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>
    </cellXfs>
</styleSheet>';
    }

    /**
     * Get Shared Strings XML
     */
    private function getSharedStrings(): string
    {
        $strings = [];
        foreach ($this->sheets as $data) {
            foreach ($data as $value) {
                if (is_string($value) && !is_numeric($value)) {
                    $strings[] = $value;
                }
            }
        }

        $strings = array_unique($strings);
        $count = count($strings);

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . $count . '" uniqueCount="' . $count . '">';

        foreach ($strings as $string) {
            $xml .= '<si><t>' . htmlspecialchars($string) . '</t></si>';
        }

        $xml .= '</sst>';

        return $xml;
    }

    /**
     * Get Sheet XML
     */
    private function getSheetXml(array $data): string
    {
        // Build shared strings index
        $stringIndex = [];
        $index = 0;
        foreach ($this->sheets as $sheetData) {
            foreach ($sheetData as $value) {
                if (is_string($value) && !is_numeric($value) && !isset($stringIndex[$value])) {
                    $stringIndex[$value] = $index++;
                }
            }
        }

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<sheetData>';

        // Group data by rows
        $rows = [];
        foreach ($data as $cell => $value) {
            preg_match('/([A-Z]+)(\d+)/', $cell, $matches);
            $col = $matches[1];
            $row = (int)$matches[2];
            $rows[$row][$col] = $value;
        }

        ksort($rows);

        foreach ($rows as $rowNum => $cols) {
            $xml .= '<row r="' . $rowNum . '">';
            ksort($cols);

            foreach ($cols as $col => $value) {
                $cellRef = $col . $rowNum;

                if (is_numeric($value)) {
                    $xml .= '<c r="' . $cellRef . '"><v>' . $value . '</v></c>';
                } else {
                    $sIndex = $stringIndex[$value] ?? 0;
                    $xml .= '<c r="' . $cellRef . '" t="s"><v>' . $sIndex . '</v></c>';
                }
            }

            $xml .= '</row>';
        }

        $xml .= '</sheetData></worksheet>';

        return $xml;
    }

    /**
     * Format duration
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

        foreach (['*.xlsx', '*.csv'] as $pattern) {
            $files = glob($this->exportPath . '/' . $pattern);
            foreach ($files as $file) {
                if (filemtime($file) < $threshold) {
                    unlink($file);
                    $count++;
                }
            }
        }

        return $count;
    }
}
