# Worx Landroid für IP-Symcon

Ein MIT-lizenziertes IP-Symcon-Modul für Worx Landroid Mähroboter. Die Codebasis stammt aus [c-tomic/IPSymconWorx](https://github.com/c-tomic/IPSymconWorx); bestehende Modulkennungen und Variablen-Idents bleiben für Updates erhalten.

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

Danach eine Instanz **Worx Cloud** anlegen, die Worx-Kontodaten eintragen und den **Worx Configurator** verwenden.

## Voraussetzungen

- IP-Symcon 6.0 oder neuer
- Ein Worx-Landroid-Konto und eine Worx-Cloud-Verbindung
- Für Echtzeitstatus und Steuerung die Symcon-Module „WebSocket Client“ und „MQTT Client“

Ein eigener MQTT-Broker ist nicht erforderlich: Nach dem Eintragen der Worx-Kontodaten legt das Modul den MQTT-Client automatisch an und verbindet ihn über einen WebSocket direkt mit Worx/AWS IoT. Dafür wird kein lokaler Broker-Host und -Port konfiguriert.

## Gerätespezifische Funktionen

Die Mower-Instanz zeigt Status und Funktionen auf Basis des empfangenen Geräteobjekts. Der Wochenplan wird für bestätigte Protokoll-0-Daten als lokaler Entwurf dargestellt. Das Anwenden der Instanzkonfiguration sendet keinen Zeitplan. Zurzeit gibt es keine Sende-Schaltfläche; auch ein direkter Aufruf wird abgewiesen. Die Übertragung bleibt deaktiviert, bis das konkrete WR105SI.1-Format samt Rückmeldung belegt ist.

Die bereitgestellten App-Bilder zeigen beim WR105SI.1 einen manuellen Wochenplan mit einem dargestellten Zeitfenster je Wochentag. In der Tagesansicht sind „Rasenkanten-Schnitt“, „Ganzer Tag“, Start, Ende und Löschen sichtbar. Außerdem ist „Automatischer Zeitplan“ ausgeschaltet und die Zeiterweiterung steht auf 0 %. Die individuelle Anzeige ist nicht in den Quellcode übernommen. Die auf der Modellseite als „Latest“ gezeigte Firmware 3.52.0+1 ist nicht als installierte Version bestätigt.

Siehe [Worx-Feature-Matrix](docs/FEATURE_MATRIX.md), [Architektur und Updatepfad](docs/ARCHITECTURE.md) und [Worx Mower](WorxMower/README.md).

## Entwicklung

Der genaue Quell-Commit und offene Gerätebelege stehen in [docs/FEATURE_MATRIX.md](docs/FEATURE_MATRIX.md). Die GPL-lizenzierte Home-Assistant-Integration dient nur der Recherche; ihr Code wird nicht übernommen.

## Lizenz

MIT. Die ursprünglichen Catomic-Lizenz- und Copyright-Hinweise stehen in [LICENSE.txt](LICENSE.txt).
