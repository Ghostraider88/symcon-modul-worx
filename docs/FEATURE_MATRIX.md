# Feature matrix

The module uses runtime-reported device capabilities and fields to decide which controls to expose. Availability can differ by mower, firmware, and cloud response. A control is not considered supported solely because another integration or model exposes it.

| Area | Behavior | Evidence and limits |
|---|---|---|
| Cloud connection | Account authentication, device inventory, REST, and MQTT transport | Requires valid Worx account access and the Symcon transport modules configured by the instance |
| Device discovery | Configurator creates Mower instances for devices returned by the cloud | Device identifiers remain in local instance configuration and must not be included in issue reports |
| Status | Numeric status/error values with adjacent readable strings; battery, connectivity, and available telemetry | Only fields present in the received device data can be displayed |
| Mower commands | Start, pause, and return actions where supported by the device and transport | Request status and mower-reported status remain separate |
| Weekly schedule | Native Symcon weekly event; changes are validated, sent, and confirmed by device read-back | Only schedule formats and fields understood by the connected mower are editable; unsupported fields must be preserved or rejected safely |
| Settings | Automatic scheduling, rain delay, time adjustment, lock, and other fields where the required capability is reported | Requested and confirmed values are separate; individual controls remain hidden without supporting device data |
| Firmware maintenance | Availability check and update request only when explicitly confirmed by the cloud | An update request requires a positive availability response and runtime safety checks; it is never triggered by configuration application |
| Additional mowing modes | One-time mowing, party mode, and manual edge-cut commands remain unavailable until device support and round-trip behavior are evidenced | Scheduled edge-cut flags remain part of the received weekly schedule. Multi-zone control is outside the current project scope; current-zone telemetry is read-only |

The user-facing module must not log credentials, serial numbers, UUIDs, MAC addresses, locations, or full cloud responses. Redacted diagnostic output should use an explicit field allowlist.