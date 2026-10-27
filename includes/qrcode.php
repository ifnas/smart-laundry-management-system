<?php
declare(strict_types=1);

/**
 * Smart Laundry Pure-PHP QR Code Generator
 * Generates ISO/IEC 18004 compliant QR Codes in SVG format.
 * Zero external dependencies. Completely offline and local.
 */
class SmartLaundryQr {
    // Galois field 256 log and exp tables
    private static array $exp = [];
    private static array $log = [];
    private static bool $gfInit = false;

    private static function initGf(): void {
        if (self::$gfInit) return;
        self::$exp = array_fill(0, 512, 0);
        self::$log = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$exp[$i] = $x;
            self::$log[$x] = $i;
            $x <<= 1;
            if ($x & 256) $x ^= 0x11D; // Primitive polynomial x^8 + x^4 + x^3 + x^2 + 1
        }
        for ($i = 255; $i < 512; $i++) {
            self::$exp[$i] = self::$exp[$i - 255];
        }
        self::$gfInit = true;
    }

    private static function gfMul(int $x, int $y): int {
        if ($x === 0 || $y === 0) return 0;
        return self::$exp[self::$log[$x] + self::$log[$y]];
    }

    // Version definitions: [version, total_codewords, ec_codewords_M, num_blocks_M]
    // Versions 1 to 6 (sufficient for up to 134 bytes at Medium error correction)
    private static array $versions = [
        1 => ['total' => 26,  'ec' => 10, 'blocks' => 1, 'align' => []],
        2 => ['total' => 44,  'ec' => 16, 'blocks' => 1, 'align' => [6, 18]],
        3 => ['total' => 70,  'ec' => 26, 'blocks' => 1, 'align' => [6, 22]],
        4 => ['total' => 100, 'ec' => 36, 'blocks' => 2, 'align' => [6, 26]],
        5 => ['total' => 134, 'ec' => 48, 'blocks' => 2, 'align' => [6, 30]],
        6 => ['total' => 172, 'ec' => 64, 'blocks' => 4, 'align' => [6, 34]],
    ];

    public static function getSvg(string $text, int $size = 200, string $fg = '#0f172a', string $bg = '#ffffff'): string {
        $matrix = self::generateMatrix($text);
        $n = count($matrix);
        $padding = 4; // Quiet zone
        $totalSize = $n + ($padding * 2);

        $pathData = '';
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                if ($matrix[$r][$c] === 1) {
                    $x = $c + $padding;
                    $y = $r + $padding;
                    $pathData .= "M{$x},{$y}h1v1h-1z ";
                }
            }
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d" style="background:%s;shape-rendering:crispEdges;" role="img" aria-label="QR Code"><path d="%s" fill="%s"/></svg>',
            $totalSize, $totalSize, $size, $size, htmlspecialchars($bg, ENT_QUOTES), trim($pathData), htmlspecialchars($fg, ENT_QUOTES)
        );
    }

    public static function generateMatrix(string $text): array {
        self::initGf();
        $len = strlen($text);

        // Find appropriate version
        $version = 1;
        foreach (self::$versions as $v => $cfg) {
            $dataCap = $cfg['total'] - $cfg['ec'];
            // In byte mode: 4 bits mode + 8 bits length + data + 4 bits terminator
            $neededBytes = 2 + $len;
            if ($neededBytes <= $dataCap) {
                $version = $v;
                break;
            }
        }

        $vCfg = self::$versions[$version];
        $totalCodewords = $vCfg['total'];
        $ecCodewords = $vCfg['ec'];
        $dataCodewords = $totalCodewords - $ecCodewords;

        // 1. Bit buffer for byte mode
        $bits = '';
        $bits .= '0100'; // Byte mode indicator
        $bits .= str_pad(decbin($len), 8, '0', STR_PAD_LEFT);
        for ($i = 0; $i < $len; $i++) {
            $bits .= str_pad(decbin(ord($text[$i])), 8, '0', STR_PAD_LEFT);
        }

        // Terminator
        $bits .= '0000';
        // Pad to byte boundary
        if (strlen($bits) % 8 !== 0) {
            $bits .= str_repeat('0', 8 - (strlen($bits) % 8));
        }

        // Pad to capacity with 0xEC and 0x11
        $dataBytes = [];
        for ($i = 0; $i < strlen($bits); $i += 8) {
            $dataBytes[] = bindec(substr($bits, $i, 8));
        }
        $padBytes = [0xEC, 0x11];
        $padIdx = 0;
        while (count($dataBytes) < $dataCodewords) {
            $dataBytes[] = $padBytes[$padIdx];
            $padIdx = ($padIdx + 1) % 2;
        }

        // 2. Reed-Solomon Error Correction
        $numBlocks = $vCfg['blocks'];
        $blockEc = (int)($ecCodewords / $numBlocks);
        $blockData = (int)($dataCodewords / $numBlocks);

        $genPoly = self::getRsGenerator($blockEc);
        $allEc = [];
        $allData = [];

        for ($b = 0; $b < $numBlocks; $b++) {
            $chunk = array_slice($dataBytes, $b * $blockData, $blockData);
            $allData[] = $chunk;
            $allEc[] = self::calculateRsRemainder($chunk, $genPoly);
        }

        // Interleave data and EC codewords
        $finalCodewords = [];
        for ($i = 0; $i < $blockData; $i++) {
            for ($b = 0; $b < $numBlocks; $b++) {
                $finalCodewords[] = $allData[$b][$i];
            }
        }
        for ($i = 0; $i < $blockEc; $i++) {
            for ($b = 0; $b < $numBlocks; $b++) {
                $finalCodewords[] = $allEc[$b][$i];
            }
        }

        // 3. Populate matrix
        $size = 17 + ($version * 4);
        $matrix = array_fill(0, $size, array_fill(0, $size, null));
        $reserved = array_fill(0, $size, array_fill(0, $size, false));

        // Function patterns
        self::placeFinderPattern($matrix, $reserved, 0, 0);
        self::placeFinderPattern($matrix, $reserved, 0, $size - 7);
        self::placeFinderPattern($matrix, $reserved, $size - 7, 0);

        // Timing patterns
        for ($i = 8; $i < $size - 8; $i++) {
            $val = ($i % 2 === 0) ? 1 : 0;
            if (!$reserved[6][$i]) { $matrix[6][$i] = $val; $reserved[6][$i] = true; }
            if (!$reserved[$i][6]) { $matrix[$i][6] = $val; $reserved[$i][6] = true; }
        }

        // Dark module
        $matrix[(4 * $version) + 9][8] = 1;
        $reserved[(4 * $version) + 9][8] = true;

        // Alignment patterns for version 2+
        if (!empty($vCfg['align'])) {
            $coords = $vCfg['align'];
            foreach ($coords as $r) {
                foreach ($coords as $c) {
                    if ($matrix[$r][$c] === null && !$reserved[$r][$c]) {
                        self::placeAlignmentPattern($matrix, $reserved, $r, $c);
                    }
                }
            }
        }

        // Reserve format info area
        for ($i = 0; $i < 9; $i++) {
            $reserved[8][$i] = true;
            $reserved[$i][8] = true;
        }
        for ($i = $size - 8; $i < $size; $i++) {
            $reserved[8][$i] = true;
            $reserved[$i][8] = true;
        }

        // 4. Place data bits
        $bitStream = '';
        foreach ($finalCodewords as $cw) {
            $bitStream .= str_pad(decbin($cw), 8, '0', STR_PAD_LEFT);
        }
        // Remainder bits if needed
        $bitStream .= '0000000';

        $bitIdx = 0;
        $bitLen = strlen($bitStream);
        $up = true;

        for ($col = $size - 1; $col > 0; $col -= 2) {
            if ($col === 6) $col--; // Skip vertical timing pattern
            $rows = $up ? range($size - 1, 0) : range(0, $size - 1);
            foreach ($rows as $row) {
                for ($c = 0; $c < 2; $c++) {
                    $currCol = $col - $c;
                    if (!$reserved[$row][$currCol]) {
                        $bit = ($bitIdx < $bitLen) ? (int)$bitStream[$bitIdx] : 0;
                        // Apply Mask 0: (row + col) % 2 == 0
                        $mask = (($row + $currCol) % 2 === 0) ? 1 : 0;
                        $matrix[$row][$currCol] = $bit ^ $mask;
                        $bitIdx++;
                    }
                }
            }
            $up = !$up;
        }

        // 5. Format Information: EC Level M (00) + Mask 0 (000) = 00000
        // BCH(15, 5) code for 00000 with mask 101010000010010 = 101010000010010
        $formatBits = '101010000010010';
        self::placeFormatInfo($matrix, $formatBits);

        return $matrix;
    }

    private static function placeFinderPattern(array &$m, array &$r, int $top, int $left): void {
        for ($y = -1; $y <= 7; $y++) {
            for ($x = -1; $x <= 7; $x++) {
                $row = $top + $y;
                $col = $left + $x;
                if ($row >= 0 && $row < count($m) && $col >= 0 && $col < count($m)) {
                    if ($y >= 0 && $y <= 6 && $x >= 0 && $x <= 6) {
                        $isBlack = ($y === 0 || $y === 6 || $x === 0 || $x === 6 || ($y >= 2 && $y <= 4 && $x >= 2 && $x <= 4));
                        $m[$row][$col] = $isBlack ? 1 : 0;
                    } else {
                        $m[$row][$col] = 0;
                    }
                    $r[$row][$col] = true;
                }
            }
        }
    }

    private static function placeAlignmentPattern(array &$m, array &$r, int $centerR, int $centerC): void {
        for ($y = -2; $y <= 2; $y++) {
            for ($x = -2; $x <= 2; $x++) {
                $row = $centerR + $y;
                $col = $centerC + $x;
                $isBlack = (abs($y) === 2 || abs($x) === 2 || ($y === 0 && $x === 0));
                $m[$row][$col] = $isBlack ? 1 : 0;
                $r[$row][$col] = true;
            }
        }
    }

    private static function placeFormatInfo(array &$m, string $bits): void {
        $size = count($m);
        // Top-left finder area
        $seq = [
            [8, 0], [8, 1], [8, 2], [8, 3], [8, 4], [8, 5],
            [8, 7], [8, 8], [7, 8], [5, 8], [4, 8], [3, 8], [2, 8], [1, 8], [0, 8]
        ];
        for ($i = 0; $i < 15; $i++) {
            [$r, $c] = $seq[$i];
            $m[$r][$c] = (int)$bits[$i];
        }

        // Split around top-right and bottom-left
        for ($i = 0; $i < 7; $i++) {
            $m[$size - 1 - $i][8] = (int)$bits[$i];
        }
        for ($i = 7; $i < 15; $i++) {
            $m[8][$size - 15 + $i] = (int)$bits[$i];
        }
    }

    private static function getRsGenerator(int $degree): array {
        $poly = [1];
        for ($i = 0; $i < $degree; $i++) {
            $poly = self::polyMul($poly, [1, self::$exp[$i]]);
        }
        return $poly;
    }

    private static function polyMul(array $p1, array $p2): array {
        $result = array_fill(0, count($p1) + count($p2) - 1, 0);
        for ($i = 0; $i < count($p1); $i++) {
            for ($j = 0; $j < count($p2); $j++) {
                $result[$i + $j] ^= self::gfMul($p1[$i], $p2[$j]);
            }
        }
        return $result;
    }

    private static function calculateRsRemainder(array $data, array $gen): array {
        $degree = count($gen) - 1;
        $remainder = array_merge($data, array_fill(0, $degree, 0));
        for ($i = 0; $i < count($data); $i++) {
            $lead = $remainder[$i];
            if ($lead !== 0) {
                for ($j = 0; $j < count($gen); $j++) {
                    $remainder[$i + $j] ^= self::gfMul($gen[$j], $lead);
                }
            }
        }
        return array_slice($remainder, count($data));
    }
}

