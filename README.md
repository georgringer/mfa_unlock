# MFA unlock

Unlocks locked MFA providers of backend users. After too many failed attempts
a provider is locked and only the user themselves can unlock it, by switching
to that user. This extension lets administrators do it for others, without
deleting the provider's secret.

- a new view **MFA unlock** in the dropdown of the backend user module,
  listing all users with a locked provider and an unlock button for each of
  them, or for all at once
- the CLI command `mfa:unlock`

Supports TYPO3 v14 and v15. The view is available to administrators only.

## Installation

```bash
composer require georgringer/mfa-unlock
```

## Usage

### Backend

Open **Admin Tools > Users** and select **MFA unlock** in the dropdown at the
top left.

### CLI

List the users with a locked provider:

```bash
vendor/bin/typo3 mfa:unlock --list
```

Unlock a single user, by uid or by username:

```bash
vendor/bin/typo3 mfa:unlock 16
vendor/bin/typo3 mfa:unlock editor
```

Unlock all users:

```bash
vendor/bin/typo3 mfa:unlock --all
```

Exactly one of the uid or username, `--all` and `--list` has to be given.
Unlocking resets the failed attempts only, users keep their configured
providers.
