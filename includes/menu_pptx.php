<?php
declare(strict_types=1);

const PPTX_DRAWINGML_NS = 'http://schemas.openxmlformats.org/drawingml/2006/main';

function normalizeMenuToken(string $value): string
{
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    return $value;
}

function normalizeForMatch(string $value): string
{
    $value = mb_strtoupper($value, 'UTF-8');
    $map = [
        'Ç' => 'C',
        'Ğ' => 'G',
        'İ' => 'I',
        'I' => 'I',
        'Ö' => 'O',
        'Ş' => 'S',
        'Ü' => 'U',
    ];
    return strtr($value, $map);
}

function parseDateDmy(string $raw): ?string
{
    $raw = trim($raw);
    if (!preg_match('/^(\d{1,2})[.\-\/](\d{1,2})[.\-\/](\d{4})$/', $raw, $m)) {
        return null;
    }
    $d = (int)$m[1];
    $mo = (int)$m[2];
    $y = (int)$m[3];
    if (!checkdate($mo, $d, $y)) {
        return null;
    }
    return sprintf('%04d-%02d-%02d', $y, $mo, $d);
}

/**
 * Tek bir hücrenin metnini okur.
 */
function pptxExtractCellText(DOMElement $tc): string
{
    $xpath = new DOMXPath($tc->ownerDocument);
    $xpath->registerNamespace('a', PPTX_DRAWINGML_NS);
    $parts = [];
    foreach ($xpath->query('.//a:t', $tc) as $t) {
        $parts[] = normalizeMenuToken((string)$t->textContent);
    }

    return normalizeMenuToken(implode(' ', $parts));
}

/**
 * Slideteki tüm tabloları satır/sütun matrisi olarak döndürür.
 *
 * @return list<list<list<string>>>
 */
function pptxExtractTableMatricesFromSlideXml(string $slideXml): array
{
    $doc = new DOMDocument();
    if (!@$doc->loadXML($slideXml)) {
        return [];
    }
    $matrices = [];
    $tbls = $doc->getElementsByTagNameNS(PPTX_DRAWINGML_NS, 'tbl');
    for ($ti = 0; $ti < $tbls->length; $ti++) {
        $tbl = $tbls->item($ti);
        if (!$tbl instanceof DOMElement) {
            continue;
        }
        $rows = [];
        foreach ($tbl->childNodes as $trNode) {
            if (!$trNode instanceof DOMElement
                || $trNode->namespaceURI !== PPTX_DRAWINGML_NS
                || $trNode->localName !== 'tr') {
                continue;
            }
            $row = [];
            foreach ($trNode->childNodes as $tcNode) {
                if (!$tcNode instanceof DOMElement
                    || $tcNode->namespaceURI !== PPTX_DRAWINGML_NS
                    || $tcNode->localName !== 'tc') {
                    continue;
                }
                $row[] = pptxExtractCellText($tcNode);
            }
            if ($row !== []) {
                $rows[] = $row;
            }
        }
        if ($rows !== []) {
            $matrices[] = $rows;
        }
    }

    return $matrices;
}

/**
 * Pazartesi–Cuma başlıklı menü tablosunu seçer (ilk satır gün adları).
 *
 * @param list<list<list<string>>> $allMatrices
 * @return list<list<string>>|null
 */
function pptxSelectWeeklyMenuGrid(array $allMatrices): ?array
{
    $needles = ['PAZARTESI', 'SALI', 'CARSAMBA', 'PERSEMBE', 'CUMA'];
    foreach ($allMatrices as $grid) {
        if ($grid === [] || count($grid[0] ?? []) < 5) {
            continue;
        }
        $ok = true;
        for ($j = 0; $j < 5; $j++) {
            if (normalizeForMatch((string)($grid[0][$j] ?? '')) !== $needles[$j]) {
                $ok = false;
                break;
            }
        }
        if ($ok) {
            return $grid;
        }
    }

    return null;
}

/**
 * Bir sütundaki yemek satırlarını (başlık hariç) sırayla döndürür; boşları atlar.
 *
 * @param list<list<string>> $grid
 * @return list<string>
 */
function pptxColumnMenuLines(array $grid, int $colIndex): array
{
    $items = [];
    for ($r = 1; $r < count($grid); $r++) {
        $cell = normalizeMenuToken((string)($grid[$r][$colIndex] ?? ''));
        if ($cell !== '' && $cell !== '-') {
            $items[] = $cell;
        }
    }

    return $items;
}

/**
 * @param list<string> $lines
 * @return array{soup:string,main_course:string,side_dish:string,salad:string,dessert:string,menu_items:string}
 */
function pptxLinesToDbFields(array $lines): array
{
    $json = $lines === [] ? '[]' : json_encode(array_values($lines), JSON_UNESCAPED_UNICODE);
    $firstFive = array_slice($lines, 0, 5);
    $pad = array_pad($firstFive, 5, '-');

    return [
        'soup' => $pad[0] !== '' ? $pad[0] : '-',
        'main_course' => $pad[1] !== '' ? $pad[1] : '-',
        'side_dish' => $pad[2] !== '' ? $pad[2] : '-',
        'salad' => $pad[3] !== '' ? $pad[3] : '-',
        'dessert' => $pad[4] !== '' ? $pad[4] : '-',
        'menu_items' => $json,
    ];
}

/**
 * @return array{menus: array<int, array{menu_date:string,soup:string,main_course:string,side_dish:string,salad:string,dessert:string,menu_items:string}>, range: string}
 */
function parseWeeklyMenuFromPptx(string $pptxPath): array
{
    if (!is_file($pptxPath)) {
        throw new RuntimeException('PPTX dosyası bulunamadı.');
    }

    $zip = new ZipArchive();
    if ($zip->open($pptxPath) !== true) {
        throw new RuntimeException('PPTX dosyası açılamadı.');
    }

    $slides = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string)$zip->getNameIndex($i);
        if (preg_match('#^ppt/slides/slide\d+\.xml$#', $name)) {
            $slides[] = $name;
        }
    }
    sort($slides, SORT_NATURAL);

    $tokens = [];
    $allTableMatrices = [];
    foreach ($slides as $slideName) {
        $xml = (string)$zip->getFromName($slideName);
        if ($xml === '') {
            continue;
        }
        foreach (pptxExtractTableMatricesFromSlideXml($xml) as $m) {
            $allTableMatrices[] = $m;
        }
        $doc = new DOMDocument();
        if (!@$doc->loadXML($xml)) {
            continue;
        }
        $textNodes = $doc->getElementsByTagNameNS(PPTX_DRAWINGML_NS, 't');
        foreach ($textNodes as $node) {
            $text = normalizeMenuToken((string)$node->textContent);
            if ($text !== '') {
                $tokens[] = $text;
            }
        }
    }
    $zip->close();

    if (empty($tokens)) {
        throw new RuntimeException('Sunum içinde metin bulunamadı.');
    }

    $joined = implode(' ', $tokens);
    if (!preg_match('/(\d{1,2}[.\-\/]\d{1,2}[.\-\/]\d{4})\s*\/\s*(\d{1,2}[.\-\/]\d{1,2}[.\-\/]\d{4})/u', $joined, $rm)) {
        throw new RuntimeException('Haftalık tarih aralığı bulunamadı.');
    }
    $startDate = parseDateDmy($rm[1]);
    $endDate = parseDateDmy($rm[2]);
    if ($startDate === null || $endDate === null) {
        throw new RuntimeException('Tarih aralığı okunamadı.');
    }

    $grid = pptxSelectWeeklyMenuGrid($allTableMatrices);
    $menus = [];
    $startTs = strtotime($startDate . ' 12:00:00');

    if ($grid !== null) {
        for ($c = 0; $c < 5; $c++) {
            $lines = pptxColumnMenuLines($grid, $c);
            $fields = pptxLinesToDbFields($lines);
            $date = date('Y-m-d', strtotime('+' . $c . ' day', $startTs));
            $menus[] = array_merge(['menu_date' => $date], $fields);
        }
    } else {
        $weekdayNeedles = ['PAZARTESI', 'SALI', 'CARSAMBA', 'PERSEMBE', 'CUMA'];
        $startIdx = -1;
        for ($i = 0; $i < count($tokens); $i++) {
            $norm = normalizeForMatch($tokens[$i]);
            if ($norm === $weekdayNeedles[0]) {
                $slice = array_slice($tokens, $i, 5);
                if (count($slice) < 5) {
                    continue;
                }
                $ok = true;
                for ($j = 0; $j < 5; $j++) {
                    if (normalizeForMatch((string)$slice[$j]) !== $weekdayNeedles[$j]) {
                        $ok = false;
                        break;
                    }
                }
                if ($ok) {
                    $startIdx = $i;
                    break;
                }
            }
        }
        if ($startIdx < 0) {
            throw new RuntimeException('Hafta günleri satırı bulunamadı.');
        }

        $content = [];
        for ($i = $startIdx + 5; $i < count($tokens); $i++) {
            $t = normalizeMenuToken($tokens[$i]);
            $n = normalizeForMatch($t);
            if ($n === '' || strncmp($n, 'NOT', 3) === 0 || strpos($n, 'AFIYET') !== false || strpos($n, 'DEDEM') !== false) {
                break;
            }
            $content[] = $t;
        }
        if (count($content) < 15) {
            throw new RuntimeException('Tablodaki menü hücreleri okunamadı (tablo XML bulunamadı, metin sırası da yetersiz).');
        }

        $content = array_slice($content, 0, 25);
        while (count($content) < 25) {
            $content[] = '';
        }
        $matrix = [];
        for ($r = 0; $r < 5; $r++) {
            $matrix[$r] = [];
            for ($c = 0; $c < 5; $c++) {
                $matrix[$r][$c] = normalizeMenuToken((string)$content[$r * 5 + $c]);
            }
        }

        for ($c = 0; $c < 5; $c++) {
            $lines = [];
            for ($r = 0; $r < 5; $r++) {
                $cell = $matrix[$r][$c];
                if ($cell !== '' && $cell !== '-') {
                    $lines[] = $cell;
                }
            }
            $fields = pptxLinesToDbFields($lines);
            $date = date('Y-m-d', strtotime('+' . $c . ' day', $startTs));
            $menus[] = array_merge(['menu_date' => $date], $fields);
        }
    }

    return [
        'menus' => $menus,
        'range' => $startDate . ' / ' . $endDate,
    ];
}
