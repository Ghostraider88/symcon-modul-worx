# Changelog

## V1 release candidate — build 22 (2026-10-02)

- Fixes variable presentation option keys for Symcon 9 so presentation editors open without unsupported fields.

- Keeps the native IP-Symcon weekly event as the single schedule editor, with changes sent to Worx and confirmed against the schedule read back from the mower. App-originated schedule changes update the event.
- Shows numeric status and error values alongside readable text variables, and keeps requested commands separate from the mower-reported state.
- Provides Start, Pause, and return-to-charging-station controls, exercised on the test mower.
- Reflects app-originated daily work-time adjustments live in Symcon. Write/read-back behavior for other settings remains unverified.
- Keeps unknown or unsupported devices locked and exposes controls only when the required device data and capabilities are available.
- Documents MQTT transport setup, installation, update behavior, privacy limits, and the V1 scope.

V1 is validated against one mower setup only. No compatibility claim is made for other models, protocols, or firmware variants.

Out of scope: multi-zone operation, all-day scheduling, one-time mowing, Party mode, manual edge-cut commands, initial Wi-Fi pairing, ACS, blade-height or torque controls, and other mower models. Scheduled edge-cut flags remain part of the supported weekly schedule format; they do not enable a manual edge-cut action.

This is a release candidate, not a general-release declaration. Fresh installation and repeated configuration have passed. Installation and update through the Module Control UI, live handling of invalid credentials and unavailable transports, and write/read-back checks for remaining settings are still open; see [the release checklist](docs/RELEASE_CHECKLIST.md).
