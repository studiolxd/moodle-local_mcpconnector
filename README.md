# MCP Connector for Moodle (local_mcpconnector)

[![Moodle Plugin CI](https://github.com/studiolxd/moodle-local_mcpconnector/actions/workflows/moodle-plugin-ci.yml/badge.svg)](https://github.com/studiolxd/moodle-local_mcpconnector/actions/workflows/moodle-plugin-ci.yml)

Connect your Moodle site to AI assistants (Claude, and any client that speaks
the [Model Context Protocol](https://modelcontextprotocol.io)) through the
[MoodleMCP](https://moodlemcp.com) panel. The plugin provisions role-scoped
Moodle web-service tokens and turns them into revocable **MCP keys** that an
assistant presents to query your site.

## Requires a MoodleMCP account (commercial service)

**This plugin does not work on its own.** It is the Moodle-side client of the
[**MoodleMCP**](https://moodlemcp.com) hosted panel — a separate, commercial
subscription service. You need an account and a license there to use it; an
account can be created at [moodlemcp.com](https://moodlemcp.com) with a
30-day free trial. The plugin talks to that panel over a signed HTTPS API and
to no other third party.

### Data sent to the MoodleMCP panel

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

Review the panel's own [terms](https://moodlemcp.com/en/legal/terms) and
[privacy policy](https://moodlemcp.com/en/legal/privacy) before enabling this
in production; permanent web-service tokens and MCP keys are delivered by
email.

## What it adds

Under *Site administration → Plugins → Local plugins → MCP Connector*:

- **License** — panel URL, license key and signing secret, MCP endpoint URL.
- **Services** — the six role-scoped web-service definitions and their allowed
  functions.
- **Users** — assign/remove users to a service (mints/revokes their key).
- **Keys** — per-user key lifecycle: suspend, activate, revoke, regenerate +
  email, and reconcile status with the panel.
- **Settings** — per-role auto-sync and the key-delivery email template.

## Install

1. Copy this directory to `<moodleroot>/local/mcpconnector` (or install the zip
   via *Site administration → Plugins → Install plugins*) and complete the
   upgrade.
2. In your [MoodleMCP panel](https://moodlemcp.com), connect your Moodle site
   and copy the **license key** and **panel secret** (shown once).
3. In Moodle, open *MCP Connector → License*, paste the panel URL, license key,
   secret and MCP endpoint URL, and validate.
4. Assign users under *Users*; they receive their MCP key by email.

## Security notes

- The plugin signs every panel request with HMAC-SHA256 over a per-install
  secret (±5-minute replay window); the license key only identifies the install.
- Web-service tokens and MCP key values are never written to Moodle logs.
- MCP keys and tokens are delivered by email — treat the mailbox accordingly.

## Support & source

- Website: https://moodlemcp.com
- Docs: https://moodlemcp.com/en/docs
- Source: https://github.com/studiolxd/moodle-local_mcpconnector
- Issues: https://github.com/studiolxd/moodle-local_mcpconnector/issues

## License

GNU GPL v3 or later — see the header of each source file.
