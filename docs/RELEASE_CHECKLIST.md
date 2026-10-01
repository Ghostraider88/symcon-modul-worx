# Installation und Release-Abnahme

Zielrepository: [`Ghostraider88/symcon-modul-worx`](https://github.com/Ghostraider88/symcon-modul-worx)

## Aktueller Arbeitsstand (2026-10-01)

- Arbeitsbranch: `codex/worx-wr105si` (laufender Entwicklungs- und Testbranch).
- Der Docker-Testkernel wurde zuletzt mit Library 2.0 / Build 20 aus `753b00c` geprüft. Der Modul-Checkout im Docker wurde auf `d3824f6` aktualisiert. Die Anzeige des nächsten Planstarts wurde am 2026-10-01 in der Symcon-Laufzeit mit `01.10.2026 17:00` bestätigt.
- GitHub Actions für den aktuellen Branch: sechs Prüfungen erfolgreich (PHP-Syntax, Style und Tests auf beiden Runnern).
- PR #1 ist offen und als Entwurf markiert. `main` ist noch nicht veröffentlicht.
- Der zuletzt dokumentierte Docker-Zustand: Kernel 9.0, Cloud und Mower aktiv, Mäher in der Ladestation, Fehler 0, Mähzeitplan täglich 17:00–19:00 mit den ursprünglichen Kantenschnitt-Tagen. Keine Fahraktion beim Build-20-Check.
- Die Detailbelege, Gerätegrenzen und nicht belegten Funktionen stehen in der [Feature-Matrix](FEATURE_MATRIX.md). Die Architektur und der Updatepfad stehen in [ARCHITECTURE.md](ARCHITECTURE.md).

## Noch zu erledigen – in dieser Reihenfolge

1. **Manueller Kantenschnitt (abgeschlossen):** Worx führt den WR105SI.1 nicht unter den Modellen für den manuellen Einzel-Kantenschnitt. Die Aktion ist deshalb nicht bedienbar; geplanter Kantenschnitt bleibt im Wochenplan verfügbar.
2. **Einstellungs-Rückrichtung (abgeschlossen):** Der Nutzer bestätigte am 2026-10-01, dass eine in der Worx-App geänderte tägliche Arbeitszeit unmittelbar in Symcon erscheint. Symcon→Cloud-Schreibrundläufe sind ebenfalls belegt.
3. **Mehrzonen:** das Modell meldet `multi_zone` und `multi_zone_percentage`, der Nutzer verwendet aber nur eine Zone. Die nichtnullige `cfg.mz`-/`cfg.mzv`-Abbildung und ein sicherer Schreib-Rundlauf fehlen. Keine Bedienelemente oder Payloads raten; erst mit einem echten Mehrzonen-Gerätebeleg implementieren.
4. **Installationshärtung:** kontrollierte vollständige Neuinstallation mit Kontoeinrichtung und Repository-Deinstallation; Live-Fehlerfälle für Cloud/MQTT, Token-Erneuerung und Rate-Limit. Bestehende Unit-Tests decken HTTP-/Parserfehler bereits ab.
5. **Nicht belegte App-Felder:** „Ganzer Tag“ und Zeitfenster über Mitternacht bleiben ohne nachgewiesene verlustfreie Protokollabbildung dokumentiert und werden nicht als bedienbar ausgegeben. Einmaliger Einsatz und Party-Modus sind für dieses Modell nicht belegt.
6. **Nächster Planstart (abgeschlossen):** read-only Anzeige in Docker geprüft; mit unverändertem Plan wird `01.10.2026 17:00` angezeigt. Es wurde kein Mähbefehl ausgelöst.
7. **Veröffentlichung:** Matrix, Dokumentation und Teststand abgleichen, alle CI-Prüfungen grün halten und PR #1 nach Abschluss der notwendigen Abnahme zur Veröffentlichung über `main` freigeben.

## Abgeschlossene Kernabnahme

- Eigene, stabile Library-/Modul-GUIDs; Catomic-Präfixe und bestehende Variablen-Idents bleiben erhalten. Die Regressionstests prüfen beide Invarianten.
- Native Symcon-Wochenplanereignis „Mähzeitplan“ als einziger Editor; beide Synchronisationsrichtungen sind im Docker-Test bestätigt. Einzeltage deaktivieren und Kantenschnitt-Tage ändern sich mit Geräteecho. `ApplyChanges()` sendet keinen Mähbefehl.
- Status-/Fehlerzahlen und direkt daneben lesbare String-Variablen; keine Legacy-Variablenprofile. Die Zeitverlängerung bildet die signierten Werte −100…+100 % direkt ab.
- Start, Pause und Heimfahrt wurden am Gerät geprüft; der Nutzer bestätigte die Rückkehr in die Ladestation.
- Regenverzögerung, Sperre, Zeitverlängerung, automatischer Zeitplan und Cloud-Präferenz für Firmware-Auto-Update wurden mit Echo geprüft und auf ihre Ausgangswerte zurückgestellt. App→Symcon für die tägliche Arbeitszeitänderung wurde live vom Nutzer bestätigt. Ein Firmware-Update selbst wurde nicht gestartet.

## Testinstallation

Für die Einrichtung im leeren Symcon-Kernel werden WebSocket Client und MQTT Client als Symcon-Kommunikationsmodule benötigt. Worx Cloud erzeugt den passenden MQTT-Client; ein eigener Broker mit frei erfundenem Host/Port ist nicht erforderlich. Danach Worx Configurator zum Anlegen des Mähers verwenden. Vollständige Schritte und Voraussetzungen stehen im [Repository-README](../README.md).

Vor Änderungen am Live-Zeitplan immer aktuellen vollständigen `cfg.sc`-Plan und Ereignispunkte sichern, nur gezielt testen, Geräteecho abwarten und exakt auf den Ausgangszustand zurückstellen. MQTT-Publish oder HTTP-Erfolg allein ist keine Gerätebestätigung.
