# Worx Mower

Die Mower-Instanz übernimmt die vorhandene Worx-Seriennummer aus dem Configurator. Bestehende Instanz-GUID, Präfix und Variablen-Idents entsprechen der Catomic-Basis.

## Befehle und bestätigter Zustand

Start, Pause und Heimfahrt werden über MQTT gesendet. Die Variablen `LastCommand` und `CommandStatus` zeigen den angeforderten Befehl und seine Rückmeldung. `State` bleibt der vom Mäher gemeldete Zustand. Ein erfolgreicher MQTT-Publish ist keine Gerätebestätigung.

## Wochenplan

Der Editor wird nur angezeigt, wenn der Mäher Protokoll 0 mit sieben gültigen `cfg.sc.d`-Einträgen meldet. Ein zweiter Einsatz pro Tag erscheint nur, wenn auch ein gültiger `cfg.sc.dd`-Block empfangen wird. Das Ereignis „Mähzeitplan“ ist der einzige Zeitplan und wird direkt bearbeitet. Änderungen werden automatisch an Worx übertragen; der zurückgemeldete `cfg.sc`-Plan bestätigt sie. Es gibt keine Entwurfsvariable und keinen separaten Zeitplan-Editor.

`ApplyChanges()` überträgt keinen Zeitplan. Die dritte Slot-Komponente ist anhand des redigierten WR105SI.1-Datensatzes als Kantenschnitt zugeordnet. Der Schreibweg sendet den erhaltenen Protokoll-0-`sc`-Block auf `commandIn`; als Bestätigung zählt ausschließlich das zurückgemeldete `cfg.sc`. Der Schreibbefehl und die Symcon-Ereignisbenachrichtigung sind im Docker-System noch live zu prüfen. „Ganzer Tag“ und Einsätze über Mitternacht bleiben offen.



## Redigierter Gerätenachweis

Die Variable „Gerätenachweis (redigiert)“ zeigt Modellfähigkeiten sowie ausschließlich ausgewählte, für die Feature-Prüfung nötige Felder. Dazu gehören empfangene Zeitplan-, Regenverzögerungs- und Zonendaten sowie Drehmoment, Zone und Regenstatus. Seriennummer, UUID, MAC-Adresse, Standort und Zugangsdaten werden nicht angezeigt. Der angezeigte Datensatz kann bei der Prüfung weiterer Gerätefunktionen weitergegeben werden.
