<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_mcpconnector\local;

/**
 * The plugin's file-upload model: files reach Moodle by URL, never through
 * the MCP channel.
 *
 * MCP tool arguments are JSON generated token by token by a language model —
 * a binary file would have to travel base64-encoded inside them (1 MiB ≈
 * 350k output tokens), which no model can emit reliably. So every
 * file-consuming webservice in this plugin takes an HTTPS URL instead and
 * lets the MOODLE SERVER download it: this helper fetches the URL with
 * Moodle's curl wrapper (curl_security_helper blocks internal hosts — SSRF),
 * enforces a size cap, optionally validates the payload, and stages it as a
 * user draft file ready for *_add_instance via add_moduleinfo.
 *
 * To add a new file-consuming tool (folder, file replacement…): take a
 * `fileurl` parameter, call fetch_to_draft(), and pass the returned draft
 * item id in the field the module expects (resource: `files`,
 * scorm: `packagefile`).
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class url_file {
    /** Maximum accepted download size in bytes (512 MiB). */
    public const MAX_BYTES = 536870912;

    /**
     * Downloads an HTTPS URL into a user draft area and returns the draft item id.
     *
     * @param string $url HTTPS URL of the file.
     * @param string|null $forcedfilename Filename to store (extension drives
     *     Moodle's icon/viewer); null derives it from the URL path.
     * @param string $fallbackfilename Used when the URL has no usable filename.
     * @param callable|null $validate Called with the temp file path after the
     *     download; throw from it to reject the payload.
     * @return int Draft item id holding the single staged file.
     */
    public static function fetch_to_draft(
        string $url,
        ?string $forcedfilename = null,
        string $fallbackfilename = 'file.bin',
        ?callable $validate = null
    ): int {
        global $USER;

        $tmpfile = self::fetch_to_temp($url, $validate);
        try {
            $usercontext = \context_user::instance($USER->id);
            $draftitemid = file_get_unused_draft_itemid();
            get_file_storage()->create_file_from_pathname([
                'contextid' => $usercontext->id,
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => $draftitemid,
                'filepath' => '/',
                'filename' => self::filename($url, $forcedfilename, $fallbackfilename),
            ], $tmpfile);
        } finally {
            @unlink($tmpfile);
        }

        return $draftitemid;
    }

    /**
     * Downloads an HTTPS URL to a temp file (guarded, capped, validated) and
     * returns its path. The CALLER must unlink it when done.
     *
     * @param string $url HTTPS URL of the file.
     * @param callable|null $validate Called with the temp file path after the
     *     download; throw from it to reject the payload.
     * @return string Absolute path of the temp file.
     */
    public static function fetch_to_temp(string $url, ?callable $validate = null): string {
        global $CFG;

        require_once($CFG->libdir . '/filelib.php');

        if (stripos($url, 'https://') !== 0) {
            throw new \invalid_parameter_exception('fileurl must be an https:// URL');
        }

        // Download to a temp file (never into memory — files can be large).
        // Moodle's curl wrapper applies curl_security_helper by default, which
        // blocks internal hosts/ports (SSRF) — do NOT bypass it.
        $tmpfile = tempnam(make_temp_directory('mcpconnector'), 'urlfile');
        try {
            $response = download_file_content($url, null, null, true, 300, 20, false, $tmpfile);
            if (empty($response) || (int) $response->status !== 200) {
                $detail = isset($response->status) ? "HTTP {$response->status}" : ($response->error ?? 'download failed');
                throw new \moodle_exception('errorwhiledownload', 'error', '', $detail);
            }

            $size = (int) filesize($tmpfile);
            if ($size <= 0) {
                throw new \moodle_exception('errorwhiledownload', 'error', '', 'empty file');
            }
            if ($size > self::MAX_BYTES) {
                throw new \invalid_parameter_exception('file exceeds the 512 MiB limit');
            }

            if ($validate !== null) {
                $validate($tmpfile);
            }
        } catch (\Throwable $e) {
            @unlink($tmpfile);
            throw $e;
        }

        return $tmpfile;
    }

    /**
     * Uploads a local file to an HTTPS URL with a plain PUT (the transfer
     * bucket's presigned URLs). Same guarded Moodle curl as the downloads
     * (curl_security_helper applies). The Content-Type must match what the
     * panel signed into the slot — application/octet-stream, its default.
     *
     * @param string $url Presigned HTTPS PUT URL.
     * @param string $path Local file to send.
     */
    public static function put_from_path(string $url, string $path): void {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        if (stripos($url, 'https://') !== 0) {
            throw new \invalid_parameter_exception('uploadurl must be an https:// URL');
        }

        $curl = new \curl(['timeout' => 300]);
        $curl->setHeader(['Content-Type: application/octet-stream']);
        $response = $curl->put($url, ['file' => $path]);
        $info = $curl->get_info();
        $status = (int) ($info['http_code'] ?? 0);
        if ($curl->get_errno() || $status < 200 || $status >= 300) {
            $detail = $curl->error ?: "HTTP {$status}" . ($response ? ' ' . substr((string) $response, 0, 200) : '');
            throw new \moodle_exception('errorwhiledownload', 'error', '', 'upload failed: ' . $detail);
        }
    }

    /**
     * Resolves the stored filename: forced name, else the URL path's basename,
     * else the fallback. Cleaned with PARAM_FILE either way.
     *
     * @param string $url
     * @param string|null $forced
     * @param string $fallback
     * @return string
     */
    private static function filename(string $url, ?string $forced, string $fallback): string {
        $name = $forced ?? basename(parse_url($url, PHP_URL_PATH) ?? '');
        $name = clean_param($name, PARAM_FILE);
        return $name === '' ? $fallback : $name;
    }
}
