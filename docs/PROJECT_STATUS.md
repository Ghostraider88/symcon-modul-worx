# Project status — V1 release candidate

**Status date:** 2026-10-10
**Repository branch:** `main`
**Current module version:** 2.0, build 24
**Compatibility floor:** IP-Symcon 9.0
**Release state:** Release candidate; the general V1 release is not yet cleared.

## Summary

The core V1 path has been exercised with the single mower installation used for validation: MQTT/cloud discovery, the native Symcon weekly schedule event, app-to-Symcon schedule updates, signed daily work-time adjustment, and the basic Start, Pause, and return-home actions. The exact device identity and private configuration are deliberately not recorded here or in public source files. V1 does not claim support for other models, protocols, or firmware variants.

Build 22 fixes the Symcon 9 variable-presentation option schema that previously stopped Mower instance creation. Build 23 assigns a positive action ID to no-mowing schedule points, as required by Symcon 9. The live reload completed without warnings and the Mower instance returned to active status. The native schedule now contains all seven weekdays and reports a verified event representation. The live MQTT Client and separate MQTT-Landroid bridge are offline, so fresh device data and cloud command confirmation are still unavailable. The local product allowlist is also unconfirmed; neither limitation is treated as a module success.

Build 24 keeps the native event active so Symcon can calculate `NextRun`. Its event actions are no-op PHP comments and do not call the mower. Live device checks still depend on restoring the MQTT Client and MQTT-Landroid bridge.

## Confirmed

- [x] The weekly schedule is represented by one native Symcon weekly event. Symcon edits are sent to Worx and checked against the mower's returned schedule; app-originated schedule changes are reflected in Symcon.
- [x] App-originated signed daily work-time changes are reflected live in Symcon.
- [x] Start, Pause, and return-home were exercised on the validation mower; the user confirmed it returned to its charging station.
- [x] Numeric status/error values and adjacent readable text variables were checked; modern value presentations are used instead of legacy profiles.
- [x] Fresh-install/removal and repeated-configuration lifecycle checks are recorded in the release checklist.
- [x] Build 22 corrects the unsupported Symcon 9 presentation option keys. GitHub Actions style and test checks pass for the current `main` head.
- [x] Build 23 uses positive Symcon event action IDs. Live module reload completed without warnings and the native event was rebuilt for all seven weekdays.
- [x] Only `main` is present as a local or remote branch in the checked repository state.

## Open work before general V1 release

### 1. Complete the V1 product allowlist check

- [ ] In the Worx Configurator, verify the numeric product ID shown for the supported schedule candidate and set the local allowlist.
- [ ] Set the same product ID on the already-created Mower instance. Existing instances do not automatically inherit a later Configurator allowlist change.
- [ ] Confirm that the instance becomes active, receives a valid mower state, and displays the confirmed weekly schedule after refresh.
- [ ] Record only the result of this check here; do not add serial numbers, product IDs, account details, or full cloud responses to public documentation.

The allowlist is fail-closed: `0`, a missing product ID, or a mismatch keeps the device locked. If the Configurator does not show a candidate product ID, investigate the returned device fields before changing the gate; do not infer the numeric ID from a marketing model name.

The current live system also reports its MQTT Client parent and the separate MQTT-Landroid bridge offline. Reconnect those transports before checking fresh status, settings, or mower command read-back. The repeated bridge errors originate from that offline transport path, not from the Worx module reload.

### 2. Verify installation and update delivery

- [ ] Confirm the installed IP-Symcon Module Control package reports version 2.0/build 22.
- [ ] Complete the documented clean-install and Build 21-to-22 update path through Module Control on IP-Symcon 9.0 or later.
- [ ] Confirm Mower instance creation produces no PHP warnings/fatal errors and repeated Apply Changes remains clean.

The production report indicates the former creation fatal is no longer the visible failure, but the loaded package version has not been independently read back in this repository status record.

### 3. Finish setting-level live checks

- [ ] For each in-scope writable setting exposed by the validated mower (automatic schedule, rain delay, and lock where supported), test one change and confirm the returned mower/cloud value independently of the sent command.
- [ ] Do not issue a firmware installation request as a routine release test.
- [ ] Record unsupported or unverified settings as unavailable or out of scope in the user documentation.

### 4. Close release and privacy review

- [ ] Recheck current tracked files, all reachable Git refs/history, and release assets for private data immediately before V1 publication.
- [ ] Review `library.json`, module manifests, changelog, installation guide, feature matrix, and release checklist together against the final build.
- [ ] Verify the MIT license and final installation/update instructions in the published package.

## Explicit V1 exclusions

Multi-zone controls, “All day” schedule mode, one-time mowing, Party mode, manual edge-cut actions, initial Wi-Fi pairing, ACS, blade-height/torque controls, and additional unverified mower models remain out of scope. Scheduled edge-cut fields that belong to the confirmed weekly schedule are retained as schedule data; they do not enable a manual edge-cut action.

## Release decision

Do not label this a general V1 release until the product allowlist is confirmed on the validation installation, package installation/update is verified, remaining in-scope setting checks are either passed or explicitly excluded, and the final privacy/release review is complete.
