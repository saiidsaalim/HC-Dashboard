<?php

$path = __DIR__.'/template-import-sap.xlsx';
$archive = new ZipArchive;
if ($archive->open($path) !== true) {
    throw new RuntimeException('Workbook cannot be opened for read-only inspection.');
}

$sharedStrings = [];
$sharedXml = $archive->getFromName('xl/sharedStrings.xml');
if ($sharedXml !== false) {
    $sharedDocument = simplexml_load_string($sharedXml, SimpleXMLElement::class, LIBXML_NONET);
    foreach ($sharedDocument->si as $item) {
        $text = (string) ($item->t ?? '');
        foreach ($item->r as $run) {
            $text .= (string) ($run->t ?? '');
        }
        $sharedStrings[] = $text;
    }
}

$sheetXml = $archive->getFromName('xl/worksheets/sheet1.xml');
$stylesXml = $archive->getFromName('xl/styles.xml');
$archive->close();
if ($sheetXml === false) {
    throw new RuntimeException('The first worksheet is missing.');
}

$sheet = simplexml_load_string($sheetXml, SimpleXMLElement::class, LIBXML_NONET);
$styles = $stylesXml === false ? null : simplexml_load_string($stylesXml, SimpleXMLElement::class, LIBXML_NONET);
$customFormats = [];
foreach ($styles?->numFmts->numFmt ?? [] as $format) {
    $customFormats[(int) $format['numFmtId']] = (string) $format['formatCode'];
}
$styleFormats = [];
foreach ($styles?->cellXfs->xf ?? [] as $styleId => $format) {
    $numberFormatId = (int) $format['numFmtId'];
    $styleFormats[$styleId] = [
        'numFmtId' => $numberFormatId,
        'formatCode' => $customFormats[$numberFormatId] ?? ($numberFormatId >= 14 && $numberFormatId <= 22 ? 'built-in date/time format' : 'General/number format'),
    ];
}
$headers = [];
$targetValues = ['Masa Kontrak' => [], 'Organilk' => []];
$formulaReferences = [];

foreach ($sheet->sheetData->row as $sheetRow) {
    $cells = [];
    foreach ($sheetRow->c as $cell) {
        preg_match('/[A-Z]+/i', (string) $cell['r'], $matches);
        $columnIndex = 0;
        foreach (str_split(strtoupper($matches[0] ?? 'A')) as $letter) {
            $columnIndex = ($columnIndex * 26) + ord($letter) - 64;
        }
        $columnIndex--;

        $value = (string) ($cell->v ?? '');
        if ((string) $cell['t'] === 's' && $value !== '') {
            $value = $sharedStrings[(int) $value] ?? '';
        } elseif ((string) $cell['t'] === 'inlineStr') {
            $value = (string) ($cell->is->t ?? '');
        }

        if (isset($cell->f)) {
            $formulaReferences[] = (string) $cell['r'];
        }

        $cells[$columnIndex] = [
            'value' => $value,
            'type' => (string) $cell['t'],
            'style' => (string) $cell['s'],
            'formula' => isset($cell->f),
        ];
    }

    if ($cells === []) {
        continue;
    }
    ksort($cells);

    if ($headers === []) {
        foreach ($cells as $cell) {
            $headers[] = trim($cell['value']);
        }
        continue;
    }

    foreach (array_keys($targetValues) as $target) {
        $columnIndex = array_search($target, $headers, true);
        if ($columnIndex === false || ! isset($cells[$columnIndex])) {
            continue;
        }

        $cell = $cells[$columnIndex];
        if ($cell['value'] !== '' || $cell['formula']) {
            $targetValues[$target][] = [
                'row' => (int) $sheetRow['r'],
                ...$cell,
            ];
        }
    }
}

$summaries = [];
foreach ($targetValues as $field => $values) {
    $examples = [];
    foreach ($values as $value) {
        $example = $value['value'] !== '' ? $value['value'] : ($value['formula'] ? '[formula]' : '');
        if ($example !== '' && ! in_array($example, $examples, true)) {
            $examples[] = $example;
        }
    }

    $summaries[$field] = [
        'nonempty_cells' => count($values),
        'formula_cells' => count(array_filter($values, fn (array $value): bool => $value['formula'])),
        'examples' => array_slice($examples, 0, 20),
        'cell_types' => array_count_values(array_column($values, 'type')),
        'style_formats' => array_values(array_unique(array_map(
            fn (array $value): string => json_encode([
                'style_id' => $value['style'],
                ...($styleFormats[(int) $value['style']] ?? ['numFmtId' => null, 'formatCode' => 'unknown']),
            ], JSON_UNESCAPED_UNICODE),
            $values,
        ))),
    ];
}

var_export([
    'headers' => $headers,
    'style_xml' => $stylesXml,
    'row_count' => max(0, count($sheet->sheetData->row) - 1),
    'formula_cell_count' => count($formulaReferences),
    'formula_cell_sample' => array_slice($formulaReferences, 0, 20),
    'fields' => $summaries,
]);