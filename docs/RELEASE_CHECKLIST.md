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
| Manueller Kantenschnitt (cmd 4) | Regressionstest bestätigt, dass nur Mäherstatus 32 den Befehl als bestätigt abschließt; Status 1, 5, 6, 7 und 34 lassen die Rückmeldung offen. Live-Echo-Test am WR105SI.1 steht aus. |
| Einzelnen Wochentag im Symcon-Wochenplan deaktivieren und wiederherstellen | Am 2026-10-01 Montag kurz auf „Kein Mähfenster“ gesetzt: `cfg.sc.d[1]` bestätigte `00:00`, Dauer 0, Kantenschnitt 0. Danach exakt `11:00`, 135 Minuten, ohne Kantenschnitt wiederhergestellt; gesamter `cfg.sc`-Plan unverändert, Status „Vom Mäher zurückgelesen und bestätigt“, Event bleibt deaktiviert mit sieben Tagesgruppen. |
| Kantenschnitt im nativen Wochenplan umschalten und wiederherstellen | Am 2026-10-01 Montag-Flag auf Kantenschnitt gestellt: `cfg.sc.d[1][2]` bestätigte 1; danach auf den Ausgangswert 0 zurückgesetzt. Der vollständige `cfg.sc`-Plan ist exakt wiederhergestellt und der Mäher bestätigt den Rücklese-Echo. Kein manueller Kantenschnitt- oder Mähbefehl ausgelöst. |
| „Ganzer Tag“ und Zeitfenster über Mitternacht | Vorher-/Nachher-Datensatz beim Umschalten von „Ganzer Tag“ identisch; Wire-Abbildung bleibt offen. Mitternachtsfenster ebenfalls offen. |
| Worx-App → Einstellungs-Eingaben in Symcon | Build 12: Soll- und bestätigte Werte werden synchronisiert. Die Geräte-Rundläufe für Regenverzögerung, Arbeitszeit und Sperre wurden am 2026-10-01 über die Docker-Testinstanz bestätigt. Ein unabhängiger neuer App→Symcon-Wechsel dieser Eingabefelder bleibt noch zu prüfen. |
| Automatischer Zeitplan, Regenverzögerung, tägliche Arbeitszeitänderung und Sperre | Am 2026-10-01 bestätigt: Regenverzögerung 30 → 330 → 30 Minuten mit cfg.rd-Echo; Arbeitszeit 0 → −40 → +60 → 0 % mit cfg.sc.p-Echo und unverändertem Wochenplan; Sperre false → true → false mit dat.lk 0/1/0. Alle Ausgangswerte wiederhergestellt. Automatischer Zeitplan und Firmware-Auto-Update-Präferenz wurden ebenfalls false → true → false aus der Cloud zurückgelesen und bestätigt; Wochenplan bzw. Firmwarestand blieben unverändert. |
| Start, Pause und Heimfahrt | Am 2026-10-01 unter Build 13 erneut live geprüft: Start (Status 2), Pause (Status 34) und Heimfahrt-Annahme (Status 5) bestätigt. Der Mäher suchte anschließend die Begrenzung und kehrte in die Ladestation zurück; Ankunft vom Nutzer bestätigt. Status 6/7 allein bleiben laut Regressionstest keine Befehlsannahme. |
| Aktueller Modulstand der Docker-Mower-Instanz | Unter Build 18 am 2026-10-01 per read-only RPC geprüft: Kernel 9.0, Cloud und Mower Status 102, 35 Kindobjekte erhalten, Online=true, Status/Text 1 / In der Ladestation, Fehler/Text 0 / Kein Fehler, ursprüngliches cfg.sc mit 17:00–19:00 samt Kantenschnitt-Flags; Wochenplanereignis Typ 2 deaktiviert mit demselben Plan. Danach auf c78a789 (Build 20) aktualisiert und Testcontainer neu gestartet; Konsole antwortet mit HTTP 200. Read-only RPC unter Build 20 bestätigt Cloud/Mower Status 102, Online=true, State/Text 1 / In der Ladestation, Error/Text 0 / Kein Fehler, cfg.sc mit täglich 17:00–19:00 und korrekte Kantenschnitt-Flags sowie das deaktivierte Wochenplanereignis mit denselben Tagespunkten. Nach „Jetzt aktualisieren“ ist LastUpdate ungleich 0; ScheduleSyncStatus bestätigt den App-Abgleich. Keine Fahraktion ausgelöst. |
| Rückstellung des Zeitplans auf den Nutzer-Ausgangszustand | Am 2026-10-01 nach Nutzeranweisung das native Ereignis auf den ursprünglich gelieferten Wochenplan zurückgestellt. Alle 14 Start-/Endpunkte wurden erfolgreich gesetzt; die ursprünglichen Kantenschnitt-Tage blieben erhalten. Der Mäher las den vollständigen ursprünglichen `cfg.sc`-Inhalt zurück; `ScheduleSyncStatus` meldet Bestätigung. Ereignis blieb deaktiviert, Status 1 „In der Ladestation“, kein Gerätestart ausgelöst. |
| Aktueller Code-Stand im Testbranch | Build 20 ergänzt den Empfangszeit-Fallback und den passenden Regressionstest; Build 19 lokalisiert dynamische Formularhinweise und prüft Status-/Formularbeschriftungen. GitHub Actions: alle sechs PHP-Syntax-, Style- und Testjobs erfolgreich. Commit c78a789 ist im Docker-Testcheckout; nach Neustart und „Jetzt aktualisieren“ bestätigen read-only RPC den gesetzten Zeitstempel, korrekte Status-/Textvariablen und den übereinstimmenden Wochenplan. |
| Regenverzögerung 330 Minuten → Worx-App und bestätigte Variable | Geräte-Rundlauf am 2026-10-01 bestätigt: Eingabe 330 Minuten, bestätigte Variable und cfg.rd melden 330; anschließend 30 Minuten wiederhergestellt und bestätigt. Eine zusätzliche Sichtprüfung der Worx-App wurde dabei nicht durchgeführt. |
| Neuinstallation, Update, wiederholte Konfiguration und Wiederanlauf | Build 11/12: leere Cloud ohne Zugangsdaten bleibt Status 104 und erzeugt keinen MQTT-Parent. Zwei neue Mower ohne Serial haben jeweils 34 unabhängige Variablen ohne Profile; zweimaliges ApplyChanges erzeugt keine Duplikate. Leerer Configurator liefert gültiges Formular-JSON. Alle eigens angelegten Testobjekte wurden entfernt. Containerneustart unter Build 12: Cloud und Mower wieder aktiv, IDs und Zeitplanpunkte erhalten; Aktionsscripts enthalten ausschließlich einen Kommentar. Vollständige Neuinstallation mit erneuter Kontoeinrichtung und Deinstallation des Repository bleiben offen. |
| Fehlerfälle (falsche Zugangsdaten, Cloud-/MQTT-Ausfall, leere Antwort) | Ungültige Zugangsdaten, fehlende Kontodaten und nicht unterstützte Cloud wurden zuvor in einer separaten Testinstanz geprüft. Build 15 fügt Unit-Tests für Transportfehler, HTTP 503/401, leere Antworten, ungültiges JSON und erfolgreiche/204-Antworten hinzu; CI ist grün. Live-Ausfalltests, vollständige Token-Erneuerung und API-Limits bleiben offen. |
| Mindestversion IP-Symcon 9.0 | Manifest/README verlangen 9.0; Docker-Kernel 9.0 per read-only RPC bestätigt; PHP-8.5-Syntax- und Repository-Prüfungen in CI erfolgreich |

Live-Zeitplantests verändern die Cloud-Konfiguration und können den nächsten Mähstart verschieben. Vor jeder Änderung den vollständigen redigierten `cfg.sc`-Plan und den Status des nativen Wochenplanereignisses sichern. Danach genau diese Ausgangswerte zurückspielen und das vollständige Mäher-Echo abwarten; keinen früher protokollierten Zustand als aktuelle Ausgangsbasis annehmen. Bleibt das Echo aus oder weicht es ab, keine weiteren Live-Änderungen ausführen.

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
