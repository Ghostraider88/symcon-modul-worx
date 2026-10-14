# Changelog

## V1.0.0 — build 37 (2026-10-14)

First general release of the Worx Landroid IP-Symcon module. Validated with one mower setup; no compatibility claim is made for other models, protocols, or firmware variants.

- Connects to Worx Cloud, discovers supported devices, and uses the configured IP-Symcon MQTT transport for mower commands.
- Provides Start, Pause, and return-home controls with separate command feedback and mower-reported state.
- Uses the native IP-Symcon weekly event as the single schedule editor and confirms changes by reading the schedule back from the mower.
- Reflects app-originated schedule and daily work-time changes in Symcon.
- Supports capability-gated automatic scheduling, rain delay, and lock; write/read-back was confirmed on the validation mower.
- Displays readable status and error text, telemetry, daily progress, and maintenance counters.
- Refreshes Worx tokens before reconnecting MQTT authorization and applies bounded transport reconnect backoff.
- Handles an incomplete weekly-event record without an undefined EventType warning.
- Keeps unknown devices and unsupported actions locked.

Firmware auto-upgrade preference write/read-back was not live-tested. Firmware installation is not part of routine release validation. See the README and feature matrix for V1 exclusions and setup requirements.

## V1 release candidate — build 36 (2026-10-14)

- Renews the Worx access token before expiry and refreshes MQTT authorization before watchdog reconnects.
- Updates the WebSocket transport when REST polling rotates the token.

## V1 release candidate — build 35 (2026-10-10)

- Backs off repeated MQTT/WebSocket reconnects from five minutes up to one hour.
- Resets reconnect backoff only after ten minutes of stable transport and avoids a needless reconnect at that point.

## V1 release candidate — build 34 (2026-10-10)

- Sets REST polling to 15 minutes by default and honors HTTP 429 Retry-After with bounded backoff.
- Keeps valid cloud tokens across configuration applies, avoiding unnecessary sign-ins during routine changes.

## V1 release candidate — build 33 (2026-10-10)

- Clears the MQTT password field; Worx custom authorization is carried in the username only.

## V1 release candidate — build 32 (2026-10-10)

- Matches pyworxcloud's URL quoting for Worx MQTT authorizer components, preserving forward slashes.

## V1 release candidate — build 31 (2026-10-10)

- Generates a unique Worx MQTT client ID and keeps it stable across token refreshes to avoid client ID collisions.

## V1 release candidate — build 30 (2026-10-10)

- Sends Worx AWS custom-authorizer credentials in the MQTT CONNECT username, following pyworxcloud/Home Assistant.

## V1 release candidate — build 29 (2026-10-10)

- Keeps REST polling operational when the unofficial Worx MQTT endpoint disconnects.
- Extends MQTT reconnect backoff to 15 minutes with jitter and resets it only after a stable connection.
- Renews the Worx access token before rebuilding MQTT authorizer headers.

## V1 release candidate — build 28 (2026-10-10)

- Adds daily schedule progress and compatible archive history migration.
- Adds blade-runtime and battery-cycle reset baselines and local reset actions.

## V1 release candidate — build 27 (2026-10-10)

- Restores categorized variables before registration during instance creation, preventing duplicate idents on reload.

## V1 release candidate — build 26 (2026-10-10)

- Fixes lookup of variables moved into child categories without logging missing direct-child IDs during module updates.

## V1 release candidate — build 25 (2026-10-10)

- Groups diagnostics, firmware maintenance, feedback/history, and numeric state/error codes in child categories while preserving variable IDs and module access.

## V1 release candidate — build 24 (2026-10-10)

- Keeps the native weekly schedule event active so Symcon can display its next run; schedule actions remain no-op PHP comments.

## V1 release candidate — build 23 (2026-10-10)

- Uses a valid positive Symcon schedule action ID for "no mowing" switch points.

## V1 release candidate — build 22 (2026-10-02)

- Fixes variable presentation option keys for Symcon 9 so presentation editors open without unsupported fields.
