# Worx Landroid for IP-Symcon

This MIT-licensed module connects compatible Worx Landroid mowers to IP-Symcon. It keeps the existing cloud transport and device discovery components and exposes mower status, supported controls, and schedule data through IP-Symcon.

## Modules

- **Worx Cloud** handles account authentication, cloud requests, and MQTT transport.
- **Worx Configurator** discovers cloud devices and creates mower instances.
- **Worx Mower** displays confirmed state and provides supported controls. Its native Symcon weekly event is the schedule editor.

Controls are shown only when the connected mower reports the required capabilities and data. Requested commands and confirmed mower state are kept separate. Applying instance configuration does not issue mowing or schedule commands.

## Requirements

- IP-Symcon 9.0 or later.
- A Worx account and the Symcon transport modules required by the selected cloud connection.

## Installation

Add this repository in IP-Symcon Module Control and create a Worx Cloud instance. Configure the account and required Symcon transport modules, then use the Worx Configurator to add a mower. Follow the module configuration and status messages if a transport or account prerequisite is missing.

Use a test instance before enabling device controls. Confirm schedule changes against the state returned by the mower. Never include credentials, device identifiers, locations, or complete cloud responses in public issue reports.

## Development

The module is licensed under MIT. Third-party integrations may be consulted for protocol research; their source code is not copied. See [architecture](docs/ARCHITECTURE.md), [feature matrix](docs/FEATURE_MATRIX.md), [release checklist](docs/RELEASE_CHECKLIST.md), and [mower guide](WorxMower/README.md).
