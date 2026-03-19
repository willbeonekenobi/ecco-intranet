<?php
if (!defined('ABSPATH')) exit;

/* =========================================================
   ECCO_Certificate_PDF
   Generates a professional landscape A4 PDF certificate
   without any external library dependencies.

   Usage:
     $pdf  = new ECCO_Certificate_PDF();
     $bytes = $pdf->generate([
         'employee_name'  => 'Jane Doe',
         'course_name'    => 'Electrical Safety',
         'date_completed' => '15 June 2025',
         'score'          => 85,           // percentage, optional
         'valid_until'    => '15 June 2026', // optional
         'site_name'      => 'ECCO',
         'cert_id'        => 'ECCO-2025-00042',
     ]);
   ========================================================= */

class ECCO_Certificate_PDF {

    /* A4 landscape (points @ 72dpi) */
    private float $W = 841.89;
    private float $H = 595.28;

    /* Helvetica character widths (1/1000 em) — full printable ASCII */
    private array $HV = [
        32=>278,33=>278,34=>355,35=>556,36=>556,37=>889,38=>667,39=>191,
        40=>333,41=>333,42=>389,43=>584,44=>278,45=>333,46=>278,47=>278,
        48=>556,49=>556,50=>556,51=>556,52=>556,53=>556,54=>556,55=>556,
        56=>556,57=>556,58=>278,59=>278,60=>584,61=>584,62=>584,63=>556,
        64=>1015,65=>667,66=>667,67=>722,68=>722,69=>667,70=>611,71=>778,
        72=>722,73=>278,74=>500,75=>667,76=>611,77=>833,78=>722,79=>778,
        80=>667,81=>778,82=>722,83=>667,84=>611,85=>722,86=>667,87=>944,
        88=>667,89=>667,90=>611,91=>278,92=>278,93=>278,94=>469,95=>556,
        96=>333,97=>556,98=>556,99=>500,100=>556,101=>556,102=>278,103=>556,
        104=>556,105=>222,106=>222,107=>500,108=>222,109=>833,110=>556,111=>556,
        112=>556,113=>556,114=>333,115=>500,116=>278,117=>556,118=>500,119=>722,
        120=>500,121=>500,122=>500,123=>334,124=>260,125=>334,126=>584,
    ];

    /* Bold variant widths (Helvetica-Bold) */
    private array $HB = [
        32=>278,33=>333,34=>474,35=>556,36=>556,37=>889,38=>722,39=>278,
        40=>333,41=>333,42=>389,43=>584,44=>278,45=>333,46=>278,47=>278,
        48=>556,49=>556,50=>556,51=>556,52=>556,53=>556,54=>556,55=>556,
        56=>556,57=>556,58=>333,59=>333,60=>584,61=>584,62=>584,63=>611,
        64=>975,65=>722,66=>722,67=>722,68=>722,69=>667,70=>611,71=>778,
        72=>722,73=>278,74=>556,75=>722,76=>611,77=>833,78=>722,79=>778,
        80=>667,81=>778,82=>722,83=>667,84=>611,85=>722,86=>667,87=>944,
        88=>667,89=>667,90=>611,91=>333,92=>278,93=>333,94=>584,95=>556,
        96=>278,97=>556,98=>611,99=>556,100=>611,101=>556,102=>333,103=>611,
        104=>611,105=>278,106=>278,107=>556,108=>278,109=>889,110=>611,111=>611,
        112=>611,113=>611,114=>389,115=>556,116=>333,117=>611,118=>556,119=>778,
        120=>556,121=>556,122=>500,123=>389,124=>280,125=>389,126=>584,
    ];

    /* ── Public entry-point ─────────────────────────────── */

    public function generate(array $data): string {

        $content = $this->buildPageContent($data);

        /* ── Objects ─────────────────────────────────────── */
        $pdf  = "%PDF-1.4\n%\xe2\xe3\xcf\xd3\n";
        $offs = [];

        /* 1 – Catalog */
        $offs[1] = strlen($pdf);
        $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";

        /* 2 – Pages */
        $offs[2] = strlen($pdf);
        $pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";

        /* 3 – Page (landscape A4) */
        $w   = round($this->W, 2);
        $h   = round($this->H, 2);
        $offs[3] = strlen($pdf);
        $pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 $w $h]"
              . " /Contents 4 0 R"
              . " /Resources << /Font << /F1 5 0 R /F2 6 0 R /F3 7 0 R >> >> >>\nendobj\n";

        /* 4 – Content stream */
        $len = strlen($content);
        $offs[4] = strlen($pdf);
        $pdf .= "4 0 obj\n<< /Length $len >>\nstream\n$content\nendstream\nendobj\n";

        /* 5 – Helvetica (regular) */
        $offs[5] = strlen($pdf);
        $pdf .= "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n";

        /* 6 – Helvetica-Bold */
        $offs[6] = strlen($pdf);
        $pdf .= "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>\nendobj\n";

        /* 7 – Times-Italic */
        $offs[7] = strlen($pdf);
        $pdf .= "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Times-Italic /Encoding /WinAnsiEncoding >>\nendobj\n";

        /* ── XRef ────────────────────────────────────────── */
        $xrefOff = strlen($pdf);
        $pdf .= "xref\n0 8\n";
        $pdf .= "0000000000 65535 f \r\n";
        for ($i = 1; $i <= 7; $i++) {
            $pdf .= str_pad($offs[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \r\n";
        }
        $pdf .= "trailer\n<< /Size 8 /Root 1 0 R >>\nstartxref\n$xrefOff\n%%EOF\n";

        return $pdf;
    }

    /* ── Helpers ─────────────────────────────────────────── */

    /** Encode a UTF-8 string as a PDF literal string (Latin-1 safe). */
    private function str(string $text): string {
        if (function_exists('iconv')) {
            $s = iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);
        } else {
            $s = preg_replace('/[^\x00-\x7F]/', '?', $text);
        }
        $s = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
        return "($s)";
    }

    /**
     * Convert UTF-8 to Latin-1 for width calculations.
     * Returns an ASCII/Latin-1 string.
     */
    private function toLatin1(string $text): string {
        if (function_exists('iconv')) {
            return (string) iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);
        }
        return preg_replace('/[^\x00-\x7F]/', '?', $text) ?? '';
    }

    /** Approximate text width in pt for a given font + size. */
    private function tw(string $latin1, float $size, bool $bold = false): float {
        $table = $bold ? $this->HB : $this->HV;
        $w = 0;
        $n = strlen($latin1);
        for ($i = 0; $i < $n; $i++) {
            $c  = ord($latin1[$i]);
            $w += $table[$c] ?? 556;
        }
        return ($w / 1000) * $size;
    }

    /** X coordinate that centres a string on the page. */
    private function cx(string $latin1, float $size, bool $bold = false): float {
        return round(($this->W - $this->tw($latin1, $size, $bold)) / 2, 3);
    }

    /** Emit one positioned BT…ET text block. */
    private function textBlock(
        string $text,       // raw input (UTF-8)
        float  $x,
        float  $y,
        float  $size,
        string $font,       // F1=Helvetica, F2=Helvetica-Bold, F3=Times-Italic
        string $r,          // red   0–1
        string $g,          // green
        string $b           // blue
    ): string {
        $pstr = $this->str($text);
        $xr   = round($x, 3);
        $yr   = round($y, 3);
        return "BT /{$font} {$size} Tf {$r} {$g} {$b} rg {$xr} {$yr} Td {$pstr} Tj ET";
    }

    /* ── Page content stream ─────────────────────────────── */

    private function buildPageContent(array $d): string {

        $W = $this->W;
        $H = $this->H;

        /* ---- Prepare text strings ---- */
        $site  = strtoupper($this->toLatin1($d['site_name']      ?? 'ECCO'));
        $emp   = $this->toLatin1($d['employee_name'] ?? 'Employee');
        $crs   = $this->toLatin1($d['course_name']   ?? 'Course');
        $date  = $this->toLatin1($d['date_completed'] ?? date('d F Y'));
        $score = isset($d['score']) ? round((float)$d['score']) . '%' : '';
        $valid = $this->toLatin1($d['valid_until'] ?? '');
        $cid   = $this->toLatin1($d['cert_id']    ?? '');

        /* Clamp long strings */
        if (strlen($emp) > 52) $emp = substr($emp, 0, 49) . '...';
        if (strlen($crs) > 68) $crs = substr($crs, 0, 65) . '...';

        $ops = [];

        /* ══ BACKGROUND ════════════════════════════════════ */
        $ops[] = '0.976 0.961 0.929 rg';
        $ops[] = "0 0 $W $H re f";

        /* ══ OUTER BORDER – navy ════════════════════════════ */
        $ops[] = 'q 0.051 0.200 0.420 RG 3.5 w 16 16 809 563 re S Q';

        /* ══ INNER BORDER – gold ════════════════════════════ */
        $ops[] = 'q 0.722 0.541 0.102 RG 1.5 w 24 24 793 547 re S Q';

        /* ══ CORNER ORNAMENTS – small gold squares ══════════ */
        $ops[] = 'q 0.722 0.541 0.102 rg';
        $ops[] = '27 27 10 10 re f';
        $ops[] = round($W - 37) . ' 27 10 10 re f';
        $ops[] = '27 ' . round($H - 37) . ' 10 10 re f';
        $ops[] = round($W - 37) . ' ' . round($H - 37) . ' 10 10 re f';
        $ops[] = 'Q';

        /* ══ HEADER BAND – navy ═════════════════════════════ */
        // bottom of band = 488, height = 80 → top = 568
        $ops[] = 'q 0.051 0.200 0.420 rg 30 488 781 80 re f Q';

        /* ══ GOLD HORIZONTAL DIVIDER ════════════════════════ */
        $ops[] = 'q 0.722 0.541 0.102 RG 0.8 w 80 270 m 761 270 l S Q';

        /* ══ HEADER TEXT ════════════════════════════════════ */

        // Site / company name  (white, bold)
        $siteFs = 28.0;
        $ops[] = $this->textBlock($site, $this->cx($site, $siteFs, true), 530, $siteFs, 'F2', '1','1','1');

        // "CERTIFICATE OF COMPLETION" subtitle
        $sub  = 'CERTIFICATE OF COMPLETION';
        $subFs = 10.0;
        $ops[] = $this->textBlock($sub, $this->cx($sub, $subFs), 500, $subFs, 'F1', '0.72','0.85','1');

        /* ══ MAIN CONTENT ═══════════════════════════════════ */

        // "This is to certify that"
        $tag  = 'This is to certify that';
        $tagFs = 13.0;
        $ops[] = $this->textBlock($tag, $this->cx($tag, $tagFs), 436, $tagFs, 'F3', '0.40','0.40','0.40');

        // Employee name (large, navy bold)
        $empFs = 36.0;
        $ops[] = $this->textBlock($emp, $this->cx($emp, $empFs, true), 382, $empFs, 'F2', '0.051','0.200','0.420');

        // "has successfully completed"
        $hsc  = 'has successfully completed';
        $hscFs = 13.0;
        $ops[] = $this->textBlock($hsc, $this->cx($hsc, $hscFs), 342, $hscFs, 'F3', '0.40','0.40','0.40');

        // Course name (medium, navy bold)
        $crsFs = 22.0;
        $ops[] = $this->textBlock($crs, $this->cx($crs, $crsFs, true), 304, $crsFs, 'F2', '0.051','0.200','0.420');

        /* ══ DETAILS BELOW DIVIDER ══════════════════════════ */

        $detFs = 11.0;
        $detR  = '0.30'; $detG = '0.30'; $detB = '0.30';

        // Date completed
        $dateLabel = 'Date Completed:  ' . $date;
        $ops[] = $this->textBlock($dateLabel, $this->cx($dateLabel, $detFs), 248, $detFs, 'F1', $detR,$detG,$detB);

        $nextY = 228.0;

        // Score (optional)
        if ($score !== '') {
            $scoreLabel = 'Score Achieved:  ' . $score;
            $ops[] = $this->textBlock($scoreLabel, $this->cx($scoreLabel, $detFs), $nextY, $detFs, 'F1', $detR,$detG,$detB);
            $nextY -= 20;
        }

        // Valid until (optional, in red-ish)
        if ($valid !== '') {
            $validLabel = 'Valid Until:  ' . $valid;
            $ops[] = $this->textBlock($validLabel, $this->cx($validLabel, $detFs), $nextY, $detFs, 'F1', '0.65','0.12','0.12');
        }

        /* ══ CERTIFICATE ID FOOTER ══════════════════════════ */
        if ($cid !== '') {
            $cidLabel = 'Certificate No:  ' . $cid;
            $ops[] = $this->textBlock($cidLabel, $this->cx($cidLabel, 8.0), 40, 8.0, 'F1', '0.60','0.60','0.60');
        }

        return implode("\n", $ops);
    }
}


/* =========================================================
   PUBLIC HELPER  –  ecco_generate_certificate_pdf()
   Generates the PDF, writes it to a temp file and returns
   the file path. Returns false on failure.
   ========================================================= */

function ecco_generate_certificate_pdf(array $data): string|false {

    try {
        $gen   = new ECCO_Certificate_PDF();
        $bytes = $gen->generate($data);

        $tmp = wp_tempnam('ecco-cert-') . '.pdf';
        if (file_put_contents($tmp, $bytes) === false) {
            error_log('ECCO Courses: could not write PDF to ' . $tmp);
            return false;
        }
        return $tmp;

    } catch (\Throwable $e) {
        error_log('ECCO Courses: PDF generation failed: ' . $e->getMessage());
        return false;
    }
}
