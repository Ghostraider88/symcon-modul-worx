# Installation and release checklist

## Confirmed on the user's test installation

- [x] The native Symcon weekly event is the single schedule editor. Symcon edits are sent and confirmed by mower read-back; app-originated changes update the event without manual refresh.
- [x] App-originated daily work-time changes are reflected live in Symcon, including signed values.
- [x] Start, pause, and return-home were exercised; the mower was confirmed back at the charging station.
- [x] The read-only next schedule start was checked against the unchanged test schedule.
- [x] Numeric status and adjacent readable status text display correctly without legacy profiles.
- [x] The latest recorded automated run passed 74 PHPUnit tests / 897 assertions, PHP syntax checks, PHP-CS-Fixer, and JSON validation. Re-run these after the release changes and confirm CI on the exact release commit.

## Remaining before a general V1 release

### Installation and update

- [ ] Install from a clean IP-Symcon 9.x test kernel and remove the test instances cleanly.
- [ ] Verify missing and invalid credentials, unavailable MQTT transport, and an empty device inventory.
- [ ] Apply instance configuration repeatedly and confirm there are no duplicate objects or unintended commands.
- [ ] Update existing test instances and verify stable module GUIDs, prefixes, and variable identifiers.
- [ ] Document the required MQTT broker, MQTT Client parent, and Worx Cloud setup in the install guide.

### Device behavior

- [ ] Review every visible control against the V1 feature matrix and verify runtime capability gating.
- [ ] Live-check write/read-back for settings not yet confirmed on the test installation; do not issue a firmware installation request as a routine test.
- [ ] Keep other models and protocol variants explicitly outside the V1 compatibility claim.

### Privacy and quality

- [ ] Scan the final tracked tree, all pushed branch/tag refs, commit metadata, and reachable PR refs for credentials, personal contact details, device identifiers, locations, and private settings.
- [ ] Keep screenshots and full cloud responses out of the repository.
- [ ] Re-run automated tests, style checks, and PHP syntax checks after the final source changes; require green CI on the exact release commit.
- [ ] Review release notes, LICENSE, manifests, stable identities, and final diff.
- [ ] Resolve GitHub's retained sensitive pull-request refs and cached views before claiming complete removal of historical data.
