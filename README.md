# Worx Landroid for IP-Symcon

This MIT-licensed module connects the Worx Landroid installation used to validate V1 to IP-Symcon. V1 has been tested with one mower setup; it does not claim compatibility with other models, protocols, or firmware versions. Exact device identifiers and private configuration are intentionally omitted from this repository.

## Modules

- **Worx Cloud** handles account authentication, cloud requests, and MQTT transport.
- **Worx Configurator** discovers cloud devices and creates mower instances.
- **Worx Mower** displays confirmed state and provides supported controls. Its native Symcon weekly event is the only schedule editor.

Controls are shown only when the connected mower reports the required capabilities and data. Requested commands and confirmed mower state are kept separate. Applying instance configuration does not issue mowing or schedule commands. App-originated schedule changes are synchronized back from the mower.

V1 intentionally excludes multi-zone operation, all-day scheduling, one-time mowing, Party mode, manual edge-cut commands, initial Wi-Fi pairing, ACS features, blade-height/torque controls, and other mower models. Edge-cut flags that are part of the supported weekly schedule remain schedule fields; they do not enable a manual edge-cut command.

## Requirements

- IP-Symcon 9.0 or later.
- A Worx account.
- An MQTT broker reachable from the IP-Symcon host or container.
- The standard IP-Symcon MQTT Client and transport modules required by the Worx Cloud instance.

## Installation

1. Make an MQTT broker available to IP-Symcon and create/configure its MQTT Client instance. Use the broker's host, port, and credentials; these are not Worx Cloud server settings. When the broker runs in another Docker container, use its reachable service name or network address, not `localhost`.
2. Add this repository in IP-Symcon Module Control and create a Worx Cloud instance. Select the configured MQTT Client as its parent transport and enter the Worx account credentials in the instance configuration.
3. Open the Worx Configurator. It lists local candidates and their numeric product ID; by default, creating a new mower instance is locked. Enter the product ID of the mower validated for this installation in "Freigegebene Produkt-ID". Only an exact integer match is enabled. This value stays in the local IP-Symcon configuration and is not committed to the repository.
4. Confirm that the mower is online and its confirmed weekly schedule appears as the native Symcon weekly event before editing it.

For updates, keep the existing Worx Cloud and Worx Mower instances. Their module identities and variable identifiers are part of the update path. Review status and command feedback after updating.

Use a test instance before enabling device controls. Confirm schedule changes against the state returned by the mower. Never include credentials, device identifiers, locations, private schedule values, or complete cloud responses in public issue reports.

## Development

The module is licensed under MIT. Third-party integrations may be consulted for protocol research; their source code is not copied. See [architecture](docs/ARCHITECTURE.md), [feature matrix](docs/FEATURE_MATRIX.md), [release checklist](docs/RELEASE_CHECKLIST.md), and [mower guide](WorxMower/README.md).
