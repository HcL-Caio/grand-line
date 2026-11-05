<?php
declare(strict_types=1);

namespace Atlas;

/**
 * Gerador de PDF mínimo e sem dependências: texto e tabelas em A4, fontes Helvetica
 * embutidas no formato (não exigem arquivo de fonte) e codificação WinAnsi (CP1252),
 * que cobre o português. Caracteres fora do CP1252 viram '?'.
 */
final class Pdf
{
    /** Larguras da Helvetica (milésimos de em) para ASCII 32–126. */
    private const WIDTHS = [
        278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278,
        556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556,
        1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778,
        667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556,
        333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556,
        556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584,
    ];

    /** @var list<string> */
    private array $pages = [];
    private string $stream = '';
    private float $y = 0.0;
    private readonly float $width;
    private readonly float $height;
    private readonly float $margin;
    /** @var list<string> */
    private array $repeatHeader = [];
    /** @var array{0:list<string>,1:list<float>}|null */
    private ?array $tableHead = null;

    public function __construct(private readonly string $title, bool $landscape = false, private readonly string $footer = 'Grand Line Atlas')
    {
        [$this->width, $this->height] = $landscape ? [841.89, 595.28] : [595.28, 841.89];
        $this->margin = 40.0;
        $this->newPage();
    }

    // ----------------------------------------------------------- conteúdo

    public function heading(string $text, int $size = 20): void
    {
        $this->ensure($size + 10);
        $this->y -= $size;
        $this->text($this->margin, $this->y, $size, true, $text, [0.09, 0.13, 0.14]);
        $this->y -= 8;
    }

    /** Linha de apoio pequena (cinza), com quebra automática. */
    public function note(string $text, int $size = 9): void
    {
        foreach ($this->wrap($text, $this->width - 2 * $this->margin, $size) as $line) {
            $this->ensure($size + 4);
            $this->y -= $size + 2;
            $this->text($this->margin, $this->y, $size, false, $line, [0.35, 0.38, 0.38]);
        }
        $this->y -= 4;
    }

    public function space(float $points): void
    {
        $this->y -= $points;
    }

    /**
     * @param list<string> $head  títulos das colunas
     * @param list<list<string>> $rows  linhas (já em texto simples)
     * @param list<float> $weights  proporção de largura de cada coluna
     * @param list<bool> $right  true para alinhar a coluna à direita (números)
     */
    public function table(array $head, array $rows, array $weights, array $right = []): void
    {
        $total = array_sum($weights);
        $available = $this->width - 2 * $this->margin;
        $columns = array_map(static fn (float $weight): float => $available * $weight / $total, $weights);
        $this->tableHead = [$head, $columns];
        $this->tableRight = $right;

        $this->drawTableHead();
        foreach ($rows as $index => $row) {
            $this->ensure(20, true);
            $this->y -= 20;
            if ($index % 2 === 1) {
                $this->rect($this->margin, $this->y - 5, $available, 20, [0.95, 0.95, 0.93]);
            }
            $this->drawRow($row, false);
        }
        if ($rows === []) {
            $this->y -= 20;
            $this->text($this->margin + 6, $this->y, 10, false, 'Nenhum registro encontrado para os filtros selecionados.', [0.35, 0.38, 0.38]);
        }
        $this->tableHead = null;
        $this->y -= 10;
    }

    /** @var list<bool> */
    private array $tableRight = [];

    private function drawTableHead(): void
    {
        [$head, $columns] = $this->tableHead;
        $this->ensure(24);
        $this->y -= 20;
        $this->rect($this->margin, $this->y - 5, array_sum($columns), 20, [0.09, 0.13, 0.14]);
        $this->drawRow($head, true);
    }

    /** @param list<string> $cells */
    private function drawRow(array $cells, bool $header): void
    {
        $columns = $this->tableHead[1];
        $x = $this->margin;
        foreach ($cells as $i => $cell) {
            $room = $columns[$i] - 12;
            $fit = $this->fit($cell, $room, 9, $header);
            $offset = ($this->tableRight[$i] ?? false) ? $room - $this->measure($fit, 9, $header) : 0.0;
            $this->text($x + 6 + $offset, $this->y, 9, $header, $fit, $header ? [1.0, 1.0, 1.0] : [0.1, 0.12, 0.12]);
            $x += $columns[$i];
        }
    }

    // ------------------------------------------------------------- saída

    public function output(): string
    {
        $this->closePage();
        $count = count($this->pages);
        $pages = [];
        foreach ($this->pages as $i => $content) {
            $label = $this->footer . '  ·  Página ' . ($i + 1) . ' de ' . $count;
            $pages[] = $content
                . $this->line($this->margin, 38, $this->width - $this->margin, 38, [0.85, 0.85, 0.82])
                . $this->textOp($this->margin, 26, 8, false, $label, [0.45, 0.47, 0.47]);
        }

        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        foreach ($pages as $i => $content) {
            $pageId = 5 + $i * 2;
            $kids[] = "{$pageId} 0 R";
            $objects[$pageId] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
                $this->width,
                $this->height,
                $pageId + 1
            );
            $objects[$pageId + 1] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $count . ' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $info = 6 + ($count - 1) * 2 + 1;
        $objects[$info] = '<< /Title <' . $this->utf16Hex($this->title) . '> /Producer (Grand Line Atlas) /CreationDate (D:' . gmdate('YmdHis') . 'Z) >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }
        $xref = strlen($pdf);
        $max = max(array_keys($objects));
        $pdf .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
        for ($id = 1; $id <= $max; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }
        $pdf .= "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R /Info {$info} 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
        return $pdf;
    }

    // --------------------------------------------------------- primitivas

    private function newPage(): void
    {
        $this->stream = '';
        $this->y = $this->height - $this->margin;
        // Faixa dourada discreta e marca no topo de cada página.
        $this->stream .= $this->rect($this->margin, $this->height - 28, 36, 3, [0.91, 0.74, 0.40], true);
        $this->stream .= $this->textOp($this->margin + 44, $this->height - 31, 8, true, 'GRAND LINE ATLAS', [0.45, 0.47, 0.47]);
        $this->y -= 22;
    }

    private function closePage(): void
    {
        $this->pages[] = $this->stream;
        $this->stream = '';
    }

    private function ensure(float $needed, bool $withHead = false): void
    {
        if ($this->y - $needed >= 52) {
            return;
        }
        $this->closePage();
        $this->newPage();
        if ($withHead && $this->tableHead !== null) {
            $this->drawTableHead();
        }
    }

    /** @param array{0:float,1:float,2:float} $rgb */
    private function rect(float $x, float $y, float $w, float $h, array $rgb, bool $return = false): string
    {
        $op = sprintf("%.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f\n", $rgb[0], $rgb[1], $rgb[2], $x, $y, $w, $h);
        if ($return) {
            return $op;
        }
        $this->stream .= $op;
        return '';
    }

    /** @param array{0:float,1:float,2:float} $rgb */
    private function line(float $x1, float $y1, float $x2, float $y2, array $rgb): string
    {
        return sprintf("%.3F %.3F %.3F RG 0.6 w %.2F %.2F m %.2F %.2F l S\n", $rgb[0], $rgb[1], $rgb[2], $x1, $y1, $x2, $y2);
    }

    /** @param array{0:float,1:float,2:float} $rgb */
    private function text(float $x, float $y, int $size, bool $bold, string $text, array $rgb): void
    {
        $this->stream .= $this->textOp($x, $y, $size, $bold, $text, $rgb);
    }

    /** @param array{0:float,1:float,2:float} $rgb */
    private function textOp(float $x, float $y, int $size, bool $bold, string $text, array $rgb): string
    {
        $encoded = $this->encode($text);
        $escaped = str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $encoded);
        return sprintf("BT %.3F %.3F %.3F rg /%s %d Tf %.2F %.2F Td (%s) Tj ET\n", $rgb[0], $rgb[1], $rgb[2], $bold ? 'F2' : 'F1', $size, $x, $y, $escaped);
    }

    private function encode(string $text): string
    {
        $converted = mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
        return is_string($converted) ? $converted : '?';
    }

    private function utf16Hex(string $text): string
    {
        return 'FEFF' . strtoupper(bin2hex((string)mb_convert_encoding($text, 'UTF-16BE', 'UTF-8')));
    }

    // ------------------------------------------------------------ medidas

    private function measure(string $text, int $size, bool $bold = false): float
    {
        $sum = 0;
        foreach (mb_str_split($text) as $char) {
            $sum += $this->charWidth($char);
        }
        return $sum * $size / 1000 * ($bold ? 1.06 : 1.0);
    }

    private function charWidth(string $char): int
    {
        $code = ord($char);
        if (strlen($char) === 1 && $code >= 32 && $code <= 126) {
            return self::WIDTHS[$code - 32];
        }
        if (class_exists(\Normalizer::class)) {
            $base = \Normalizer::normalize($char, \Normalizer::FORM_D);
            if (is_string($base) && $base !== '' && ord($base[0]) >= 32 && ord($base[0]) <= 126) {
                return self::WIDTHS[ord($base[0]) - 32];
            }
        }
        return 556;
    }

    private function fit(string $text, float $room, int $size, bool $bold = false): string
    {
        $text = trim((string)preg_replace('/\s+/u', ' ', $text));
        if ($this->measure($text, $size, $bold) <= $room) {
            return $text;
        }
        $chars = mb_str_split($text);
        while ($chars !== [] && $this->measure(implode('', $chars) . '...', $size, $bold) > $room) {
            array_pop($chars);
        }
        return rtrim(implode('', $chars)) . '...';
    }

    /** @return list<string> */
    private function wrap(string $text, float $room, int $size): array
    {
        $lines = [];
        $line = '';
        foreach (preg_split('/\s+/u', trim($text)) ?: [] as $word) {
            $try = $line === '' ? $word : $line . ' ' . $word;
            if ($line !== '' && $this->measure($try, $size) > $room) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $try;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }
        return $lines;
    }
}
