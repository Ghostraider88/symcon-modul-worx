# Worx Landroid V1.0.0 release status

- Release version: V1.0.0
- Status date: 2026-10-14
- IP-Symcon library metadata: version 2.0, build 38
- Validated scope: IP-Symcon 9.0 and one mower setup

## Summary

Build 38 was installed from GitHub through IP-Symcon Module Control. The library reload and module validation succeeded. The Worx Cloud and Mower instances stayed active, and Symcon reported no Worx instance errors. A live Start command was confirmed by the mower. Fresh valid mower data now clears the temporary "waiting for fresh data" feedback, while real command and setting feedback stays visible.

The automatic schedule, rain delay, and mower lock controls were each changed and confirmed by the mower, then restored to their previous values. The final read-back showed automatic schedule off, rain delay at 30 minutes, and the mower unlocked. The mower remained online and reported mowing after the checks.

## Quality checks

- PHPUnit: 85 tests and 957 assertions passed.
- PHP syntax: all five production PHP files passed.
- PHP-CS-Fixer: dry-run reported no files needing changes. Its only remaining notice is the deprecated configured rule visibility_required.
- GitHub Actions: test, style, and PHP 8.5 syntax workflows passed on the release commit.
- IP-Symcon module validation and reload passed after the GitHub update.

The IP-Symcon module_lint helper still stops while loading the external SymconStubs test submodule because its stub redeclares GetValue(). The local production validation, reload, syntax checks, and GitHub workflows pass; this is recorded as a limitation of that lint helper.

## Privacy and release metadata

The privacy review found no real credentials, tokens, account data, mower serials, or private installation identifiers in repository files or reachable main history. The 13 main-branch commits that used a non-Noreply author/committer address were rewritten to the account's ID-based GitHub Noreply address; the release branch was checked after the rewrite.

The public GitHub release is V1.0.0. The IP-Symcon library manifest retains version 2.0 and advances its build from 37 to 38 so existing installations keep their established library version while receiving the release build.

## V1 scope and limits

V1.0.0 is validated against one mower setup. This does not establish compatibility with other models, protocols, or firmware variants.

Automatic scheduling, rain delay, and lock have live write/read-back evidence for the validation mower. The firmware auto-upgrade preference was not live-write-tested, and firmware installation was not part of release testing. Multi-zone operation, all-day scheduling, one-time mowing, Party mode, manual edge-cut commands, initial Wi-Fi pairing, ACS, and blade-height or torque controls are outside the V1 scope.

The release tag identifies the exact tested source commit. See the [release checklist](RELEASE_CHECKLIST.md), [feature matrix](FEATURE_MATRIX.md), and [installation guide](../README.md).
