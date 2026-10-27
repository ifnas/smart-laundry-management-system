<?php
declare(strict_types=1);

/**
 * Smart Laundry Pure-PHP Standard PDF Generator
 * Compliant with ISO 32000-1 / PDF 1.4 Specification
 * Zero external dependencies. Generates valid binary PDF documents.
 */
class SmartLaundryPdf {
    protected float $w = 595.28; // A4 width in points (72 dpi)
    protected float $h = 841.89; // A4 height in points
    protected float $x = 40.0;
    protected float $y = 40.0;
    protected float $leftMargin = 40.0;
    protected float $rightMargin = 40.0;
    protected float $topMargin = 40.0;
    protected float $bottomMargin = 40.0;
    
    protected array $offsets = [];
    protected array $pages = [];
    protected int $currentPage = 0;
    protected string $buffer = '';
    protected string $currentFont = 'Helvetica';
    protected float $currentFontSize = 10.0;
    protected string $currentDrawColor = '0 0 0 RG';
    protected string $currentFillColor = '0 0 0 rg';
    protected float $currentLineWidth = 1.0;
    
    public function __construct() {
        $this->addPage();
    }
    
    public function addPage(): void {
        $this->currentPage++;
        $this->pages[$this->currentPage] = '';
        $this->x = $this->leftMargin;
        $this->y = $this->topMargin;
    }
    
    public function setDrawColor(int $r, int $g, int $b): void {
        $this->currentDrawColor = sprintf('%.3F %.3F %.3F RG', $r/255, $g/255, $b/255);
        $this->appendContent($this->currentDrawColor . "\n");
    }
    
    public function setFillColor(int $r, int $g, int $b): void {
        $this->currentFillColor = sprintf('%.3F %.3F %.3F rg', $r/255, $g/255, $b/255);
        $this->appendContent($this->currentFillColor . "\n");
    }
    
    public function setLineWidth(float $width): void {
        $this->currentLineWidth = $width;
        $this->appendContent(sprintf('%.2F w' . "\n", $width));
    }
    
    public function setFont(string $family, float $size = 10.0): void {
        $family = in_array($family, ['Helvetica', 'Helvetica-Bold', 'Helvetica-Oblique'], true) ? $family : 'Helvetica';
        $this->currentFont = $family;
        $this->currentFontSize = $size;
    }
    
    public function setXY(float $x, float $y): void {
        $this->x = $x;
        $this->y = $y;
    }
    
    public function getX(): float {
        return $this->x;
    }
    
    public function getY(): float {
        return $this->y;
    }
    
    public function line(float $x1, float $y1, float $x2, float $y2): void {
        // PDF coordinates start from bottom-left
        $pdfY1 = $this->h - $y1;
        $pdfY2 = $this->h - $y2;
        $this->appendContent(sprintf('%.2F %.2F m %.2F %.2F l S' . "\n", $x1, $pdfY1, $x2, $pdfY2));
    }
    
    public function rect(float $x, float $y, float $w, float $h, string $style = 'D'): void {
        $pdfY = $this->h - $y - $h;
        $op = 'S';
        if ($style === 'F') $op = 'f';
        elseif ($style === 'FD' || $style === 'DF') $op = 'B';
        $this->appendContent(sprintf('%.2F %.2F %.2F %.2F re %s' . "\n", $x, $pdfY, $w, $h, $op));
    }
    
    public function text(float $x, float $y, string $text): void {
        $pdfY = $this->h - $y;
        $escaped = $this->escapeText($text);
        $fontAlias = $this->getFontAlias($this->currentFont);
        $content = sprintf(
            "BT /%s %.2F Tf %.2F %.2F Td (%s) Tj ET\n",
            $fontAlias,
            $this->currentFontSize,
            $x,
            $pdfY,
            $escaped
        );
        $this->appendContent($content);
    }
    
    public function cell(float $w, float $h, string $text = '', int $border = 0, int $ln = 0, string $align = 'L', bool $fill = false): void {
        $x = $this->x;
        $y = $this->y;
        
        if ($fill) {
            $this->rect($x, $y, $w, $h, 'F');
        }
        if ($border === 1) {
            $this->rect($x, $y, $w, $h, 'D');
        } elseif ($border) {
            // Can be extended if specific borders needed
            $this->rect($x, $y, $w, $h, 'D');
        }
        
        if ($text !== '') {
            $textWidth = $this->getStringWidth($text);
            $tx = $x + 2; // Left padding
            if ($align === 'R') {
                $tx = $x + $w - $textWidth - 4;
            } elseif ($align === 'C') {
                $tx = $x + ($w - $textWidth) / 2;
            }
            // Vertical centering baseline approximation
            $ty = $y + ($h / 2) + ($this->currentFontSize * 0.35);
            $this->text($tx, $ty, $text);
        }
        
        if ($ln > 0) {
            $this->y += $h;
            $this->x = $this->leftMargin;
        } else {
            $this->x += $w;
        }
    }
    
    public function getStringWidth(string $text): float {
        // Average character width factor for standard Helvetica
        $len = strlen($text);
        $factor = ($this->currentFont === 'Helvetica-Bold') ? 0.58 : 0.52;
        return $len * $this->currentFontSize * $factor;
    }
    
    public function escapeText(string $s): string {
        $s = str_replace('\\', '\\\\', $s);
        $s = str_replace('(', '\\(', $s);
        $s = str_replace(')', '\\)', $s);
        // Convert non-ASCII or unsupported chars to clean ASCII
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $s) ?: $s;
    }
    
    protected function getFontAlias(string $font): string {
        switch ($font) {
            case 'Helvetica-Bold': return 'F2';
            case 'Helvetica-Oblique': return 'F3';
            default: return 'F1';
        }
    }
    
    protected function appendContent(string $data): void {
        if ($this->currentPage === 0) {
            $this->addPage();
        }
        $this->pages[$this->currentPage] .= $data;
    }
    
    public function render(): string {
        $nbPages = count($this->pages);
        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        
        // Object 1: Catalog
        $offsets[1] = strlen($out);
        $out .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        
        // Object 2: Pages
        $kids = [];
        for ($i = 1; $i <= $nbPages; $i++) {
            $kids[] = (3 + ($i - 1) * 2) . ' 0 R';
        }
        $offsets[2] = strlen($out);
        $out .= "2 0 obj\n<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count " . $nbPages . " >>\nendobj\n";
        
        // Objects for Pages and Contents
        $nextObj = 3;
        $contentObjs = [];
        for ($i = 1; $i <= $nbPages; $i++) {
            $pageObjId = $nextObj++;
            $contentObjId = $nextObj++;
            $contentObjs[$i] = $contentObjId;
            
            $offsets[$pageObjId] = strlen($out);
            $out .= $pageObjId . " 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 " . sprintf('%.2F %.2F', $this->w, $this->h) . "] /Contents " . $contentObjId . " 0 R /Resources << /Font << /F1 " . ($nextObj + ($nbPages - $i) * 2) . " 0 R /F2 " . ($nextObj + 1 + ($nbPages - $i) * 2) . " 0 R /F3 " . ($nextObj + 2 + ($nbPages - $i) * 2) . " 0 R >> >> >>\nendobj\n";
            
            $stream = $this->pages[$i];
            $filter = '';
            if (function_exists('gzcompress')) {
                $compressed = gzcompress($stream);
                if ($compressed !== false) {
                    $stream = $compressed;
                    $filter = " /Filter /FlateDecode";
                }
            }
            $offsets[$contentObjId] = strlen($out);
            $out .= $contentObjId . " 0 obj\n<< /Length " . strlen($stream) . $filter . " >>\nstream\n" . $stream . "\nendstream\nendobj\n";
        }
        
        // Font Objects
        $f1Id = $nextObj++;
        $offsets[$f1Id] = strlen($out);
        $out .= $f1Id . " 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n";
        
        $f2Id = $nextObj++;
        $offsets[$f2Id] = strlen($out);
        $out .= $f2Id . " 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>\nendobj\n";
        
        $f3Id = $nextObj++;
        $offsets[$f3Id] = strlen($out);
        $out .= $f3Id . " 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Oblique /Encoding /WinAnsiEncoding >>\nendobj\n";
        
        // XRef Table
        $xrefStart = strlen($out);
        $totalObjs = $nextObj;
        $out .= "xref\n0 " . $totalObjs . "\n";
        $out .= "0000000000 65535 f \n";
        for ($i = 1; $i < $totalObjs; $i++) {
            $out .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }
        
        // Trailer
        $out .= "trailer\n<< /Size " . $totalObjs . " /Root 1 0 R >>\n";
        $out .= "startxref\n" . $xrefStart . "\n%%EOF\n";
        
        return $out;
    }
    
    public function output(string $dest = 'I', string $name = 'document.pdf'): void {
        $pdf = $this->render();
        if ($dest === 'S') {
            return;
        }
        
        if (headers_sent()) {
            throw new RuntimeException("HTTP headers already sent; cannot stream PDF.");
        }
        
        header('Content-Type: application/pdf');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        if ($dest === 'D') {
            header('Content-Disposition: attachment; filename="' . rawurlencode($name) . '"');
        } else {
            header('Content-Disposition: inline; filename="' . rawurlencode($name) . '"');
        }
        
        echo $pdf;
        exit;
    }
}

