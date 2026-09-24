# Worx-Feature-Matrix

Stand: 2026-09-24. Zielgerät: Worx Landroid WR105SI.1.

## Anlagenstand und Belege

- Modell: im vom Nutzer bereitgestellten Worx-App-Screenshot als WR105SI.1 angezeigt.
- Firmware: Der redigierte, vom Nutzer bereitgestellte Gerätedatensatz meldet die installierte Version 3.52.0+1.
- Der konkrete Gerätedatensatz nennt außerdem `follow_border`, `lock`, `mqtt`, `multi_zone_percentage`, `multi_zone`, `ota_upgrade`, `pairing_smartlink`, `rain_delay` und `unrestricted_mowing_time`. Diese Capability-Namen belegen die Geräteausstattung, allein aber weder ein Cloud-Feld noch eine bestätigte Schreibaktion.
- IP-Symcon: Kernel 9.1, read-only abgefragt.
- Docker-Testinstallation: Worx Cloud und Mower sind aktiv; das native Ereignis „Mähzeitplan“ ist Typ 2, deaktiviert und enthält sieben Tagesgruppen. `Schedule` und `SchedulePreview` sind verborgen. Der Nutzer hat bestätigt, dass Änderungen im nativen Symcon-Ereignis in der Worx-App ankommen und der Mäher sie annimmt. Nach dem Update auf die aktuelle Modulversion hat der Nutzer den Zeitplan in der Worx-App geändert; Symcon übernahm die Änderung sofort und ohne manuellen Refresh. Beide Richtungen des Zeitplan-Rundlaufs sind damit in der Docker-Testinstallation bestätigt.
- App-Zeitplan: manueller Wochenplan mit einem Zeitfenster pro Wochentag. Die App bietet je Eintrag „Rasenkanten-Schnitt“, „Ganzer Tag“, Start, Ende und Löschen. „Automatischer Zeitplan“ ist ausgeschaltet; die Zeiterweiterung wird mit 0 % angezeigt und lässt sich über Plus/Minus bedienen. Individuelle Uhrzeiten werden nicht veröffentlicht.
- Transportbeleg: Ein redigierter Gerätedatensatz mit Protokoll 0 und Wochenplan wurde aus der Docker-Testinstallation empfangen; dafür war kein eigener lokaler MQTT-Broker nötig. Der Live-Lesepfad ist damit belegt. `sc.p=0` stimmt mit den 0 % Zeiterweiterung in der App überein. Es wurde kein manuelles Start-, Pause- oder Heimfahrtkommando ausgelöst; der Nutzer hat den separaten Zeitplan-Sendeweg getestet und die Annahme in der App bestätigt.
- Catomic-Basis: Commit 1ec15909b3d1f5b10ac566dd84e149ea2256d831.
- Ziel: [Ghostraider88/symcon-modul-worx](https://github.com/Ghostraider88/symcon-modul-worx).

| Funktion | Catomic-Ausgang | Beleg für WR105SI.1 | Transport / Bedienelement | Stand |
|---|---|---|---|---|
| Anmeldung und Gerätesuche | REST-Token, Inventar | Worx-App ist für das Gerät eingerichtet; Catomic-Transportbestandteil | WorxCloud, bestehende Instanz-GUID | Übernommen; Live-Anmeldung hier nicht ausgeführt |
| Status und Telemetrie | MQTT-Push plus REST-Abfrage; Status, Fehler, Akku, Laufzeit, Regen, Sperre, Zone, Firmware | Der redigierte Gerätebeleg und die gemeldete Datenübertragung bestätigen Live-Empfang | Bestehende WorxMower-Variablen | Live-Lesepfad in der Docker-Testinstallation bestätigt |
| Start, Pause, Heimfahrt | MQTT-Befehle cmd 1/2/3 | Bestehender Catomic-Worx-Pfad | Control bleibt Aktion; State bleibt bestätigter Istzustand; zusätzlicher Befehlsstatus | Übernommen; keine Geräteaktion im Test |
| Manueller Wochenplan | Catomic hatte keinen Scheduler | App und redigierter WR105SI.1-Datensatz belegen Protokoll 0 und `cfg.sc.d` mit sieben Tagesfeldern. In Docker sind Typ 2, deaktivierter Zustand sowie die Übereinstimmung von `cfg.sc.d` und den nativen Ereignispunkten lesend verifiziert. | Das native Symcon-Wochenplanereignis „Mähzeitplan“ ist der einzige Editor. Änderungen werden nach Abschluss der Symcon-Eventkaskade gebündelt und an MQTT `commandIn` gesendet; passendes `cfg.sc`-Echo bestätigt. | Beide Richtungen durch Nutzertest in Docker bestätigt: Symcon→Worx-App/Mäher angenommen; Worx-App→Symcon-Ereignis automatisch ohne manuellen Refresh übernommen |
| Zweiter Einsatz je Tag | Nicht vorhanden | Der redigierte Gerätedatensatz enthält keinen dd-Block; der Nutzer bestätigt ein Zeitfenster pro Tag in der App | Nur bei empfangenem dd-Block / Gerätefähigkeit anbieten | Für WR105SI.1 derzeit nicht vorhanden |
| Tagesaktivierung und Löschen | Nicht vorhanden | App bietet „Löschen“ je Zeitplaneintrag | Aktivieren/deaktivieren des vorhandenen Tagesslots im Editor | UI belegt; Wire-Format offen |
| Kantenschnitt | Nicht vorhanden | App zeigt je Zeitplan-Eintrag „Rasenkanten-Schnitt“; der redigierte Protokoll-0-Datensatz und Nutzerabgleich ordnen Slot-Komponente 3 zu | Aktion im nativen Ereignis wird auf das Worx-Slotfeld 3 abgebildet | Feldzuordnung belegt; Live-Schreibbestätigung ausstehend |
| Ganzer Tag | Nicht vorhanden | App zeigt den Schalter „Ganzer Tag“ | Editorfeld erst nach Datenbeleg für die entsprechende Repräsentation | UI belegt; Wire-Zuordnung offen |
| Automatischer Zeitplan | Nicht vorhanden | Worx-App-Screenshot zeigt den Schalter; Cloud-Gerätedatensatz führt `auto_schedule` als Boolean. Reverse-engineerte API-Referenz beschreibt `PUT /api/v2/product-items/{serial}` mit diesem Feld. | Nur bei tatsächlich gemeldetem Boolean: getrennter Sollwert und Cloud-rückgelesener Istwert. | Lesestatus und API-Schreibweg ergänzt; Docker-Echo-Test offen |
| Zeiterweiterung | Nicht vorhanden | App zeigt 0 %; WR105SI.1-Diagnose enthält Capability `unrestricted_mowing_time` und `cfg.sc.p=0`. Die Referenz beschreibt `p` als vorzeichenbehafteten Prozentwert; der Wertebereich der konkreten App ist nicht vollständig belegt. | Getrennte Eingabe „Zeiterweiterung setzen“ und bestätigter Istwert. Gesendet wird ausschließlich bei Capability und vorhandenem numerischem `p`; Profilbereich -100 bis 100. | Lesen und Schreibweg implementiert; Geräteecho am WR105SI.1 offen |
| Regenverzögerung | Regenstatus vorhanden | Die redigierte Docker-Meldung des WR105SI.1 enthält `capabilities: rain_delay` und `cfg.rd: 30`. Worx bestätigt, dass die Regenverzögerung beim WR105SI.1 einstellbar ist; die aktuelle Home-Assistant-Referenz begrenzt den Eingabebereich auf 0–300 Minuten. Der Maximalwert ist für genau diesen Mäher noch nicht direkt in der App geprüft. | Getrennte Eingabe „Regenverzögerung setzen“ und bestätigte Istvariable in Minuten; Capability- und Wertebereichsprüfung 0–300 Minuten. | Feld live empfangen; Schreibfunktion ergänzt; Geräteecho-Test offen |
| Zonen | Aktuelle Zone vorhanden | Redigierte Docker-Meldung enthält `multi_zone`, `multi_zone_percentage`, `cfg.mz` (vier Nullen), `cfg.mzv` (zehn Nullen) und `dat.lz=0`. Felder und Struktur sind empfangen; Bedeutung der Werte und aktuell konfigurierte Startzonen sind nicht belegt. | Aktuelle Zone bleibt sichtbar; keine Zonenbedienung, bis die konkrete Konfiguration und der Schreibrundlauf belegt sind. | Capability und Felder live; Zonenbelegung/Schreibweg offen |
| Sperre | Sperrstatus vorhanden | WR105SI.1-Datensatz meldet `lock`; der Status `dat.lk` wird live gelesen. | Status bleibt lesbar; Sperr-/Entsperrbefehl ist getrennt mit Geräteecho und Timeout. | Capability und Status belegt; Schreibfunktion ergänzt; Geräteecho-Test offen |
| Firmware / Wartungswerte | Firmware, Laufzeiten, Ladezyklen | Im Cloud-Modell vorhanden; App zeigt Modell-Firmwareseite | Read-only-Variablen | Vorhanden |
| Einmalmähen, Party-Modus, Drehmoment, Fern-Schnitthöhe, ACS, Off Limits | Nicht vorhanden | Für das konkrete Gerät bisher kein belastbarer API-Beleg; Schnitthöhe beim WR105SI.1 mechanisch einstellbar | Keine Bedienelemente ohne Beleg | Nicht belegt |
| Nächster Einsatz / Tagesfortschritt | Nicht vorhanden | Aus bestätigtem Wochenplan und aktuellem Gerätestatus ableitbar | Read-only-Berechnung erst bei aktuellem Zeitplan | Offen |
| OTA-Firmwareupdate | Nicht vorhanden | WR105SI.1-Capability `ota_upgrade` gemeldet; kein konkreter Updateauftrag oder bestätigter Ablauf im Gerätedatensatz | Updatebedienung erst mit modellbezogenem Transport-, Versions- und Rückmeldungsbeleg | Capability vorhanden; Bedienweg offen |
| Smartlink-Pairing | Nicht vorhanden | WR105SI.1-Capability `pairing_smartlink` gemeldet; konkreter Pairingzustand oder Folgefelder fehlen | Kein Pairing-Bedienelement bis Ablauf und Sicherheitsfolgen für dieses Modell geklärt sind | Capability vorhanden; Funktion offen |
| Uneingeschränkte Mähzeit | Nicht vorhanden | Identische Worx-Capability und dasselbe Protokollfeld `cfg.sc.p` wie „Zeiterweiterung“; kein separates Gerätefeld belegt | Wird über „Zeiterweiterung setzen“ abgebildet; kein zweites Bedienelement | Schreibweg implementiert; Geräteecho am WR105SI.1 offen (siehe Zeiterweiterung) |

## Recherchequellen

- Automatischer Zeitplan: top-level `auto_schedule` und PUT-Schreibweg laut reverse-engineerter Referenz [pyWorxCloud, `__init__.py`](https://github.com/MTrab/pyworxcloud/blob/master/pyworxcloud/__init__.py#L1456-L1480). Nur als Protokollrecherche verwendet; es wurde kein Code übernommen.

- Nutzerbelege: die bereitgestellten Worx-App-Screenshots (Modell, Planansicht, Tagesdetails, Zeiterweiterung).
- Regenverzögerung: [Worx-Hilfe zum WR105SI.1](https://wiki.worx.com/en/migrated/how-to-set-rain-delay) belegt die Einstellbarkeit; die [Home-Assistant-Referenz](https://github.com/MTrab/landroid_cloud#entities) dokumentiert 0–300 Minuten. Die zweite Quelle dient ausschließlich der Bereichsrecherche, es wurde kein Quellcode übernommen.
- Hersteller: [WR105SI-Downloadseite](https://www.teknihall.be/en/downloads/worx/wr105si), [Classic-Kantenschnitt](https://wiki.worx.com/en/Landroid-Classic-Installation-Setup-and-Usage/Cut-to-Edge-And-Border-Management).
- Öffentliche Protokollbeobachtung: [ioBroker Landroid-Diskussion](https://forum.iobroker.net/topic/74380/adapter-worx-landroid-v3-x-x/186?page=2). Der dortige Datensatz gehört nicht zur Nutzeranlage. Personen- und Gerätekennungen wurden nicht übernommen.
- Schnittstellenrecherche: [pyWorxCloud](https://github.com/MTrab/pyworxcloud) und [Home Assistant Worx Landroid](https://github.com/MTrab/landroid_cloud). Beide GPL-lizenzierten Projekte werden nur als Recherche verwendet; Quellcode wird nicht übernommen.
- Symcon-Wochenplan: [offizielle Übersicht](https://www.symcon.de/de/service/dokumentation/grundlagen/ereignisse/wochenplan/), [Kachelvisualisierung](https://www.symcon.de/de/service/dokumentation/komponenten/objekt-darstellung/wochenplan/) und [Schaltpunkte](https://www.symcon.de/de/service/dokumentation/befehlsreferenz/ereignisverwaltung/ips-seteventschedulegrouppoint/).

Zugangsdaten, Seriennummern, UUIDs, MAC-Adressen, Standorte und vollständige Cloud-Antworten gehören nicht in das öffentliche Repository.

## Fortschritt nach Ausführungsplan

| Phase | Stand | Beleg / offene Arbeit |
|---|---|---|
| 0 – Mäher und Ausgangsbasis | Erledigt | WR105SI.1, Firmware 3.52.0+1, Symcon-Kernel 9.1, Catomic-Basiscommit, GitHub-Ziel und Protokoll 0 mit Wochenplan sind dokumentiert. Der lesende Cloud-/Statusabruf wurde in der Docker-Testinstallation bestätigt; ein eigener lokaler MQTT-Broker war dafür nicht erforderlich. |
| 1 – Struktur und Datenmodell | Erledigt | Cloud, Mower und Configurator sind getrennt; Scheduler und Codec sind in der Mower-Instanz vorgesehen. Schnittstellen und Updatepfad stehen in `ARCHITECTURE.md`. Fork-GUIDs sind eigenständig; Catomic-Präfixe und bestehende Variablen-Idents bleiben stabil. |
| 2 – Bestehende Funktionen absichern | Teilweise erledigt | Anmeldung, Gerätesuche und lesende Statusverteilung laufen im Test. Start, Pause und Heimfahrt sind implementiert, aber nicht am echten Mäher getestet. Installations-/Updatepfad, Wiederanlauf, Fehlerfälle und Symcon-Template-/Lokalisierungsübernahme sind noch nicht vollständig abgenommen. |
| 3 – Worx-Scheduler | Weitgehend erledigt | Das native Ereignis ist vorhanden (sieben Tagesgruppen, inaktiv). Beide Richtungen sind im Docker-Test durch den Nutzer bestätigt: Symcon→Worx wird in der App angezeigt und vom Mäher angenommen; Worx-App→Ereignis erscheint automatisch ohne manuellen Refresh. Offen bleiben „Ganzer Tag“ und Mitternachtsgrenzen. `ApplyChanges()` löst keinen Zeitplanversand aus. |
| 4 – Weitere Worx-Funktionen | In Arbeit | Automatischer Zeitplan (Cloud-API), Regenverzögerung, Zeiterweiterung und Sperre haben capability-/feldgeprüfte getrennte Eingaben mit bestätigtem Istzustand; Schreib-/Echo-Test am WR105SI.1 steht jeweils aus. Weitere Bedienfelder benötigen konkrete Gerätebelege. |
| Veröffentlichung | Noch offen | README, Installations- und Updatehinweise, Qualitätsprüfungen und Freigabe für das festgelegte GitHub-Repository sind vor einer öffentlichen Veröffentlichung abzuschließen. |

### Nächster Schritt

Nächster Schritt: die Geräteeinstellungen einzeln am WR105SI.1 prüfen: zuerst automatischer Zeitplan, danach Regenverzögerung, Zeiterweiterung und Sperre. „Ganzer Tag“ und Mähfenster über Mitternacht bleiben gesondert zu belegen.
