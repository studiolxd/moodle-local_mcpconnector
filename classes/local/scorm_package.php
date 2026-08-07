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
 * SCORM .zip package handling shared by create_scorm and update_scorm.
 *
 * Extracted so both entry points validate and stage the package identically
 * (a create/update drift here would mean one of them silently accepts a
 * non-SCORM zip the other rejects).
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class scorm_package {
    /**
     * Downloads packageurl into a draft file area, rejecting anything that
     * isn't a zip containing an imsmanifest.xml. Size cap, SSRF guard and
     * draft staging live in url_file — this only adds the SCORM-specific
     * shape check.
     *
     * @param string $packageurl HTTPS URL of the .zip package.
     * @return int Draft item id ready to hand to add_moduleinfo/update_moduleinfo
     *             as the 'packagefile' field.
     */
    public static function fetch_to_draft(string $packageurl): int {
        return url_file::fetch_to_draft(
            $packageurl,
            self::filename($packageurl),
            'package.zip',
            function (string $tmpfile): void {
                $zip = new \ZipArchive();
                $isscorm = $zip->open($tmpfile) === true
                    && ($zip->locateName('imsmanifest.xml', \ZipArchive::FL_NODIR) !== false);
                $zip->close();
                if (!$isscorm) {
                    reject::because('the file is not a SCORM package (no imsmanifest.xml in the zip)');
                }
            }
        );
    }

    /**
     * Filename for the staged package, derived from the URL (fallback safe name).
     *
     * @param string $url
     * @return string
     */
    public static function filename(string $url): string {
        $name = basename(parse_url($url, PHP_URL_PATH) ?? '');
        $name = clean_param($name, PARAM_FILE);
        if ($name === '' || strtolower(substr($name, -4)) !== '.zip') {
            $name = 'package.zip';
        }
        return $name;
    }
}
