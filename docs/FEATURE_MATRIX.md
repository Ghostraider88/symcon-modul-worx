# Worx-Feature-Matrix

Stand: 2026-09-24. Zielgerät: Worx Landroid WR105SI.1.

## Anlagenstand und Belege

- Modell: im vom Nutzer bereitgestellten Worx-App-Screenshot als WR105SI.1 angezeigt.
- Firmware: Der redigierte, vom Nutzer bereitgestellte Gerätedatensatz meldet die installierte Version 3.52.0+1.
- Der konkrete Gerätedatensatz nennt außerdem `follow_border`, `lock`, `mqtt`, `multi_zone_percentage`, `multi_zone`, `ota_upgrade`, `pairing_smartlink`, `rain_delay` und `unrestricted_mowing_time`. Diese Capability-Namen belegen die Geräteausstattung, allein aber weder ein Cloud-Feld noch eine bestätigte Schreibaktion.
- IP-Symcon: Kernel 9.1, read-only abgefragt.
- Docker-Testinstallation: Worx Cloud und Mower sind aktiv; nach Branch-Update ist das native Ereignis „Mähzeitplan“ Typ 2, deaktiviert und enthält sieben Tagesgruppen. Die früheren Hilfsvariablen `Schedule` und `SchedulePreview` sind verborgen; sichtbar bleibt das eine Ereignis. Ein Ereignisedit löste den neuen MQTT-Sendeweg aus. Beim folgenden read-only Abgleich meldeten Worx und Ereignis wieder übereinstimmende Daten sowie den Status „Änderung aus der Worx-App übernommen“. Für den Ereignis→Mäher-Schreibtest fehlt noch der eindeutige Echo-Beleg für die geänderte Fassung. Im anschließenden Nutzertest war die Symcon-Änderung in der Worx-App sichtbar, während Symcon auf den Echo wartete; eine spätere App-Änderung wurde beim bisherigen „Jetzt aktualisieren“ nicht übernommen. Ursache: die Schaltfläche las nur den Cloud-Cache, und der Bestätigungsabgleich verlangte unnötig JSON-Gleichheit des gesamten `sc`-Blocks. Beide Pfade wurden korrigiert; Docker-Nachtest steht aus.
- App-Zeitplan: manueller Wochenplan mit einem Zeitfenster pro Wochentag. Die App bietet je Eintrag „Rasenkanten-Schnitt“, „Ganzer Tag“, Start, Ende und Löschen. „Automatischer Zeitplan“ ist ausgeschaltet; die Zeiterweiterung wird mit 0 % angezeigt und lässt sich über Plus/Minus bedienen. Individuelle Uhrzeiten werden nicht veröffentlicht.
- Transportbeleg: Ein redigierter Gerätedatensatz mit Protokoll 0 und Wochenplan wurde aus der Docker-Testinstallation empfangen; dafür war kein eigener lokaler MQTT-Broker nötig. Der Live-Lesepfad ist damit belegt. sc.p=0 stimmt mit den 0 % Zeiterweiterung in der App überein. Es wurde kein manuelles Start-, Pause- oder Heimfahrtkommando ausgelöst; der separate Zeitplan-Sendeweg wurde getestet.
- Catomic-Basis: Commit 1ec15909b3d1f5b10ac566dd84e149ea2256d831.
- Ziel: [Ghostraider88/symcon-modul-worx](https://github.com/Ghostraider88/symcon-modul-worx).

| Funktion | Catomic-Ausgang | Beleg für WR105SI.1 | Transport / Bedienelement | Stand |
|---|---|---|---|---|
| Anmeldung und Gerätesuche | REST-Token, Inventar | Worx-App ist für das Gerät eingerichtet; Catomic-Transportbestandteil | WorxCloud, bestehende Instanz-GUID | Übernommen; Live-Anmeldung hier nicht ausgeführt |
| Status und Telemetrie | MQTT-Push plus REST-Abfrage; Status, Fehler, Akku, Laufzeit, Regen, Sperre, Zone, Firmware | Der redigierte Gerätebeleg und die gemeldete Datenübertragung bestätigen Live-Empfang | Bestehende WorxMower-Variablen | Live-Lesepfad in der Docker-Testinstallation bestätigt |
| Start, Pause, Heimfahrt | MQTT-Befehle cmd 1/2/3 | Bestehender Catomic-Worx-Pfad | Control bleibt Aktion; State bleibt bestätigter Istzustand; zusätzlicher Befehlsstatus | Übernommen; keine Geräteaktion im Test |
| Manueller Wochenplan | Catomic hatte keinen Scheduler | App und redigierter WR105SI.1-Datensatz belegen Protokoll 0 und `cfg.sc.d` mit sieben Tagesfeldern. In Docker sind Typ 2, deaktivierter Zustand und sieben Tagesgruppen mit je drei Punkten lesend verifiziert; der Status zeigt eine aus der Worx-App übernommene Änderung. | Das native Symcon-Wochenplanereignis „Mähzeitplan“ ist der einzige Editor. Änderungen werden nach Abschluss der Symcon-Eventkaskade gebündelt und an MQTT `commandIn` gesendet; passendes `cfg.sc`-Echo bestätigt. | App→Ereignis im aktuellen Branch mit Statusmeldung und übereinstimmendem Stand beobachtet; Ereignis→Mäher-Publish wurde ausgelöst, passendes Echo für die Teständerung noch offen |
| Zweiter Einsatz je Tag | Nicht vorhanden | Der redigierte Gerätedatensatz enthält keinen dd-Block; der Nutzer bestätigt ein Zeitfenster pro Tag in der App | Nur bei empfangenem dd-Block / Gerätefähigkeit anbieten | Für WR105SI.1 derzeit nicht vorhanden |
| Tagesaktivierung und Löschen | Nicht vorhanden | App bietet „Löschen“ je Zeitplaneintrag | Aktivieren/deaktivieren des vorhandenen Tagesslots im Editor | UI belegt; Wire-Format offen |
| Kantenschnitt | Nicht vorhanden | App zeigt je Zeitplan-Eintrag „Rasenkanten-Schnitt“; der redigierte Protokoll-0-Datensatz und Nutzerabgleich ordnen Slot-Komponente 3 zu | Aktion im nativen Ereignis wird auf das Worx-Slotfeld 3 abgebildet | Feldzuordnung belegt; Live-Schreibbestätigung ausstehend |
| Ganzer Tag | Nicht vorhanden | App zeigt den Schalter „Ganzer Tag“ | Editorfeld erst nach Datenbeleg für die entsprechende Repräsentation | UI belegt; Wire-Zuordnung offen |
| Automatischer Zeitplan | Nicht vorhanden | App zeigt die Funktion; im Screenshot ist sie ausgeschaltet | Status nur aus Geräte-/Cloud-Daten; keine voreilige Fernsteuerung | Verfügbarkeit belegt; Transport offen |
| Zeiterweiterung | Nicht vorhanden | App zeigt 0 %; sc.p=0 im redigierten Gerätedatensatz stimmt damit überein | Read-only-Anzeige eines empfangenen Werts 0–100; Einstellung bleibt bis zur Feld- und Grenzbestätigung deaktiviert | Read-only-Feldzuordnung bestätigt; Schreibweg offen |
| Regenverzögerung | Regenstatus vorhanden | WR105SI.1-Datensatz meldet `rain_delay`; im bisher geteilten Ausschnitt fehlt `cfg.rd`. Öffentliche MQTT-Beispiele verwenden `rd` für Minuten, sind Reverse-Engineering-Erfahrungen. | Istwert/Bedienung erst sichtbar, wenn der konkrete Gerätestatus `cfg.rd` enthält; Senden erfordert Rückmeldung. | Capability belegt; Feld in Gerätedaten und Rundlauf offen |
| Zonen | Aktuelle Zone vorhanden | WR105SI.1-Datensatz meldet `multi_zone` und `multi_zone_percentage`; im bisher geteilten Ausschnitt fehlen `cfg.mz` und `cfg.mzv`. | Aktuelle Zone bleibt sichtbar; Zoneneditor erst nach Prüfung des konkreten Cloud-Feldes und bestätigtem Rundlauf. | Capability belegt; konkrete Zonendaten offen |
| Sperre | Sperrstatus vorhanden | WR105SI.1-Datensatz meldet `lock`; der Status `dat.lk` wird live gelesen. | Status bleibt lesbar; Sperr-/Entsperrbefehl noch getrennt und mit Rückmeldung abzusichern. | Capability und Status belegt; Steuerweg offen |
| Firmware / Wartungswerte | Firmware, Laufzeiten, Ladezyklen | Im Cloud-Modell vorhanden; App zeigt Modell-Firmwareseite | Read-only-Variablen | Vorhanden |
| Einmalmähen, Party-Modus, Drehmoment, Fern-Schnitthöhe, ACS, Off Limits | Nicht vorhanden | Für das konkrete Gerät bisher kein belastbarer API-Beleg; Schnitthöhe beim WR105SI.1 mechanisch einstellbar | Keine Bedienelemente ohne Beleg | Nicht belegt |
| Nächster Einsatz / Tagesfortschritt | Nicht vorhanden | Aus bestätigtem Wochenplan und aktuellem Gerätestatus ableitbar | Read-only-Berechnung erst bei aktuellem Zeitplan | Offen |
| OTA-Firmwareupdate | Nicht vorhanden | WR105SI.1-Capability `ota_upgrade` gemeldet; kein konkreter Updateauftrag oder bestätigter Ablauf im Gerätedatensatz | Updatebedienung erst mit modellbezogenem Transport-, Versions- und Rückmeldungsbeleg | Capability vorhanden; Bedienweg offen |
| Smartlink-Pairing | Nicht vorhanden | WR105SI.1-Capability `pairing_smartlink` gemeldet; konkreter Pairingzustand oder Folgefelder fehlen | Kein Pairing-Bedienelement bis Ablauf und Sicherheitsfolgen für dieses Modell geklärt sind | Capability vorhanden; Funktion offen |
| Uneingeschränkte Mähzeit | Nicht vorhanden | WR105SI.1-Capability `unrestricted_mowing_time` und `cfg.sc.p=0` gemeldet; App zeigt Zeiterweiterung 0 % | Wert read-only anzeigen; Änderungssteuerung erst nach Modellgrenzen und Mäher-Echo | Capability/Feldbezug teilweise belegt; Schreiben offen |

## Recherchequellen

- Nutzerbelege: die bereitgestellten Worx-App-Screenshots (Modell, Planansicht, Tagesdetails, Zeiterweiterung).
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
| 3 – Worx-Scheduler | Teilweise erledigt | Der aktuelle Branch ist in Docker geladen. Das native Ereignis ist vorhanden (sieben Tagesgruppen, inaktiv); App→Ereignis und Auslösung des Ereignis→Mäher-Publish sind im Test beobachtet. Der passende Echo-Beleg für die konkrete Symcon-Änderung steht noch aus. Offen bleiben außerdem „Ganzer Tag“/Mitternachtsgrenzen. `ApplyChanges()` löst keinen Zeitplanversand aus. |
| 4 – Weitere Worx-Funktionen | In Arbeit | Die belegten und offenen Funktionen sind in der Matrix erfasst. Weitere Bedienfelder werden erst ergänzt, wenn Daten oder Modell-/Firmwarebelege sie für den WR105SI.1 bestätigen. |
| Veröffentlichung | Noch offen | README, Installations- und Updatehinweise, Qualitätsprüfungen und Freigabe für das festgelegte GitHub-Repository sind vor einer öffentlichen Veröffentlichung abzuschließen. |

### Nächster Schritt

Nächster Schritt: den Zeitplan-Rundlauf in Docker abschließen. Der Branch ist geladen und das Symcon-Ereignis hat bereits einen Zeitplan-Publish ausgelöst. Bitte prüfe bei einer kleinen Änderung, ob „Zeitplanrückmeldung“ auf „Vom Mäher zurückgelesen und bestätigt“ wechselt; danach eine Änderung in der Worx-App vornehmen und kontrollieren, dass dasselbe Ereignis aktualisiert wird. Der aktuelle read-only Stand zeigt übereinstimmende Gerätedaten und Ereigniswerte. „Ganzer Tag“ und Mähfenster über Mitternacht bleiben gesondert zu belegen.
