# Architecture

## Module responsibilities

| Module | Responsibility | Stable identity |
|---|---|---|
| Worx Cloud | Authentication, REST requests, device inventory, MQTT transport, and data distribution | Library/module GUID and `WORX` prefix |
| Worx Configurator | Device discovery and Mower instance creation | Module GUID and `WORXCONF` prefix |
| Worx Mower | Confirmed status, supported controls, and the native weekly schedule event | Module GUID and `WORXMOWER` prefix |

The module identities and existing variable identifiers are part of the update path. They must remain stable after release.

## Data flow

The Worx Cloud instance owns account and transport state. The Configurator maps cloud devices to Mower instances. A Mower instance requests device data from its configured parent, displays confirmed values, and sends only actions supported by the device data available at runtime.

The native Symcon weekly event is the only schedule editor. Schedule changes are validated and sent through the transport supported by the device. A returned device schedule is authoritative; transport acceptance alone is not confirmation. App-originated changes update the event through the same read-back path.

## Safety and compatibility

- `ApplyChanges()` is idempotent and does not send mowing or schedule commands.
- Existing module GUIDs, prefixes, and variable identifiers are preserved.
- Unsupported or unknown device actions are not exposed as controls.
- Credentials, device identifiers, locations, and complete cloud payloads are not logged or persisted in public source files.
- Protocol-specific fields are retained when writing schedule changes where the format permits it.