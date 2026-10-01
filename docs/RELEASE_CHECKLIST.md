# Installation und Release-Abnahme

Zielrepository: [`Ghostraider88/symcon-modul-worx`](https://github.com/Ghostraider88/symcon-modul-worx)

## Aktueller Arbeitsstand (2026-10-01)

- Arbeitsbranch: `codex/worx-wr105si` (laufender Entwicklungs- und Testbranch).
- Der Docker-Testkernel (Symcon Kernel 9.0) läuft mit Library 2.0 / Build 20. Der Docker-Checkout folgt dem Arbeitsbranch `codex/worx-wr105si`; der letzte PHP-Code-Commit ist `8741ee9`, spätere Branchcommits enthalten Dokumentations- und Prüfnachweise. Der Nutzer bestätigte auf diesem Code die App→Symcon-Rückmeldung der täglichen Arbeitszeit mit +10 % und Rückstellung auf 0 %. Die Anzeige des nächsten Planstarts `01.10.2026 17:00` wurde ebenfalls bestätigt; bei beiden Prüfungen wurde kein Mähbefehl ausgelöst.
- Ein zusätzlicher, vollständig getrennter Symcon-9-Kernel ohne Worx-Zugangsdaten oder Mäherinstanz wurde am 2026-10-01 für den Modul-Lebenszyklus verwendet. Installation des Repositorys, Umschalten auf `codex/worx-wr105si` (Commit `ff866eb5`), Laden der Library 2.0 / Build 20 und Erkennung aller drei Modul-GUIDs waren erfolgreich. Unkonfigurierte Cloud- und Mower-Instanzen ließen sich anlegen und verbinden; zweimaliges `ApplyChanges()` ließ die 35 Mower-Statusvariablen unverändert. Die Instanzen wurden anschließend entfernt, das Repository erfolgreich deinstalliert und derselbe Branch erneut frisch installiert. Der fehlende Cloud-Elterntransport und die fehlenden Zugangsdaten hielten beide Instanzen inaktiv; ein echter Konto-Login war damit nicht Teil dieses Tests.
- Vor dem Merge müssen alle sechs GitHub-Actions-Prüfungen auf dem aktuellen PR-Commit erfolgreich sein (PHP-Syntax, Style und Tests auf beiden Runnern).
- PR #1 ist offen und als Entwurf markiert. `main` ist noch nicht veröffentlicht.
- Der zuletzt dokumentierte Docker-Zustand: Kernel 9.0, Cloud und Mower aktiv, Mäher in der Ladestation, Fehler 0, Mähzeitplan täglich 17:00–19:00 mit den ursprünglichen Kantenschnitt-Tagen. Keine Fahraktion beim Build-20-Check.
- Die Detailbelege, Gerätegrenzen und nicht belegten Funktionen stehen in der [Feature-Matrix](FEATURE_MATRIX.md). Die Architektur und der Updatepfad stehen in [ARCHITECTURE.md](ARCHITECTURE.md).

## Abgeschlossen

- Manueller Einzel-Kantenschnitt wird für WR105SI.1 nicht angeboten; der geplante Kantenschnitt bleibt im Wochenplan verfügbar.
- App→Symcon für die tägliche Arbeitszeit ist live bestätigt: +10 % erschien unmittelbar in Symcon und wurde danach auf 0 % zurückgestellt. Symcon→Cloud-Schreibrundläufe sind ebenfalls belegt.
- Die read-only Anzeige „Nächster Planstart“ zeigte bei unverändertem Plan `01.10.2026 17:00`; dabei wurde kein Mähbefehl ausgelöst.

## Vor Veröffentlichung noch erforderlich – in dieser Reihenfolge

1. **Installationshärtung:** Repository-Installation, Branch-Update, Instanzanlage, idempotentes `ApplyChanges()`, Instanzentfernung und Repository-Deinstallation sind im isolierten Kernel geprüft. Kontoeinrichtung mit echtem Worx-Konto und ein Live-Ausfalltest für Cloud/MQTT bleiben offen. Unit-Tests decken Transport-/HTTP-/Parserfehler einschließlich 401, 429 und 503 sowie Refresh-Token-Erneuerung und Passwort-Fallback ab; ein Live-429 wird wegen des Worx-API-Limits nicht absichtlich ausgelöst.
2. **Nicht belegte App-Felder:** „Ganzer Tag“ und Zeitfenster über Mitternacht bleiben ohne nachgewiesene verlustfreie Protokollabbildung dokumentiert und werden nicht als bedienbar ausgegeben. Einmaliger Einsatz und Party-Modus sind für dieses Modell nicht belegt.
3. **Firmware-OTA:** Die read-only Metadatenabfrage lieferte für WR105SI.1 im Livekonto keine verwertbare Antwort. Die Startaktion wird daher nur nach positiver, seriennummergebundener Bestätigung eingeblendet. Eine positive Liveabfrage und ein tatsächliches Update bleiben ungeprüft; ein Update wurde nicht ausgelöst.
4. **Veröffentlichung:** Matrix, Dokumentation und Teststand abgleichen, alle CI-Prüfungen grün halten und PR #1 nach Abschluss der notwendigen Abnahme über `main` veröffentlichen.

## Explizite Scope-Entscheidung

Mehrzonenbetrieb ist für dieses Vorhaben aus dem Nutzungs- und Abnahmeumfang genommen: Der Nutzer verwendet ausschließlich eine Zone. Die Hersteller-Capability und die nicht geklärte `cfg.mz`/`cfg.mzv`-Semantik bleiben in der Feature-Matrix als Gerätefakten dokumentiert; das Modul zeigt dafür keine Bedienung an und verändert die Gerätekonfiguration nicht.

## Abgeschlossene Kernabnahme

- Eigene, stabile Library-/Modul-GUIDs; Catomic-Präfixe und bestehende Variablen-Idents bleiben erhalten. Die Regressionstests prüfen beide Invarianten.
- Native Symcon-Wochenplanereignis „Mähzeitplan“ als einziger Editor; beide Synchronisationsrichtungen sind im Docker-Test bestätigt. Einzeltage deaktivieren und Kantenschnitt-Tage ändern sich mit Geräteecho. `ApplyChanges()` sendet keinen Mähbefehl.
- Status-/Fehlerzahlen und direkt daneben lesbare String-Variablen; keine Legacy-Variablenprofile. Die Zeitverlängerung bildet die signierten Werte −100…+100 % direkt ab.
- Start, Pause und Heimfahrt wurden am Gerät geprüft; der Nutzer bestätigte die Rückkehr in die Ladestation.
- Regenverzögerung, Sperre, Zeitverlängerung, automatischer Zeitplan und Cloud-Präferenz für Firmware-Auto-Update wurden mit Echo geprüft und auf ihre Ausgangswerte zurückgestellt. App→Symcon für die tägliche Arbeitszeitänderung wurde live vom Nutzer bestätigt. Ein Firmware-Update selbst wurde nicht gestartet.

## Testinstallation

Für die Einrichtung im leeren Symcon-Kernel werden WebSocket Client und MQTT Client als Symcon-Kommunikationsmodule benötigt. Worx Cloud erzeugt den passenden MQTT-Client; ein eigener Broker mit frei erfundenem Host/Port ist nicht erforderlich. Danach Worx Configurator zum Anlegen des Mähers verwenden. Vollständige Schritte und Voraussetzungen stehen im [Repository-README](../README.md).

Vor Änderungen am Live-Zeitplan immer aktuellen vollständigen `cfg.sc`-Plan und Ereignispunkte sichern, nur gezielt testen, Geräteecho abwarten und exakt auf den Ausgangszustand zurückstellen. MQTT-Publish oder HTTP-Erfolg allein ist keine Gerätebestätigung.
