# MCP Connector for Moodle (local_mcpconnector)

[![Moodle Plugin CI](https://github.com/studiolxd/moodle-local_mcpconnector/actions/workflows/moodle-plugin-ci.yml/badge.svg)](https://github.com/studiolxd/moodle-local_mcpconnector/actions/workflows/moodle-plugin-ci.yml)

Connect your Moodle site to AI assistants (Claude, and any client that speaks
the [Model Context Protocol](https://modelcontextprotocol.io)) through the
[Studio LXD](https://slxd.app) panel. The plugin provisions role-scoped
Moodle web-service tokens and turns them into revocable **MCP keys** that an
assistant presents to query your site.

This repository is the **source of truth** for the plugin. It is developed
against the [`lmsmcp`](https://lmsmcp.slxd.app) panel — part of the
[Studio LXD](https://slxd.app) suite — and released independently of that
panel's codebase.

## Requires a Studio LXD account (commercial service)

**This plugin does not work on its own.** It is the Moodle-side client of the
[**Studio LXD**](https://slxd.app) hosted panel (product: `lmsmcp`, at
[lmsmcp.slxd.app](https://lmsmcp.slxd.app)) — a separate, commercial
subscription service. You need an account and a license there to use it; an
account can be created at [slxd.app](https://slxd.app) with a
30-day free trial. The plugin talks to that panel over a signed HTTPS API and
to no other third party.

### Data sent to the Studio LXD panel

When an administrator provisions a user, the plugin sends the following to the
panel (see the plugin's Privacy settings for the machine-readable declaration):

- the user's **full name** and, in the emailed key, their **email address**;
- the user's **effective roles** (admin / manager / teacher / student / …);
- a **Moodle web-service token** minted for that user, scoped to the MCP
  services this plugin creates.

The panel stores the token **encrypted** and returns an **MCP key** whose value
is shown **once** (it is emailed to the user and never stored by Moodle again —
only its last 4 characters and status are kept locally). Revoking or suspending
a key from the plugin, or deleting the Moodle user, revokes it on the panel.

Review the panel's own [terms](https://slxd.app/en/legal/terms) and
[privacy policy](https://slxd.app/en/legal/privacy) before enabling this
in production; permanent web-service tokens and MCP keys are delivered by
email.

## What it adds

Under *Site administration → Plugins → Local plugins → MCP Connector*:

- **License** — panel URL, license key and signing secret, MCP endpoint URL.
- **Services** — the six role-scoped web-service definitions and their allowed
  functions.
- **Users** — assign/remove users to a service (mints/revokes their key).
- **Keys** — per-user key lifecycle: suspend, activate, revoke, regenerate +
  email, and reconcile status with the panel. Keys issued for a *different*
  panel (see below) are flagged here and can be regenerated in bulk.
- **Chat** — the assistant's own identity: creates a Moodle service account
  (no interactive access), gives it a role you pick from the site's existing
  roles **at system level**, mints its token and registers it in the panel as
  the chat's service key. Nothing is created until you ask for it here, and
  the page says plainly what that role can reach and that every member of your
  organisation chats through that one identity.
- **Settings** — per-role auto-sync and the key-delivery email template.

## Install

1. Copy this directory to `<moodleroot>/local/mcpconnector` (or install the zip
   via *Site administration → Plugins → Install plugins*) and complete the
   upgrade.
2. In your [Studio LXD panel](https://lmsmcp.slxd.app), connect your Moodle
   site and copy the **license key** and **panel secret** (shown once).
3. In Moodle, open *MCP Connector → License*, paste the panel URL, license key,
   secret and MCP endpoint URL, and validate.
4. Assign users under *Users*; they receive their MCP key by email. The mail
   is sent by a background task, so the page returns as soon as the keys are
   minted — keys arrive as cron runs.

### Moving to another panel

A key only works on the panel that minted it. If you re-pair the site with a
different panel (or a different license), validating the new license raises a
warning on *License* and *Keys* saying how many keys were issued for the
previous one, with a **Regenerate all keys and email them** button: each
affected user's key is revoked and replaced, and they are emailed the new
value. Above ten affected users the work runs as a background task instead of
in the page (you get one email per user as the keys are issued).

## Security notes

- The plugin signs every panel request with HMAC-SHA256 over a per-install
  secret (±5-minute replay window); the license key only identifies the install.
- Web-service tokens and MCP key values are never written to Moodle logs.
- MCP keys and tokens are delivered by email — treat the mailbox accordingly.
- A key value only ever exists in the panel's create response. When the email
  is sent from a background task, the value travels in the task queue
  **encrypted** with the site key (`\core\encryption`); it is decrypted only to
  be sent, and if the site cannot encrypt it the email is sent inline instead.

## Testing and CI

This repository is the plugin's source of truth: `version.php` at the root
carries the release, and there is no nested `plugin/` directory to look for.

- **Manual testing against a local panel** — see [TESTING.md](TESTING.md) for
  the full runbook (symlinking this repo into a local Moodle checkout,
  license validation, key lifecycle, and the automatic sync flows).
- **Local CI** — `scripts/ci.sh` mirrors the `phpcs` job of the GitHub Actions
  workflow (Moodle coding style + `php -l`) against a local Moodle checkout,
  so you get the real verdict without pushing. `scripts/ci.sh --fix` runs
  `phpcbf` first; `scripts/ci.sh --tests` also runs the PHPUnit suite. Needs
  `phpcs` with the `moodle` standard (`composer global require
  moodlehq/moodle-cs`) and `MOODLE_ROOT` pointing at a Moodle checkout.
- **GitHub Actions** — every push runs the full `moodle-plugin-ci` suite
  (lint, coding style, PHPDoc, validation, upgrade savepoints, PHPUnit)
  across the supported Moodle/PHP/database matrix.

## Support & source

- Website: https://slxd.app
- Panel: https://lmsmcp.slxd.app
- Docs: https://slxd.app/docs/lmsmcp
- Source: https://github.com/studiolxd/moodle-local_mcpconnector
- Issues: https://github.com/studiolxd/moodle-local_mcpconnector/issues

## License

GNU GPL v3 or later — see the header of each source file.
