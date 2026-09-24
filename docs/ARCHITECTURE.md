# Worx-Modul: Schnittstellen und Updatepfad

## Bestandteile

| Bestandteil | Verantwortung | Projektidentität |
|---|---|---|
| WorxCloud | Worx-Login, Token, REST-Inventar, MQTT-Transport, Statusverteilung und Gerätebefehle | {2A3889B6-AD03-4B1E-8782-BEB0E6CABCC1}, Prefix WORX |
| WorxConfigurator | Worx-Mäher im Konto anzeigen und Mower-Instanzen anlegen | {A8373ED9-7396-421E-A78E-4904A6AC4657}, Prefix WORXCONF |
| WorxMower | Status, Bedienaktionen, bestätigte Rückmeldungen und den nativen Wochenplan | {39CA7807-D252-4375-8D05-1C5F918552C0}, Prefix WORXMOWER |
| WorxScheduleCodec | Protokoll-0-Zeitplan zwischen Worx-Slots und Symcon-Wochenplan abbilden; unbekannte Felder erhalten | PHP-Hilfsklasse in libs/, kein Symcon-Modul |

Library-, Modul- und modulinterne DataFlow-GUIDs sind für diesen Fork eigenständig neu vergeben und wurden gegen den Catomic-Quellcommit `1ec15909b3d1f5b10ac566dd84e149ea2256d831` verglichen. Die Catomic-Library-/Modul-GUIDs sowie die internen Schnittstellen `{01E8C2C0-BB9C-4615-AD25-A78E12D842F0}` und `{5279818E-0317-46AB-A451-0683BEBC6E90}` werden nicht verwendet. Geteilt bleiben nur die Symcon-MQTT-Standardschnittstellen `{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}` (Senden) und `{7F7632D9-FA40-4F38-8DEA-C83CD4325A32}` (Empfangen), die mit dem MQTT-Client kompatibel bleiben müssen. Catomic-Funktionspräfixe und vorhandene Variablen-Idents bleiben innerhalb dieses Forks stabil.

Wegen der neuen Modul-GUIDs ist der Fork kein Update der Catomic-Instanzen. Bestehende Catomic-Instanzen werden nicht automatisch aktualisiert. Da beide Versionen das Funktionspräfix WORX verwenden, zuerst in einem separaten Symcon-Testsystem testen und erst nach geklärter Präfix-Kollision im selben Kernel installieren.

## Datenfluss

Worx REST / AWS IoT MQTT → WorxCloud → WorxMower

WorxCloud übernimmt Anmeldung, Token, Inventar, Statusverteilung und MQTT. WorxMower zeigt bestätigte Statuswerte, nimmt Nutzeraktionen entgegen und enthält genau einen bearbeitbaren Zeitplan: das native Symcon-Wochenplanereignis „Mähzeitplan“. Es gibt keinen Entwurf und keinen separaten Scheduler-Splitter. Die lokale Variable „Gerätenachweis (redigiert)“ enthält ausschließlich eine Feld-Whitelist für Capability-/Feature-Metadaten, `cfg.sc`, `cfg.rd`, `cfg.mz`/`mzv`/`mzk` sowie ausgewählte Statusfelder für Sperre, Drehmoment, Zone und Regen. Gerätekennungen, Standort und Authentifizierung werden nicht übernommen.

## Direkte Synchronisierung des nativen Wochenplanereignisses

1. WorxCloud verteilt den empfangenen Gerätestatus einschließlich `cfg.sc` an WorxMower.
2. WorxMower legt idempotent ein natives IP-Symcon-Wochenplanereignis (Ereignistyp 2) als Kind der Mower-Instanz an und ordnet sieben Tagesgruppen zu. Vor jedem Cloud-Update werden vorhandene Gruppen einschließlich vom Nutzer neu gruppierter Gruppen vollständig entfernt. Das Ereignis bleibt deaktiviert, damit Zeitpunkte keine Automationen auslösen.
3. Die bestätigten Mäherdaten sind die Ausgangsbasis. Start und Ende eines Einsatzes werden als Zustandswechsel im Ereignis abgebildet; Kantenschnitt wird als eigene Wochenplanaktion erhalten. Unbekannte oder nicht darstellbare Felder dürfen nicht stillschweigend verworfen werden.
4. Änderungen werden über `EM_UPDATE` und die Wochenplan-Nachrichten für Gruppen und Schaltpunkte (`EM_ADDSCHEDULEGROUP` sowie `EM_ADDSCHEDULEGROUPPOINT`, `EM_REMOVESCHEDULEGROUPPOINT` und `EM_CHANGESCHEDULEGROUPPOINT`) erkannt. Da Symcon beim Bearbeiten mehrere Nachrichten ausgibt, bündelt ein 750-ms-Timer die Änderungen und liest anschließend den vollständigen Endstand. Die Ereignisaktionen bleiben No-op und starten keinen Mähvorgang.
5. WorxMower liest das Ereignis erneut, validiert Zeit und Dauer und sendet eine `sc`-Änderung auf das gerätespezifische MQTT-`commandIn`. `ApplyChanges()` sendet keinen Zeitplan.
6. WorxCloud prüft Seriennummer, Protokoll 0, Topic und sieben gültige Tagesfelder vor dem Versand.
7. Der Plan gilt erst nach Rücklesen des passenden `cfg.sc` als bestätigt. Ein MQTT-Publish allein ist keine Gerätebestätigung. Abweichende oder verspätete Statusmeldungen überschreiben eine noch offene Übertragung nicht. Ein Timeout stößt einmalig eine lesende Auswertung des zuletzt empfangenen Worx-Stands an.
8. Bei Timeout bleibt die manuell eingestellte Ereigniszeit sichtbar; der Status zeigt die fehlende Bestätigung. Derselbe fehlgeschlagene Plan wird nicht bei jeder Statusmeldung erneut gesendet.

IP-Symcon-Wochenpläne speichern Aktionswechsel zu Zeitpunkten, nicht ein separates Dauerfeld. Dauer wird daher über Start- und Rückwechselzeit codiert. Die WR105SI.1-Zuordnung von „Ganzer Tag“ und Randfällen an Mitternacht ist noch nicht durch einen Live-Schreibtest belegt. Start um 00:00 ist darstellbar. Zeitfenster, die über Mitternacht reichen oder genau um Mitternacht enden, werden abgewiesen, bis eine verlustfreie Ereignisdarstellung dafür feststeht. Die Einstellung „Erhöhen/Verringern der täglichen Arbeitszeit“ bleibt eine eigenständige `cfg.sc.p`-Planoption und wird nicht in das Zeitplanereignis gezwungen. Die App-Skala −100 bis +100 % wird auf den Rohwert 0 bis 100 mit `raw=(App+100)/2` abgebildet; zurück gilt `App=2×raw−100`.

Der derzeit bekannte Worx-Kandidat ist Protokoll 0 mit `sc.d` (sieben Tage). `sc.dd` wird nur ausgewertet, wenn der Mäher es selbst zurückmeldet. Die App-Screenshots belegen die Oberfläche, nicht die vollständige Codierung von „Ganzer Tag“ oder die bestätigende Schreibantwort.

## Update vorhandener Installationen

- Die drei Modul-GUIDs, Library-GUID, Funktionspräfixe, DataFlow-GUIDs und bestehenden Variablen-Idents werden nicht geändert.
- Bestehende Instanzen dieses Worx-Forks erhalten sichere Standardwerte für neue Eigenschaften. Die früheren fork-eigenen Zusatzvariablen `Schedule` und `SchedulePreview` werden in Fork-Installationen ausgeblendet statt gelöscht; der native Wochenplan ist die einzige Zeitplanoberfläche.
- Der bisherige Cloud-Wert bleibt lesbar. Nicht-Worx-Werte werden mit einem verständlichen Status abgewiesen; dadurch werden Altinstanzen nicht still an einen anderen Anbieter umgeleitet.
- Nur Instanzen, die bereits mit den GUIDs dieses Worx-Forks angelegt wurden, erhalten Updates über Module Control. Catomic-Instanzen haben absichtlich andere GUIDs und werden nicht in-place aktualisiert; eine Migration ist manuell und nicht Teil dieses Moduls.
- ApplyChanges() darf Geräteinformationen lesend aktualisieren, aber keine Mäh- oder Zeitplanbefehle senden.

## Noch benötigte Laufzeitbelege

Der Nutzer hat Modell WR105SI.1, Firmware 3.52.0+1 und einen redigierten Protokoll-0-Statusdatensatz mit sieben `sc.d`-Tagesfeldern bereitgestellt. Der lesende Cloud-Pfad ist in der Docker-Installation bestätigt. Dort ist ein deaktiviertes Wochenplanereignis mit sieben Tagesgruppen und je drei Schaltpunkten vorhanden; der Status meldet eine aus der Worx-App übernommene Änderung. Der Nutzer hat auf dem aktuellen Docker-Modulstand beide Richtungen des Zeitplan-Rundlaufs bestätigt: Ereignisänderungen werden in der Worx-App angezeigt und angenommen; App-Änderungen erscheinen automatisch im Symcon-Ereignis, ohne manuellen Refresh. „Ganzer Tag“ und ganztägige Mitternachtsgrenzen benötigen zusätzlich einen eigenen Datenbeleg.
