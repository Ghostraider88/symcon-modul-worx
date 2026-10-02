# Installation and release checklist

## Confirmed on the user's test installation

- [x] The native Symcon weekly event is the single schedule editor. Symcon edits are sent and confirmed by mower read-back; app-originated changes update the event without manual refresh.
- [x] App-originated daily work-time changes are reflected live in Symcon, including signed values.
- [x] Start, pause, and return-home were exercised; the mower was confirmed back at the charging station.
- [x] The read-only next schedule start was checked against the unchanged test schedule.
- [x] Numeric status and adjacent readable status text display correctly without legacy profiles.
- [x] Commit `8bd6d6fb7075585f639b43d0d3d508cb0e272c74` is installed in both Docker test kernels; both were running with healthy logs.
- [x] MQTT broker, MQTT Client parent, and Worx Cloud setup are documented in the installation guide.

## Remaining before a general V1 release

### Installation and update

- [x] Installed in a clean IP-Symcon 9.0 lifecycle kernel; removed the test instances and restored the baseline afterward.
- [ ] Verify installation and update through the Module Control UI.
- [ ] Verify missing and invalid credentials, unavailable MQTT transport, and an empty device inventory.
- [x] Applied instance configuration three times in the isolated lifecycle kernel; all 36 Mower variables and IDs remained stable, with no errors or warnings.
- [x] Existing test instances were updated on main; CI asserts stable library/module GUIDs and prefixes. Variable identifiers remain unchanged in mower registration code.

### Device behavior and compatibility

- [x] Reviewed visible controls against the V1 feature matrix; excluded controls are not presented as supported actions, and writable options are capability-gated.
- [ ] Live-check write/read-back for settings not yet confirmed on the test installation; do not issue a firmware installation request as a routine test.
- [ ] Enter the tested mower product_id in the local Configurator property and verify only rows with that exact model ID receive a create action; the property defaults to 0 and the code contains no device-specific product ID.
- [x] Documentation keeps other models and protocol variants outside the V1 compatibility claim.

### Privacy and quality

- [x] Reviewed the current tracked file tree at `35f7a762fa70f9a56fabe658609d4f70b3cc2379`; known private device, account, contact, network, path, and credential values are absent from current files.
- [x] Rewrote and checked the history reachable from published branches and tags at `35f7a762fa70f9a56fabe658609d4f70b3cc2379`; the remote advertises only `main` and no tags, and known private mower identifiers, firmware, and schedule values were not found in its current tree or reachable history.
- [ ] Ask GitHub Support to purge hidden pull-request refs and cached views that retain device details or personal commit metadata. This is separate from cleanup of published branch and tag history.
- [x] Screenshots and full cloud responses are excluded from the current tracked file tree.
- [x] GitHub Actions passed Check Style, Run Tests, and PHP 8.5 syntax checks for commit `35f7a762fa70f9a56fabe658609d4f70b3cc2379`.
- [ ] Review release metadata, release notes, license, manifests, and the final diff together after the remaining V1 gates pass.
