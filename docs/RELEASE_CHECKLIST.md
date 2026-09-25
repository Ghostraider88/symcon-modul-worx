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
| Symcon-Wochenplan → Worx-App/Mäher | Vom Nutzer bestätigt |
| Worx-App → Symcon-Wochenplan ohne manuellen Refresh | Vom Nutzer bestätigt |
| „Ganzer Tag“ und Zeitfenster über Mitternacht | Offen; Format und verlustfreie Symcon-Abbildung belegen |
| Automatischer Zeitplan, Regenverzögerung, tägliche Arbeitszeitänderung und Sperre | Schreibweg implementiert; Zeit-Skala korrigiert, Echo nach Korrektur noch zu bestätigen; übrige Geräteechos je Funktion offen |
| Start, Pause und Heimfahrt | Implementiert; am echten Mäher noch nicht bestätigt |
| Neuinstallation, Update, wiederholte Konfiguration und Wiederanlauf | Noch vollständig abzunehmen |
| Fehlerfälle (falsche Zugangsdaten, Cloud-/MQTT-Ausfall, leere Antwort) | Noch vollständig abzunehmen |
| Mindestversion IP-Symcon 9.0 | Manifest und README aktualisiert; PHP-8.5-Syntaxprüfung läuft in CI |

Gerätesteuerungen nur einzeln und mit sichtbarer Sollwert-/Rückmeldungsprüfung ausprobieren. Ein MQTT-Publish oder HTTP-Erfolg allein gilt nicht als Gerätebestätigung. Keine Aktionen im Rahmen statischer Codeprüfungen ausführen.

## Prüfschritt: tägliche Arbeitszeit −100…+100 %

Nach Aktualisierung des Testbranches und erneutem Anwenden der Mower-Konfiguration:

1. Nach Modulupdate und erneutem Anwenden der Mower-Konfiguration den nächsten Cloud-Status abwarten. Im vorliegenden Gerätezustand muss „Tägliche Arbeitszeit (bestätigt)“ den aus `cfg.sc.p=-40` gelesenen Wert −40 % anzeigen. Die Instanzkonfiguration zeigt denselben Wert als „Vom Mäher gemeldete tägliche Arbeitszeitänderung“. Fehlt eine der Anzeigen oder weicht sie ab, den redigierten `cfg.sc.p`-Wert und die bestätigte Variable vergleichen.
2. Für den Schreibrundlauf in Symcon „Tägliche Arbeitszeit setzen“ auf einen von der aktuellen App-Einstellung abweichenden negativen Wert setzen. Erst wenn die Worx-App und „Zeiterweiterung (bestätigt)“ denselben Wert zurückmelden, gilt das ausgehende Mapping als bestätigt; anschließend den notierten Ausgangswert wiederherstellen.
3. Vor dem Test den aktuellen App-Wert notieren. Nach dem Echo den gewünschten Ausgangswert gezielt wiederherstellen, falls er geändert wurde.

Die Werte müssen jeweils aus demselben Rückmeldezeitpunkt stammen. Eine bloße Publish-Meldung zählt nicht als Gerätebestätigung.
## Veröffentlichung

`main` erst aktualisieren, wenn die offenen Punkte der [Feature-Matrix](FEATURE_MATRIX.md) und obigen Abnahme geschlossen oder nachvollziehbar als nicht unterstützte Modellfunktion gekennzeichnet sind, GitHub Style- und Testprüfungen erfolgreich sind und README, Modulformulare und Updatepfad den tatsächlich bestätigten Stand beschreiben.
