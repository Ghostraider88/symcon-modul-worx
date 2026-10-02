# Changelog

## V1 release candidate — build 21 (2026-10-02)

- Keeps the native IP-Symcon weekly event as the single schedule editor, with changes sent to Worx and confirmed against the schedule read back from the mower. App-originated schedule changes update the event.
- Shows numeric status and error values alongside readable text variables, and keeps requested commands separate from the mower-reported state.
- Provides Start, Pause, and return-to-charging-station controls, exercised on the test mower.
- Reflects app-originated daily work-time adjustments live in Symcon. Write/read-back behavior for settings not yet verified on the test installation remains unconfirmed.
- Locks mower instances by default and requires a local exact product-ID match plus valid device status and supported schedule data before exposing operation.
- Documents MQTT transport setup, installation, update behavior, privacy limits, and the V1 scope.

V1 is validated against one mower setup only. No compatibility claim is made for other models, protocols, or firmware variants. The current tracked file tree omits exact device identifiers, firmware values, and private settings. Historical removal is not complete; see the release checklist.

Out of scope: multi-zone operation, all-day scheduling, one-time mowing, Party mode, manual edge-cut commands, initial Wi-Fi pairing, ACS, blade-height or torque controls, and other mower models. Scheduled edge-cut flags remain part of the supported weekly schedule format; they do not enable a manual edge-cut action.

This is a release candidate, not a general-release declaration. Clean installation, invalid-credential and unavailable-transport handling, repeated configuration, remaining setting write/read-back checks, and historical GitHub reference cleanup are still open; see [the release checklist](docs/RELEASE_CHECKLIST.md).
