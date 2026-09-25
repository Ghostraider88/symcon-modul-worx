# Änderungsprotokoll

## Unveröffentlicht

- Bestehende, vom Mower-Modul verwaltete Variablen entfernen veraltete benutzerdefinierte Profilzuweisungen vor dem erneuten Anwenden der modernen Variablenpräsentationen; benutzerdefinierte Zusatzvariablen bleiben unberührt.

- Library-Build auf 4 angehoben, damit der korrigierte Variablenprofil-Migrationsstand als neues Modulupdate erkennbar ist.

- Mindestversion auf IP-Symcon 9.0 angehoben; Produktionsdateien werden in CI mit PHP 8.5 auf Syntax geprüft.
- Seriennummern in dynamischen Worx-API-Pfaden werden vor der Debug-Protokollierung maskiert.

- Die Zeiterweiterung verwendet für den WR105SI.1 den von der App gemeldeten signierten Wert `cfg.sc.p` direkt; Lesen, Schreiben und Gerätebestätigung bleiben auf der App-Skala von −100 bis +100 %.
- Ein manueller Kantenschnitt-Knopf wird nur angezeigt, wenn der Mäher Protokoll 0 und `follow_border` meldet; Gerätebestätigung bleibt vom Publish-Status getrennt.
- Die Arbeitszeitvariablen verwenden moderne Symcon-Wertdarstellungen mit dem Suffix „ %“. Es werden keine Legacy-, Tilde- oder benutzerdefinierten Variablenprofile registriert; bestehende Instanzen erhalten die Darstellung erneut in ApplyChanges(). Der signierte Worx-Wert bleibt unverändert im Bereich −100 bis +100 %.
- Der gemeldete Firmware-Auto-Update-Status und die separate Cloud-Präferenz erhalten capability-geprüfte Variablen. Das Setzen der Präferenz startet kein Firmware-Update; Cloud-Echo am WR105SI.1 noch nicht validiert.
- Zeitplan- und Arbeitszeitübertragungen registrieren die erwartete Rückmeldung vor dem MQTT-Publish; schnelle Geräteechos werden dadurch dem wartenden Befehl zugeordnet.

- Start/Pause/Heimfahrt, Sperre und Regenverzögerung registrieren die ausstehende Gerätebestätigung vor dem Sendeaufruf, damit schnelle Geräteechos nicht verpasst werden; abgelehnte Sendungen räumen den Wartezustand auf.


- Catomic-Module als Codebasis in das Worx-Zielrepository übernommen; eigenständige Fork-GUIDs erstellt, Worx-Funktionspräfixe und bestehende Variablen-Idents beibehalten.
- Repository auf Worx Landroid begrenzt; Kress-, Landxcape- und Ferrex-Endpunkte entfernt.
- Worx-Feature-Matrix, Schnittstellen, Updatepfad und WR105SI.1-App-Belege dokumentiert.
- Protokoll-0-Zeitplan-Codec validiert Symcon-Ereigniswerte und erhält unbekannte Gerätefelder.
- Nach dem Übernehmen eines Cloud-Zeitplans wird das native Symcon-Wochenplanereignis zurückgelesen und der gespeicherte Inhalt gegen die Mäherdaten geprüft; der Status meldet die Event-Prüfung explizit. Natives Symcon-Wochenplanereignis als einziger Editor. Die manuelle Aktualisierung fragt Worx jetzt frisch per REST ab; Echo-Abgleich prüft die bearbeitbaren Slot-Felder und übernimmt abweichende Cloud-/App-Änderungen in dasselbe Ereignis. Der Nutzer bestätigt nach Update auf `5809cdc`, dass eine Änderung aus der Worx-App ohne manuellen Refresh im nativen Wochenplan-Ereignis erscheint. Symcon→Worx war zuvor ebenfalls durch App-Anzeige und Mäherannahme bestätigt.
- Regenverzögerung wird bei passender Capability getrennt als Sollwert und bestätigter Istwert geführt; MQTT-`rd`-Befehl und Echo-Timeout ergänzt, Nutzertest am WR105SI.1 offen.
- Zeiterweiterung und Sperre erhalten getrennte Soll-/Istvariablen mit Capability-Prüfung und Mäher-Echo. Schreiben noch nicht am WR105SI.1 vom Nutzer bestätigt.
- Der Nutzer hat nach dem Update den vollständigen Wochenplan-Rundlauf bestätigt: Änderungen aus der Worx-App erscheinen ohne manuellen Refresh im Symcon-Ereignis; Symcon-Änderungen werden vom Mäher angenommen.
- Der automatische Zeitplan wird bei tatsächlich gemeldetem Cloud-Boolean getrennt als Soll- und Istwert angeboten; der Sollwert wird über die Worx-Cloud-API gesendet und durch frische Rücklesung bestätigt; Gerätetest offen.
- Englische Lokalisierungen für die drei Modulkonfigurationsformulare ergänzt; Geräte-Capabilities und offene Steuerwege in der Feature-Matrix einzeln dokumentiert.
- PHPUnit-, Style- und JSON-Prüfungen ausgeführt; falsche Wochentag-Indizes in zwei Tests korrigiert und Formatierung an die Symcon-Style-Regeln angepasst.
