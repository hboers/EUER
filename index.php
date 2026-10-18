<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

const HEADERS = ['Buchung', 'Beleg', 'Beschreibung', 'Typ', 'Netto', 'UST/VST', 'UST/VST %', 'RC', 'Land'];

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (!in_array($requestPath, ['/', '/index.php'], true)) { http_response_code(404); exit('Nicht gefunden'); }

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function shortDescription(string $value, int $length = 20): string {
    if (function_exists('mb_strlen') && function_exists('mb_substr')) return mb_strlen($value, 'UTF-8') > $length ? mb_substr($value, 0, $length, 'UTF-8') . '…' : $value;
    preg_match_all('/./us', $value, $characters);
    $characters = $characters[0] ?? [];
    return count($characters) > $length ? implode('', array_slice($characters, 0, $length)) . '…' : $value;
}
function loadEnv(string $path): array {
    $values = [];
    if (!is_file($path)) return $values;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $values[trim($key)] = trim(trim($value), "'\"");
    }
    return $values;
}
function countryOptions(): array {
    return [
        'AD'=>'Andorra','AE'=>'Vereinigte Arabische Emirate','AF'=>'Afghanistan','AG'=>'Antigua und Barbuda','AI'=>'Anguilla','AL'=>'Albanien','AM'=>'Armenien','AO'=>'Angola','AQ'=>'Antarktis','AR'=>'Argentinien','AS'=>'Amerikanisch-Samoa','AT'=>'Österreich','AU'=>'Australien','AW'=>'Aruba','AX'=>'Ålandinseln','AZ'=>'Aserbaidschan',
        'BA'=>'Bosnien und Herzegowina','BB'=>'Barbados','BD'=>'Bangladesch','BE'=>'Belgien','BF'=>'Burkina Faso','BG'=>'Bulgarien','BH'=>'Bahrain','BI'=>'Burundi','BJ'=>'Benin','BL'=>'Saint-Barthélemy','BM'=>'Bermuda','BN'=>'Brunei Darussalam','BO'=>'Bolivien','BQ'=>'Bonaire, Sint Eustatius und Saba','BR'=>'Brasilien','BS'=>'Bahamas','BT'=>'Bhutan','BV'=>'Bouvetinsel','BW'=>'Botswana','BY'=>'Belarus','BZ'=>'Belize',
        'CA'=>'Kanada','CC'=>'Kokosinseln','CD'=>'Demokratische Republik Kongo','CF'=>'Zentralafrikanische Republik','CG'=>'Republik Kongo','CH'=>'Schweiz','CI'=>'Côte d’Ivoire','CK'=>'Cookinseln','CL'=>'Chile','CM'=>'Kamerun','CN'=>'China','CO'=>'Kolumbien','CR'=>'Costa Rica','CU'=>'Kuba','CV'=>'Cabo Verde','CW'=>'Curaçao','CX'=>'Weihnachtsinsel','CY'=>'Zypern','CZ'=>'Tschechien',
        'DE'=>'Deutschland','DJ'=>'Dschibuti','DK'=>'Dänemark','DM'=>'Dominica','DO'=>'Dominikanische Republik','DZ'=>'Algerien',
        'EC'=>'Ecuador','EE'=>'Estland','EG'=>'Ägypten','EH'=>'Westsahara','ER'=>'Eritrea','ES'=>'Spanien','ET'=>'Äthiopien',
        'FI'=>'Finnland','FJ'=>'Fidschi','FK'=>'Falklandinseln','FM'=>'Mikronesien','FO'=>'Färöer','FR'=>'Frankreich',
        'GA'=>'Gabun','GB'=>'Vereinigtes Königreich','GD'=>'Grenada','GE'=>'Georgien','GF'=>'Französisch-Guayana','GG'=>'Guernsey','GH'=>'Ghana','GI'=>'Gibraltar','GL'=>'Grönland','GM'=>'Gambia','GN'=>'Guinea','GP'=>'Guadeloupe','GQ'=>'Äquatorialguinea','GR'=>'Griechenland','GS'=>'Südgeorgien und die Südlichen Sandwichinseln','GT'=>'Guatemala','GU'=>'Guam','GW'=>'Guinea-Bissau','GY'=>'Guyana',
        'HK'=>'Hongkong','HM'=>'Heard und McDonaldinseln','HN'=>'Honduras','HR'=>'Kroatien','HT'=>'Haiti','HU'=>'Ungarn',
        'ID'=>'Indonesien','IE'=>'Irland','IL'=>'Israel','IM'=>'Isle of Man','IN'=>'Indien','IO'=>'Britisches Territorium im Indischen Ozean','IQ'=>'Irak','IR'=>'Iran','IS'=>'Island','IT'=>'Italien',
        'JE'=>'Jersey','JM'=>'Jamaika','JO'=>'Jordanien','JP'=>'Japan',
        'KE'=>'Kenia','KG'=>'Kirgisistan','KH'=>'Kambodscha','KI'=>'Kiribati','KM'=>'Komoren','KN'=>'St. Kitts und Nevis','KP'=>'Nordkorea','KR'=>'Südkorea','KW'=>'Kuwait','KY'=>'Kaimaninseln','KZ'=>'Kasachstan',
        'LA'=>'Laos','LB'=>'Libanon','LC'=>'St. Lucia','LI'=>'Liechtenstein','LK'=>'Sri Lanka','LR'=>'Liberia','LS'=>'Lesotho','LT'=>'Litauen','LU'=>'Luxemburg','LV'=>'Lettland','LY'=>'Libyen',
        'MA'=>'Marokko','MC'=>'Monaco','MD'=>'Moldau','ME'=>'Montenegro','MF'=>'Saint-Martin (französischer Teil)','MG'=>'Madagaskar','MH'=>'Marshallinseln','MK'=>'Nordmazedonien','ML'=>'Mali','MM'=>'Myanmar','MN'=>'Mongolei','MO'=>'Macau','MP'=>'Nördliche Marianen','MQ'=>'Martinique','MR'=>'Mauretanien','MS'=>'Montserrat','MT'=>'Malta','MU'=>'Mauritius','MV'=>'Malediven','MW'=>'Malawi','MX'=>'Mexiko','MY'=>'Malaysia','MZ'=>'Mosambik',
        'NA'=>'Namibia','NC'=>'Neukaledonien','NE'=>'Niger','NF'=>'Norfolkinsel','NG'=>'Nigeria','NI'=>'Nicaragua','NL'=>'Niederlande','NO'=>'Norwegen','NP'=>'Nepal','NR'=>'Nauru','NU'=>'Niue','NZ'=>'Neuseeland',
        'OM'=>'Oman',
        'PA'=>'Panama','PE'=>'Peru','PF'=>'Französisch-Polynesien','PG'=>'Papua-Neuguinea','PH'=>'Philippinen','PK'=>'Pakistan','PL'=>'Polen','PM'=>'Saint-Pierre und Miquelon','PN'=>'Pitcairninseln','PR'=>'Puerto Rico','PS'=>'Palästinensische Gebiete','PT'=>'Portugal','PW'=>'Palau','PY'=>'Paraguay',
        'QA'=>'Katar',
        'RE'=>'Réunion','RO'=>'Rumänien','RS'=>'Serbien','RU'=>'Russland','RW'=>'Ruanda',
        'SA'=>'Saudi-Arabien','SB'=>'Salomonen','SC'=>'Seychellen','SD'=>'Sudan','SE'=>'Schweden','SG'=>'Singapur','SH'=>'St. Helena, Ascension und Tristan da Cunha','SI'=>'Slowenien','SJ'=>'Spitzbergen und Jan Mayen','SK'=>'Slowakei','SL'=>'Sierra Leone','SM'=>'San Marino','SN'=>'Senegal','SO'=>'Somalia','SR'=>'Suriname','SS'=>'Südsudan','ST'=>'São Tomé und Príncipe','SV'=>'El Salvador','SX'=>'Sint Maarten (niederländischer Teil)','SY'=>'Syrien','SZ'=>'Eswatini',
        'TC'=>'Turks- und Caicosinseln','TD'=>'Tschad','TF'=>'Französische Süd- und Antarktisgebiete','TG'=>'Togo','TH'=>'Thailand','TJ'=>'Tadschikistan','TK'=>'Tokelau','TL'=>'Timor-Leste','TM'=>'Turkmenistan','TN'=>'Tunesien','TO'=>'Tonga','TR'=>'Türkei','TT'=>'Trinidad und Tobago','TV'=>'Tuvalu','TW'=>'Taiwan','TZ'=>'Tansania',
        'UA'=>'Ukraine','UG'=>'Uganda','UM'=>'Kleinere Amerikanische Überseeinseln','US'=>'Vereinigte Staaten','UY'=>'Uruguay','UZ'=>'Usbekistan',
        'VA'=>'Vatikanstadt','VC'=>'St. Vincent und die Grenadinen','VE'=>'Venezuela','VG'=>'Britische Jungferninseln','VI'=>'Amerikanische Jungferninseln','VN'=>'Vietnam','VU'=>'Vanuatu',
        'WF'=>'Wallis und Futuna','WS'=>'Samoa',
        'YE'=>'Jemen','YT'=>'Mayotte','ZA'=>'Südafrika','ZM'=>'Sambia','ZW'=>'Simbabwe'
    ];
}
function stagedPath(int $year): string {
    return __DIR__ . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . 'Staged.csv';
}
function ignoredStagePath(int $year): string {
    return __DIR__ . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . 'Ignored.csv';
}
function readIgnoredStageIds(int $year): array {
    $path = ignoredStagePath($year);
    if (!is_file($path) || !($fp = fopen($path, 'rb'))) return [];
    fgetcsv($fp, 0, ';');
    $ids = [];
    while (($row = fgetcsv($fp, 0, ';')) !== false) {
        $id = normalizeReceiptId($row[0] ?? '');
        if (preg_match('/^\d{10}$/', $id)) $ids[$id] = true;
    }
    fclose($fp);
    return array_keys($ids);
}
function normalizeReceiptId(mixed $value): string {
    return trim((string)$value);
}
function writeIgnoredStageIds(int $year, array $ids): bool {
    $path = ignoredStagePath($year);
    $tmp = $path . '.tmp';
    $fp = fopen($tmp, 'wb');
    if (!$fp) return false;
    fwrite($fp, "\xEF\xBB\xBF");
    fputcsv($fp, ['Beleg'], ';', '"', '\\');
    $ids = array_map('normalizeReceiptId', $ids);
    $ids = array_values(array_unique(array_filter($ids, static fn($id) => preg_match('/^\d{10}$/', $id))));
    sort($ids, SORT_STRING);
    foreach ($ids as $id) fputcsv($fp, [$id], ';', '"', '\\');
    fclose($fp);
    if (!rename($tmp, $path)) { @unlink($tmp); return false; }
    return true;
}
function csvPath(int $year): string {
    $dir = __DIR__ . DIRECTORY_SEPARATOR . $year;
    $path = $dir . DIRECTORY_SEPARATOR . 'EUER.csv';
    $legacy = __DIR__ . DIRECTORY_SEPARATOR . $year . '.csv';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    if (!is_file($path) && is_file($legacy)) @rename($legacy, $path);
    return $path;
}
function readLedger(string $path): array {
    if (!is_file($path)) return [];
    $fp = fopen($path, 'rb'); if (!$fp) return [];
    $rows = []; $header = fgetcsv($fp, 0, ';');
    $legacyBrutto = is_array($header) && in_array('Brutto', $header, true);
    while (($row = fgetcsv($fp, 0, ';')) !== false) {
        if ($legacyBrutto) array_splice($row, 6, 1);
        $row = array_pad(array_slice($row, 0, count(HEADERS)), count(HEADERS), '');
        if (count(array_filter($row, static fn($v) => $v !== null && $v !== '')) === 0) continue;
        $rows[] = array_map(static fn($v) => (string)($v ?? ''), $row);
    }
    fclose($fp); return $rows;
}
function writeLedger(string $path, array $rows): bool {
    $tmp = $path . '.tmp'; $fp = fopen($tmp, 'wb'); if (!$fp) return false;
    fwrite($fp, "\xEF\xBB\xBF"); fputcsv($fp, HEADERS, ';', '"', '\\');
    foreach ($rows as $row) fputcsv($fp, array_pad(array_slice($row, 0, count(HEADERS)), count(HEADERS), ''), ';', '"', '\\');
    fclose($fp);
    if (!rename($tmp, $path)) { @unlink($tmp); return false; }
    return true;
}
function backupCsv(string $csvPath): bool {
    if (!is_file($csvPath)) return true;
    $backupDir = dirname($csvPath) . DIRECTORY_SEPARATOR . 'backups';
    if (!is_dir($backupDir) && !@mkdir($backupDir, 0775, true)) return false;
    $stamp = (new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin')))->format('Ymd-His') . '-' . bin2hex(random_bytes(4));
    $prefix = pathinfo(basename($csvPath), PATHINFO_FILENAME);
    $snapshot = $backupDir . DIRECTORY_SEPARATOR . $prefix . '-' . $stamp . '.csv';
    if (!@copy($csvPath, $snapshot)) return false;
    $snapshots = glob($backupDir . DIRECTORY_SEPARATOR . $prefix . '-*.csv') ?: [];
    sort($snapshots, SORT_STRING);
    while (count($snapshots) > 5) {
        $oldest = array_shift($snapshots);
        if (!@unlink($oldest)) return false;
    }
    return true;
}
function migrateLedgerSchema(string $path): bool {
    if (!is_file($path) || !($fp = fopen($path, 'rb'))) return true;
    $header = fgetcsv($fp, 0, ';'); fclose($fp);
    $legacyBrutto = is_array($header) && in_array('Brutto', $header, true);
    $legacyRc = !is_array($header) || !in_array('RC', $header, true);
    $legacyCountry = !is_array($header) || !in_array('Land', $header, true);
    $rateMarker = dirname($path) . DIRECTORY_SEPARATOR . '.' . basename($path) . '.integer-rate';
    $legacyRate = !is_file($rateMarker);
    $incomeMarker = dirname($path) . DIRECTORY_SEPARATOR . '.' . basename($path) . '.income-type';
    $legacyIncome = !is_file($incomeMarker);
    if (!$legacyBrutto && !$legacyRate && !$legacyIncome && !$legacyRc && !$legacyCountry) return true;
    $rows = readLedger($path);
    if ($legacyRate || $legacyIncome) {
        foreach ($rows as &$row) {
            if ($legacyRate) {
            $rate = trim((string)($row[6] ?? ''));
            if ($rate !== '' && is_numeric($rate) && abs((float)$rate) <= 1) $row[6] = (string)(int)round((float)$rate * 100);
            }
            $net = (float)str_replace(',', '.', (string)($row[4] ?? '0'));
            $tax = (float)str_replace(',', '.', (string)($row[5] ?? '0'));
            if ($legacyIncome && is_numeric($row[4] ?? null) && is_numeric($row[5] ?? null) && round($net + $tax, 2) > 0) $row[3] = 'Einnahmen';
        }
        unset($row);
    }
    if (!backupCsv($path) || !writeLedger($path, $rows)) return false;
    if ($legacyRate && file_put_contents($rateMarker, '1') === false) return false;
    return !$legacyIncome || file_put_contents($incomeMarker, '1') !== false;
}
function stagedArchiveRows(string $archiveDir, int $year, array $rows, array $ignoredIds = []): array {
    $known = [];
    foreach ($rows as $row) {
        $id = normalizeReceiptId($row[1] ?? '');
        if ($id !== '') $known[$id] = true;
    }
    foreach ($ignoredIds as $id) {
        $id = normalizeReceiptId($id);
        if ($id !== '') $known[$id] = true;
    }
    $yearDir = realpath($archiveDir . DIRECTORY_SEPARATOR . $year);
    if (!$yearDir || !is_dir($yearDir)) return [];
    $staged = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($yearDir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $entry) {
        if (!$entry->isFile() || strtolower($entry->getExtension()) !== 'pdf') continue;
        $filename = $entry->getFilename();
        if (!preg_match('/^(\\d{10})-(.+)\\.pdf$/i', $filename, $match)) continue;
        $id = $match[1];
        if (isset($known[$id])) continue;
        $description = trim(str_replace(['_', '-'], ' ', $match[2]));
        $staged[] = ['', $id, $description, 'Sonstiges', '', '', ''];
        $known[$id] = true;
    }
    return $staged;
}
function archivePdfPath(string $archiveDir, int $year, string $receiptId): string|false {
    if (!preg_match('/^\d{10}$/', $receiptId)) return false;
    $yearDir = realpath($archiveDir . DIRECTORY_SEPARATOR . $year);
    $base = realpath($archiveDir);
    if (!$yearDir || !$base || !str_starts_with($yearDir, $base . DIRECTORY_SEPARATOR)) return false;
    $matches = glob($yearDir . DIRECTORY_SEPARATOR . $receiptId . '-*.pdf') ?: [];
    if (!$matches) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($yearDir, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $entry) if ($entry->isFile() && preg_match('/^' . preg_quote($receiptId, '/') . '-.*\.pdf$/i', $entry->getFilename())) $matches[] = $entry->getPathname();
    }
    $path = $matches ? realpath($matches[0]) : false;
    return $path && str_starts_with($path, $yearDir . DIRECTORY_SEPARATOR) ? $path : false;
}
function pdfText(string $path): string|false {
    $proc = @proc_open(['pdftotext','-layout','-enc','UTF-8',$path,'-'], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
    if (!is_resource($proc)) return false;
    fclose($pipes[0]); $text = stream_get_contents($pipes[1]); fclose($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[2]);
    return proc_close($proc) === 0 ? $text : false;
}
function amountFromTextLine(string $line): string {
    if (!preg_match_all('/(?<![A-Za-z])[-+]?\d{1,3}(?:\.\d{3})*(?:,\d{2})|[-+]?\d+(?:[,.]\d{2})/', $line, $matches) || !$matches[0]) return '';
    $value = end($matches[0]);
    if (str_contains($value, ',')) $value = str_replace(',', '.', str_replace('.', '', $value));
    return is_numeric($value) ? number_format((float)$value, 2, '.', '') : '';
}
function extractPdfFields(string $text): array {
    $result = ['date'=>'','net'=>'','tax'=>'','rate'=>''];
    $grossFound = '';
    $lines = preg_split('/\R/u', $text) ?: [];
    foreach ($lines as $line) {
        if ($result['date'] === '' && preg_match('/(Rechnungsdatum|Invoice date|Date of issue|Rechnungsstellung|\bDatum\b|\bRechnung\b.*\bvom\b)/iu', $line)) {
            if (preg_match('/\b(\d{4})-(\d{2})-(\d{2})\b/', $line, $m)) $result['date'] = $m[1].'-'.$m[2].'-'.$m[3];
            elseif (preg_match('/\b(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{2,4})\b/', $line, $m)) { $y=(int)$m[3]; if($y<100)$y+=2000; $result['date']=sprintf('%04d-%02d-%02d',$y,(int)$m[2],(int)$m[1]); }
            elseif (($ts=strtotime($line))!==false && preg_match('/\b(19|20)\d{2}\b/',$line)) $result['date']=date('Y-m-d',$ts);
        }
        if ($grossFound==='' && preg_match('/gesamtbetrag\s*\(brutto\)|gesamt brutto|rechnungsendbetrag|rechnungsbetrag|gesamtsumme|zahlbetrag|zu zahlender betrag|\btotal\b/i', $line)) $grossFound=amountFromTextLine($line);
        if ($result['net']==='' && preg_match('/nettosumme|nettobetrag|net amount|subtotal|net total/i', $line)) $result['net']=amountFromTextLine($line);
        if ($result['tax']==='' && preg_match('/umsatzsteuer|mehrwertsteuer|\bmwst\b|\bvat\b|ust\.?(?:\s|\/|$)/i', $line) && !preg_match('/ust-id|umsatzsteuer-id|steueridentifikationsnummer/i',$line)) $result['tax']=amountFromTextLine($line);
        if ($grossFound==='' && str_contains(strtolower($line),'brutto') && preg_match('/gesamt|summe|amount|total|betrag/i',$line)) $grossFound=amountFromTextLine($line);
    }
    if ($result['net']==='' && $grossFound!=='' && $result['tax']!=='') $result['net']=number_format((float)$grossFound-(float)$result['tax'],2,'.','');
    elseif ($result['net']==='' && $grossFound!=='' && $result['tax']==='') {
        $result['net']=$grossFound;
        $result['tax']='0.00';
        $result['rate']='0';
    }
    if ($result['net']!=='' && $result['tax']!=='' && abs((float)$result['net'])>0.000001) $result['rate']=(string)(int)round(abs((float)$result['tax']/(float)$result['net'])*100);
    return $result;
}
function extractedPdfFields(string $pdfPath): array|false {
    $text=pdfText($pdfPath); if ($text===false) return false;
    $data=extractPdfFields($text);
    $data['_version']=5;
    $data['_note']=$text===''?'PDF enthält keinen durchsuchbaren Text.':'Datum aus der Rechnung; bitte Buchungsdatum und extrahierte Werte vor Übernahme prüfen. Wenn nur ein Bruttobetrag erkannt wurde, wurde er als Netto mit 0 % vorgeschlagen.';
    return $data;
}
function validYear(mixed $v): int { $n = filter_var($v, FILTER_VALIDATE_INT); return $n !== false && $n >= 2000 && $n <= 2100 ? $n : (int)date('Y'); }
function allYears(): array {
    $years = [];
    foreach (glob(__DIR__ . DIRECTORY_SEPARATOR . '[0-9][0-9][0-9][0-9]', GLOB_ONLYDIR) ?: [] as $dir) {
        if (is_file($dir . DIRECTORY_SEPARATOR . 'EUER.csv')) $years[] = (int)basename($dir);
    }
    foreach (glob(__DIR__ . DIRECTORY_SEPARATOR . '[0-9][0-9][0-9][0-9].csv') ?: [] as $legacy) $years[] = (int)basename($legacy, '.csv');
    $years = array_values(array_unique($years)); sort($years); return $years;
}
final class ArithmeticParser {
    private int $position = 0;
    private function __construct(private array $tokens) {}
    public static function evaluate(string $expression): ?float {
        $expression = preg_replace('/[^0-9,.+*\/-]/u', '', trim($expression)) ?? '';
        if (str_contains($expression, ',')) {
            if (str_contains($expression, '.') && strrpos($expression, ',') < strrpos($expression, '.')) $expression = str_replace(',', '', $expression);
            else $expression = str_replace(',', '.', str_replace('.', '', $expression));
        }
        $expression = preg_replace('/\s+/u', '', $expression) ?? '';
        if ($expression === '' || !preg_match_all('/(?:\d+(?:\.\d*)?|\.\d+)|[()+\-*\/]/', $expression, $matches) || implode('', $matches[0]) !== $expression) return null;
        try {
            $parser = new self($matches[0]);
            $value = $parser->parseExpression();
            return $parser->position === count($parser->tokens) && is_finite($value) ? $value : null;
        } catch (Throwable) { return null; }
    }
    private function parseExpression(): float {
        $value = $this->parseTerm();
        while (isset($this->tokens[$this->position]) && in_array($this->tokens[$this->position], ['+','-'], true)) {
            $operator = $this->tokens[$this->position++]; $right = $this->parseTerm();
            $value = $operator === '+' ? $value + $right : $value - $right;
        }
        return $value;
    }
    private function parseTerm(): float {
        $value = $this->parseFactor();
        while (isset($this->tokens[$this->position]) && in_array($this->tokens[$this->position], ['*','/'], true)) {
            $operator = $this->tokens[$this->position++]; $right = $this->parseFactor();
            if ($operator === '/' && abs($right) < PHP_FLOAT_MIN) throw new RuntimeException('Division durch 0');
            $value = $operator === '*' ? $value * $right : $value / $right;
        }
        return $value;
    }
    private function parseFactor(): float {
        $token = $this->tokens[$this->position] ?? null;
        if ($token === '+' || $token === '-') { $this->position++; $value = $this->parseFactor(); return $token === '-' ? -$value : $value; }
        if ($token === '(') {
            $this->position++; $value = $this->parseExpression();
            if (($this->tokens[$this->position] ?? null) !== ')') throw new RuntimeException('Klammer fehlt');
            $this->position++; return $value;
        }
        if ($token === null || !is_numeric($token)) throw new RuntimeException('Zahl erwartet');
        $this->position++; return (float)$token;
    }
}
function evaluateNetInput(string $expression): ?float {
    if (preg_match('/^(.+)\*-([0-9]+(?:[,.][0-9]+)?)%$/', trim($expression), $match)) {
        $base = ArithmeticParser::evaluate($match[1]);
        $percent = ArithmeticParser::evaluate($match[2]);
        if ($base === null || $percent === null || $percent < 0 || $percent > 100) return null;
        return $base * (1 - $percent / 100);
    }
    if (str_contains($expression, '%')) return null;
    return ArithmeticParser::evaluate($expression);
}
function displayNumber(string $value, float $factor = 1.0): string {
    if ($value === '' || !is_numeric(str_replace(',', '.', $value))) return '';
    return number_format((float)str_replace(',', '.', $value) * $factor, 2, ',', '');
}
function validateFinancialRow(array $row): string {
    foreach ([4=>'Netto',5=>'USt/VSt',6=>'USt/VSt %'] as $index=>$label) {
        if (!isset($row[$index]) || trim((string)$row[$index]) === '' || !is_numeric(str_replace(',', '.', (string)$row[$index]))) return $label . ' muss als Zahl angegeben werden.';
    }
    $net = (float)str_replace(',', '.', (string)$row[4]);
    $tax = (float)str_replace(',', '.', (string)$row[5]);
    $rate = (float)str_replace(',', '.', (string)$row[6]);
    if (($row[3] ?? '') === 'Spenden' && (abs($tax) > 0.001 || abs($rate) > 0.000001)) return 'Bei Spenden müssen USt/VSt-Betrag und Steuersatz 0 sein.';
    // The UI intentionally displays VAT percentages without decimals.
    // Validate at that same precision so values such as 19.02% round-trip as 19%.
    $expectedPercent = abs($net) < 0.000001 ? 0.0 : round(abs($tax / $net) * 100);
    $enteredPercent = round(abs($rate));
    if ($expectedPercent !== $enteredPercent) return 'USt/VSt % passt nicht zum Verhältnis von Netto und Steuerbetrag.';
    return '';
}
function financialErrorFields(string $message): array {
    if (str_contains($message, 'Spenden')) return [3,5,6];
    if (str_contains($message, 'Verhältnis')) return [4,5,6];
    if (str_contains($message, 'Netto')) return [4];
    if (str_contains($message, 'USt/VSt %')) return [6];
    if (str_contains($message, 'USt/VSt')) return [5];
    return [];
}
function adoptionError(array $row): string {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string)($row[0] ?? ''))) || trim((string)($row[1] ?? '')) === '' || trim((string)($row[2] ?? '')) === '') return 'Bitte Buchungsdatum, Belegnummer und Beschreibung ergänzen.';
    return validateFinancialRow($row);
}
function calculatedAmount(array $row): ?float {
    if (!isset($row[4], $row[5]) || !is_numeric(str_replace(',', '.', (string)$row[4])) || !is_numeric(str_replace(',', '.', (string)$row[5]))) return null;
    return round((float)str_replace(',', '.', (string)$row[4]) + (float)str_replace(',', '.', (string)$row[5]), 2);
}
function validIsoDate(string $value): bool {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}
function usdEurRateForDate(string $date): array|false {
    if (!validIsoDate($date)) return false;
    $end = new DateTimeImmutable($date);
    $start = $end->modify('-7 days');
    $url = 'https://data-api.ecb.europa.eu/service/data/EXR/D.USD.EUR.SP00.A?startPeriod=' . $start->format('Y-m-d') . '&endPeriod=' . rawurlencode($date) . '&format=csvdata';
    $context = stream_context_create(['http'=>['timeout'=>8, 'header'=>"Accept: text/csv\r\nUser-Agent: EUER-local-app\r\n"], 'https'=>['timeout'=>8, 'header'=>"Accept: text/csv\r\nUser-Agent: EUER-local-app\r\n"]]);
    $csv = @file_get_contents($url, false, $context);
    if (!is_string($csv) || $csv === '') return false;
    $lines = preg_split('/\r?\n/', trim($csv));
    if (count($lines) < 2) return false;
    $header = str_getcsv(array_shift($lines));
    $dateIndex = array_search('TIME_PERIOD', $header, true);
    $rateIndex = array_search('OBS_VALUE', $header, true);
    if ($dateIndex === false || $rateIndex === false) return false;
    $best = null;
    foreach ($lines as $line) {
        $columns = str_getcsv($line);
        $observedDate = $columns[$dateIndex] ?? '';
        $rate = $columns[$rateIndex] ?? '';
        if ($observedDate !== '' && $observedDate <= $date && is_numeric($rate) && (float)$rate > 0 && ($best === null || $observedDate > $best['date'])) $best = ['date'=>$observedDate, 'rate'=>(float)$rate];
    }
    return $best ?? false;
}
function rowMatchesFilter(array $row, string $filter, string $dateFrom, string $dateTo): bool {
    $date = (string)($row[0] ?? '');
    // Keep undated staged documents visible so they can be completed in the form.
    if ($date !== '' && (($dateFrom !== '' && $date < $dateFrom) || ($dateTo !== '' && $date > $dateTo))) return false;
    if ($filter === '') return true;
    $text = implode(' ', array_map(static fn($value) => (string)$value, $row));
    if (stripos($text, $filter) !== false) return true;
    $pattern = '~' . str_replace('~', '\\~', $filter) . '~iu';
    return @preg_match($pattern, $text) === 1;
}
function rowFromPost(?string &$conversionNote = null, ?string &$conversionError = null): array {
    $row = [];
    foreach (HEADERS as $i => $key) $row[] = trim((string)($_POST['entry'][$i] ?? ''));
    if ($row[8] !== '' && !isset(countryOptions()[$row[8]])) $row[8] = '';
    $conversionNote = '';
    $conversionError = '';
    $usdFields = [];
    if (str_contains($row[4], '$')) $usdFields[] = 4;
    $usdRate = false;
    if ($usdFields) {
        if (!validIsoDate($row[0])) {
            $conversionError = 'Für Dollarbeträge bitte zuerst ein gültiges Buchungsdatum angeben.';
            return $row;
        }
        $usdRate = usdEurRateForDate($row[0]);
        if ($usdRate === false) {
            $conversionError = 'Der USD/EUR-Wechselkurs für dieses Datum konnte bei der EZB nicht abgerufen werden. Bitte Verbindung prüfen und erneut speichern.';
            return $row;
        }
    }
    foreach ([4,6] as $i) {
        if ($row[$i] === '') continue;
        $hasDollar = $i === 4 && str_contains($row[$i], '$');
        $row[$i] = preg_replace($i === 4 ? '/[^0-9,.+*\/%-]/u' : '/[^0-9,.+*\/-]/u', '', $row[$i]) ?? '';
        if ($row[$i] === '') continue;
        $calculated = $i === 4 ? evaluateNetInput($row[$i]) : ArithmeticParser::evaluate($row[$i]);
        if ($calculated !== null) {
            if ($hasDollar && is_array($usdRate)) $calculated /= $usdRate['rate'];
            $row[$i] = $i === 6 ? (string)(int)round($calculated) : number_format($calculated, 2, '.', '');
        }
    }
    if ($usdFields && is_array($usdRate)) $conversionNote = 'USD-Betrag mit EZB-Referenzkurs vom ' . $usdRate['date'] . ' (1 EUR = ' . number_format($usdRate['rate'], 4, ',', '.') . ' USD) in EUR umgerechnet.';
    if (is_numeric($row[4]) && $row[6] === '') $row[6] = '0';
    if ($row[6] !== '' && is_numeric($row[6])) $row[6] = (string)(int)round((float)$row[6]);
    $row[5] = is_numeric($row[4]) && is_numeric($row[6])
        ? number_format(round((float)$row[4] * (float)$row[6] / 100, 2), 2, '.', '')
        : '';
    if (in_array($row[3], ['Leistung','Ware'], true)) {
        foreach ([4,5] as $i) if ($row[$i] !== '' && is_numeric($row[$i])) $row[$i] = number_format(-(float)$row[$i], 2, '.', '');
    }
    if ($row[3] === 'Spenden') {
        $row[5] = '0';
        $row[6] = '0';
    }
    $row[7] = in_array(strtolower(trim($row[7] ?? '')), ['1','x','ja','rc'], true) ? '1' : '';
    return $row;
}

if (isset($_GET['download'])) {
    $downloadYear = validYear($_GET['year'] ?? date('Y'));
    $downloadFile = csvPath($downloadYear);
    if (!is_file($downloadFile)) { http_response_code(404); exit('CSV nicht gefunden'); }
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="EUER-' . $downloadYear . '.csv"');
    readfile($downloadFile); exit;
}

$env = loadEnv(__DIR__ . '/.env');
$archive = $env['PDF_ARCHIVE'] ?? '../PDF_Archive';
$archiveDir = realpath(__DIR__ . DIRECTORY_SEPARATOR . $archive) ?: (__DIR__ . DIRECTORY_SEPARATOR . $archive);
$archiveExists = is_dir($archiveDir);
$countryList = countryOptions();
$countriesByGroup = [];
foreach (['EU', 'NONEU'] as $group) {
    $codes = array_values(array_unique(array_filter(array_map('trim', explode(',', $env[$group] ?? '')), static fn($code) => $code !== '' && isset($countryList[$code]))));
    $countriesByGroup[$group] = array_intersect_key($countryList, array_flip($codes));
}
$typeOptions = ['Einnahmen','Leistung','Ware','Sonstiges','Spenden'];
$defaultType = in_array($env['EUER_DEFAULT_TYPE'] ?? 'Leistung', $typeOptions, true) ? ($env['EUER_DEFAULT_TYPE'] ?? 'Leistung') : 'Leistung';
if (isset($_GET['beleg']) && is_string($_GET['beleg']) && preg_match('/^\d{10}$/', $_GET['beleg'])) {
    $receiptYear = validYear($_GET['year'] ?? date('Y'));
    $pdf = archivePdfPath($archiveDir, $receiptYear, $_GET['beleg']);
    if (!$pdf) { http_response_code(404); exit('Beleg nicht gefunden'); }
    header('Content-Type: application/pdf'); header('Content-Disposition: inline; filename="' . basename($pdf) . '"');
    readfile($pdf); exit;
}
$year = validYear($_REQUEST['year'] ?? date('Y'));
$file = csvPath($year);
$message = '';
$formErrorFields = [];
$preservedFormRow = null;
$preserveFormSource = '';
$nextStageIndex = null;
$highlightIndex = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $year = validYear($_POST['year'] ?? $year); $file = csvPath($year);
    $stagedFile = stagedPath($year);
    if (!migrateLedgerSchema($file) || !migrateLedgerSchema($stagedFile)) { http_response_code(500); exit('CSV-Schema konnte nicht migriert werden.'); }
    $rows = readLedger($file);
    $stagedRows = readLedger($stagedFile);
    $sourceStaged = ($_POST['source'] ?? '') === 'staged';
    if ($action === 'stage_archive') {
        $newRows = stagedArchiveRows($archiveDir, $year, array_merge($rows, $stagedRows), readIgnoredStageIds($year));
        if (!$newRows) $message = 'Keine neuen nummerierten PDF-Belege im Archiv gefunden.';
        elseif (!backupCsv($stagedFile)) $message = 'Sicherung von Staged.csv fehlgeschlagen; Belege wurden nicht eingetragen.';
        else { $stagedRows = array_merge($stagedRows, $newRows); $message = writeLedger($stagedFile, $stagedRows) ? count($newRows) . ' Beleg(e) in Staged.csv vorgemerkt.' : 'Staged.csv konnte nicht geschrieben werden.'; }
    } elseif ($action === 'adopt') {
        $index = filter_var($_POST['index'] ?? '', FILTER_VALIDATE_INT);
        if (!$sourceStaged || $index === false || !isset($stagedRows[$index])) $message = 'Staged-Beleg nicht gefunden.';
        else {
            $conversionNote = ''; $conversionError = '';
            $candidate = rowFromPost($conversionNote, $conversionError);
            if ($conversionError !== '') { $message = $conversionError; $preservedFormRow = $candidate; $preserveFormSource = 'staged'; $formErrorFields = str_contains($conversionError, 'Datum') ? [0] : [4,5]; }
            elseif (($adoptionProblem = adoptionError($candidate)) !== '') { $message = $adoptionProblem; $preservedFormRow = $candidate; $preserveFormSource = 'staged'; $formErrorFields = str_contains($adoptionProblem, 'Datum') ? array_values(array_filter([0,1,2], static fn($i)=>$i===0 ? !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$candidate[$i]) : trim((string)$candidate[$i])==='')) : financialErrorFields($adoptionProblem); }
            elseif (!backupCsv($file) || !backupCsv($stagedFile)) $message = 'CSV-Sicherung fehlgeschlagen; Beleg wurde nicht übernommen.';
            else {
                $rows[] = $candidate; array_splice($stagedRows, $index, 1);
                if (writeLedger($file, $rows) && writeLedger($stagedFile, $stagedRows)) {
                    if ($stagedRows) $nextStageIndex = $index < count($stagedRows) ? $index : count($stagedRows) - 1;
                    $message = 'Beleg einzeln nach EUER.csv übernommen.';
                    if ($conversionNote !== '') $message .= ' ' . $conversionNote;
                } else $message = 'CSV-Datei konnte nicht vollständig aktualisiert werden; Sicherungen liegen in backups/.';
            }
        }
    } elseif ($action === 'save') {
        $conversionNote = ''; $conversionError = '';
        $row = rowFromPost($conversionNote, $conversionError); $index = filter_var($_POST['index'] ?? '', FILTER_VALIDATE_INT);
        if ($conversionError !== '') { $message = $conversionError; $preservedFormRow = $row; $preserveFormSource = $sourceStaged ? 'staged' : 'main'; $formErrorFields = str_contains($conversionError, 'Datum') ? [0] : [4,5]; }
        elseif ($sourceStaged) {
            if ($index === false || !isset($stagedRows[$index])) $message = 'Staged-Beleg nicht gefunden.';
            else { $financialReady = count(array_filter(array_slice($row,4,3), static fn($v) => trim($v) !== '')) === 3; $financialError = $financialReady ? validateFinancialRow($row) : ''; if ($financialError !== '') { $message = $financialError; $preservedFormRow = $row; $preserveFormSource = 'staged'; $formErrorFields = financialErrorFields($financialError); } else { $stagedRows[$index] = $row; if (!backupCsv($stagedFile)) $message = 'Sicherung von Staged.csv fehlgeschlagen; Änderung wurde nicht gespeichert.'; else { $message = writeLedger($stagedFile, $stagedRows) ? 'Staged-Beleg gespeichert.' : 'Staged.csv konnte nicht geschrieben werden.'; if ($conversionNote !== '') $message .= ' ' . $conversionNote; } } }
        } else {
            $savedIndex = null;
            if ($row[0] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $row[0])) { $message = 'Bitte ein gültiges Buchungsdatum (JJJJ-MM-TT) angeben.'; $preservedFormRow = $row; $preserveFormSource = 'main'; $formErrorFields = [0]; }
            elseif (($financialError = validateFinancialRow($row)) !== '') { $message = $financialError; $preservedFormRow = $row; $preserveFormSource = 'main'; $formErrorFields = financialErrorFields($financialError); }
            elseif ($index === false) { $rows[] = $row; $savedIndex = count($rows) - 1; $message = 'Eintrag hinzugefügt.'; }
            elseif (!isset($rows[$index])) $message = 'Eintrag wurde nicht gefunden.';
            else { $rows[$index] = $row; $savedIndex = $index; $message = 'Eintrag gespeichert.'; }
            if ($preservedFormRow === null && $savedIndex !== null) {
                if (!backupCsv($file)) $message = 'CSV-Sicherung fehlgeschlagen; Änderung wurde nicht gespeichert.';
                elseif (!writeLedger($file, $rows)) $message = 'CSV-Datei konnte nicht geschrieben werden.';
                else { $highlightIndex = $savedIndex; if ($conversionNote !== '') $message .= ' ' . $conversionNote; }
            }
        }
    } elseif ($action === 'delete') {
        $index = filter_var($_POST['index'] ?? '', FILTER_VALIDATE_INT);
        if ($sourceStaged && $index !== false && isset($stagedRows[$index])) {
            $receiptId = trim($stagedRows[$index][1] ?? '');
            if (!backupCsv($stagedFile)) $message = 'Sicherung von Staged.csv fehlgeschlagen; Staged-Beleg wurde nicht gelöscht.';
            elseif (!preg_match('/^\d{10}$/', $receiptId)) $message = 'Ungültige Belegnummer; Staged-Beleg wurde nicht gelöscht.';
            else {
                $ignoredIds = readIgnoredStageIds($year);
                $ignoredIds[] = $receiptId;
                if (!writeIgnoredStageIds($year, $ignoredIds)) $message = 'Belegnummer konnte nicht dauerhaft vorgemerkt werden; Staged-Beleg wurde nicht gelöscht.';
                else {
                    array_splice($stagedRows, $index, 1);
                    if (writeLedger($stagedFile, $stagedRows)) {
                        $message = 'Staged-Beleg gelöscht; Belegnummer wird bei Aktualisieren übersprungen.';
                        if ($stagedRows) $nextStageIndex = $index < count($stagedRows) ? $index : count($stagedRows) - 1;
                    } else $message = 'Staged.csv konnte nicht geschrieben werden. Belegnummer bleibt vorgemerkt.';
                }
            }
        }
        elseif (!$sourceStaged && $index !== false && isset($rows[$index])) { if (!backupCsv($file)) $message = 'CSV-Sicherung fehlgeschlagen; Eintrag wurde nicht gelöscht.'; else { array_splice($rows, $index, 1); $message = writeLedger($file, $rows) ? 'Eintrag gelöscht.' : 'CSV-Datei konnte nicht geschrieben werden.'; } }
    } elseif ($action === 'new_year') {
        if (!is_file($file)) $message = writeLedger($file, []) ? 'Leere Jahresdatei angelegt.' : 'CSV-Datei konnte nicht angelegt werden.';
        else $message = 'Für dieses Jahr existiert bereits eine CSV-Datei.';
    }
    if (($conversionNote ?? '') !== '' && $preservedFormRow !== null) $message .= ' ' . $conversionNote;
    if ($action !== '') {
        if ($preservedFormRow !== null) {
            $_GET['tab'] = 'form'; $_GET['q'] = (string)($_POST['q'] ?? ''); $_GET['date_from'] = (string)($_POST['date_from'] ?? ''); $_GET['date_to'] = (string)($_POST['date_to'] ?? ''); $_GET['edit'] = $preserveFormSource === 'main' ? ($_POST['index'] ?? '') : null;
            $_GET['stage_edit'] = $preserveFormSource === 'staged' ? ($_POST['index'] ?? '') : null;
        } else {
            $tab = $action === 'stage_archive' || $sourceStaged ? 'stage' : 'list';
            $redirect = ['year'=>$year, 'msg'=>$message, 'tab'=>$tab, 'q'=>($_POST['q'] ?? ''), 'date_from'=>($_POST['date_from'] ?? ''), 'date_to'=>($_POST['date_to'] ?? '')];
            if ($sourceStaged && isset($_POST['index']) && is_numeric($_POST['index'])) $redirect['stage_active'] = (int)$_POST['index'];
            if ($highlightIndex !== null) { $redirect['edit'] = $highlightIndex; $redirect['tab'] = 'list'; }
            if ($nextStageIndex !== null) { $redirect['stage_edit'] = $nextStageIndex; $redirect['stage_active'] = $nextStageIndex; $redirect['tab'] = 'form'; }
            header('Location: ?' . http_build_query($redirect)); exit;
        }
    }
}
if (!is_file($file)) writeLedger($file, []);
$stagedFile = stagedPath($year);
if (!migrateLedgerSchema($file) || !migrateLedgerSchema($stagedFile)) { http_response_code(500); exit('CSV-Schema konnte nicht migriert werden.'); }
$rows = readLedger($file);
$stagedRows = readLedger($stagedFile);
$years = allYears();
$selected = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
$stageSelected = filter_var($_GET['stage_edit'] ?? null, FILTER_VALIDATE_INT);
$stageActive = filter_var($_GET['stage_active'] ?? $_GET['stage_edit'] ?? null, FILTER_VALIDATE_INT);
if ($stageActive === false || $stageActive === null || !isset($stagedRows[$stageActive])) $stageActive = null;
$isStageEditing = $stageSelected !== false && $stageSelected !== null && isset($stagedRows[$stageSelected]);
$activeTab = is_string($_GET['tab'] ?? null) && in_array($_GET['tab'] ?? '', ['list','form','stage','vorsteuer','jahreserklaerung'], true) ? $_GET['tab'] : (($isStageEditing || ($selected !== false && $selected !== null && isset($rows[$selected]))) ? 'form' : 'list');
$filter = is_string($_REQUEST['q'] ?? null) ? trim($_REQUEST['q']) : '';
$dateFromInput = $_REQUEST['date_from'] ?? null;
$dateToInput = $_REQUEST['date_to'] ?? null;
$dateFrom = is_string($dateFromInput) && validIsoDate($dateFromInput) ? $dateFromInput : sprintf('%04d-01-01', $year);
$dateTo = is_string($dateToInput) && validIsoDate($dateToInput) ? $dateToInput : sprintf('%04d-12-31', $year);
$editing = $isStageEditing ? $stagedRows[$stageSelected] : (($selected !== false && $selected !== null && isset($rows[$selected])) ? $rows[$selected] : array_fill(0, count(HEADERS), ''));
if (!$isStageEditing && ($selected === false || $selected === null) && ($editing[3] ?? '') === '') $editing[3] = $defaultType;
if ($preservedFormRow !== null) $editing = $preservedFormRow;
if (($editing[8] ?? '') === '') {
    $countryText = (string)($editing[2] ?? '');
    $defaultCountry = preg_match('/open\s*ai/i', $countryText) ? 'US' : 'DE';
    if (isset($countriesByGroup['EU'][$defaultCountry]) || isset($countriesByGroup['NONEU'][$defaultCountry])) $editing[8] = $defaultCountry;
}
$formEditing = $editing;
if (in_array($editing[3] ?? '', ['Leistung','Ware'], true)) {
    foreach ([4,5] as $i) if (isset($formEditing[$i]) && is_numeric($formEditing[$i])) $formEditing[$i] = (string)(-(float)$formEditing[$i]);
}
$previewReceipt = '';
$previewPdf = false;
if (($isStageEditing || ($selected !== false && $selected !== null && isset($rows[$selected]))) && preg_match('/^\d{10}$/', $editing[1])) {
    $previewPdf = archivePdfPath($archiveDir, $year, $editing[1]);
    if ($previewPdf) $previewReceipt = $editing[1];
}
$extractionNote = '';
if ($isStageEditing && $previewPdf) {
    $extracted = extractedPdfFields($previewPdf);
    if ($extracted !== false) {
        $extractionNote = (string)($extracted['_note'] ?? '');
        $fieldMap = [0=>'date', 4=>'net', 5=>'tax', 6=>'rate'];
        $fieldLabels = [0=>'Buchung', 4=>'Netto', 5=>'USt/VSt', 6=>'USt/VSt %'];
        $changed = false;
        $conflicts = [];
        foreach ($fieldMap as $index=>$key) {
            $currentValue = trim($editing[$index]);
            $extractedValue = trim((string)($extracted[$key] ?? ''));
            if ($currentValue === '' && $extractedValue !== '') {
                $editing[$index] = (string)$extracted[$key];
                $changed = true;
            } elseif ($currentValue !== '' && $extractedValue !== '') {
                if ($index === 0) $matches = $currentValue === $extractedValue;
                elseif ($index === 6) $matches = is_numeric($currentValue) && is_numeric($extractedValue) && (int)round(abs((float)$currentValue)) === (int)round(abs((float)$extractedValue));
                else $matches = is_numeric($currentValue) && is_numeric($extractedValue) && round((float)$currentValue, 2) === round((float)$extractedValue, 2);
                if (!$matches) $conflicts[] = $fieldLabels[$index];
            }
        }
        if ($conflicts) $extractionNote .= ' Bereits eingetragene Werte wurden nicht überschrieben. Abweichungen zum PDF bei: ' . implode(', ', $conflicts) . '.';
        if ($changed) {
            $stagedRows[$stageSelected] = $editing;
            if (backupCsv($stagedFile) && writeLedger($stagedFile, $stagedRows)) $message = 'PDF-Daten wurden als Vorschlag in Staged.csv ergänzt.';
            else $message = 'PDF-Daten werden angezeigt, konnten aber nicht in Staged.csv gespeichert werden.';
        }
    } else $extractionNote = 'PDF-Text konnte nicht ausgelesen werden; bitte Felder manuell ergänzen.';
}
$adoptionComplete = $isStageEditing && adoptionError($editing) === '';
$filteredRows = array_filter($rows, fn($row) => rowMatchesFilter($row, $filter, $dateFrom, $dateTo));
$filteredStagedRows = array_filter($stagedRows, fn($row) => rowMatchesFilter($row, $filter, $dateFrom, $dateTo));
$incomeRows = array_filter($filteredRows, static fn($row) => ($row[3] ?? '') === 'Einnahmen');
$expenseRows = array_filter($filteredRows, static fn($row) => !in_array(($row[3] ?? ''), ['Einnahmen','Spenden'], true));
$sumColumn = static fn(array $entries, int $column): float => array_sum(array_map(static fn($row) => (float)($row[$column] ?? 0), $entries));
$incomeNet = $sumColumn($incomeRows, 4);
$incomeVat = $sumColumn($incomeRows, 5);
$expenseNet = abs($sumColumn($expenseRows, 4));
$expenseVat = abs($sumColumn($expenseRows, 5));
$reverseChargeRows = array_filter($filteredRows, static fn($row) => (string)($row[7] ?? '') === '1');
$regularVatRows = array_filter($filteredRows, static fn($row) => (string)($row[7] ?? '') !== '1');
$reverseChargeVat = array_sum(array_map(static fn($row) => abs((float)str_replace(',', '.', (string)($row[5] ?? '0'))), $reverseChargeRows));
$vatIncludingRc = $sumColumn($regularVatRows, 5) + $reverseChargeVat;
$vatResult = $vatIncludingRc - $reverseChargeVat;
$grossBalance = array_sum(array_map(static fn($row) => calculatedAmount($row) ?? 0.0, array_merge($incomeRows, $expenseRows)));
$calculateRcTotals = static function(array $ledgerRows) use ($countriesByGroup): array {
    $totals = ['euBase'=>0.0,'euTax'=>0.0,'otherBase'=>0.0,'otherTax'=>0.0];
    foreach ($ledgerRows as $row) {
        if ((string)($row[7] ?? '') !== '1') continue;
        $country = strtoupper(trim((string)($row[8] ?? '')));
        if ($country === '') $country = preg_match('/open\s*ai/i', (string)($row[2] ?? '')) ? 'US' : 'DE';
        $base = abs((float)str_replace(',', '.', (string)($row[4] ?? '0')));
        $tax = abs((float)str_replace(',', '.', (string)($row[5] ?? '0')));
        if ($country !== 'DE' && isset($countriesByGroup['EU'][$country])) {
            $totals['euBase'] += $base;
            $totals['euTax'] += $tax;
        } else {
            $totals['otherBase'] += $base;
            $totals['otherTax'] += $tax;
        }
    }
    return $totals;
};
$vatReportRows = array_filter($rows, static function(array $row) use ($dateFrom, $dateTo): bool {
    $date = (string)($row[0] ?? '');
    return validIsoDate($date) && $date >= $dateFrom && $date <= $dateTo;
});
$calculateElsterTotals = static function(array $ledgerRows) use ($calculateRcTotals): array {
    $totals = ['sales19Base'=>0.0,'sales19Tax'=>0.0,'sales7Base'=>0.0,'sales7Tax'=>0.0,'salesOtherBase'=>0.0,'salesOtherTax'=>0.0,'inputTax'=>0.0];
    foreach ($ledgerRows as $row) {
        $type = trim((string)($row[3] ?? ''));
        $net = (float)str_replace(',', '.', (string)($row[4] ?? '0'));
        $tax = (float)str_replace(',', '.', (string)($row[5] ?? '0'));
        $rate = (float)str_replace(',', '.', (string)($row[6] ?? '0'));
        $isRc = (string)($row[7] ?? '') === '1';
        if ($type === 'Einnahmen' && !$isRc) {
            if (abs($rate - 19) < 0.001) { $totals['sales19Base'] += $net; $totals['sales19Tax'] += $tax; }
            elseif (abs($rate - 7) < 0.001) { $totals['sales7Base'] += $net; $totals['sales7Tax'] += $tax; }
            elseif ($rate > 0) { $totals['salesOtherBase'] += $net; $totals['salesOtherTax'] += $tax; }
        } elseif (!$isRc && !in_array($type, ['Einnahmen','Spenden'], true)) {
            $totals['inputTax'] += abs($tax);
        }
    }
    $totals['rc'] = $calculateRcTotals($ledgerRows);
    $totals['rcInputTax'] = $totals['rc']['euTax'] + $totals['rc']['otherTax'];
    $totals['vatDue'] = $totals['sales19Tax'] + $totals['sales7Tax'] + $totals['salesOtherTax'] + $totals['rcInputTax'] - $totals['inputTax'] - $totals['rcInputTax'];
    return $totals;
};
$vatReport = $calculateElsterTotals($vatReportRows);
$annualYear = $year - 1;
$annualCandidates = [
    __DIR__ . DIRECTORY_SEPARATOR . $annualYear . DIRECTORY_SEPARATOR . 'EUER.csv',
    __DIR__ . DIRECTORY_SEPARATOR . $annualYear . '.csv',
];
$annualCsvPath = null;
foreach ($annualCandidates as $candidatePath) if (is_file($candidatePath)) { $annualCsvPath = $candidatePath; break; }
$annualAvailable = $annualCsvPath !== null;
$annualReport = $calculateElsterTotals($annualAvailable ? readLedger($annualCsvPath) : []);
$vatUstvaRows = [
    ['81','Steuerpflichtige Umsätze 19 %',$vatReport['sales19Base'],$vatReport['sales19Tax']],
    ['86','Steuerpflichtige Umsätze 7 %',$vatReport['sales7Base'],$vatReport['sales7Tax']],
    ['35 / 36','Umsätze zu anderen Steuersätzen',$vatReport['salesOtherBase'],$vatReport['salesOtherTax']],
    ['46 / 47','§ 13b: sonstige Leistungen EU',$vatReport['rc']['euBase'],$vatReport['rc']['euTax']],
    ['84 / 85','§ 13b: andere Leistungen',$vatReport['rc']['otherBase'],$vatReport['rc']['otherTax']],
    ['66','Abziehbare Vorsteuer aus Rechnungen',null,$vatReport['inputTax']],
    ['67','Abziehbare Vorsteuer aus § 13b',null,$vatReport['rcInputTax']],
    ['83','Verbleibende Umsatzsteuer-Vorauszahlung / Überschuss',null,$vatReport['vatDue']],
];
$vatAnnualRows = [
    ['177','Steuerpflichtige Umsätze 19 %',$annualReport['sales19Base'],$annualReport['sales19Tax']],
    ['275','Steuerpflichtige Umsätze 7 %',$annualReport['sales7Base'],$annualReport['sales7Tax']],
    ['155 / 156','Umsätze zu anderen Steuersätzen',$annualReport['salesOtherBase'],$annualReport['salesOtherTax']],
    ['846 / 847','§ 13b: sonstige Leistungen EU',$annualReport['rc']['euBase'],$annualReport['rc']['euTax']],
    ['877 / 878','§ 13b: andere Leistungen',$annualReport['rc']['otherBase'],$annualReport['rc']['otherTax']],
    ['320','Abziehbare Vorsteuer aus Rechnungen',null,$annualReport['inputTax']],
    ['467','Abziehbare Vorsteuer aus § 13b',null,$annualReport['rcInputTax']],
];
$reportBase = static fn(?float $value): string => $value === null ? '—' : number_format(round($value), 0, ',', '.') . ' €';
$reportTax = static fn(float $value): string => number_format($value, 2, ',', '.') . ' €';
$message = is_string($_GET['msg'] ?? null) ? $_GET['msg'] : $message;
?>
<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>EÜR <?=h((string)$year)?></title>
<style>
:root{font-family:system-ui,-apple-system,"Segoe UI",sans-serif;color:#202733;background:#f3f5f8}*{box-sizing:border-box}body{margin:0}.top{padding:14px 22px;background:#17324d;color:white;display:flex;gap:18px;align-items:center;justify-content:space-between}.top h1{font-size:1.25rem;margin:0}.top small{opacity:.8}.toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;justify-content:flex-end}.date-range{display:flex;align-items:center;gap:0;margin:0;overflow:hidden;border-radius:5px;background:#e5eaf0}.date-range label{display:flex;align-items:center;gap:4px;padding:0 8px;color:#243344;font-size:.8rem;white-space:nowrap}.date-range input{height:38px;min-width:132px;padding:8px 5px;border:0;background:transparent;color:#243344;font:inherit}.date-range .date-separator{height:38px;padding:8px 10px;border-right:1px solid #cbd4de;border-left:1px solid #cbd4de;border-radius:0;background:#f0f2f5;color:#526174;cursor:default}.date-range .btn[type=submit]{height:38px;padding:8px 10px;border-left:1px solid #cbd4de;border-radius:0}.button-group{display:flex;align-items:center;overflow:hidden;border-radius:5px}.button-group form{margin:0}.button-group select{height:38px;padding:9px 13px;border:0;border-right:1px solid #cbd4de;background:#e5eaf0;color:#243344;font:inherit;cursor:pointer}.button-group .btn{height:38px;border-radius:0;padding:9px 13px}.layout{display:grid;grid-template-columns:minmax(350px,1fr) minmax(320px,1fr);gap:16px;padding:16px;max-width:1600px;margin:auto}.layout.report-layout{grid-template-columns:minmax(0,1fr);max-width:none}.left-column{display:grid;gap:16px;align-content:start;min-width:0}.tabs{display:flex;gap:4px;border-bottom:1px solid #cbd4de}.tab-menu{margin-left:auto}.tab{padding:9px 15px;color:#334155;text-decoration:none;border-radius:6px 6px 0 0}.tab.active{background:#1769aa;color:white}.tab-menu{position:relative}.tab-menu summary{list-style:none;user-select:none}.tab-menu summary::-webkit-details-marker{display:none}.tab-dropdown{position:absolute;top:100%;left:0;z-index:5;display:grid;min-width:190px;padding:5px;background:#fff;border:1px solid #d8dee7;border-radius:0 6px 6px 6px;box-shadow:0 4px 12px #22334422}.tab-dropdown a{padding:9px 10px;color:#334155;text-decoration:none;border-radius:4px;white-space:nowrap}.tab-dropdown a:hover,.tab-dropdown a.active{background:#e8f2fb;color:#1769aa}.preview-panel{position:sticky;top:16px;height:calc(100vh - 100px);min-height:420px}.preview-filename{padding:8px 14px;border-bottom:1px solid #e3e7ec;font-size:.85rem;color:#526174;overflow-wrap:anywhere}.pdf-preview{display:block;width:100%;height:calc(100% - 84px);border:0;background:#e9edf2}.panel{background:white;border:1px solid #d8dee7;border-radius:9px;box-shadow:0 2px 8px #2233440c;overflow:hidden}.panel h2{font-size:1rem;margin:0;padding:14px 16px;border-bottom:1px solid #e3e7ec}.filter-bar{padding:10px 12px;display:flex;gap:8px;border-bottom:1px solid #e3e7ec}.filter-bar input{flex:1;min-width:0;padding:8px 10px;border:1px solid #bbc5d0;border-radius:5px;font:inherit}.listing{max-height:42vh;overflow:auto}.visually-hidden{position:absolute!important;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}.entry{display:grid;grid-template-columns:88px 84px minmax(70px,1fr) 82px;gap:8px;padding:9px 12px;border-bottom:1px solid #edf0f3;align-items:center;font-size:.88rem}.bookings-listing .entry{grid-template-columns:78px 84px minmax(70px,1fr) 72px 90px}.stage-listing .entry{grid-template-columns:78px 84px minmax(70px,1fr) 72px 90px}.bookings-listing .entry>span:first-child,.stage-listing .entry>span:first-child{white-space:nowrap}.entry.head{position:sticky;top:0;background:#eef2f6;font-weight:650;z-index:1}.entry.active{background:#e8f2fb}.entry.invalid{box-shadow:inset 3px 0 #c52a36}.entry:not(.head):hover{background:#f0f6fc}.entry a{color:inherit;text-decoration:none}.entry a:hover{text-decoration:underline}.desc{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.money{text-align:right;font-variant-numeric:tabular-nums}.negative{color:#b4232d;font-weight:600}.missing{display:block;color:#b4232d;font-size:.7rem;white-space:nowrap}.missing-ref{color:#b4232d!important;font-weight:700}.form{padding:16px}.form fieldset.entry-fields{border:1px solid #d8dee7;border-radius:6px;padding:10px 12px 0;margin:0 0 12px;min-width:0}.form fieldset.entry-fields legend{padding:0 6px;font-size:.85rem;font-weight:650;color:#526174}.form label{display:block;font-size:.85rem;font-weight:600;margin:0 0 12px} .form input,.form select{width:100%;padding:9px;border:1px solid #bbc5d0;border-radius:5px;margin-top:4px;font:inherit}.form input[type=checkbox]{width:auto;padding:0;vertical-align:middle}.form label.field-7 input[type=checkbox]{display:block;width:18px;height:18px;margin:9px 0 0}.form input[readonly]{background:#f2f5f8;color:#526174}.input-with-unit{display:flex;align-items:center;gap:7px;margin-top:4px}.form .input-with-unit input{margin-top:0;text-align:right}.form label.invalid-field input,.form label.invalid-field select{border-color:#c52a36;background:#fff5f5;box-shadow:0 0 0 2px #c52a3622}.form label.invalid-field{color:#a51f2b}.input-unit{color:#526174;white-space:nowrap}.fields{display:grid;grid-template-columns:1fr 1fr;gap:0 12px}.fields[hidden]{display:none}.vat-report-body{padding:12px 16px 18px}.vat-report-body p{margin:0 0 14px;color:#526174;font-size:.88rem}.vat-report-body h3{margin:16px 0 8px;font-size:.95rem}.vat-report-body table{width:100%;border-collapse:collapse;font-size:.9rem}.vat-report-body th,.vat-report-body td{padding:8px;border-bottom:1px solid #e3e7ec;text-align:left}.vat-report-body td:last-child{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}.vat-report-body tbody th{width:70px}.vat-report-body tbody tr:hover{background:#f0f6fc}.wide{grid-column:1/-1}@media(min-width:992px){.fields{grid-template-columns:repeat(12,minmax(0,1fr))}.fields label{grid-column:span 6}.fields label:nth-child(-n+3){grid-column:span 4}.fields label.wide{grid-column:1/-1}.fields label.field-4,.fields label.field-5,.fields label.field-6{grid-column:span 4}.fields label.gross-field,.fields label.field-8,.fields label.field-7{grid-column:span 4}.btn{border:0;border-radius:5px;background:#1769aa;color:white;text-decoration:none;padding:9px 13px;font:inherit;cursor:pointer}.btn.secondary{background:#e5eaf0;color:#243344}.btn.danger{background:#a32b32}.actions{display:flex;gap:8px;align-items:center}.actions .new-entry{margin-left:auto}.notice{margin:12px 16px 0;padding:10px;background:#e9f5e9;border-radius:5px}.archive-error{margin:12px;padding:10px 12px;border-radius:5px;background:#fff0f0;color:#a51f2b;border:1px solid #e8b7bb}.extraction-note{margin:10px 16px;padding:10px 12px;border-radius:5px;background:#fff4d6;color:#744d00;font-size:.9rem}.empty{padding:18px;color:#657386}.sum{padding:10px 14px;background:#f7f9fb;text-align:right;font-weight:650}.summary-table{padding:8px 14px;background:#f7f9fb}.summary-row{display:grid;grid-template-columns:minmax(100px,1fr) minmax(90px,130px) minmax(90px,130px);gap:8px;padding:5px 0;text-align:right;font-variant-numeric:tabular-nums}.summary-row span:first-child,.summary-row strong:first-child{text-align:left}.summary-head{font-size:.8rem;color:#526174;border-bottom:1px solid #d8dee7}.summary-result{border-top:1px solid #d8dee7;margin-top:4px;font-weight:700}form.inline{display:inline}@media(max-width:850px){.layout{grid-template-columns:1fr}.listing{max-height:55vh}.top{align-items:flex-start;flex-direction:column}.entry{grid-template-columns:75px 68px minmax(70px,1fr) 70px}.bookings-listing .entry{grid-template-columns:62px 68px minmax(48px,1fr) 52px 60px}.stage-listing .entry{grid-template-columns:62px 68px minmax(48px,1fr) 52px 60px}}
.entry .type{white-space:nowrap}</style></head><body>
<header class="top"><div><h1>Einnahmenüberschussrechnung · <?=h((string)$year)?></h1><small><?=count($rows)?> Buchungen · <?=count($stagedRows)?> staged</small></div><div class="toolbar"><form method="get" class="date-range button-group"><input type="hidden" name="year" value="<?=$year?>"><input type="hidden" name="tab" value="<?=h($activeTab)?>"><input type="hidden" name="q" value="<?=h($filter)?>"><label>Von <input type="date" name="date_from" value="<?=h($dateFrom)?>"></label><button class="btn secondary date-separator" type="button" tabindex="-1" aria-hidden="true">bis</button><label>Bis <input type="date" name="date_to" value="<?=h($dateTo)?>"></label><button class="btn secondary" type="submit">Anwenden</button></form><div class="button-group"><form method="get"><input type="hidden" name="tab" value="<?=h($activeTab)?>"><input type="hidden" name="q" value="<?=h($filter)?>"><input type="hidden" name="date_from" value="<?=h($dateFrom)?>"><input type="hidden" name="date_to" value="<?=h($dateTo)?>"><select name="year" onchange="this.form.elements.date_from.value=this.value+'-01-01';this.form.elements.date_to.value=this.value+'-12-31';this.form.submit()" aria-label="Jahr"><?php foreach($years as $y):?><option value="<?=$y?>" <?=$y===$year?'selected':''?>><?=$y?></option><?php endforeach?></select></form><form method="post" class="inline"><input type="hidden" name="year" value="<?=$year?>"><input type="hidden" name="q" value="<?=h($filter)?>"><input type="hidden" name="date_from" value="<?=h($dateFrom)?>"><input type="hidden" name="date_to" value="<?=h($dateTo)?>"><button class="btn secondary" name="action" value="new_year">Jahr anlegen</button></form></div></div></header>
<?php if($message!==''):?><div class="notice"><?=h($message)?></div><?php endif?>
<main class="layout <?=in_array($activeTab,['vorsteuer','jahreserklaerung'],true)?'report-layout':''?>"><div class="left-column"><nav class="tabs" role="tablist"><a role="tab" class="tab <?=$activeTab==='list'?'active':''?>" href="?year=<?=$year?>&amp;tab=list&amp;q=<?=rawurlencode($filter)?>&amp;date_from=<?=h($dateFrom)?>&amp;date_to=<?=h($dateTo)?><?=$selected!==false && $selected!==null && isset($rows[$selected])?'&amp;edit='.$selected:''?><?=$stageActive!==null?'&amp;stage_active='.$stageActive:''?>">Belege (<?=count($filteredRows)?>)</a><a role="tab" class="tab <?=$activeTab==='stage'?'active':''?>" href="?year=<?=$year?>&amp;tab=stage&amp;q=<?=rawurlencode($filter)?>&amp;date_from=<?=h($dateFrom)?>&amp;date_to=<?=h($dateTo)?><?=$selected!==false && $selected!==null && isset($rows[$selected])?'&amp;edit='.$selected:''?><?=$stageActive!==null?'&amp;stage_active='.$stageActive:''?>">Stage (<?=count($filteredStagedRows)?>)</a><a role="tab" class="tab <?=$activeTab==='form'?'active':''?>" href="?year=<?=$year?>&amp;tab=form&amp;q=<?=rawurlencode($filter)?>&amp;date_from=<?=h($dateFrom)?>&amp;date_to=<?=h($dateTo)?><?=$selected!==false && $selected!==null && isset($rows[$selected])?'&amp;edit='.$selected:''?><?=$stageActive!==null?'&amp;stage_active='.$stageActive:''?>">Buchung</a><details class="tab-menu"><summary class="tab <?=in_array($activeTab,['vorsteuer','jahreserklaerung'],true)?'active':''?>">Dead and Tax</summary><div class="tab-dropdown"><a class="<?=$activeTab==='vorsteuer'?'active':''?>" href="?year=<?=$year?>&amp;tab=vorsteuer&amp;q=<?=rawurlencode($filter)?>&amp;date_from=<?=h($dateFrom)?>&amp;date_to=<?=h($dateTo)?>">Vorsteuer</a><a class="<?=$activeTab==='jahreserklaerung'?'active':''?>" href="?year=<?=$year?>&amp;tab=jahreserklaerung&amp;q=<?=rawurlencode($filter)?>&amp;date_from=<?=h($dateFrom)?>&amp;date_to=<?=h($dateTo)?>">Jahreserklärung</a></div></details></nav><section class="panel" <?=$activeTab!=='list'?'hidden':''?>><h2>Buchungen</h2><form method="get" class="filter-bar"><input type="hidden" name="year" value="<?=$year?>"><input type="hidden" name="tab" value="list"><input type="hidden" name="date_from" value="<?=h($dateFrom)?>"><input type="hidden" name="date_to" value="<?=h($dateTo)?>"><input type="search" name="q" value="<?=h($filter)?>" placeholder="Belegnummer oder Beschreibung filtern"><button class="btn secondary">Filtern</button><?php if($filter!==''):?><a class="btn secondary" href="?year=<?=$year?>&amp;date_from=<?=h($dateFrom)?>&amp;date_to=<?=h($dateTo)?>">Zurücksetzen</a><?php endif?></form><div class="listing bookings-listing"><div class="entry head"><span>Datum</span><span>Beleg</span><span>Beschreibung</span><span>Typ</span><span class="money">Brutto</span></div>
<?php foreach($filteredRows as $i=>$r):?><?php $receiptId=$r[1]; $amount=calculatedAmount($r); $financialProblem=validateFinancialRow($r); $receiptFiles=preg_match('/^\d{10}$/',$receiptId)&&archivePdfPath($archiveDir,$year,$receiptId)!==false?[true]:[]; ?><div class="entry <?=$selected===$i?'active':''?> <?=$financialProblem!==''?'invalid':''?>" onclick="window.location='?year=<?=$year?>&amp;edit=<?=$i?>&amp;q=<?=rawurlencode($filter)?>&amp;tab=form&amp;date_from=<?=h($dateFrom)?>&amp;date_to=<?=h($dateTo)?>'" style="cursor:pointer"><span><?=h($r[0])?></span><span><?php if($receiptId!==''):?><a class="<?=$receiptFiles?'':'missing-ref'?>" <?=$receiptFiles?'':'title="PDF fehlt"'?> href="?year=<?=$year?>&amp;edit=<?=$i?>&amp;q=<?=rawurlencode($filter)?>&amp;tab=form&amp;date_from=<?=h($dateFrom)?>&amp;date_to=<?=h($dateTo)?>" onclick="event.stopPropagation()"><?=h($receiptId)?></a><?php else:?><?=h($receiptId)?><?php endif?></span><span class="desc" title="<?=h($r[2]??'')?>"><?=h(shortDescription((string)($r[2]??'')))?></span><span class="type"><?=h($r[3])?><?=($r[7]??'')==='1'?' · RC':''?></span><span title="<?=h($financialProblem)?>" class="money <?=($amount??0)<0?'negative':''?>"><?=$amount!==null?number_format($amount,2,',','.').' €':'—'?></span></div><?php endforeach?>
<?php if(!$filteredRows):?><div class="empty"><?=$filter!==''?'Keine Belege für diesen Filter gefunden.':'Noch keine Buchungen in diesem Jahr.'?></div><?php endif?></div><div class="summary-table"><div class="summary-row summary-head"><span></span><span>Netto</span><span>USt/VSt</span></div><div class="summary-row"><strong>Einnahmen</strong><span><?=number_format($incomeNet,2,',','.')?> €</span><span><?=number_format($incomeVat,2,',','.')?> €</span></div><div class="summary-row"><strong>Ausgaben</strong><span><?=number_format($expenseNet,2,',','.')?> €</span><span><?=number_format($expenseVat,2,',','.')?> €</span></div><div class="summary-row"><strong>Reverse Charge</strong><span>—</span><span><?=number_format(-$reverseChargeVat,2,',','.')?> €</span></div><div class="summary-row summary-result"><strong>Ergebnis</strong><strong><?=number_format($incomeNet-$expenseNet,2,',','.')?> €</strong><strong><?=number_format($vatResult,2,',','.')?> €</strong></div></div><form method="post" class="filter-bar"><input type="hidden" name="year" value="<?=$year?>"><input type="hidden" name="q" value="<?=h($filter)?>"><input type="hidden" name="date_from" value="<?=h($dateFrom)?>"><input type="hidden" name="date_to" value="<?=h($dateTo)?>"><button class="btn secondary" name="action" value="stage_archive">Aktualisieren</button><span>Neue nummerierte PDF-Belege werden zunächst als Staged eingetragen.</span></form></section>
<section class="panel" <?=$activeTab!=='stage'?'hidden':''?>><h2>Staged-Belege · <?=count($filteredStagedRows)?> vorgemerkt</h2><?php if(!$archiveExists):?><p class="archive-error">PDF_ARCHIVE-Verzeichnis nicht gefunden: <?=h($archive)?></p><?php endif?><form method="get" class="filter-bar"><input type="hidden" name="year" value="<?=$year?>"><input type="hidden" name="tab" value="stage"><input type="hidden" name="date_from" value="<?=h($dateFrom)?>"><input type="hidden" name="date_to" value="<?=h($dateTo)?>"><input type="search" name="q" value="<?=h($filter)?>" placeholder="Belegnummer oder Typ filtern"><button class="btn secondary">Filtern</button><?php if($filter!==''):?><a class="btn secondary" href="?year=<?=$year?>&amp;tab=stage&amp;date_from=<?=h($dateFrom)?>&amp;date_to=<?=h($dateTo)?>">Zurücksetzen</a><?php endif?></form><div class="listing stage-listing"><div class="entry head"><span>Buchung</span><span>Beleg</span><span>Beschreibung</span><span>Typ</span><span class="money">Brutto</span></div><?php foreach($filteredStagedRows as $i=>$r):?><?php $amount=calculatedAmount($r);?><div class="entry <?=$stageActive===$i?'active':''?>"><span><?=h($r[0])?></span><span><a href="?year=<?=$year?>&amp;stage_edit=<?=$i?>&amp;stage_active=<?=$i?>&amp;tab=form&amp;q=<?=rawurlencode($filter)?>&amp;date_from=<?=h($dateFrom)?>&amp;date_to=<?=h($dateTo)?>"><?=h($r[1])?></a></span><span class="desc" title="<?=h($r[2]??'')?>"><?=h(shortDescription((string)($r[2]??'')))?></span><span class="type"><?=h($r[3])?><?=($r[7]??'')==='1'?' · RC':''?></span><span class="money"><?=$amount!==null?number_format($amount,2,',','.').' €':'—'?></span></div><?php endforeach?><?php if(!$filteredStagedRows):?><div class="empty"><?=$filter!==''?'Keine Stage-Belege für diesen Filter gefunden.':'Keine staged Belege vorhanden.'?></div><?php endif?></div><form method="post" class="filter-bar"><input type="hidden" name="year" value="<?=$year?>"><input type="hidden" name="q" value="<?=h($filter)?>"><input type="hidden" name="date_from" value="<?=h($dateFrom)?>"><input type="hidden" name="date_to" value="<?=h($dateTo)?>"><button class="btn secondary" name="action" value="stage_archive" <?=$archiveExists?'':'disabled'?>>Aktualisieren</button><span>Neue nummerierte PDFs werden in Staged.csv vorgemerkt.</span></form></section>
<section class="panel" <?=$activeTab!=='form'?'hidden':''?>><h2><?=$isStageEditing?'Staged-Beleg prüfen':($selected!==false && $selected!==null?'Eintrag bearbeiten':'Neuer Eintrag')?></h2><?php if($isStageEditing && $extractionNote!==''):?><p class="extraction-note"><?=h($extractionNote)?></p><?php endif?>
<form method="post" class="form"><input type="hidden" name="year" value="<?=$year?>"><input type="hidden" name="q" value="<?=h($filter)?>"><input type="hidden" name="date_from" value="<?=h($dateFrom)?>"><input type="hidden" name="date_to" value="<?=h($dateTo)?>"><input type="hidden" name="index" value="<?=$isStageEditing?$stageSelected:($selected!==false && $selected!==null?$selected:'')?>"><input type="hidden" name="source" value="<?=$isStageEditing?'staged':'main'?>"><div class="fields">
<?php foreach([0,1,3,2,4,5,6,8,7] as $i): if($i===8):?></div><fieldset class="entry-fields"><legend>BFM Unsinn</legend><div class="fields"><?php $initialGross=is_numeric($formEditing[4]??null)&&is_numeric($formEditing[5]??null)?number_format((float)$formEditing[4]+(float)$formEditing[5],2,',','.') : '';?><label class="gross-field">Brutto<div class="input-with-unit"><input id="grossAmount" value="<?=h($initialGross)?>" readonly aria-label="Berechneter Bruttobetrag"><span class="input-unit">€</span></div></label><?php endif; $label=$i===7?'Reverse Charge':HEADERS[$i];?><label class="field-<?=$i?> <?=$i===2?'wide':''?> <?=in_array($i,$formErrorFields,true)?'invalid-field':''?>"><?=h($label)?><?php if($i===7):?><input type="checkbox" name="entry[7]" value="1" <?=$editing[7]==='1'?'checked':''?> aria-label="Reverse Charge"> <?php elseif($i===3):?><select name="entry[<?=$i?>]"><?php foreach($typeOptions as $type):?><option <?=$editing[$i]===$type?'selected':''?>><?=h($type)?></option><?php endforeach?></select><?php elseif($i===8):?><select name="entry[8]"><option value="">Land auswählen</option><?php foreach($countriesByGroup as $group=>$options): if(!$options) continue;?><optgroup label="<?=h($group==='NONEU'?'Nicht-EU':($group==='EU'?'EU':'Weitere'))?>"><?php foreach($options as $code=>$country):?><option value="<?=h($code)?>" <?=($editing[8]??"")===$code?"selected":""?>><?=h($country)?></option><?php endforeach?></optgroup><?php endforeach?></select><?php else:?><?php $moneyField=in_array($i,[4,5],true); $rateField=$i===6; $shownValue=$moneyField?(is_numeric($formEditing[$i])?displayNumber($formEditing[$i]):$formEditing[$i]):($rateField?($editing[$i]!==''?number_format((float)str_replace(',', '.', $editing[$i]),0,',',''):''):$editing[$i]); ?><?php if($moneyField||$rateField):?><div class="input-with-unit"><input name="entry[<?=$i?>]" value="<?=h($shownValue)?>" inputmode="decimal" <?=$i===4?'placeholder="z. B. 100*-1,5%"':''?> <?=$i===5?'readonly':''?> ><span class="input-unit"><?=$moneyField?'€':'%'?></span></div><?php else:?><input name="entry[<?=$i?>]" value="<?=h($shownValue)?>" <?=$i===0?'type="date"'.(!$isStageEditing?' required':''):''?> <?=$i===1?'placeholder="Belegnummer"':''?> <?=$i===2?'placeholder="Buchungstext"':''?>><?php endif?><?php endif?></label><?php endforeach?></div></fieldset><div class="actions"><button class="btn" name="action" value="save">Speichern</button><?php if($isStageEditing):?><button class="btn" name="action" value="adopt" formnovalidate <?= $adoptionComplete ? '' : 'disabled' ?>>Übernehmen</button><?php endif?><?php if(!$isStageEditing):?><a class="btn secondary new-entry" href="?year=<?=$year?>&amp;tab=form&amp;q=<?=rawurlencode($filter)?>&amp;date_from=<?=h($dateFrom)?>&amp;date_to=<?=h($dateTo)?>">Neu</a><?php endif?><?php if(($isStageEditing) || ($selected!==false && $selected!==null && isset($rows[$selected]))):?><button class="btn danger" name="action" value="delete" formnovalidate <?= $isStageEditing ? '' : 'onclick="return confirm(&quot;Eintrag wirklich löschen?&quot;)"' ?>>Löschen</button><?php endif?></div></form></section><section class="panel vat-report" <?=$activeTab!=="vorsteuer"?'hidden':''?>><h2>Vorsteuer · USt-Voranmeldung</h2><div class="vat-report-body"><p>Zeitraum <?=h($dateFrom)?> bis <?=h($dateTo)?></p><table><thead><tr><th>Kz.</th><th>ELSTER-Feld</th><th>Bemessungsgrundlage</th><th>Steuer / Vorsteuer</th></tr></thead><tbody><?php foreach($vatUstvaRows as [$code,$label,$base,$tax]):?><tr><th><?=h($code)?></th><td><?=h($label)?></td><td><?=h($reportBase($base))?></td><td><?=h($reportTax($tax))?></td></tr><?php endforeach?></tbody></table><p>Enthalten sind die aus den Buchungen ableitbaren Felder. Weitere ELSTER-Angaben (z. B. innergemeinschaftliche Warenkäufe, Einfuhrumsatzsteuer, steuerfreie Umsätze oder Vorauszahlungen) sind nicht in der CSV erfasst.</p></div></section><section class="panel vat-report" <?=$activeTab!=="jahreserklaerung"?'hidden':''?>><h2>USt-Jahreserklärung <?=h((string)$annualYear)?></h2><div class="vat-report-body"><?php if(!$annualAvailable):?><p>Vorjahresdaten (<?=h((string)$annualYear)?>/EUER.csv) nicht vorhanden.</p><?php endif?><table><thead><tr><th>Kz.</th><th>ELSTER-Feld</th><th>Bemessungsgrundlage</th><th>Steuer / Vorsteuer</th></tr></thead><tbody><?php foreach($vatAnnualRows as [$code,$label,$base,$tax]):?><tr><th><?=h($code)?></th><td><?=h($label)?></td><td><?=h($annualAvailable ? $reportBase($base) : '—')?></td><td><?=h($annualAvailable ? $reportTax($tax) : '—')?></td></tr><?php endforeach?></tbody></table><p>Enthalten sind die aus den Vorjahresbuchungen ableitbaren Felder. Weitere ELSTER-Angaben (z. B. innergemeinschaftliche Warenkäufe, Einfuhrumsatzsteuer, steuerfreie Umsätze oder Vorauszahlungen) sind nicht in der CSV erfasst.</p></div></section></div><?php if(!in_array($activeTab,['vorsteuer','jahreserklaerung'],true)):?><section class="panel preview-panel"><h2>Belegvorschau</h2><?php if($previewPdf):?><div class="preview-filename"><?=h(basename($previewPdf))?></div><?php endif?><iframe id="pdfPreview" name="pdfPreview" class="pdf-preview" title="PDF-Beleg" src="<?=$previewReceipt!==''?'?year='.$year.'&amp;beleg='.rawurlencode($previewReceipt):'about:blank'?>"></iframe><?php if($previewReceipt===''):?><div class="empty">Belegnummer anklicken, um das PDF hier anzuzeigen.</div><?php endif?></section><?php endif?></main><script>
const firstInvalid=document.querySelector('.form label.invalid-field input,.form label.invalid-field select');
if(firstInvalid){firstInvalid.focus();firstInvalid.scrollIntoView({behavior:'smooth',block:'center'});}
const bookingsListing=document.querySelector('.bookings-listing');
const activeBooking=bookingsListing?.querySelector('.entry.active');
if(activeBooking&&!bookingsListing.closest('.panel').hidden){activeBooking.scrollIntoView({behavior:'smooth',block:'nearest'});}
const stageListing=document.querySelector('.stage-listing');
const activeStage=stageListing?.querySelector('.entry.active');
if(activeStage&&!stageListing.closest('.panel').hidden){activeStage.scrollIntoView({behavior:'smooth',block:'nearest'});}
const bookingForm=document.querySelector('.form');
if(bookingForm){
  const typeField=bookingForm.querySelector('[name="entry[3]"]');
  const netField=bookingForm.querySelector('[name="entry[4]"]');
  const taxField=bookingForm.querySelector('[name="entry[5]"]');
  const rateField=bookingForm.querySelector('[name="entry[6]"]');
  const grossField=bookingForm.querySelector('#grossAmount');
  const number=value=>{
    value=(value||'').trim();
    const discount=value.match(/^(.+)\*-([0-9]+(?:[,.][0-9]+)?)%$/);
    if(discount){const base=number(discount[1]),percent=number(discount[2]);return base!==null&&percent!==null&&percent>=0&&percent<=100?base*(1-percent/100):null;}
    if(value.includes('%'))return null;
    value=value.replace(/[$€\s]/g,'');
    if(value.includes(',')){const comma=value.lastIndexOf(','), dot=value.lastIndexOf('.');value=dot>comma?value.replace(/,/g,''):value.replace(/\./g,'').replace(',','.');}
    const tokens=value.match(/(?:\d+(?:\.\d*)?|\.\d+)|[()+\-*\/]/g);
    if(!tokens||tokens.join('')!==value)return null;
    let p=0;
    const factor=()=>{const t=tokens[p];if(t==='+'||t==='-'){p++;const n=factor();return t==='-'?-n:n;}if(t==='('){p++;const n=expr();if(tokens[p++]!==')')throw Error();return n;}if(t===undefined||!Number.isFinite(Number(t)))throw Error();p++;return Number(t);};
    const term=()=>{let n=factor();while(tokens[p]==='*'||tokens[p]==='/'){const op=tokens[p++],r=factor();if(op==='/'&&r===0)throw Error();n=op==='*'?n*r:n/r;}return n;};
    const expr=()=>{let n=term();while(tokens[p]==='+'||tokens[p]==='-'){const op=tokens[p++],r=term();n=op==='+'?n+r:n-r;}return n;};
    try{const n=expr();return p===tokens.length&&Number.isFinite(n)?n:null;}catch{return null;}
  };
  const sanitizeAmount=field=>{field.value=field.value.replace(field===netField?/[^0-9,.+*\/$%-]/g:/[^0-9,.+*\/$-]/g,'');};
  const amountFields=[netField,taxField,rateField];
  amountFields.forEach(field=>{sanitizeAmount(field);field.addEventListener('input',()=>sanitizeAmount(field));});
  bookingForm.addEventListener('submit',()=>amountFields.forEach(sanitizeAmount));
  const setDonationTax=()=>{if(typeField.value==='Spenden'){taxField.value='0';rateField.value='0';}};
  const setNetOnlyDefaults=()=>{if(typeField.value!=='Spenden'&&netField.value.trim()!==''&&taxField.value.trim()===''&&rateField.value.trim()===''){taxField.value='0';rateField.value='0';}};
  const recalculateGross=()=>{
    const net=number(netField.value), tax=number(taxField.value);
    grossField.value=net!==null&&tax!==null?(net+tax).toFixed(2).replace('.',','):'';
  };
  const recalculateTax=()=>{
    if(typeField.value==='Spenden'){taxField.value='0';recalculateGross();return;}
    const net=number(netField.value), rate=number(rateField.value);
    taxField.value=net!==null&&rate!==null?(net*Math.round(rate)/100).toFixed(2).replace('.',','):'';
    recalculateGross();
  };
  typeField.addEventListener('change',setDonationTax);
  typeField.addEventListener('change',recalculateTax);
  netField.addEventListener('input',setDonationTax);
  netField.addEventListener('input',setNetOnlyDefaults);
  netField.addEventListener('input',recalculateTax);
  rateField.addEventListener('input',recalculateTax);
  setDonationTax();
  recalculateTax();
  taxField.addEventListener('input',recalculateGross);
  const adoptButton=bookingForm.querySelector('[name="action"][value="adopt"]');
  if(adoptButton){
    const field=name=>bookingForm.querySelector('[name="entry['+name+']"]');
    const checkAdoption=()=>{
      const date=field(0).value, receipt=field(1).value.trim(), description=field(2).value.trim();
      const net=number(field(4).value), tax=number(field(5).value), rate=number(field(6).value);
      let complete=/^\d{4}-\d{2}-\d{2}$/.test(date)&&/^\d{10}$/.test(receipt)&&description!==''&&net!==null&&tax!==null&&rate!==null;
      if(complete){
        const taxPercent=Math.abs(net)<0.000001?0:Math.round(Math.abs(tax/net)*100);
        const enteredPercent=Math.round(Math.abs(rate));
        complete=Math.abs(taxPercent-enteredPercent)<0.001;
        if(typeField.value==='Spenden')complete=complete&&Math.abs(tax)<0.001&&Math.abs(rate)<0.000001;
      }
      adoptButton.disabled=!complete;
    };
    bookingForm.querySelectorAll('.fields input,.fields select').forEach(el=>el.addEventListener('input',checkAdoption));
    bookingForm.querySelectorAll('.fields input,.fields select').forEach(el=>el.addEventListener('change',checkAdoption));
    checkAdoption();
  }
}
</script></body></html>
