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
| `State`/`Error` als Zahlen ohne Aufzählung; `StateText`/`ErrorText` daneben und bei Statuswechsel aktualisiert; neue Reihenfolge wird auf Bestandsinstanz angewandt | Schreibgeschützter Docker-RPC bestätigt State 1 / „In der Ladestation“ und Error 0 / „Kein Fehler“ mit passenden Strings direkt dahinter (Position 0–3), Integer ohne Variablenprofile und ohne Aktion. Verlaufshistorie noch nicht geprüft; Archive Control muss die gewünschten Strings separat aufzeichnen. |
| Symcon-Wochenplan → Worx-App/Mäher | Vom Nutzer bestätigt |
| Worx-App → Symcon-Wochenplan ohne manuellen Refresh | Vom Nutzer bestätigt |
| „Ganzer Tag“ und Zeitfenster über Mitternacht | Vorher-/Nachher-Datensatz beim Umschalten von „Ganzer Tag“ identisch; Wire-Abbildung bleibt offen. Mitternachtsfenster ebenfalls offen. |
| Automatischer Zeitplan, Regenverzögerung, tägliche Arbeitszeitänderung und Sperre | Schreibwege implementiert; Arbeitszeitfeld ist eine unveränderte signierte Abbildung; Docker bestätigt nach Build 5 (Commit `0f181ee`), dass alle 34 Variablen keine Legacy- oder benutzerdefinierten Profile verwenden und der Slider `%` anzeigt. Negativer Schreib-Echo und Geräteechos der übrigen Einstellungen offen. Firmware-Auto-Update wird capability-geprüft als getrennte bestätigte Variable und Symcon-Schalter angezeigt; der Schalter ändert nur die Cloud-Präferenz, nicht die Firmware selbst. Cloud-Echo-Test in Docker und am Gerät offen. |
| Start, Pause und Heimfahrt | Implementiert; am echten Mäher noch nicht bestätigt |
| Aktueller Modulstand der Docker-Mower-Instanz | Library-API bestätigt Version 2.0, Build 7. Alle 34 Mower-Variablen haben keine Standard- oder benutzerdefinierten Profile; Wochenplanereignis vorhanden, keine Entwurfsvariablen. Regenverzögerungs-Slider: 0–720 Minuten in 30-Minuten-Schritten; bestätigter Wert 180 Minuten. |
| Aktueller Code-Stand im Testbranch | Build 7 enthält die Regenverzögerung 0 oder 30-Minuten-Schritte bis 720 Minuten auch auf der Cloud-Transportschicht. Docker ist auf Build 7 aktualisiert; der Schreib-Echo bleibt offen. |
| Regenverzögerung 330 Minuten → Worx-App und bestätigte Variable | Build 7 ist installiert; Darstellung und aktueller Wert 180 Minuten sind live bestätigt. Der 330-Minuten-Schreibrundlauf über der früheren Cloud-Grenze von 300 ist noch auszuführen. Danach den Ausgangswert wiederherstellen. |
| Neuinstallation, Update, wiederholte Konfiguration und Wiederanlauf | Noch vollständig abzunehmen |
| Fehlerfälle (falsche Zugangsdaten, Cloud-/MQTT-Ausfall, leere Antwort) | Noch vollständig abzunehmen |
| Mindestversion IP-Symcon 9.0 | Manifest/README verlangen 9.0; Docker-Kernel 9.0 per read-only RPC bestätigt; PHP-8.5-Syntax- und Repository-Prüfungen in CI erfolgreich |

Gerätesteuerungen nur einzeln und mit sichtbarer Sollwert-/Rückmeldungsprüfung ausprobieren. Ein MQTT-Publish oder HTTP-Erfolg allein gilt nicht als Gerätebestätigung. Keine Aktionen im Rahmen statischer Codeprüfungen ausführen.

## Prüfschritt: tägliche Arbeitszeit −100…+100 %

Nach Aktualisierung des Testbranches und erneutem Anwenden der Mower-Konfiguration:

1. Nach Modulupdate und erneutem Anwenden der Mower-Konfiguration in der Worx-App −100 %, −50 %, 0 % und +30 % einstellen. „Tägliche Arbeitszeit (bestätigt)“ muss jeweils denselben Wert wie App und redigiertes `cfg.sc.p` zeigen.
2. Für den Schreibrundlauf in Symcon „Tägliche Arbeitszeit setzen“ nacheinander auf −40 % und +60 % setzen. Die Worx-App und „Tägliche Arbeitszeit (bestätigt)“ müssen jeweils denselben Wert zeigen; der redigierte `cfg.sc.p`-Wert muss dem Sollwert entsprechen; Status und Mäher-Echo abwarten. Anschließend den notierten Ausgangswert wiederherstellen.
3. Vor dem Test den aktuellen App-Wert notieren. Nach dem Echo den gewünschten Ausgangswert gezielt wiederherstellen, falls er geändert wurde.

Die Werte müssen jeweils aus demselben Rückmeldezeitpunkt stammen. Eine bloße Publish-Meldung zählt nicht als Gerätebestätigung.

## Prüfschritt: Regenverzögerung bis über 300 Minuten

Bei installierter Build-7-Version:

1. Den aktuellen Wert aus der Worx-App und „Regenverzögerung (bestätigt)“ notieren.
2. In Symcon „Regenverzögerung setzen“ auf 330 Minuten stellen. Das prüft gezielt die frühere 300-Minuten-Grenze im Cloud-Transport.
3. Erst dann als bestätigt werten, wenn „Einstellungsrückmeldung“, „Regenverzögerung (bestätigt)“, der redigierte `cfg.rd`-Wert und die Worx-App 330 Minuten melden.
4. Den notierten Ausgangswert wiederherstellen und auch dessen Echo prüfen.

Den Test in einem Zeitfenster durchführen, in dem eine Regenverzögerung den geplanten Mähbetrieb nicht stört.

## Veröffentlichung

`main` erst aktualisieren, wenn die offenen Punkte der [Feature-Matrix](FEATURE_MATRIX.md) und obigen Abnahme geschlossen oder nachvollziehbar als nicht unterstützte Modellfunktion gekennzeichnet sind, GitHub Style- und Testprüfungen erfolgreich sind und README, Modulformulare und Updatepfad den tatsächlich bestätigten Stand beschreiben.
