# Installation and release checklist

## Confirmed on the user's test installation

- [x] The native Symcon weekly event is the single schedule editor. Symcon edits are sent and confirmed by mower read-back; app-originated changes update the event without manual refresh.
- [x] App-originated daily work-time changes are reflected live in Symcon, including signed values.
- [x] Start, pause, and return-home were exercised; the mower was confirmed back at the charging station.
- [x] The read-only next schedule start was checked against the unchanged test schedule.
- [x] Numeric status and adjacent readable status text display correctly without legacy profiles.
- [x] Commit `c42b42ab99ba3288e53cb9c2d75a24ec542dd661` is installed in both Docker test kernels; both were running, and their last ten minutes of logs contained no PHP fatal errors, warnings, parse errors, or uncaught exceptions.
- [x] MQTT broker, MQTT Client parent, and Worx Cloud setup are documented in the installation guide.

## Remaining before a general V1 release

### Installation and update

- [ ] Install from a clean IP-Symcon 9.x test kernel and remove the test instances cleanly.
- [ ] Verify missing and invalid credentials, unavailable MQTT transport, and an empty device inventory.
- [ ] Apply instance configuration repeatedly and confirm there are no duplicate objects or unintended commands.
- [x] Existing test instances were updated on main; CI asserts stable library/module GUIDs and prefixes. Variable identifiers remain unchanged in mower registration code.

### Device behavior and compatibility

- [x] Reviewed visible controls against the V1 feature matrix; excluded controls are not presented as supported actions, and writable options are capability-gated.
- [ ] Live-check write/read-back for settings not yet confirmed on the test installation; do not issue a firmware installation request as a routine test.
- [ ] Enter the tested mower product_id in the local Configurator property and verify only rows with that exact model ID receive a create action; the property defaults to 0 and the code contains no device-specific product ID.
- [x] Documentation keeps other models and protocol variants outside the V1 compatibility claim.

### Privacy and quality

- [x] Reviewed the current tracked file tree at `c42b42ab99ba3288e53cb9c2d75a24ec542dd661`; known device, account, contact, network, path, and credential values are absent from current files.
- [ ] Rewrite and verify all commits reachable from published branches and tags. A private mower firmware value remains in reachable `main` history; this gate stays open until the value is absent after the history rewrite and remote verification.
- [ ] Ask GitHub Support to purge hidden pull-request refs and cached views that retain device details or personal commit metadata. This is separate from cleanup of published branch and tag history.
- [x] Screenshots and full cloud responses are excluded from the current tracked file tree.
- [x] GitHub Actions passed Check Style, Run Tests, and PHP 8.5 syntax checks for commit `c42b42ab99ba3288e53cb9c2d75a24ec542dd661`.
- [ ] Review release metadata, release notes, license, manifests, and the final diff together after the remaining V1 gates pass.
