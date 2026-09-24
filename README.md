# Worx Landroid für IP-Symcon

Ein MIT-lizenziertes IP-Symcon-Modul für Worx Landroid Mähroboter. Die Codebasis stammt aus [c-tomic/IPSymconWorx](https://github.com/c-tomic/IPSymconWorx). Die Library- und Modul-GUIDs dieses Forks sind eigenständig und unterscheiden sich absichtlich von Catomic. Die vorhandenen Worx-Funktionspräfixe und Variablen-Idents bleiben innerhalb des Forks stabil; Catomic-Instanzen werden dadurch nicht automatisch zu Fork-Instanzen.

Dieses Repository unterstützt ausschließlich **Worx**. Kress, Landxcape und Ferrex werden nicht als kompatible Hersteller angeboten.

## Enthaltene Module

| Modul | Aufgabe |
|---|---|
| Worx Cloud | Worx-Anmeldung, REST-Abfrage, MQTT-Verbindung und Verteilung der Gerätestatus |
| Worx Configurator | Mäher des Worx-Kontos finden und als Instanzen anlegen |
| Worx Mower | Status, Start/Pause/Heimfahrt und gerätespezifische Funktionen |

## Installation

In IP-Symcon unter **Kerninstanz → Module Control → Repository hinzufügen** diese URL verwenden:

https://github.com/Ghostraider88/symcon-modul-worx

Für den aktuellen Teststand nach dem Hinzufügen über das Zahnrad in Module Control den Zweig `codex/worx-wr105si` auswählen; `main` ist noch nicht der freigegebene Stand. Danach eine Instanz **Worx Cloud** anlegen, die Worx-Kontodaten eintragen und den **Worx Configurator** verwenden. Erst nach Abschluss der Geräte- und Release-Prüfungen wird der freigegebene Stand über `main` installiert.

## Voraussetzungen

- IP-Symcon 6.0 oder neuer
- Ein Worx-Landroid-Konto und eine Worx-Cloud-Verbindung
- Für Echtzeitstatus und Steuerung die Symcon-Module „WebSocket Client“ und „MQTT Client“

Ein eigener MQTT-Broker ist nicht erforderlich: Nach dem Eintragen der Worx-Kontodaten legt das Modul den MQTT-Client automatisch an und verbindet ihn über einen WebSocket direkt mit Worx/AWS IoT. Dafür wird kein lokaler Broker-Host und -Port konfiguriert.

Falls Symcon beim Erstellen zunächst einen Client Socket anbietet, kann dieser inaktiv bleiben: Aktiv aus, Host leer, Port 0 und SSL aus. Nach Eingabe der Worx-Kontodaten ersetzt das Modul diese Standardverbindung automatisch durch den Worx-WebSocket.

## Gerätespezifische Funktionen

Die Mower-Instanz stellt den bestätigten Zeitplan als natives Symcon-Wochenplanereignis „Mähzeitplan“ bereit. Das Ereignis ist der einzige Editor. Änderungen daran werden automatisch über MQTT an Worx gesendet; die Rückmeldung wird erst nach erneutem Lesen des passenden Gerätezeitplans als bestätigt angezeigt. `ApplyChanges()` selbst sendet keinen Zeitplan. Im Docker-Test wurden beide Richtungen bestätigt: Symcon-Änderungen erscheinen in der Worx-App und werden vom Mäher angenommen; Änderungen in der Worx-App erscheinen automatisch im Symcon-Ereignis, ohne manuellen Refresh. „Ganzer Tag“ und Mähfenster über Mitternacht benötigen noch eigene Datenbelege.

Die bereitgestellten App-Bilder zeigen beim WR105SI.1 einen manuellen Wochenplan mit einem dargestellten Zeitfenster je Wochentag. In der Tagesansicht sind „Rasenkanten-Schnitt“, „Ganzer Tag“, Start, Ende und Löschen sichtbar. Außerdem ist „Automatischer Zeitplan“ ausgeschaltet und die Zeiterweiterung steht auf 0 %. Die individuelle Anzeige ist nicht in den Quellcode übernommen. Der vom Nutzer bereitgestellte Gerätedatensatz meldet Firmware 3.52.0+1.

Siehe [Worx-Feature-Matrix](docs/FEATURE_MATRIX.md), [Architektur und Updatepfad](docs/ARCHITECTURE.md), [Release-Abnahme](docs/RELEASE_CHECKLIST.md) und [Worx Mower](WorxMower/README.md).

## Entwicklung

Der genaue Quell-Commit und offene Gerätebelege stehen in [docs/FEATURE_MATRIX.md](docs/FEATURE_MATRIX.md). Die GPL-lizenzierte Home-Assistant-Integration dient nur der Recherche; ihr Code wird nicht übernommen.

## Lizenz

MIT. Die ursprünglichen Catomic-Lizenz- und Copyright-Hinweise stehen in [LICENSE.txt](LICENSE.txt).
