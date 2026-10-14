# V1.0.0 release checklist

## Live validation

- [x] Build 37 from GitHub was installed through IP-Symcon Module Control.
- [x] Module reload and module validation completed successfully after the update.
- [x] Worx Cloud and Mower instances remained active; Symcon reported no Worx instance errors.
- [x] A live Start request was confirmed by the mower.
- [x] Automatic scheduling was set to on, confirmed from Worx Cloud, and restored to off.
- [x] Rain delay was changed from 30 to 60 minutes, confirmed by the mower, and restored to 30 minutes.
- [x] The mower lock was set, confirmed, then cleared and confirmed unlocked.
- [x] All three tested settings returned to their original values; the mower remained online and reported mowing.

The validation installation's device identity, account data, and private configuration are intentionally omitted.

## Quality checks

- [x] PHPUnit passed: 84 tests and 952 assertions.
- [x] PHP syntax checks passed for all five production PHP files.
- [x] PHP-CS-Fixer dry-run found no files needing changes.
- [x] GitHub Actions test, style, and PHP 8.5 syntax workflows passed on the release commit.
- [x] IP-Symcon module validation and reload passed after the GitHub update.
- [x] The Symcon module_lint limitation is documented below.

## Privacy and release artifacts

- [x] Current source, reachable main history, and release contents were reviewed for credentials and private device/account data; none were found.
- [x] The 13 affected main-branch commits were rewritten to the account's ID-based GitHub Noreply address and the remote main history was verified.
- [x] The library and module manifests, changelog, MIT license, installation guide, feature matrix, and final release diff were reviewed.
- [x] The user-facing release version is V1.0.0; the Symcon library manifest stays at version 2.0 and advances to build 37.
- [x] The release tag points to the final tested commit.

## Known validation limitation

The IP-Symcon module_lint helper stops while loading the separately maintained SymconStubs submodule because its test stub redeclares GetValue(). The production module passes module validation and reload; PHP syntax, unit tests, style, and the GitHub workflows pass. No change was made to the external stub repository or its submodule pointer.

## V1 scope

V1.0.0 is validated against one mower setup only. No compatibility claim is made for other models, protocols, or firmware variants. The firmware auto-upgrade preference was not live-write-tested; firmware installation was deliberately excluded from release testing.

Multi-zone controls, all-day scheduling, one-time mowing, Party mode, manual edge-cut actions, initial Wi-Fi pairing, ACS, and blade-height or torque controls are outside V1. Scheduled edge-cut flags remain part of the observed weekly schedule format; they do not enable a manual edge-cut action.

Do not issue a firmware installation request as a routine release test. The GitHub release tag must identify the exact source commit whose checks are recorded above.
