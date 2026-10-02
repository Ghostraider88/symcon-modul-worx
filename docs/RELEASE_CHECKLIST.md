# Installation and release checklist

## Confirmed on the user's test installation

- [x] The native Symcon weekly event is the single schedule editor. Symcon edits are sent and confirmed by mower read-back; app-originated changes update the event without manual refresh.
- [x] App-originated daily work-time changes are reflected live in Symcon, including signed values.
- [x] Start, pause, and return-home were exercised; the mower was confirmed back at the charging station.
- [x] The read-only next schedule start was checked against the unchanged test schedule.
- [x] Numeric status and adjacent readable status text display correctly without legacy profiles.
- [x] The main branch runtime fix is installed in both Docker test kernels; both were running and their last ten minutes of logs contained no PHP fatal errors, warnings, parse errors, or uncaught exceptions.
- [x] GitHub Actions passed `Check Style` and `Run Tests` for the current main commit `a14c473b553c81dbf05d90fd9392a5f9bd53512b`.
- [x] MQTT broker, MQTT Client parent, and Worx Cloud setup are documented in the installation guide.

## Remaining before a general V1 release

### Installation and update

- [ ] Install from a clean IP-Symcon 9.x test kernel and remove the test instances cleanly.
- [ ] Verify missing and invalid credentials, unavailable MQTT transport, and an empty device inventory.
- [ ] Apply instance configuration repeatedly and confirm there are no duplicate objects or unintended commands.
- [x] Existing test instances were updated; automated identity checks cover stable module GUIDs and legacy variable identifiers.

### Device behavior and compatibility

- [x] Reviewed visible controls against the V1 feature matrix; excluded controls are not presented as supported actions, and writable options are capability-gated.
- [ ] Live-check write/read-back for settings not yet confirmed on the test installation; do not issue a firmware installation request as a routine test.
- [ ] Enter the tested mower product_id in the local Configurator property and verify the exact matching row alone receives a create action; the property defaults to 0 and the code contains no device-specific product ID.
- [x] Documentation keeps other models and protocol variants outside the V1 compatibility claim.

### Privacy and quality

- [x] Scanned the final tracked main tree, pushed branch/tag refs, and rewritten commit metadata for known private device, account, contact, network, path, and credential data; no such values were found in main. The public maintainer handle remains in package metadata and license attribution.
- [x] Screenshots and full cloud responses are excluded from the repository.
- [x] Automated tests, style checks, and CI passed on the current main commit `a14c473b553c81dbf05d90fd9392a5f9bd53512b`.
- [ ] Final release metadata, release notes, license, manifests, and diff must be reviewed together after remaining gates pass.
- [ ] GitHub retains hidden pull-request refs. PR 1 still contains device details and personal commit metadata; PR 2 retains personal commit metadata. GitHub Support must purge those refs and cached views before complete historical removal can be claimed.
