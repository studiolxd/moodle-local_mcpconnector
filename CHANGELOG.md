# Changelog

All notable changes to the MCP Connector for Moodle (`local_mcpconnector`).

## 1.0.1 — 2026-08-10

Housekeeping release: no functional change, no database change. Everything
below is about meeting the standards the moodle.org plugins directory checks.

### Added
- **Continuous integration.** A `moodle-plugin-ci` workflow now runs on every
  push, covering both ends of the supported range (Moodle 4.2 on PHP 8.1 and
  Moodle 5.2 on PHP 8.3) against PostgreSQL and MariaDB: lint, coding style,
  PHPDoc, validation, upgrade savepoints and the PHPUnit suite.

### Fixed
- **Moodle coding style.** 777 violations reported by `moodle-cs` are gone,
  the bulk of them indentation of multi-line function calls. Language files
  are now alphabetically ordered as `moodle.Files.LangFilesOrdering` requires
  — same 178 strings with the same values in both `en` and `es`, only the
  order changed.
- **Development comments left in Spanish** in `settings.php` are now English,
  as required for a published plugin.
- **The header of `db/service_functions.php`** pointed at a generator script
  that no longer exists, which made the provenance of that file impossible to
  verify. It now describes where the whitelists actually come from.

## 1.0.0 — 2026-08-06

Public launch. The 2.x series below was internal pre-release numbering;
1.0.0 succeeds 2.24 and is identical to it apart from the version. Moodle
keys plugin upgrades on the internal date version (`$plugin->version`,
`2026080600`), never on the release label, so upgrading over any installed
2.x works normally.

## 2.24 — 2026-08-03

### Added
- **"Deprovision" button on the Services admin page.** Uninstalling the
  plugin already revoked panel keys and wiped every service/token/authorization
  it created, but that cleanup only ever ran once, automatically, at
  uninstall time — there was no way to force it again, or to run it without
  removing the plugin. The button (behind a confirmation step, same as
  "Restore service") calls the exact same cleanup the uninstall hook uses,
  now shared via `local_mcpconnector_deprovision_resources()`, while keeping
  the license/panel connection intact so the plugin can re-provision itself
  afterwards.

### Fixed
- **Panel key revocation failures during cleanup were silent.** The
  uninstall hook's call to the panel's `revoke-all` endpoint only logged to
  `debugging()` (developer-mode only) on failure, so an unreachable panel at
  uninstall time left orphaned, still-active MCP keys with no visible trace.
  The shared cleanup now surfaces that failure to the caller; the new
  Deprovision button shows it as an explicit warning notification.

## 2.23 — 2026-07-17

### Added
- **`update_scorm`: replace a SCORM's package without losing its cmid,
  attempts or tracking.** Until now the only way to fix a published SCORM's
  content was to delete the activity and recreate it — breaking direct
  links/bookmarks and losing student attempts. Uses the same
  update_moduleinfo() path `update_module` uses for every other module;
  `packageurl` is optional (omit to only touch settings).
- **`create_scorm`/`update_scorm` now accept the mod_scorm settings**
  previously only reachable through the site's defaults: `grademethod`,
  `maxgrade`, `whatgrade`, `maxattempt`, `forcenewattempt`, `forcecompleted`,
  `lastattemptlock`, `masteryoverride`, `auto`, `popup`, `width`, `height`,
  `skipview`, `hidebrowse`, `displaycoursestructure`, `hidetoc`, `nav`,
  `navpositionleft`, `navpositiontop`, `displayattemptstatus`, `timeopen`,
  `timeclose`. All optional; omitted fields keep the previous behaviour
  (site defaults on create, current value on update).
- **`update_section`: update a course section's name, summary and/or
  visibility.** Of a course's three text surfaces (a label's body, the
  course summary, a section's summary) the section summary had no
  webservice able to write it. Uses `course_update_section()`, the same
  core helper the course-editing AJAX calls.

### Fixed
- **`create_scorm`'s `grademethod` was hardcoded to `GRADESCOES` (0,
  "highest grade of any SCO")** regardless of the site's actual grading
  intent — a SCORM created by this function silently graded differently
  from one created through the settings form. Now overridable (see above);
  the hardcoded value remains the default when omitted, for backwards
  compatibility with existing callers.

## 2.22 — 2026-07-15

### Added
- **Content authoring**: `create_h5p` (mod_h5pactivity from an HTTPS .h5p —
  validated zip with h5p.json, generator defaults: tracking on, highest
  attempt, review on completion), `create_book` (book + ordered chapters
  with HTML content in ONE call — core can only read books; import-tool
  storage model), and `add_to_content_bank` (upload e.g. an .h5p into the
  course or site content bank — no core write webservice exists). Lesson
  authoring (page graph) deliberately deferred to a later release.

## 2.21 — 2026-07-15

### Added
- **Course backup/restore over MCP** — core has NO webservice for either.
  `backup_course {courseid, uploadurl, includeusers?}` runs the real backup
  engine (backup_controller, MODE_GENERAL; content-only by default) and PUTs
  the .mbz to a presigned HTTPS URL (mint one with
  `moodle_request_file_upload`). `restore_course {fileurl, categoryid,
  fullname?, shortname?}` downloads a .mbz (SSRF-guarded, 512 MiB cap,
  validated as a Moodle backup BEFORE any course is created) and restores it
  into a NEW course via restore_controller with precheck — a failed precheck
  deletes the shell course. Both raise the PHP time limit to 300s; caps
  backup:backupcourse (+ backup:userinfo with includeusers) and
  course:create + restore:restorecourse respectively.

## 2.20 — 2026-07-15

### Added
- **`issue_badge`: award a badge by webservice.** Core can only READ badges
  over webservices — awarding is form-only. Uses the same core path as the
  manual-award UI (`core_badges\badge::issue`), so events, criteria locking
  and the user's badge-privacy preference all apply. Requires the badge to be
  active and `moodle/badges:awardbadge` in its context; awarding twice is an
  idempotent no-op (`alreadyissued`). Rejects clearly when badges are
  disabled site-wide.

## 2.19 — 2026-07-15

### Added
- **Health tab**: panel connectivity (cached license state + last check),
  MCP keys by status (incl. derived "expired"), last user sync + auto-sync
  services, plugin/Moodle/PHP versions and telemetry state — everything
  support asks for, on one page.
- **Opt-in telemetry**: new Settings toggle (default OFF). When enabled, the
  existing sync task posts a small snapshot (~daily, self-throttled) to the
  panel: versions and key COUNTS only, never personal data. "Send now"
  button on the Health tab.

## 2.18 — 2026-07-15

### Added
- **`create_module` creates working URL resources.** New `externalurl`
  (required for `modname:"url"`, validated http(s)) and optional `display`
  (0 auto, 1 embed, 3 new window, 5 open, 6 popup) parameters; the instance
  is created with its destination in one call. Both parameters reject on any
  other module type.

## 2.17 — 2026-07-15

### Fixed
- **Uninstall now revokes every live MCP key on the panel** (best-effort call
  to the new `/api/moodle/keys/revoke-all` BEFORE wiping local config — an
  unreachable panel never blocks the uninstall) so no credential survives an
  uninstalled site.
- **Uninstall removes the plugin's webservices again.** Since 2.3.1 the
  services are custom (component NULL), so the component-based cleanup found
  nothing and services + tokens survived; they are now collected by shortname
  as well.

## 2.16 — 2026-07-15

### Added
- **`update_module` supports `availability` (access restrictions).** Accepts
  Moodle's availability JSON tree as a string (e.g. completion conditions:
  `{"op":"&","c":[{"type":"completion","cm":123,"e":1}],"showc":[true]}`);
  empty string (or `null` literal) removes all restrictions. The tree is
  validated with `core_availability\tree` BEFORE saving (a corrupt value
  stored raw breaks the course page), rejects clearly when the site has
  `enableavailability` off, and the write goes through `update_moduleinfo`
  — the same path as the settings form, so the course cache rebuild and
  events come for free. Incremental like the rest of the tool.

## 2.15 — 2026-07-15

### Added
- **`update_grade_item` supports `grademax`.** For a quiz module item it goes
  through mod_quiz's grade calculator (updates the quiz "Maximum grade"
  setting, rescales attempt grades and feedback boundaries, refreshes the
  grade item — transactional and idempotent); writing `grade_items.grademax`
  directly would desync and be overwritten on recalculation. Manual/category/
  course items are set directly (they have no backing activity). Other module
  types reject with a clear message. The response includes a `warning` field
  when the resulting `gradepass` sits above the new `grademax` so callers fix
  it with the same tool.

## 2.14 — 2026-07-15

### Added
- **Calendar writes enabled.** The core webservices
  `core_calendar_create_calendar_events` and
  `core_calendar_delete_calendar_events` joined every role whitelist (Moodle
  enforces the per-event capability, so students can still only manage their
  own user events). The upgrade re-syncs the service whitelists; no new
  plugin functions.

## 2.13 — 2026-07-15

### Fixed
- **Moodle 4.x no longer fatals on the question-bank tools.** import_questions,
  list_question_banks, add_random_questions and purge_questions
  (deletecategories) depend on APIs introduced in Moodle 5.0 / 4.5; on older
  releases they crashed with a PHP class-not-found. They now reject with a
  clear message ("requires Moodle 5.0 or later"). Compatibility matrix: all
  other tools work on 4.2+; the question-bank subsystem requires 5.0
  (category deletion 4.5).
- **License validation is cached (10 min).** The License admin tab ran a
  synchronous signed HTTP call to the panel (up to 15s) on EVERY page load —
  a slow or unreachable panel froze the whole tab. It now reuses the cached
  status and offers a "Verify now" button.
- Hardened panel API client: a JSON-encoding failure and HTTP ≥ 400 responses
  without an `error` key are now treated as failures (the latter passed as ok).
- Added the missing Spanish translation for guard error messages
  (`errordetail`) — rejections showed in English on Spanish sites.

## 2.12 — 2026-07-15

### Added
- **`get_grade_categories`** (read-only) — a course's grade categories WITH
  their configuration: aggregation, aggregateonlygraded, item count, and the
  total item's grade type/scale/grade-to-pass. Deployments can now be
  AUDITED instead of re-asserted (no core WS reads category settings).

### Fixed
- **Guard errors are now visible to API callers.** Guard rejections
  (e.g. "grade category 23 not found in course 21") were thrown as
  invalid_parameter_exception, which buries the detail in debuginfo — sent
  only with debugging on, so production callers saw the generic "Invalid
  parameter value detected" (root cause of the "delete_grade_category lies"
  report: the deletes that "failed" were re-deleting already-deleted
  categories, and the explanatory message never reached the caller). All
  guard messages now travel in the exception MESSAGE.

## 2.11 — 2026-07-14

### Added
- **Quiz completion rules in `update_module`**: `completionpassorattemptsexhausted`
  (completed when the student passes OR runs out of attempts — enabling it
  also enables usegrade+passgrade, which Moodle requires for this rule; the
  OR is what softens "pass" into "done") and `completionminattempts`
  (require N attempts; 0 = off). Unrelated completion updates no longer wipe
  a configured min-attempts rule.
- **`update_grade_category`** — rename, aggregation, `aggregateonlygraded`,
  and the category TOTAL's scale (`scaleid` — core's
  create_gradecategories cannot set one: it's not a declared option) and
  grade-to-pass. A min-aggregated category showing an Apto/No Apto scale
  becomes an automatic all-must-pass verdict.
- **`delete_grade_category`** — items and child categories move to the
  parent (standard gradebook semantics); the course-level root is refused.

## 2.10 — 2026-07-14

### Added
- **SCORM status completion rules in `update_module`**: new
  `completionscormstatus` ('passed', 'completed', 'passedorcompleted' or
  'none' to turn the rule off — maps to mod_scorm's completionstatusrequired
  bitmask) and `completionstatusallscos` (require the status in ALL SCOs).
  Enabling a rule switches the module to automatic tracking; turning it off
  doesn't. Intro/other updates preserve the configured rule. The generic
  rules (view/grade/passgrade) can't express "complete by SCORM status".

## 2.9.1 — 2026-07-14

### Fixed
- **`update_module` crashed on QUIZZES** (any full update: completion fields,
  intro…) with `dmlwriteexception: Column 'password' cannot be null`.
  quiz_update_instance needs several fields only the mod_form seeds:
  `quizpassword` (read unconditionally), the review-option checkboxes (absent
  = every review bitmask zeroed) and the overall-feedback arrays
  (quiz_after_add_or_update DELETES all quiz_feedback rows and recreates them
  from the form data — absent = feedback wiped). The WS now seeds all of them
  from the current record, mirroring mod_form's data_preprocessing, with
  embedded feedback files carried through draft areas. Verified: completion
  rules apply, and an intro-only update preserves password, review options
  and overall feedback.

## 2.9 — 2026-07-14

### Added
- **`import_questions` respects `$CATEGORY:`** (standard Moodle behaviour):
  category pseudo-questions in GIFT/XML now create/use their nested path
  ('top/Padre/Hijo', '/' separator, accents and spaces fine, '//' escapes a
  literal slash). Paths are created IN the target context, so each course's
  bank stays isolated.
- **`import_questions` gains `category`**: a path or name inside the target
  bank, created if missing, used as the import's starting category. The
  response's `categoryid` is the resolved category's id.
- **`purge_questions`** — delete questions by explicit ids or by category
  (optionally with subcategories and, with `deletecategories`, the emptied
  categories themselves via core's category_manager). Core semantics kept:
  a question in use by a quiz is HIDDEN, not deleted (reported separately);
  the top/only category of a context is never deleted (reported as skipped).

## 2.8 — 2026-07-14

### Added
- **`create_scale`** — create a grading scale in a course (e.g.
  "No Apto, Apto"; worst value first). Idempotent by (course, name). No core
  WS manages scales. Apply it to an activity by setting its grade to
  MINUS the scale id (mod_assign convention).
- **`update_assign`** — configure an assignment: dates (due/from/cut-off/
  grading), grading by points or SCALE, attempts (`maxattempts` -1 =
  unlimited, `attemptreopenmethod`), submission types (online text / file,
  max files/size), drafts, and the "must submit" completion rule. Handles the
  mod_assign trap where update_instance DISABLES every submission/feedback
  plugin absent from the data: the current state of all plugins is seeded
  before applying changes.
- **`update_grade_item`** — grade to pass, weight (Natural aggregation
  percentage), visibility, and moving the item between grade categories. No
  core WS edits grade items (grade CATEGORIES are covered by core's
  core_grades_create_gradecategories).
- **`set_course_completion`** — replace a course's completion criteria:
  required activities (all tracked ones by default or an explicit cmid list)
  and optionally a passing grade; 'all'/'any' aggregation. Enables course
  completion tracking if off. NOTE (Moodle semantics, same as the form):
  saving clears previous criteria AND users' course-completion progress.
- **`update_module` completion rules** — new `completionview`,
  `completionusegrade`, `completionpassgrade`, `completionexpected`
  parameters.

### Fixed
- **`update_module`'s `completion` parameter was a silent no-op**:
  update_moduleinfo only applies completion fields when the form marks them
  unlocked (`completionunlocked=1`), which the WS never sent. Completion
  changes also now fail loudly when the course has completion tracking
  disabled (they were silently ignored).

## 2.7 — 2026-07-14

### Added
- **`import_questions` now targets any bank**: a quiz (as before), a shared
  question bank module (qbank cmid), or the COURSE's question bank via
  `courseid` — the hidden per-course system bank Moodle 5 creates, made on
  first use. The response now includes the `categoryid` the questions landed
  in, ready for random draws.
- **`add_random_questions`** — adds N random-question slots to a quiz drawing
  from a bank category (`categoryid` + `includesubcategories`), recomputing
  grades. Core's own `mod_quiz_add_random_questions` needs the editing UI's
  JSON filtercondition blob; this wrapper builds it server-side.
- **`list_question_banks`** — enumerates a course's banks (shared qbank
  modules and quiz banks) with their categories, ids and question counts.
  No core WS exposes this, and the category id is exactly what
  import/random need.

## 2.6 — 2026-07-14

### Added
- **`create_quiz` — create a quiz with its main settings configurable**
  (open/close dates, time limit, attempts, grading method, max grade and grade
  to pass, question behaviour, questions per page, shuffle, navigation,
  password). mod_quiz's own webservices are attempt/read only. Built on the
  canonical default map from mod/quiz's generator — quiz_add_instance reads
  most fields unconditionally, so all ~55 must be present.
  `create_module(modname='quiz')` now works too (same defaults registry).
- **`import_questions` — import GIFT, Aiken or Moodle XML questions into a
  quiz** and (by default) add them to it, recomputing the quiz's grades.
  Question import is form-only in core (qbank_importquestions); this WS runs
  the same qformat pipeline into the default category of the quiz's own
  context. These are TEXT formats, so questions travel INLINE in the
  `questions` parameter (an AI can author them directly); `fileurl` (HTTPS)
  is the alternative for large files. The pipeline's echoed notices are
  captured and returned as the error detail on failure — GIFT parse errors
  reach the caller instead of vanishing.

## 2.5 — 2026-07-14

### Added
- **`create_resource` — add any file (PDF, DOCX, image, video…) to a course
  from an HTTPS URL.** New File resource (mod_resource) built with the site's
  mod_resource defaults; optional `filename` override for URLs that hide the
  extension (Drive, signed URLs) — the extension drives Moodle's icon/viewer.
- **Files-by-URL is now the plugin's upload model**, extracted into the shared
  `local_mcpconnector\local\url_file` helper (Moodle-curl download with the
  SSRF guard, 512 MiB cap, optional payload validation, draft staging).
  MCP tool arguments are model-generated JSON and cannot carry binary
  payloads (1 MiB ≈ 350k output tokens), so every file-consuming function
  takes a URL and the MOODLE SERVER downloads it. `create_scorm` now uses the
  helper; future file tools (folders, file replacement) should too.

## 2.4.2 — 2026-07-14

### Fixed
- **`create_scorm` crashed with "Undefined constant SCORM_UPDATE_NEVER".**
  `SCORM_UPDATE_NEVER` and `GRADESCOES` are defined in `mod/scorm/locallib.php`
  — the WS only loaded `lib.php` (where just `SCORM_TYPE_LOCAL` lives). Same
  lib-vs-locallib class of bug as 2.4.1's page fix; the constants are now also
  written `\`-qualified. Verified end-to-end (download → manifest parse →
  SCOs deployed) against a real SCORM 1.2 package.

## 2.4.1 — 2026-07-14

### Fixed
- **2.4's `content` parameter crashed with a PHP Error.** `page_update_instance`
  calls `page_get_editor_options()` from `mod/page/locallib.php`, which the
  form flow loads but `update_moduleinfo` does not — the WS now requires it.
  Also, the draft area is now created with an EMPTY draft id so
  `file_prepare_draft_area` actually copies the page's existing embedded files
  into it (a pre-created id skips the copy, losing them).

## 2.4 — 2026-07-14

### Added
- **`update_module` can edit a page's content.** New optional `content`
  parameter (HTML) replaces a `mod_page` body through the supported
  `update_moduleinfo` path — the WS seeds the `page` editor array and the
  display fields the mod_form preprocessing would normally provide, and
  carries existing embedded files into the draft so surviving
  `@@PLUGINFILE@@` references keep resolving. Other content modules can be
  added the same way; a label's body is its `intro` (already editable).
- Module DELETION needs no plugin function: core's
  `core_course_edit_module(action='delete')` works and is whitelisted — the
  panel's tool catalog now advertises it properly.

## 2.3.1 — 2026-07-14

### Fixed
- **2.3 regression: installing/upgrading globally invalidated every MCP
  token.** Adding `db/services.php` made Moodle's post-upgrade sync treat the
  plugin's component-owned external services as file-managed — services not
  declared in the file are deleted TOGETHER WITH ALL THEIR TOKENS
  (`lib/upgradelib.php`, `external_update_descriptions`). The six role
  services are now created as CUSTOM services (component null, ignored by the
  sync), the upgrade detaches/recreates them, registers the new external
  functions early, and re-syncs the whitelists (which the 2.3 upgrade also
  missed, since it ran before Moodle registered the functions).
- **After upgrading, existing tokens cannot be restored** (Moodle deleted the
  rows): re-issue user keys from the plugin's Keys page, create a fresh token
  for the chat key's dedicated account and update it in the panel, and OAuth
  clients simply sign in with Moodle again (grants re-mint automatically).

## 2.3 — 2026-07-14

### Added
- **Three webservice functions filling core gaps** (verified against Moodle
  5.2: no core WS edits an activity's intro, the core quick-create functions
  only support `mod_subsection`, and nothing creates a SCORM or uploads its
  package). All gated by `moodle/course:manageactivities` and registered in
  the admin/manager/editingteacher role services (re-synced on upgrade):
  - `local_mcpconnector_update_module` — name, intro/introformat, visibility,
    completion, idnumber. Uses the supported `get_moduleinfo_data` +
    `update_moduleinfo` pair (files/caches/completion behave like a form save)
    with fast-path `set_coursemodule_name/visible` when intro isn't touched.
  - `local_mcpconnector_create_module` — creates any activity (forum, assign…)
    in a section by number via `add_moduleinfo()` with the type's defaults.
  - `local_mcpconnector_create_scorm` — downloads the package from an HTTPS
    URL (Moodle curl + curl_security_helper, 512 MiB cap, imsmanifest.xml
    check), stages it as a draft file and creates the instance with the site's
    mod_scorm defaults (`scorm_parse` validates/deploys the manifest).
- Note: no PHPUnit coverage for the new external functions yet (the plugin has
  no precedent for WS tests) — validated by php -l + live round-trip.

## 2.2 — 2026-07-13

### Changed
- **Key email covers both connection methods.** The auto-key email now explains
  the two ways to connect — "sign in with your Moodle" (OAuth, for Claude
  Desktop/ChatGPT, no key needed) and the `Bearer` key (for token clients) — and
  links to the connection guide. New `{$a->docsurl}` placeholder (derived from
  the panel URL) and `{$a->mcpurl}`; the default subject/body were updated.

## 2.1 — 2026-07-13

### Added
- **Sign in with your Moodle (OAuth SSO).** New `authorize.php` browser endpoint
  federates the MCP OAuth flow: `require_login()` (native or SSO), a panel-signed
  `state` verified on return, role→service eligibility, token mint, and signed
  HMAC delivery of the token to the panel. `local_mcpconnector_verify_oauth_state`
  and `local_mcpconnector_oauth_deliver_token` added to `lib.php`. Lets clients
  that can't send a custom header (Claude Desktop, ChatGPT) connect.

## 2.0 — 2026-07-10

Rebuilt against the MoodleMCP panel v2 API and renamed from `local_moodlemcp`
to `local_mcpconnector`.

### Changed
- **Signed panel API.** Every request is HMAC-SHA256 signed with a per-install
  secret (±5-minute replay window) and versioned (`x-panel-version: 2`),
  replacing the previous license-key-in-body authentication.
- Panel URL, signing secret and MCP endpoint URL are now configurable on the
  License page (the panel URL was previously hard-coded).
- Key metadata is mirrored in a local table (`local_mcpconnector_keys`) — only
  the key's last 4 characters, roles and status, never values or tokens. The
  Keys page reads from it and reconciles with the panel on demand.

### Security
- MCP key values exist only in the panel's create response: they are emailed
  once and never stored. "Resend" is now "regenerate + email".
- Panel request/response payloads (which carry tokens) are no longer logged.

### Requirements
- Moodle 4.2–5.1. Requires a MoodleMCP panel account (commercial service).
