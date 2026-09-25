# Installation und Release-Abnahme

Diese Checkliste gilt für `Ghostraider88/symcon-modul-worx`. Der Branch `codex/worx-wr105si` ist der aktuelle Teststand; `main` ist erst nach Abschluss der Geräte- und Installationsabnahme für die Veröffentlichung vorgesehen.

## Installation in einer Symcon-Testinstanz

1. Unter **Kerninstanz → Module Control** `https://github.com/Ghostraider88/symcon-modul-worx` als Repository eintragen und den Branch `codex/worx-wr105si` auswählen.
2. Die von Symcon benötigten Kommunikationsmodule **WebSocket Client** und **MQTT Client** installieren.
3. Eine Instanz **Worx Cloud** anlegen. Worx-Konto, Cloud „Worx Landroid“, MQTT-Echtzeit/Steuerung und ein Abfrageintervall ab 30 Sekunden konfigurieren.
4. Speichern und den Status der Cloud-Instanz prüfen. Es wird kein eigener MQTT-Broker benötigt; Host und Port eines lokalen Brokers gehören nicht in die Worx-Konfiguration.
5. Im **Worx Configurator** den erkannten Mäher hinzufügen und prüfen, dass Status, Firmware und „Gerätenachweis (redigiert)“ ankommen.
6. Prüfen, dass die Mower-Instanz genau ein natives, deaktiviertes Wochenplanereignis „Mähzeitplan“ mit sieben Tagesgruppen enthält.

## Updateprüfung

- Im selben Testkernel den Branch `codex/worx-wr105si` aktualisieren.
- Prüfen, dass vorhandene Worx-Fork-Instanzen erhalten bleiben und ihre GUIDs, Präfixe und Variablen-Idents unverändert sind.
- Konfiguration erneut anwenden und prüfen, dass keine doppelten Ereignisse oder Variablen entstehen und `ApplyChanges()` keinen Mäh- oder Zeitplanbefehl sendet.
- Nach einem Kernelneustart erneut Cloud-Verbindung, Statusdaten und automatischen App→Symcon-Zeitplanabgleich kontrollieren.

## Abnahmestand WR105SI.1

| Prüfung | Stand |
|---|---|
| Anmeldung, Geräteerkennung und Live-Status in Docker | Bestätigt |
| `State`/`Error` als Zahlen ohne Aufzählung; `StateText`/`ErrorText` daneben und bei Statuswechsel aktualisiert; neue Reihenfolge wird auf Bestandsinstanz angewandt | Sortierung und Status-Zahl-/Textdarstellung nach Modulupdate durch Nutzer in Docker bestätigt; Fehler-Textdarstellung noch zu prüfen. Für Verlaufskurven muss Archive Control die gewünschte String-Variable aufzeichnen. |
| Symcon-Wochenplan → Worx-App/Mäher | Vom Nutzer bestätigt |
| Worx-App → Symcon-Wochenplan ohne manuellen Refresh | Vom Nutzer bestätigt |
| „Ganzer Tag“ und Zeitfenster über Mitternacht | Vorher-/Nachher-Datensatz beim Umschalten von „Ganzer Tag“ identisch; Wire-Abbildung bleibt offen. Mitternachtsfenster ebenfalls offen. |
| Automatischer Zeitplan, Regenverzögerung, tägliche Arbeitszeitänderung und Sperre | Schreibwege implementiert; Arbeitszeitfeld ist eine unveränderte signierte Abbildung; Legacy-Profilanzeige durch moderne Wertdarstellung ersetzt, Docker-Ansicht noch zu bestätigen; Geräteechos der Einstellungen offen. Firmware-Auto-Update wird capability-geprüft read-only aus dem Cloud-Boolean angezeigt; kein Symcon-Schreibschalter. |
| Start, Pause und Heimfahrt | Implementiert; am echten Mäher noch nicht bestätigt |
| Neuinstallation, Update, wiederholte Konfiguration und Wiederanlauf | Noch vollständig abzunehmen |
| Fehlerfälle (falsche Zugangsdaten, Cloud-/MQTT-Ausfall, leere Antwort) | Noch vollständig abzunehmen |
| Mindestversion IP-Symcon 9.0 | Manifest und README aktualisiert; PHP-8.5-Syntaxprüfung läuft in CI |

Gerätesteuerungen nur einzeln und mit sichtbarer Sollwert-/Rückmeldungsprüfung ausprobieren. Ein MQTT-Publish oder HTTP-Erfolg allein gilt nicht als Gerätebestätigung. Keine Aktionen im Rahmen statischer Codeprüfungen ausführen.

## Prüfschritt: tägliche Arbeitszeit −100…+100 %

Nach Aktualisierung des Testbranches und erneutem Anwenden der Mower-Konfiguration:

1. Nach Modulupdate und erneutem Anwenden der Mower-Konfiguration in der Worx-App −100 %, −50 %, 0 % und +30 % einstellen. „Tägliche Arbeitszeit (bestätigt)“ muss jeweils denselben Wert wie App und redigiertes `cfg.sc.p` zeigen.
2. Für den Schreibrundlauf in Symcon „Tägliche Arbeitszeit setzen“ nacheinander auf −40 % und +60 % setzen. Die Worx-App und „Tägliche Arbeitszeit (bestätigt)“ müssen jeweils denselben Wert zeigen; der redigierte `cfg.sc.p`-Wert muss dem Sollwert entsprechen; Status und Mäher-Echo abwarten. Anschließend den notierten Ausgangswert wiederherstellen.
3. Vor dem Test den aktuellen App-Wert notieren. Nach dem Echo den gewünschten Ausgangswert gezielt wiederherstellen, falls er geändert wurde.

Die Werte müssen jeweils aus demselben Rückmeldezeitpunkt stammen. Eine bloße Publish-Meldung zählt nicht als Gerätebestätigung.
## Veröffentlichung

`main` erst aktualisieren, wenn die offenen Punkte der [Feature-Matrix](FEATURE_MATRIX.md) und obigen Abnahme geschlossen oder nachvollziehbar als nicht unterstützte Modellfunktion gekennzeichnet sind, GitHub Style- und Testprüfungen erfolgreich sind und README, Modulformulare und Updatepfad den tatsächlich bestätigten Stand beschreiben.
