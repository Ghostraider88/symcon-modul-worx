# Installation and release checklist

## Installation and update

- [ ] Install the repository in a clean IP-Symcon test kernel.
- [ ] Create Worx Cloud, Configurator, and Mower instances with the required parent/transport connections.
- [ ] Verify missing credentials, invalid credentials, unavailable cloud transport, and empty device inventory.
- [ ] Apply instance configuration repeatedly and confirm there are no duplicate objects or unintended device commands.
- [ ] Update an existing installation and verify stable module identities and variable identifiers.
- [ ] Remove test instances and uninstall the repository cleanly.

## Device behavior

- [ ] Begin with read-only status and schedule checks.
- [ ] Confirm each control is backed by reported device capabilities and fields.
- [ ] Test schedule edits with a saved copy of the original schedule and verify device read-back.
- [ ] Confirm app-originated schedule changes appear in the Symcon event.
- [ ] Test timeouts, rejected commands, offline devices, malformed payloads, and cloud rate limits using isolated or mocked tests.
- [ ] Never trigger firmware installation during routine module tests.

## Privacy and quality

- [ ] Search tracked files and commit metadata for credentials, private email addresses, device identifiers, locations, account data, and real installation settings.
- [ ] Keep screenshots and complete cloud responses out of the public repository.
- [ ] Run PHP syntax, style, and automated tests in CI.
- [ ] Review the final diff and release notes before publication.