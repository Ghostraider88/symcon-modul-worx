# Installation and release checklist

## Verified so far

- The native Symcon weekly event is the single schedule editor. Edits from Symcon are sent and confirmed by device read-back; app-originated schedule changes update the event without a manual refresh.
- App-originated daily mowing-time adjustments are reflected in Symcon. The signed range is covered by automated tests.
- Start, pause, and return-home were exercised in a controlled device session; return to the charging station was subsequently confirmed.
- The current pull-request revision has passing automated tests, style checks, and PHP 8.5 syntax checks in GitHub Actions.

## Remaining before a general release

### Installation and update

- [ ] Install in a clean IP-Symcon test kernel and remove the test instances and repository cleanly.
- [ ] Verify missing and invalid credentials, unavailable transport, and empty device inventory.
- [ ] Apply instance configuration repeatedly and confirm no duplicate objects or unintended commands.
- [ ] Update an existing installation and verify stable module identities and variable identifiers.

### Device behavior

- [ ] Confirm every visible control is capability-gated and backed by a device field.
- [ ] Live-check app read-back for each supported setting that has not yet been verified in the test installation.
- [ ] Keep firmware installation out of routine tests; test only its availability and safety gates unless a separate controlled firmware test is approved.
- [ ] Continue testing other Worx device and firmware variants before claiming broader compatibility.

### Privacy and quality

- [ ] Scan all public branches, tags, PR references, commit metadata, and tracked files for credentials, personal details, device identifiers, locations, and real installation settings.
- [ ] Keep screenshots and complete cloud responses out of the public repository.
- [x] Run automated tests, style checks, and PHP syntax checks in CI.
- [ ] Review the final diff and release notes before removing draft status.
