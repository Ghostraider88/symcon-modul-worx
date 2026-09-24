# Worx-Modul: Schnittstellen und Updatepfad

## Bestandteile

| Bestandteil | Verantwortung | Projektidentität |
|---|---|---|
| WorxCloud | Worx-Login, Token, REST-Inventar, MQTT-Transport, Statusverteilung und Gerätebefehle | {2A3889B6-AD03-4B1E-8782-BEB0E6CABCC1}, Prefix WORX |
| WorxConfigurator | Worx-Mäher im Konto anzeigen und Mower-Instanzen anlegen | {A8373ED9-7396-421E-A78E-4904A6AC4657}, Prefix WORXCONF |
| WorxMower | Status, Bedienaktionen, bestätigte Rückmeldungen und Zeitplanentwurf | {39CA7807-D252-4375-8D05-1C5F918552C0}, Prefix WORXMOWER |
| WorxScheduleCodec | Protokoll-0-Zeitplan in Symcon-Editorzeilen abbilden; unbekannte Felder erhalten | PHP-Hilfsklasse in libs/, kein Symcon-Modul |

Library-, Modul- und Worx-interne DataFlow-GUIDs sind für diesen Fork eigenständig neu vergeben. Die Catomic-Präfixe und vorhandenen Variablen-Idents bleiben stabil. Die MQTT-Sende- und Empfangs-DataIDs sind Symcon-Standardschnittstellen und müssen für die MQTT-Client-Kompatibilität unverändert bleiben.

Wegen der neuen Modul-GUIDs ist der Fork kein Update der Catomic-Instanzen. Bestehende Catomic-Instanzen werden nicht automatisch aktualisiert. Da beide Versionen das Funktionspräfix WORX verwenden, zuerst in einem separaten Symcon-Testsystem testen und erst nach geklärter Präfix-Kollision im selben Kernel installieren.

## Datenfluss

Worx REST / AWS IoT MQTT → WorxCloud → WorxMower

WorxCloud übernimmt Anmeldung, Token, Inventar, Statusverteilung und MQTT. WorxMower zeigt bestätigte Statuswerte, nimmt Nutzeraktionen entgegen und verwaltet einen lokalen Planentwurf. Der Scheduler ist Teil der bestehenden Mower-Instanz; es gibt keinen separaten Scheduler-Splitter.

## Geplante Scheduler-Schnittstelle

1. WorxCloud verteilt das empfangene Geräteobjekt an WorxMower.
2. WorxMower liest den bestätigten Plan aus last_status.payload.cfg.sc.
3. Der Editor verwendet empfangene Felder und wird für unbekannte Protokolle oder unvollständige Daten deaktiviert.
4. Änderungen landen nur in der Eigenschaft ScheduleDraft; ApplyChanges() sendet nichts.
5. Eine ausdrückliche Schaltfläche prüft den Entwurf erneut und ruft eine Cloud-Funktion auf.
6. WorxCloud prüft Worx-Cloud, MQTT-Verbindung, Gerätemetadaten und das vorhandene Planformat vor dem Versand.
7. Der Plan gilt erst nach Rücklesen des passenden cfg.sc als bestätigt. Publish-Erfolg ist keine Gerätebestätigung.
8. Bei Timeout bleibt der letzte bestätigte Plan sichtbar; Befehlsstatus und Entwurf zeigen den offenen Fehler oder Konflikt.

Der derzeit bekannte Kandidat ist Protokoll 0 mit sc.d (sieben Tage). sc.dd wird nur ausgewertet, wenn der Mäher es selbst zurückmeldet. Die App-Screenshots belegen die Oberfläche, nicht die Codierung des Feldes „Ganzer Tag“ oder die vollständige Schreibantwort.

## Update vorhandener Installationen

- Die drei Modul-GUIDs, Library-GUID, Funktionspräfixe, DataFlow-GUIDs und bestehenden Variablen-Idents werden nicht geändert.
- Bestehende Catomic-Instanzen erhalten neue Eigenschaften mit sicheren Standardwerten und neue Variablen zusätzlich zu den alten Variablen.
- Der bisherige Cloud-Wert bleibt lesbar. Nicht-Worx-Werte werden mit einem verständlichen Status abgewiesen; dadurch werden Altinstanzen nicht still an einen anderen Anbieter umgeleitet.
- Bei Wechsel des Module-Control-Repositorys auf die neue URL kann Symcon bestehende Instanzen anhand ihrer GUIDs aktualisieren.
- ApplyChanges() darf weiter die Geräteinformationen lesend aktualisieren, aber keine Mäh- oder Zeitplanbefehle senden.

## Noch benötigte Laufzeitbelege

Vor einer echten Schreibabnahme sind ein redigierter product-items-Datensatz und mindestens eine redigierte Statusantwort des WR105SI.1 nötig. Besonders zu bestätigen sind Protokollnummer, sieben d-Tagesfelder, Zuordnung der dritten Slotkomponente, ganztägiger Eintrag, Grenzen der Dauer/Zeiterweiterung und bestätigende MQTT-Rückmeldung. Die App-Screenshots enthalten keine dieser Transportdetails.
