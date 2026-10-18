<?php
declare(strict_types=1);

const HEADERS = ['Buchung', 'Beleg', 'Beschreibung', 'Typ', 'Netto', 'UST/VST', 'Brutto', 'UST/VST %'];

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (!in_array($requestPath, ['/', '/index.php'], true)) { http_response_code(404); exit('Nicht gefunden'); }

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
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
function stagedPath(int $year): string {
    return __DIR__ . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . 'Staged.csv';
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
    while (($row = fgetcsv($fp, 0, ';')) !== false) {
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
function stagedArchiveRows(string $archiveDir, int $year, array $rows): array {
    $known = [];
    foreach ($rows as $row) if (($row[1] ?? '') !== '') $known[$row[1]] = true;
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
        $staged[] = ['', $id, $description, 'Sonstiges', '', '', '', ''];
        $known[$id] = true;
    }
    return $staged;
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
function normalizeNumber(string $value): string {
    $value = str_replace(['€', '%', ' '], '', trim($value));
    if (str_contains($value, ',')) $value = str_replace(',', '.', str_replace('.', '', $value));
    return $value;
}
function displayNumber(string $value, float $factor = 1.0): string {
    if ($value === '' || !is_numeric(str_replace(',', '.', $value))) return '';
    return number_format((float)str_replace(',', '.', $value) * $factor, 2, ',', '');
}
function validateFinancialRow(array $row): string {
    foreach ([4=>'Netto',5=>'USt/VSt',6=>'Brutto',7=>'USt/VSt %'] as $index=>$label) {
        if (!isset($row[$index]) || trim((string)$row[$index]) === '' || !is_numeric(str_replace(',', '.', (string)$row[$index]))) return $label . ' muss als Zahl angegeben werden.';
    }
    $net = (float)str_replace(',', '.', (string)$row[4]);
    $tax = (float)str_replace(',', '.', (string)$row[5]);
    $gross = (float)str_replace(',', '.', (string)$row[6]);
    $rate = (float)str_replace(',', '.', (string)$row[7]);
    if (abs(round($net + $tax, 2) - round($gross, 2)) > 0.001) return 'Brutto muss Netto plus USt/VSt entsprechen.';
    $expectedPercent = abs($net) < 0.000001 ? 0.0 : round(abs($tax / $net) * 100, 2);
    $enteredPercent = round(abs($rate) * 100, 2);
    if ($expectedPercent !== $enteredPercent) return 'USt/VSt % passt nicht zum Verhältnis von Netto und Steuerbetrag.';
    return '';
}
function rowFromPost(): array {
    $row = [];
    foreach (HEADERS as $i => $key) $row[] = trim((string)($_POST['entry'][$i] ?? ''));
    foreach ([4,5,6] as $i) $row[$i] = normalizeNumber($row[$i]);
    $row[7] = normalizeNumber($row[7]);
    if ($row[7] !== '' && is_numeric($row[7])) $row[7] = (string)((float)$row[7] / 100);
    return $row;
}

if (isset($_GET['staged_download'])) {
    $stagedDownload = stagedPath(validYear($_GET['year'] ?? date('Y')));
    if (!is_file($stagedDownload)) { http_response_code(404); exit('Staged.csv nicht gefunden'); }
    header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="Staged.csv"');
    readfile($stagedDownload); exit;
}
if (isset($_GET['download'])) {
    $downloadYear = validYear($_GET['year'] ?? date('Y'));
    $downloadFile = csvPath($downloadYear);
    if (!is_file($downloadFile)) { http_response_code(404); exit('CSV nicht gefunden'); }
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="EUER.csv"');
    readfile($downloadFile); exit;
}

$env = loadEnv(__DIR__ . '/.env');
$archive = $env['PDF_ARCHIVE'] ?? '../PDF_Archive';
$archiveDir = realpath(__DIR__ . DIRECTORY_SEPARATOR . $archive) ?: (__DIR__ . DIRECTORY_SEPARATOR . $archive);
if (isset($_GET['beleg']) && is_string($_GET['beleg']) && preg_match('/^\d{10}$/', $_GET['beleg'])) {
    $dir = realpath($archiveDir . DIRECTORY_SEPARATOR . validYear($_GET['year'] ?? date('Y')));
    $base = realpath($archiveDir);
    $matches = $dir && $base && str_starts_with($dir, $base . DIRECTORY_SEPARATOR)
        ? (glob($dir . DIRECTORY_SEPARATOR . $_GET['beleg'] . '-*.pdf') ?: []) : [];
    $pdf = $matches ? realpath($matches[0]) : false;
    if (!$pdf || !str_starts_with($pdf, $dir . DIRECTORY_SEPARATOR)) { http_response_code(404); exit('Beleg nicht gefunden'); }
    header('Content-Type: application/pdf'); header('Content-Disposition: inline; filename="' . basename($pdf) . '"');
    readfile($pdf); exit;
}
$year = validYear($_REQUEST['year'] ?? date('Y'));
$file = csvPath($year);
$message = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $year = validYear($_POST['year'] ?? $year); $file = csvPath($year);
    $rows = readLedger($file);
    $stagedFile = stagedPath($year);
    $stagedRows = readLedger($stagedFile);
    $sourceStaged = ($_POST['source'] ?? '') === 'staged';
    if ($action === 'stage_archive') {
        $newRows = stagedArchiveRows($archiveDir, $year, array_merge($rows, $stagedRows));
        if (!$newRows) $message = 'Keine neuen nummerierten PDF-Belege im Archiv gefunden.';
        elseif (!backupCsv($stagedFile)) $message = 'Sicherung von Staged.csv fehlgeschlagen; Belege wurden nicht eingetragen.';
        else { $stagedRows = array_merge($stagedRows, $newRows); $message = writeLedger($stagedFile, $stagedRows) ? count($newRows) . ' Beleg(e) in Staged.csv vorgemerkt.' : 'Staged.csv konnte nicht geschrieben werden.'; }
    } elseif ($action === 'adopt') {
        $index = filter_var($_POST['index'] ?? '', FILTER_VALIDATE_INT);
        if (!$sourceStaged || $index === false || !isset($stagedRows[$index])) $message = 'Staged-Beleg nicht gefunden.';
        else {
            $candidate = rowFromPost();
            $gross = trim($candidate[6]);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $candidate[0]) || trim($candidate[1]) === '' || trim($candidate[2]) === '') $message = 'Bitte Buchungsdatum, Belegnummer und Beschreibung ergänzen.';
            elseif (($financialError = validateFinancialRow($candidate)) !== '') $message = $financialError;
            elseif (!backupCsv($file) || !backupCsv($stagedFile)) $message = 'CSV-Sicherung fehlgeschlagen; Beleg wurde nicht übernommen.';
            else {
                $rows[] = $candidate; array_splice($stagedRows, $index, 1);
                if (writeLedger($file, $rows) && writeLedger($stagedFile, $stagedRows)) $message = 'Beleg einzeln nach EUER.csv übernommen.';
                else $message = 'CSV-Datei konnte nicht vollständig aktualisiert werden; Sicherungen liegen in backups/.';
            }
        }
    } elseif ($action === 'save') {
        $row = rowFromPost(); $index = filter_var($_POST['index'] ?? '', FILTER_VALIDATE_INT);
        if ($sourceStaged) {
            if ($index === false || !isset($stagedRows[$index])) $message = 'Staged-Beleg nicht gefunden.';
            else { $financialReady = count(array_filter(array_slice($row,4,4), static fn($v) => trim($v) !== '')) === 4; $financialError = $financialReady ? validateFinancialRow($row) : ''; if ($financialError !== '') $message = $financialError; else { $stagedRows[$index] = $row; if (!backupCsv($stagedFile)) $message = 'Sicherung von Staged.csv fehlgeschlagen; Änderung wurde nicht gespeichert.'; else $message = writeLedger($stagedFile, $stagedRows) ? 'Staged-Beleg gespeichert.' : 'Staged.csv konnte nicht geschrieben werden.'; } }
        } else {
            if ($row[0] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $row[0])) $message = 'Bitte ein gültiges Buchungsdatum (JJJJ-MM-TT) angeben.';
            elseif (($financialError = validateFinancialRow($row)) !== '') $message = $financialError;
            elseif ($index === false) { $rows[] = $row; $message = 'Eintrag hinzugefügt.'; }
            elseif (!isset($rows[$index])) $message = 'Eintrag wurde nicht gefunden.';
            else { $rows[$index] = $row; $message = 'Eintrag gespeichert.'; }
            if ($message !== 'Bitte ein gültiges Buchungsdatum (JJJJ-MM-TT) angeben.' && $message !== 'Eintrag wurde nicht gefunden.') { if (!backupCsv($file)) $message = 'CSV-Sicherung fehlgeschlagen; Änderung wurde nicht gespeichert.'; elseif (!writeLedger($file, $rows)) $message = 'CSV-Datei konnte nicht geschrieben werden.'; }
        }
    } elseif ($action === 'delete') {
        $index = filter_var($_POST['index'] ?? '', FILTER_VALIDATE_INT);
        if ($sourceStaged && $index !== false && isset($stagedRows[$index])) { if (!backupCsv($stagedFile)) $message = 'Sicherung von Staged.csv fehlgeschlagen; Staged-Beleg wurde nicht gelöscht.'; else { array_splice($stagedRows, $index, 1); $message = writeLedger($stagedFile, $stagedRows) ? 'Staged-Beleg gelöscht.' : 'Staged.csv konnte nicht geschrieben werden.'; } }
        elseif (!$sourceStaged && $index !== false && isset($rows[$index])) { if (!backupCsv($file)) $message = 'CSV-Sicherung fehlgeschlagen; Eintrag wurde nicht gelöscht.'; else { array_splice($rows, $index, 1); $message = writeLedger($file, $rows) ? 'Eintrag gelöscht.' : 'CSV-Datei konnte nicht geschrieben werden.'; } }
    } elseif ($action === 'new_year') {
        if (!is_file($file)) $message = writeLedger($file, []) ? 'Leere Jahresdatei angelegt.' : 'CSV-Datei konnte nicht angelegt werden.';
        else $message = 'Für dieses Jahr existiert bereits eine CSV-Datei.';
    }
    if ($action !== '') { $tab = $action === 'stage_archive' || $sourceStaged ? 'stage' : 'list'; header('Location: ?' . http_build_query(['year'=>$year, 'msg'=>$message, 'tab'=>$tab])); exit; }
}
if (!is_file($file)) writeLedger($file, []);
$rows = readLedger($file);
$stagedFile = stagedPath($year);
$stagedRows = readLedger($stagedFile);
$years = allYears();
$selected = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
$stageSelected = filter_var($_GET['stage_edit'] ?? null, FILTER_VALIDATE_INT);
$isStageEditing = $stageSelected !== false && $stageSelected !== null && isset($stagedRows[$stageSelected]);
$activeTab = is_string($_GET['tab'] ?? null) && in_array($_GET['tab'], ['list','form','stage'], true) ? $_GET['tab'] : (($isStageEditing || ($selected !== false && $selected !== null && isset($rows[$selected]))) ? 'form' : 'list');
$filter = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$editing = $isStageEditing ? $stagedRows[$stageSelected] : (($selected !== false && $selected !== null && isset($rows[$selected])) ? $rows[$selected] : array_fill(0, count(HEADERS), ''));
$previewReceipt = '';
if (($isStageEditing || ($selected !== false && $selected !== null && isset($rows[$selected]))) && preg_match('/^\d{10}$/', $editing[1])) {
    $previewMatches = glob($archiveDir . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $editing[1] . '-*.pdf') ?: [];
    if ($previewMatches) $previewReceipt = $editing[1];
}
$message = is_string($_GET['msg'] ?? null) ? $_GET['msg'] : $message;
?>
<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>EÜR <?=h((string)$year)?></title>
<style>
:root{font-family:system-ui,-apple-system,"Segoe UI",sans-serif;color:#202733;background:#f3f5f8}*{box-sizing:border-box}body{margin:0}.top{padding:14px 22px;background:#17324d;color:white;display:flex;gap:18px;align-items:center;justify-content:space-between}.top h1{font-size:1.25rem;margin:0}.top small{opacity:.8}.toolbar{display:flex;gap:10px;align-items:center}.layout{display:grid;grid-template-columns:minmax(350px,1fr) minmax(320px,1fr);gap:16px;padding:16px;max-width:1600px;margin:auto}.left-column{display:grid;gap:16px;align-content:start;min-width:0}.tabs{display:flex;gap:4px;border-bottom:1px solid #cbd4de}.tab{padding:9px 15px;color:#334155;text-decoration:none;border-radius:6px 6px 0 0}.tab.active{background:#1769aa;color:white}.preview-panel{position:sticky;top:16px;height:calc(100vh - 100px);min-height:420px}.pdf-preview{display:block;width:100%;height:calc(100% - 48px);border:0;background:#e9edf2}.panel{background:white;border:1px solid #d8dee7;border-radius:9px;box-shadow:0 2px 8px #2233440c;overflow:hidden}.panel h2{font-size:1rem;margin:0;padding:14px 16px;border-bottom:1px solid #e3e7ec}.filter-bar{padding:10px 12px;display:flex;gap:8px;border-bottom:1px solid #e3e7ec}.filter-bar input{flex:1;min-width:0;padding:8px 10px;border:1px solid #bbc5d0;border-radius:5px;font:inherit}.listing{max-height:42vh;overflow:auto}.entry{display:grid;grid-template-columns:88px 84px minmax(70px,1fr) 82px;gap:8px;padding:9px 12px;border-bottom:1px solid #edf0f3;align-items:center;font-size:.88rem}.entry.head{position:sticky;top:0;background:#eef2f6;font-weight:650;z-index:1}.entry.active{background:#e8f2fb}.entry.invalid{box-shadow:inset 3px 0 #c52a36}.entry:not(.head):hover{background:#f0f6fc}.entry a{color:inherit;text-decoration:none}.entry a:hover{text-decoration:underline}.desc{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.money{text-align:right;font-variant-numeric:tabular-nums}.negative{color:#b4232d;font-weight:600}.missing{display:block;color:#b4232d;font-size:.7rem;white-space:nowrap}.missing-ref{color:#b4232d!important;font-weight:700}.form{padding:16px}.form label{display:block;font-size:.85rem;font-weight:600;margin:0 0 12px}.form input,.form select{width:100%;padding:9px;border:1px solid #bbc5d0;border-radius:5px;margin-top:4px;font:inherit}.input-with-unit{display:flex;align-items:center;gap:7px;margin-top:4px}.form .input-with-unit input{margin-top:0;text-align:right}.input-unit{color:#526174;white-space:nowrap}.fields{display:grid;grid-template-columns:1fr 1fr;gap:0 12px}.wide{grid-column:1/-1}@media(min-width:992px){.fields{grid-template-columns:repeat(12,minmax(0,1fr))}.fields label{grid-column:span 6}.fields label:nth-child(-n+3){grid-column:span 4}.fields label.wide{grid-column:1/-1}}.btn{border:0;border-radius:5px;background:#1769aa;color:white;text-decoration:none;padding:9px 13px;font:inherit;cursor:pointer}.btn.secondary{background:#e5eaf0;color:#243344}.btn.danger{background:#a32b32}.actions{display:flex;gap:8px;align-items:center}.notice{margin:12px 16px 0;padding:10px;background:#e9f5e9;border-radius:5px}.empty{padding:18px;color:#657386}.sum{padding:10px 14px;background:#f7f9fb;text-align:right;font-weight:650}form.inline{display:inline}@media(max-width:850px){.layout{grid-template-columns:1fr}.listing{max-height:55vh}.top{align-items:flex-start;flex-direction:column}.entry{grid-template-columns:75px 68px minmax(70px,1fr) 70px}}
</style></head><body>
<header class="top"><div><h1>Einnahmenüberschussrechnung · <?=h((string)$year)?></h1><small><?=count($rows)?> Buchungen · <?=count($stagedRows)?> staged</small></div><div class="toolbar"><form method="get"><select name="year" onchange="this.form.submit()" aria-label="Jahr"><?php foreach($years as $y):?><option value="<?=$y?>" <?=$y===$year?'selected':''?>><?=$y?></option><?php endforeach?></select></form><form method="post" class="inline"><input type="hidden" name="year" value="<?=$year?>"><button class="btn secondary" name="action" value="new_year">Jahr anlegen</button></form><a class="btn secondary" href="?year=<?=$year?>&download=1">CSV laden</a></div></header>
<?php if($message!==''):?><div class="notice"><?=h($message)?></div><?php endif?>
<main class="layout"><div class="left-column"><nav class="tabs" role="tablist"><a role="tab" class="tab <?=$activeTab==='list'?'active':''?>" href="?year=<?=$year?>&amp;tab=list&amp;q=<?=rawurlencode($filter)?>">Liste</a><a role="tab" class="tab <?=$activeTab==='form'?'active':''?>" href="?year=<?=$year?>&amp;tab=form">Buchung</a><a role="tab" class="tab <?=$activeTab==='stage'?'active':''?>" href="?year=<?=$year?>&amp;tab=stage">Stage (<?=count($stagedRows)?>)</a></nav><section class="panel" <?=$activeTab!=='list'?'hidden':''?>><h2>Buchungen</h2><form method="get" class="filter-bar"><input type="hidden" name="year" value="<?=$year?>"><input type="hidden" name="tab" value="list"><input type="search" name="q" value="<?=h($filter)?>" placeholder="Datum, Belegnummer oder Beschreibung filtern"><button class="btn secondary">Filtern</button><?php if($filter!==''):?><a class="btn secondary" href="?year=<?=$year?>">Zurücksetzen</a><?php endif?></form><div class="listing"><div class="entry head"><span>Datum</span><span>Beleg</span><span>Beschreibung</span><span class="money">Brutto</span></div>
<?php foreach($rows as $i=>$r):?><?php if($filter!=='' && stripos(implode(' ',$r),$filter)===false) continue; $receiptId=$r[1]; $financialProblem=validateFinancialRow($r); $receiptFiles=preg_match('/^\d{10}$/',$receiptId)?(glob($archiveDir.DIRECTORY_SEPARATOR.$year.DIRECTORY_SEPARATOR.$receiptId.'-*.pdf')?:[]):[]; ?><div class="entry <?=$selected===$i?'active':''?> <?=$financialProblem!==''?'invalid':''?>" onclick="window.location='?year=<?=$year?>&amp;edit=<?=$i?>&amp;q=<?=rawurlencode($filter)?>&amp;tab=form'" style="cursor:pointer"><span><?=h($r[0])?></span><span><?php if($receiptId!==''):?><a class="<?=$receiptFiles?'':'missing-ref'?>" <?=$receiptFiles?'':'title="PDF fehlt"'?> href="?year=<?=$year?>&amp;edit=<?=$i?>&amp;q=<?=rawurlencode($filter)?>&amp;tab=form" onclick="event.stopPropagation()"><?=h($receiptId)?></a><?php else:?><?=h($receiptId)?><?php endif?></span><span class="desc"><a href="?year=<?=$year?>&amp;edit=<?=$i?>&amp;q=<?=rawurlencode($filter)?>&amp;tab=form" onclick="event.stopPropagation()"><?=h($r[2])?></a></span><span title="<?=h($financialProblem)?>" class="money <?=((float)str_replace(',', '.', $r[6]))<0?'negative':''?>"><?=number_format((float)str_replace(',', '.', $r[6]),2,',','.')?> €</span></div><?php endforeach?>
<?php if(!$rows):?><div class="empty">Noch keine Buchungen in diesem Jahr.</div><?php endif?></div><div class="sum">Saldo brutto: <?=number_format(array_sum(array_map(static fn($r)=>(float)str_replace(',', '.', $r[6]),$rows)),2,',','.')?> €</div><form method="post" class="filter-bar"><input type="hidden" name="year" value="<?=$year?>"><button class="btn secondary" name="action" value="stage_archive">Aktualisieren</button><span>Neue nummerierte PDF-Belege werden zunächst als Staged eingetragen.</span></form></section>
<section class="panel" <?=$activeTab!=='stage'?'hidden':''?>><h2>Staged-Belege · <?=count($stagedRows)?> vorgemerkt</h2><div class="filter-bar"><a class="btn secondary" href="?year=<?=$year?>&amp;staged_download=1">Staged.csv laden</a></div><div class="listing"><div class="entry head"><span>Buchung</span><span>Beleg</span><span>Beschreibung</span><span class="money">Brutto</span></div><?php foreach($stagedRows as $i=>$r):?><?php if($filter!=='' && stripos(implode(' ',$r),$filter)===false) continue;?><a class="entry" href="?year=<?=$year?>&amp;stage_edit=<?=$i?>&amp;tab=form"><span><?=h($r[0])?></span><span><?=h($r[1])?></span><span class="desc"><?=h($r[2])?></span><span class="money"><?=trim($r[6])!==''?number_format((float)str_replace(',', '.', $r[6]),2,',','.').' €':'—'?></span></a><?php endforeach?><?php if(!$stagedRows):?><div class="empty">Keine staged Belege vorhanden.</div><?php endif?></div><form method="post" class="filter-bar"><input type="hidden" name="year" value="<?=$year?>"><button class="btn secondary" name="action" value="stage_archive">Aktualisieren</button><span>Neue nummerierte PDFs werden in Staged.csv vorgemerkt.</span></form></section>
<section class="panel" <?=$activeTab!=='form'?'hidden':''?>><h2><?=$isStageEditing?'Staged-Beleg prüfen':($selected!==false && $selected!==null?'Eintrag bearbeiten':'Neuer Eintrag')?></h2>
<form method="post" class="form"><input type="hidden" name="year" value="<?=$year?>"><input type="hidden" name="index" value="<?=$isStageEditing?$stageSelected:($selected!==false && $selected!==null?$selected:'')?>"><input type="hidden" name="source" value="<?=$isStageEditing?'staged':'main'?>"><div class="fields">
<?php foreach([0,1,3,2,4,6,5,7] as $i): $label=HEADERS[$i];?><label class="<?=$i===2?'wide':''?>"><?=h($label)?><?php if($i===3):?><select name="entry[<?=$i?>]"><?php foreach(['Leistung','Ware','Sonstiges'] as $type):?><option <?=$editing[$i]===$type?'selected':''?>><?=h($type)?></option><?php endforeach?></select><?php else:?><?php $moneyField=in_array($i,[4,5,6],true); $rateField=$i===7; $shownValue=$moneyField?displayNumber($editing[$i]):($rateField?displayNumber($editing[$i],100):$editing[$i]); ?><?php if($moneyField||$rateField):?><div class="input-with-unit"><input name="entry[<?=$i?>]" value="<?=h($shownValue)?>" inputmode="decimal"><span class="input-unit"><?=$moneyField?'€':'%'?></span></div><?php else:?><input name="entry[<?=$i?>]" value="<?=h($shownValue)?>" <?=$i===0?'type="date"'.(!$isStageEditing?' required':''):''?> <?=$i===1?'placeholder="Belegnummer"':''?> <?=$i===2?'placeholder="Buchungstext"':''?>><?php endif?><?php endif?></label><?php endforeach?></div><div class="actions"><button class="btn" name="action" value="save">Speichern</button><?php if($isStageEditing):?><button class="btn" name="action" value="adopt" formnovalidate>Einzeln übernehmen</button><?php endif?><a class="btn secondary" href="?year=<?=$year?>&amp;tab=form">Neu</a><?php if(($isStageEditing) || ($selected!==false && $selected!==null && isset($rows[$selected]))):?><button class="btn danger" name="action" value="delete" formnovalidate onclick="return confirm('Eintrag wirklich löschen?')">Löschen</button><?php endif?></div></form></section></div><section class="panel preview-panel"><h2>Belegvorschau</h2><iframe id="pdfPreview" class="pdf-preview" title="PDF-Beleg" src="<?=$previewReceipt!==''?'?year='.$year.'&amp;beleg='.rawurlencode($previewReceipt):'about:blank'?>"></iframe><?php if($previewReceipt===''):?><div class="empty">Belegnummer anklicken, um das PDF hier anzuzeigen.</div><?php endif?></section></main></body></html>
