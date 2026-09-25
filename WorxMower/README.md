# Worx Mower

Die Mower-Instanz übernimmt die vorhandene Worx-Seriennummer aus dem Configurator. Bestehende Instanz-GUID, Präfix und Variablen-Idents entsprechen der Catomic-Basis.

## Befehle und bestätigter Zustand

Start, Pause und Heimfahrt werden über MQTT gesendet. Die Variablen `LastCommand` und `CommandStatus` zeigen den angeforderten Befehl und seine Rückmeldung. `State` und `Error` bleiben als Integer-Variablen mit schlichter Zahlenanzeige für Kompatibilität und Automationen erhalten; sie nutzen keine Enum-Aufzählung, die eine Variablenaktion voraussetzen würde. Direkt daneben liefern `StateText` und `ErrorText` die bei jeder Gerätemeldung aktualisierten Status- und Fehlernamen als Strings, sodass ihre Verläufe in der Visualisierung ohne Zahlencodes lesbar sind. Damit Symcon diese Verläufe speichert, öffne die passende Archive-Control-Instanz, suche `StateText` und/oder `ErrorText` und aktiviere in der Variablenbearbeitung „Alle Variablenänderungen aufzeichnen“. Das Modul schaltet die installationsabhängige Archivierung nicht automatisch ein. Details stehen in der [Symcon-Dokumentation zum Archive Control](https://www.symcon.de/de/service/dokumentation/modulreferenz/kern-instanzen/archive-control/). Die Variablen sind nach Status, Bedienung, Einstellungen, Telemetrie und Diagnose sortiert; die Positionen werden auch für bestehende Instanzen bei jedem Anwenden der Konfiguration aktualisiert, Diagnose steht am Ende. Unbekannte Gerätecodelabels werden als „Unbekannter Status (Code)“ beziehungsweise „Unbekannter Fehler (Code)“ angezeigt. Ein erfolgreicher MQTT-Publish ist keine Gerätebestätigung.

## Wochenplan

Der Editor wird nur angezeigt, wenn der Mäher Protokoll 0 mit sieben gültigen `cfg.sc.d`-Einträgen meldet. Ein zweiter Einsatz pro Tag erscheint nur, wenn auch ein gültiger `cfg.sc.dd`-Block empfangen wird. Das Ereignis „Mähzeitplan“ ist der einzige Zeitplan und wird direkt bearbeitet. Änderungen werden automatisch an Worx übertragen; der zurückgemeldete `cfg.sc`-Plan bestätigt sie. Nach jeder Worx-Aktualisierung liest das Modul das native Ereignis zurück und vergleicht Start, Dauer, Aktivität und Kantenschnitt. Die Rückmeldung nennt eine App-Übernahme nur zusammen mit dem Zusatz „Symcon-Wochenplan geprüft“, wenn dieses Ereignis denselben Plan enthält. Es gibt keine Entwurfsvariable und keinen separaten Zeitplan-Editor.

`ApplyChanges()` überträgt keinen Zeitplan. Die dritte Slot-Komponente ist anhand des redigierten WR105SI.1-Datensatzes als Kantenschnitt zugeordnet. Der Schreibweg sendet den erhaltenen Protokoll-0-`sc`-Block auf `commandIn`; als Bestätigung zählt ausschließlich das zurückgemeldete `cfg.sc`. Im Docker-Test wurden beide Richtungen bestätigt: Symcon-Änderungen erscheinen in der Worx-App und werden vom Mäher angenommen; Änderungen in der Worx-App erscheinen automatisch im Symcon-Ereignis, ohne manuellen Refresh. „Ganzer Tag“ und Einsätze über Mitternacht bleiben offen.



## Redigierter Gerätenachweis

Die Variable „Gerätenachweis (redigiert)“ zeigt Modellfähigkeiten sowie ausschließlich ausgewählte, für die Feature-Prüfung nötige Felder. Dazu gehören empfangene Zeitplan-, Regenverzögerungs- und Zonendaten sowie Drehmoment, Zone und Regenstatus. Seriennummer, UUID, MAC-Adresse, Standort und Zugangsdaten werden nicht angezeigt. Der angezeigte Datensatz kann bei der Prüfung weiterer Gerätefunktionen weitergegeben werden.

## Weitere belegte Einstellungen

`Zeiterweiterung setzen`, `Regenverzögerung setzen` und `Sperre setzen` sind getrennte Eingaben. Daneben zeigen `Zeiterweiterung (bestätigt)`, `Regenverzögerung (bestätigt)` und `Gesperrt (bestätigt)` den zuletzt vom Gerät gemeldeten Zustand. Die Befehle werden nur bei passender Capability und Protokoll 0 angeboten; `Einstellungsrückmeldung` meldet den Geräteecho oder einen Timeout. Die Schreibwege sind implementiert, im WR105SI.1-Docker-Test aber noch nicht vom Nutzer bestätigt.


## Automatischer Zeitplan

Die Variable „Automatischer Zeitplan (bestätigt)“ zeigt den Boolean aus dem Worx-Cloud-Gerätedatensatz. „Automatischen Zeitplan setzen“ wird nur eingeblendet, wenn dieser Boolean tatsächlich gemeldet wird. Der Sollwert wird per Worx-Cloud-API gesendet; als bestätigt gilt er erst, wenn eine anschließende Abfrage denselben Wert zurückliefert. Der Schreibweg ist implementiert, im WR105SI.1-Docker-Test aber noch nicht vom Nutzer geprüft.
