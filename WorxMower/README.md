# Worx Mower

Die Mower-Instanz übernimmt die vorhandene Worx-Seriennummer aus dem Configurator. Bestehende Instanz-GUID, Präfix und Variablen-Idents entsprechen der Catomic-Basis.

## Befehle und bestätigter Zustand

Start, Pause und Heimfahrt werden über MQTT gesendet. Die Variablen `LastCommand` und `CommandStatus` zeigen den angeforderten Befehl und seine Rückmeldung. `State` bleibt der vom Mäher gemeldete Zustand. Ein erfolgreicher MQTT-Publish ist keine Gerätebestätigung.

## Wochenplan

Der Editor wird nur angezeigt, wenn der Mäher Protokoll 0 mit sieben gültigen `cfg.sc.d`-Einträgen meldet. Ein zweiter Einsatz pro Tag erscheint nur, wenn auch ein gültiger `cfg.sc.dd`-Block empfangen wird. Das Ereignis „Mähzeitplan“ ist der einzige Zeitplan und wird direkt bearbeitet. Änderungen werden automatisch an Worx übertragen; der zurückgemeldete `cfg.sc`-Plan bestätigt sie. Nach jeder Worx-Aktualisierung liest das Modul das native Ereignis zurück und vergleicht Start, Dauer, Aktivität und Kantenschnitt. Die Rückmeldung nennt eine App-Übernahme nur zusammen mit dem Zusatz „Symcon-Wochenplan geprüft“, wenn dieses Ereignis denselben Plan enthält. Es gibt keine Entwurfsvariable und keinen separaten Zeitplan-Editor.

`ApplyChanges()` überträgt keinen Zeitplan. Die dritte Slot-Komponente ist anhand des redigierten WR105SI.1-Datensatzes als Kantenschnitt zugeordnet. Der Schreibweg sendet den erhaltenen Protokoll-0-`sc`-Block auf `commandIn`; als Bestätigung zählt ausschließlich das zurückgemeldete `cfg.sc`. Der Nutzer hat bestätigt, dass Symcon-Änderungen in der Worx-App ankommen und angenommen werden. Beim Rückweg aus der App meldete Symcon zwar eine Übernahme, das Ereignis blieb laut Nutzer aber unverändert; die neue Event-Rückprüfung soll diesen Fall sichtbar machen und wird im Docker-System erneut geprüft. „Ganzer Tag“ und Einsätze über Mitternacht bleiben offen.



## Redigierter Gerätenachweis

Die Variable „Gerätenachweis (redigiert)“ zeigt Modellfähigkeiten sowie ausschließlich ausgewählte, für die Feature-Prüfung nötige Felder. Dazu gehören empfangene Zeitplan-, Regenverzögerungs- und Zonendaten sowie Drehmoment, Zone und Regenstatus. Seriennummer, UUID, MAC-Adresse, Standort und Zugangsdaten werden nicht angezeigt. Der angezeigte Datensatz kann bei der Prüfung weiterer Gerätefunktionen weitergegeben werden.

## Weitere belegte Einstellungen

`Zeiterweiterung setzen`, `Regenverzögerung setzen` und `Sperre setzen` sind getrennte Eingaben. Daneben zeigen `Zeiterweiterung (bestätigt)`, `Regenverzögerung (bestätigt)` und `Gesperrt (bestätigt)` den zuletzt vom Gerät gemeldeten Zustand. Die Befehle werden nur bei passender Capability und Protokoll 0 angeboten; `Einstellungsrückmeldung` meldet den Geräteecho oder einen Timeout. Diese Schreibwege sind im WR105SI.1-Docker-Test noch nicht vom Nutzer bestätigt.
