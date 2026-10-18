# EÜR-Verwaltung

Diese Anwendung ist entstanden, um meine eigene EÜR- und Belegverwaltung einfacher zu machen. Vielleicht kennst du ähnliche Probleme. Deshalb veröffentliche ich den Code hier: Forke das Projekt, entwickle es weiter und passe es an deine Bedürfnisse an.

Das Belegnummernschema stammt aus zwei weiteren Anwendungen, die nach demselben Prinzip funktionieren. Auch diese möchte ich hier veröffentlichen, sobald sie so funktionieren, wie ich es mir vorstelle.

Die lokale PHP-Anwendung erfasst und prüft Buchungen, ordnet PDF-Belege zu und speichert Buchungen pro Jahr in CSV-Dateien. Sie ist eine organisatorische Arbeitshilfe und ersetzt keine steuerliche Beratung oder Prüfung.

## Voraussetzungen

- PHP CLI 8 oder neuer
- Bash und `curl`
- Webbrowser
- `pdftotext` für die PDF-Texterkennung

Für die Umrechnung von USD in EUR ist eine Internetverbindung zum Abruf der EZB-Referenzkurse erforderlich.

## Start

Im Anwendungsverzeichnis starten:

```bash
./serv.sh
```

Der PHP-Server läuft unter <http://127.0.0.1:8003>. Das Startskript öffnet die Adresse mit `librewolf` oder `xdg-open`, falls eines dieser Programme installiert ist. Mit `Strg+C` wird der Server beendet. Er ist standardmäßig nur vom lokalen Rechner erreichbar.

## Konfiguration

Die Anwendung liest `.env` aus dem Anwendungsverzeichnis. Beispiel:

```dotenv
PDF_ARCHIVE=/path/to/pdf-archive
EUER_DEFAULT_TYPE=Leistung
EU="DE,NL"
NONEU="US"
```

`PDF_ARCHIVE` gibt den Speicherort des Belegarchivs an. Relative Pfade werden vom Anwendungsverzeichnis aus aufgelöst. Erwartet werden Jahresordner mit nummerierten PDF-Dateien, zum Beispiel:

```text
pdf-archive/
└── 2026/
    └── 2026031001-Rechnung.pdf
```

`EUER_DEFAULT_TYPE` legt die vorausgewählte Buchungsart fest. Zulässige Werte sind `Einnahmen`, `Leistung`, `Ware`, `Sonstiges` und `Spenden`.

`EU` und `NONEU` enthalten kommaseparierte Listen der zweistelligen Länderkürzel, die im Länderfeld des Formulars angeboten werden. Der Select zeigt die Ländernamen gruppiert nach EU und Nicht-EU an; gespeichert wird das jeweilige Kürzel.

## Datendateien

Die Anwendung legt ihre Daten pro Jahr ab:

```text
YYYY/EUER.csv       gespeicherte Buchungen
YYYY/Staged.csv     noch zu prüfende Belege
YYYY/Ignored.csv    entfernte Stage-Belegnummern
YYYY/backups/       CSV-Sicherungen
```

Die PDF-Texterkennung liest das PDF beim Öffnen eines Stage-Belegs direkt aus. Die Suche durchsucht ausschließlich die CSV-Daten und öffnet keine PDF-Dateien.

CSV-Dateien verwenden UTF-8 mit BOM und Semikolon als Trennzeichen. Das Buchungsschema lautet:

```text
Buchung;Beleg;Beschreibung;Typ;Netto;UST/VST;UST/VST %;RC;Land
```

`RC` enthält `1` für Buchungen mit Reverse-Charge-Kennzeichnung und ist andernfalls leer. `Land` speichert das ausgewählte zweistellige ISO-Länderkürzel des Leistungserbringers. Vor Änderungen erstellt die Anwendung Sicherungen und behält je CSV-Datei höchstens fünf Sicherungen. Ältere CSV-Schemata werden beim Laden migriert.

## Projektvermerk

PHP war die pragmatische Wahl, weil ich normalerweise damit entwickle. Die `index.php` habe ich mir selbst allerdings nicht einmal angesehen. Die Programmierung dieser Anwendung wurde mit OpenAI Codex erstellt.
