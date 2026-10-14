# V1 feature scope

V1 is limited to the Worx Landroid installation used for validation. The repository deliberately omits its exact model identifier, firmware version, serial number, account data, schedule values, and other private settings. No compatibility claim is made for other mower models, protocols, or firmware versions.

The modules use capabilities and fields reported at runtime. A feature is shown only when the connected device reports the necessary data and the transport supports the operation.

| Area | V1 behavior | Evidence and limits |
|---|---|---|
| Cloud connection | Account authentication, device inventory, REST, and MQTT transport | Requires a reachable MQTT broker and the IP-Symcon transport modules configured on the Worx Cloud instance |
| Device discovery | Configurator lists devices with the supported schedule shape; unknown or unsupported devices remain locked | Device operation is limited to the validated V1 scope |
| Status | Numeric state/error values with adjacent readable strings, connectivity, battery, and reported telemetry | Only fields present in device data are displayed |
| Mower commands | Start, pause, and return to charging station | Exercised with the test mower; command feedback and mower-reported state remain separate |
| Weekly schedule | Native Symcon weekly event is the only schedule editor; changes are validated, sent, and confirmed by mower read-back | App-originated schedule changes update the event. The tested protocol-0 schedule contains seven weekday entries; scheduled edge-cut flags are retained as schedule fields |
| Daily work-time adjustment | Signed percentage adjustment, read from the cloud and sent when supported | App-originated changes were observed live in Symcon; the displayed scale follows the signed device value |
| Automatic schedule, rain delay, and lock | Controls appear only when the device reports the required data/capability | Write and mower read-back were tested on the validated mower; all three values were restored afterward |
| Firmware preference and maintenance | Firmware preference and actions are shown only when supported fields are reported | Preference write/read-back is not part of the V1 live validation; firmware installation is not part of routine release testing |

## Explicitly out of scope for V1

- Multi-zone control, setup, and diagnostics
- The Worx app's “All day” schedule mode
- One-time mowing and Party mode
- Manual edge-cut commands
- Initial Wi-Fi pairing/onboarding
- ACS and every ACS-related function
- Blade-height, torque, or similar controls
- Additional mower models or unverified protocol/firmware variants

Scheduled edge-cut flags are distinct from manual edge-cut commands and remain part of the observed weekly schedule format. No excluded feature is presented as a future V1 option.

The module must not log credentials, serial numbers, UUIDs, MAC addresses, locations, private schedule settings, or complete cloud payloads. Diagnostic output uses an explicit field allowlist.
