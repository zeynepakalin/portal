<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function marketFetch(string $url): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch !== false) {
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_USERAGENT => 'PortalMarketFetcher/1.0',
            ]);
            $body = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            if (is_string($body) && $body !== '' && $status >= 200 && $status < 300) {
                return $body;
            }
        }
    }

    $ctx = stream_context_create([
        'http' => [
            'timeout' => 10,
            'ignore_errors' => true,
            'header' => "User-Agent: PortalMarketFetcher/1.0\r\nAccept: application/json, */*\r\n",
        ],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    if (!is_string($body) || $body === '') {
        return null;
    }

    return $body;
}

function toFloat($value): ?float
{
    if ($value === null) {
        return null;
    }
    $raw = str_replace(',', '.', trim((string)$value));
    if ($raw === '' || !is_numeric($raw)) {
        return null;
    }

    return (float)$raw;
}

function parseTcmbHourlyGoldGramTry(string $xml): ?float
{
    $sx = @simplexml_load_string($xml);
    if ($sx === false) {
        return null;
    }
    if (!isset($sx->tcmbVeri->doviz_kur_liste)) {
        return null;
    }
    $liste = $sx->tcmbVeri->doviz_kur_liste;
    if (!isset($liste->kur)) {
        return null;
    }
    foreach ($liste->kur as $kur) {
        $code = strtoupper(trim((string)$kur->doviz_cinsi));
        if ($code !== 'XAU') {
            continue;
        }
        $alis = toFloat((string)$kur->alis);
        if ($alis === null || $alis <= 0) {
            return null;
        }
        $birim = toFloat((string)$kur->birim);
        if ($birim === null || $birim <= 0) {
            $birim = 1.0;
        }
        $perGram = $alis / $birim;
        if ($perGram > 200.0 && $perGram < 50000.0) {
            return $perGram;
        }

        return null;
    }

    return null;
}

function fetchTcmbHourlyGoldGramTry(): ?float
{
    $tz = new DateTimeZone('Europe/Istanbul');
    $now = new DateTimeImmutable('now', $tz);
    $hours = ['15:00', '14:00', '13:00', '12:00', '11:00', '10:00'];
    for ($dayOff = 0; $dayOff < 8; $dayOff++) {
        $d = $now->modify("-{$dayOff} days");
        $ym = $d->format('Ym');
        $ddmmyyyy = $d->format('dmY');
        foreach ($hours as $hm) {
            $h = str_replace(':', '', $hm);
            $url = 'https://www.tcmb.gov.tr/reeskontkur/' . $ym . '/' . $ddmmyyyy . '-' . $h . '.xml';
            $raw = marketFetch($url);
            if ($raw === null) {
                continue;
            }
            $g = parseTcmbHourlyGoldGramTry($raw);
            if ($g !== null) {
                return $g;
            }
        }
    }

    return null;
}

function goldTryFromGram(float $gramTry): array
{
    return [
        'gram' => $gramTry,
        'quarter' => $gramTry * 1.754,
        'republic' => $gramTry * 7.216,
    ];
}

/**
 * USD/TRY*100 yedek formülü ile üretilen sahte gram değerleri (~kur×100).
 * Gerçek kapalıçarşı gram fiyatı bundan belirgin şekilde yüksektir; bu aralıktaki değerleri gösterme.
 */
function isArtifactUsdTimes100Gram(float $gram, float $usdTry): bool
{
    $artifact = $usdTry * 100.0;
    $tol = max(25.0, $artifact * 0.015);

    return abs($gram - $artifact) <= $tol;
}

/** Turkiye piyasasi ozeti: gram / ceyrek / cumhuriyet satis (JSON). */
function fetchTruncgilGoldTry(): ?array
{
    $raw = marketFetch('https://finans.truncgil.com/v4/today.json');
    if (!is_string($raw) || $raw === '') {
        return null;
    }
    $j = json_decode($raw, true);
    if (!is_array($j)) {
        return null;
    }
    $gram = toFloat($j['GRA']['Selling'] ?? null);
    if ($gram === null || $gram < 500.0 || $gram > 80000.0) {
        return null;
    }
    $quarter = toFloat($j['CEYREKALTIN']['Selling'] ?? null);
    $republic = toFloat($j['CUMHURIYETALTINI']['Selling'] ?? null);
    if ($quarter === null || $quarter < 1000.0) {
        $quarter = $gram * 1.754;
    }
    if ($republic === null || $republic < 5000.0) {
        $republic = $gram * 7.216;
    }

    return [
        'gram' => $gram,
        'quarter' => $quarter,
        'republic' => $republic,
    ];
}

$fxRaw = marketFetch('https://open.er-api.com/v6/latest/USD');
if ($fxRaw === null) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'message' => 'Doviz verisi alinamadi.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$fxJson = json_decode($fxRaw, true);
if (!is_array($fxJson) || ($fxJson['result'] ?? '') !== 'success' || !isset($fxJson['rates'])) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'message' => 'Doviz verisi bozuk.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$rates = $fxJson['rates'];
$usdTry = toFloat($rates['TRY'] ?? null);
$eurUsd = toFloat($rates['EUR'] ?? null);
$gbpUsd = toFloat($rates['GBP'] ?? null);

if ($usdTry === null || $eurUsd === null || $gbpUsd === null) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'message' => 'Temel doviz kurlari eksik.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$eurTry = $usdTry / $eurUsd;
$gbpTry = $usdTry / $gbpUsd;

$gramTry = null;
$quarterTry = null;
$republicTry = null;
$goldSource = 'bilinmiyor';
$goldNote = 'Altin fiyatlari yuklenemedi.';

$tg = fetchTruncgilGoldTry();
if ($tg !== null && isArtifactUsdTimes100Gram($tg['gram'], $usdTry)) {
    $tg = null;
}
if ($tg !== null) {
    $gramTry = $tg['gram'];
    $quarterTry = $tg['quarter'];
    $republicTry = $tg['republic'];
    $goldSource = 'finans.truncgil.com (piyasa satis)';
    $goldNote = 'Gram / ceyrek / cumhuriyet: Turkiye piyasasi ozeti (satis). Ucuncu parti; bilgi amaclidir.';
}

if ($gramTry === null) {
    $tcmbGram = fetchTcmbHourlyGoldGramTry();
    if ($tcmbGram !== null) {
        $pack = goldTryFromGram($tcmbGram);
        $gramTry = $pack['gram'];
        $quarterTry = $pack['quarter'];
        $republicTry = $pack['republic'];
        $goldSource = 'TCMB reeskont (saatlik, XAU alis / birim)';
        $goldNote = 'Gram altin: TCMB saatlik kur. Ceyrek/Cumhuriyet: gram x 1,754 / 7,216 (liste carpani).';
    }
}

$xauUsd = null;
if ($gramTry === null) {
    $goldRaw = marketFetch('https://api.gold-api.com/price/XAU');
    $goldJson = is_string($goldRaw) ? json_decode($goldRaw, true) : null;
    $xauUsd = (is_array($goldJson) ? toFloat($goldJson['price'] ?? null) : null);

    if ($xauUsd === null) {
        $tcmbDayRaw = marketFetch('https://www.tcmb.gov.tr/kurlar/today.xml');
        if (is_string($tcmbDayRaw) && $tcmbDayRaw !== '') {
            $tcmb = @simplexml_load_string($tcmbDayRaw);
            if ($tcmb !== false) {
                foreach ($tcmb->Currency as $cur) {
                    $code = (string)($cur['CurrencyCode'] ?? $cur['Kod'] ?? '');
                    if (strtoupper($code) !== 'XAU') {
                        continue;
                    }
                    $xauVal = toFloat((string)$cur->ForexSelling);
                    if ($xauVal === null) {
                        $xauVal = toFloat((string)$cur->BanknoteSelling);
                    }
                    if ($xauVal !== null) {
                        if ($xauVal > 1000 && $xauVal < 20000) {
                            $xauUsd = $xauVal;
                        } elseif ($xauVal > 20000) {
                            $xauUsd = $xauVal / max($usdTry, 0.0001);
                        }
                    }
                    break;
                }
            }
        }
    }

    if ($xauUsd !== null && $xauUsd > 500 && $xauUsd < 20000) {
        $gramUsd = $xauUsd / 31.1034768;
        $g = $gramUsd * $usdTry;
        if (!isArtifactUsdTimes100Gram($g, $usdTry)) {
            $pack = goldTryFromGram($g);
            $gramTry = $pack['gram'];
            $quarterTry = $pack['quarter'];
            $republicTry = $pack['republic'];
            $goldSource = 'XAU/USD (ons) + USDTRY';
            $goldNote = 'Uluslararasi ons altin kurundan gram TL; Kapalicarsi ile fark olabilir.';
        }
    }
}

if ($gramTry !== null && isArtifactUsdTimes100Gram($gramTry, $usdTry)) {
    $gramTry = null;
    $quarterTry = null;
    $republicTry = null;
}

if ($gramTry === null) {
    $goldSource = 'altin verisi yok';
    $goldNote = 'Altın fiyatı güvenilir kaynaktan alınamadı; hatalı tahmin (kur×100) gösterilmiyor.';
}

echo json_encode([
    'ok' => true,
    'fx' => [
        'usdtry' => ['value' => $usdTry, 'change' => null],
        'eurtry' => ['value' => $eurTry, 'change' => null],
        'gbptry' => ['value' => $gbpTry, 'change' => null],
    ],
    'gold' => [
        'gram' => ['value' => $gramTry, 'change' => null],
        'quarter' => ['value' => $quarterTry, 'change' => null],
        'republic' => ['value' => $republicTry, 'change' => null],
    ],
    'source' => 'open.er-api + ' . $goldSource,
    'note' => $goldNote,
    'fetched_at' => date('c'),
], JSON_UNESCAPED_UNICODE);
