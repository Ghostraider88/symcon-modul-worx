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
| Worx-App → Einstellungs-Eingaben in Symcon | Build 12: Soll- und bestätigte Werte werden synchronisiert. Die Geräte-Rundläufe für Regenverzögerung, Arbeitszeit und Sperre wurden am 2026-10-01 über die Docker-Testinstanz bestätigt. Ein unabhängiger neuer App→Symcon-Wechsel dieser Eingabefelder bleibt noch zu prüfen. |
| Automatischer Zeitplan, Regenverzögerung, tägliche Arbeitszeitänderung und Sperre | Am 2026-10-01 bestätigt: Regenverzögerung 30 → 330 → 30 Minuten mit cfg.rd-Echo; Arbeitszeit 0 → −40 → +60 → 0 % mit cfg.sc.p-Echo und unverändertem Wochenplan; Sperre false → true → false mit dat.lk 0/1/0. Alle Ausgangswerte wiederhergestellt. Automatischer Zeitplan und Firmware-Auto-Update-Präferenz wurden ebenfalls false → true → false aus der Cloud zurückgelesen und bestätigt; Wochenplan bzw. Firmwarestand blieben unverändert. |
| Start, Pause und Heimfahrt | Am 2026-10-01 Start mit Status 2 und Pause mit Status 34 am Mäher bestätigt. Nach Heimfahrt folgten Status 5 und 6; Build 12 bestätigte diese nicht und meldete Timeout. Build 13 (9cadf5e) akzeptiert den eindeutigen Heimfahrtstatus 5, während allgemeine Begrenzungssuche 6 weiterhin nicht als Annahme zählt. CI erfolgreich; Rückkehr vom Nutzer beobachtet und anschließend über REST als Status 1 / Fehler 0 bestätigt. Build 13 danach installiert; IDs und Zeitplanpunkte erhalten. Der zusätzliche Status-5-Bestätigungspfad ist durch Unit-Test abgesichert, noch nicht bei einer erneuten Bewegungssequenz live geprüft. |
| Aktueller Modulstand der Docker-Mower-Instanz | Am 2026-10-01 geprüft: Library 2.0, Build 13, Kernel 9.0; Cloud und Mower aktiv (102), auch nach Containerneustart. Update erhält alle 35 Kindobjekt-IDs; 34 Variablen ohne Standard- oder benutzerdefinierte Profile. Genau ein deaktivierter Wochenplan mit sieben Tagesgruppen. |
| Aktueller Code-Stand im Testbranch | Build 13 (9cadf5e) bestätigt auch Heimfahrtstatus 5; ein Regressionstest schließt unspezifische Zustände 6 und 7 aus. Build 12 ergänzt dat.lk im redigierten Beleg; Build 11 korrigiert Warnungen bei fehlenden Altvariablen. Tests, Style und PHP-8.5-Syntax für Build 13 sind am 2026-10-01 erfolgreich. |
| Regenverzögerung 330 Minuten → Worx-App und bestätigte Variable | Geräte-Rundlauf am 2026-10-01 bestätigt: Eingabe 330 Minuten, bestätigte Variable und cfg.rd melden 330; anschließend 30 Minuten wiederhergestellt und bestätigt. Eine zusätzliche Sichtprüfung der Worx-App wurde dabei nicht durchgeführt. |
| Neuinstallation, Update, wiederholte Konfiguration und Wiederanlauf | Build 11/12: leere Cloud ohne Zugangsdaten bleibt Status 104 und erzeugt keinen MQTT-Parent. Zwei neue Mower ohne Serial haben jeweils 34 unabhängige Variablen ohne Profile; zweimaliges ApplyChanges erzeugt keine Duplikate. Leerer Configurator liefert gültiges Formular-JSON. Alle eigens angelegten Testobjekte wurden entfernt. Containerneustart unter Build 12: Cloud und Mower wieder aktiv, IDs und Zeitplanpunkte erhalten; Aktionsscripts enthalten ausschließlich einen Kommentar. Vollständige Neuinstallation mit erneuter Kontoeinrichtung und Deinstallation des Repository bleiben offen. |
| Fehlerfälle (falsche Zugangsdaten, Cloud-/MQTT-Ausfall, leere Antwort) | Separate leere Cloud geprüft: absichtlich ungültiger Testlogin meldet Status 201, fehlende Kontodaten 104, nicht unterstützte Cloud 203; gültiges Formular-JSON, kein MQTT-Transport angelegt, Testinstanz entfernt. Cloud-/MQTT-Ausfall, leere Antwort, Token-Erneuerung und API-Limits bleiben offen. |
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
