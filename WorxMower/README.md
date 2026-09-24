# Worx Mower

Die Mower-Instanz übernimmt die vorhandene Worx-Seriennummer aus dem Configurator. Bestehende Instanz-GUID, Präfix und Variablen-Idents entsprechen der Catomic-Basis.

## Befehle und bestätigter Zustand

Start, Pause und Heimfahrt werden über MQTT gesendet. Die Variablen `LastCommand` und `CommandStatus` zeigen den angeforderten Befehl und seine Rückmeldung. `State` bleibt der vom Mäher gemeldete Zustand. Ein erfolgreicher MQTT-Publish ist keine Gerätebestätigung.

## Wochenplan

Der Editor wird nur angezeigt, wenn der Mäher Protokoll 0 mit sieben gültigen `cfg.sc.d`-Einträgen meldet. Ein zweiter Einsatz pro Tag erscheint nur, wenn auch ein gültiger `cfg.sc.dd`-Block empfangen wird. Die Werte bleiben im Editor als lokaler Entwurf sichtbar; der bestätigte Plan steht separat in `Schedule`.

`ApplyChanges()` überträgt keinen Zeitplan. Die dritte Slot-Komponente wird vorläufig als Kantenschnitt-Kandidat dargestellt; die Zuordnung beim WR105SI.1 muss noch am Gerätedatensatz bestätigt werden. Der Sendeweg bleibt gesperrt, bis ein redigierter Datensatz des konkreten WR105SI.1 und eine passende Mäher-Rückmeldung den vollständigen Feldaufbau, „Ganzer Tag“, Feldgrenzen und Bestätigung belegen. Die Screenshots belegen die App-Bedienung, aber keine Cloud-Feldzuordnung.

