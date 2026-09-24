# Worx-Modul: Schnittstellen und Updatepfad

## Bestandteile

| Bestandteil | Verantwortung | Projektidentität |
|---|---|---|
| WorxCloud | Worx-Login, Token, REST-Inventar, MQTT-Transport, Statusverteilung und Gerätebefehle | {2A3889B6-AD03-4B1E-8782-BEB0E6CABCC1}, Prefix WORX |
| WorxConfigurator | Worx-Mäher im Konto anzeigen und Mower-Instanzen anlegen | {A8373ED9-7396-421E-A78E-4904A6AC4657}, Prefix WORXCONF |
| WorxMower | Status, Bedienaktionen, bestätigte Rückmeldungen und Zeitplanentwurf | {39CA7807-D252-4375-8D05-1C5F918552C0}, Prefix WORXMOWER |
| WorxScheduleCodec | Protokoll-0-Zeitplan zwischen Worx-Slots und Symcon-Wochenplan abbilden; unbekannte Felder erhalten | PHP-Hilfsklasse in libs/, kein Symcon-Modul |

Library-, Modul- und Worx-interne DataFlow-GUIDs sind für diesen Fork eigenständig neu vergeben. Die Catomic-Präfixe und vorhandenen Variablen-Idents bleiben stabil. Die MQTT-Sende- und Empfangs-DataIDs sind Symcon-Standardschnittstellen und müssen für die MQTT-Client-Kompatibilität unverändert bleiben.

Wegen der neuen Modul-GUIDs ist der Fork kein Update der Catomic-Instanzen. Bestehende Catomic-Instanzen werden nicht automatisch aktualisiert. Da beide Versionen das Funktionspräfix WORX verwenden, zuerst in einem separaten Symcon-Testsystem testen und erst nach geklärter Präfix-Kollision im selben Kernel installieren.

## Datenfluss

Worx REST / AWS IoT MQTT → WorxCloud → WorxMower

WorxCloud übernimmt Anmeldung, Token, Inventar, Statusverteilung und MQTT. WorxMower zeigt bestätigte Statuswerte, nimmt Nutzeraktionen entgegen und verwaltet einen lokalen Planentwurf. Der Scheduler ist Teil der bestehenden Mower-Instanz; es gibt keinen separaten Scheduler-Splitter.

## Implementierter Zeitplan-Editor und geplante Schreibschnittstelle

1. WorxCloud verteilt den empfangenen Gerätestatus einschließlich `cfg.sc` an WorxMower.
2. WorxMower legt idempotent ein natives IP-Symcon-Wochenplanereignis (Ereignistyp 2) als Kind der Mower-Instanz an und ordnet sieben Tagesgruppen zu. Das Ereignis ist deaktiviert, damit Zeitpunkte keine Automationen auslösen.
3. Die bestätigten Mäherdaten sind die Ausgangsbasis. Start und Ende eines Einsatzes werden als Zustandswechsel im Ereignis abgebildet; Kantenschnitt wird als eigene Wochenplanaktion erhalten. Unbekannte oder nicht darstellbare Felder dürfen nicht stillschweigend verworfen werden.
4. Die Ereignisaktion darf keine Mähbefehle senden. Der Wochenplan ist direkt im Symcon-Ereignis bearbeitbar; „Entwurf aus Wochenplan übernehmen“ importiert ihn als Vorschau. Das Übertragen zum Mäher bleibt ein eigener, bewusster Schritt.
5. Der Import liest den aktuellen Ereignisplan erneut aus und prüft, ob er sich verlustfrei in das bestätigte Geräteschema überführen lässt. Beim späteren ausdrücklichen Übertragen muss WorxMower ihn zusätzlich gegen die bestätigten Gerätegrenzen validieren. `ApplyChanges()` und das bloße Editieren des Ereignisses senden nichts.
6. WorxCloud prüft vor dem Versand Cloud-Verbindung, Gerätemetadaten und unterstütztes Planformat.
7. Der Plan gilt erst nach Rücklesen des passenden `cfg.sc` als bestätigt. Ein Publish-Erfolg ist keine Gerätebestätigung.
8. Bei Timeout bleibt der letzte bestätigte Plan sichtbar; der bearbeitete Ereignisplan und der Übertragungsstatus zeigen den offenen Fehler oder Konflikt.

IP-Symcon-Wochenpläne speichern Aktionswechsel zu Zeitpunkten, nicht ein separates Dauerfeld. Dauer wird daher über Start- und Rückwechselzeit codiert. Die WR105SI.1-Zuordnung von „Ganzer Tag“, Randfällen an Mitternacht und sämtlichen gültigen Gerätewerten muss vor Freigabe des Schreibwegs geprüft werden. Zeiterweiterung bleibt eine eigenständige Planoption und wird nicht in das Zeitplanereignis gezwungen.

Der derzeit bekannte Worx-Kandidat ist Protokoll 0 mit `sc.d` (sieben Tage). `sc.dd` wird nur ausgewertet, wenn der Mäher es selbst zurückmeldet. Die App-Screenshots belegen die Oberfläche, nicht die vollständige Codierung von „Ganzer Tag“ oder die bestätigende Schreibantwort.

## Update vorhandener Installationen

- Die drei Modul-GUIDs, Library-GUID, Funktionspräfixe, DataFlow-GUIDs und bestehenden Variablen-Idents werden nicht geändert.
- Bestehende Catomic-Instanzen erhalten neue Eigenschaften mit sicheren Standardwerten und neue Variablen zusätzlich zu den alten Variablen.
- Der bisherige Cloud-Wert bleibt lesbar. Nicht-Worx-Werte werden mit einem verständlichen Status abgewiesen; dadurch werden Altinstanzen nicht still an einen anderen Anbieter umgeleitet.
- Bei Wechsel des Module-Control-Repositorys auf die neue URL kann Symcon bestehende Instanzen anhand ihrer GUIDs aktualisieren.
- ApplyChanges() darf weiter die Geräteinformationen lesend aktualisieren, aber keine Mäh- oder Zeitplanbefehle senden.

## Noch benötigte Laufzeitbelege

Vor einer echten Schreibabnahme sind ein redigierter product-items-Datensatz und mindestens eine redigierte Statusantwort des WR105SI.1 nötig. Besonders zu bestätigen sind Protokollnummer, sieben d-Tagesfelder, Zuordnung der dritten Slotkomponente, ganztägiger Eintrag, Grenzen der Dauer/Zeiterweiterung und bestätigende MQTT-Rückmeldung. Die App-Screenshots enthalten keine dieser Transportdetails.
