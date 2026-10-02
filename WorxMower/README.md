# Worx Mower

The Mower instance is created by the Worx Configurator and uses the selected device from its connected Worx Cloud instance. V1 is validated against one mower setup only; additional models and firmware variants are outside its compatibility claim.

Die Laufzeit akzeptiert Geräte nur bei exakter lokaler Produkt-ID-Freigabe sowie gültigem Protokoll-0-Zeitplan und nicht leerem, plausiblem Status-Payload. Einzelne Bedienfunktionen werden zusätzlich nur bei gemeldeter Capability und passenden Feldern freigeschaltet; es gibt keine Firmware-Allowlist.

## Lokale Modellfreigabe

Die Mower-Instanz verarbeitet Status und Befehle nur, wenn `AllowedProductID` exakt mit der positiven numerischen `product_id` des Cloud-Geräts übereinstimmt. Der Standardwert `0` sperrt die Instanz. Der Konfigurator zeigt die Produkt-ID geeigneter Zeitplankandidaten lokal an; trage die geprüfte ID dort ein und lege die Instanz neu an. Bei bereits vorhandenen oder manuell angelegten Instanzen den Wert in den Instanzeigenschaften lokal setzen. Die ID wird nur als lokale Instanzeigenschaft gespeichert; sie steht nicht im öffentlichen Quelltext und wird weder protokolliert noch an die Cloud übertragen. Änderungen an `AllowedProductID` aktualisieren nur den gelesenen Gerätezustand; sie senden keinen Mäh- oder Zeitplanbefehl.
## Status and commands

Status and error codes remain numeric variables. Adjacent text variables provide readable labels and can be archived independently in Symcon. Commands show the requested action and its response separately from the state subsequently reported by the mower. A successful transport publish is not device confirmation.

## Weekly schedule

The native Symcon weekly event is the single schedule editor. The module creates or updates it only when the mower provides the supported protocol-0 schedule format. Event changes are validated, sent through the available Worx transport, and confirmed by reading the schedule back from the mower. Changes received from the Worx app update the event as well.

Applying instance configuration does not send schedule changes. Scheduled edge-cut flags are schedule fields; V1 does not provide a manual edge-cut action. All-day scheduling is outside the V1 scope. Keep a copy of the existing schedule before testing changes on a real mower.

## Other settings

Controls are shown only when the mower reports the required capability and field. Requested values and confirmed values use separate variables. Firmware installation is guarded by explicit availability and device readiness checks, but it is not part of routine tests.

For readable history in Symcon, enable archiving for the relevant status text variables in Archive Control.
