# Installation and release checklist

## Validated behavior

- [x] The native Symcon weekly event is the single schedule editor. Symcon edits are sent and confirmed by mower read-back; app-originated changes update the event without manual refresh.
- [x] App-originated daily work-time changes are reflected live in Symcon, including signed values.
- [x] Start, pause, and return-home were exercised; the mower was confirmed back at the charging station.
- [x] The read-only next schedule start was checked against the unchanged schedule.
- [x] Numeric status and adjacent readable status text display correctly without legacy profiles.
- [x] MQTT broker, MQTT Client parent, and Worx Cloud setup are documented in the installation guide.
- [x] A fresh installation and removal cycle passed.
- [x] Repeated instance configuration completed without errors, warnings, duplicate objects, or changed variable identifiers.
- [x] Compatibility-critical identifiers are preserved by module registration code and automated checks.

## Remaining before a general V1 release

### Installation and update

- [ ] Verify installation and update through the Module Control UI.
- [x] Automated regression tests cover simulated authentication failures, missing or inactive MQTT parents, and an empty device inventory.

### Device behavior and compatibility

- [x] Reviewed visible controls against the V1 feature matrix; excluded controls are not presented as supported actions, and writable options are capability-gated.
- [ ] Confirm in a live installation that device discovery and instance creation remain limited to the supported V1 device.
- [ ] Live-check write/read-back for settings not yet confirmed; do not issue a firmware installation request as a routine test.
- [x] Documentation keeps other models and protocol variants outside the V1 compatibility claim.

### Privacy and quality

- [ ] Complete a final privacy review of source files, repository history, and release assets before release.
- [x] Automated style, test, and PHP syntax checks are configured in GitHub Actions.
- [ ] Review release metadata, release notes, license, manifests, and the final diff together after the remaining V1 gates pass.
