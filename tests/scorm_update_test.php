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

namespace local_mcpconnector;

use local_mcpconnector\external\create_module;
use local_mcpconnector\external\create_scorm;
use local_mcpconnector\external\update_scorm;

/**
 * SCORM settings + package replacement (2.23).
 *
 * create_scorm/update_scorm's HTTP download step (scorm_package::fetch_to_draft)
 * is exercised in production (real uploads already succeed) and isn't re-tested
 * here — SSRF protection (curl_security_helper) blocks fetching from localhost
 * by design, so these tests stage the draft file directly with the SAME shape
 * fetch_to_draft() produces (component=user/filearea=draft) and drive the SAME
 * create_module::add()/get_moduleinfo_data()+update_moduleinfo() core calls the
 * real classes use, to verify Moodle's actual behaviour: settings land in the
 * DB, and a package replace preserves cmid/instance and, for SCOs whose
 * manifest identifier is unchanged, their scoid (so student attempts stay
 * attached) — see mod/scorm/datamodels/scormlib.php's own comment: "keep id
 * so that user tracks are kept against the same ids".
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mcpconnector\external\create_scorm
 * @covers     \local_mcpconnector\external\update_scorm
 */
final class scorm_update_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        global $CFG;
        // Same requires create_scorm.php/update_scorm.php make: add_moduleinfo/
        // get_moduleinfo_data/update_moduleinfo (course/modlib.php + lib.php),
        // and the SCORM constants (SCORM_TYPE_LOCAL, SCORM_UPDATE_NEVER,
        // GRADESCOES, GRADEHIGHEST) from mod/scorm/(lib|locallib).php.
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/mod/scorm/lib.php');
        require_once($CFG->dirroot . '/mod/scorm/locallib.php');
        require_once($CFG->libdir . '/filelib.php');
    }

    /**
     * Minimal valid SCORM 1.2 package with one SCO, zipped to a temp file.
     * Caller does not need to unlink — make_request_directory() is auto-purged.
     *
     * @param string $title SCO title (cosmetic only).
     * @param string $itemidentifier The <item identifier="..."> — this is what
     *     scorm_parse_scorm() matches existing scorm_scoes rows against.
     * @return string Path to the .zip.
     */
    private function make_scorm_zip(string $title, string $itemidentifier): string {
        $dir = make_request_directory();
        $manifest = <<<XML
<?xml version="1.0" standalone="no" ?>
<manifest identifier="MANIFEST-1" version="1.1"
    xmlns="http://www.imsproject.org/xsd/imscp_rootv1p1p2"
    xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_rootv1p2">
  <metadata>
    <schema>ADL SCORM</schema>
    <schemaversion>1.2</schemaversion>
  </metadata>
  <organizations default="ORG-1">
    <organization identifier="ORG-1">
      <title>Test course</title>
      <item identifier="{$itemidentifier}" identifierref="RES-1">
        <title>{$title}</title>
      </item>
    </organization>
  </organizations>
  <resources>
    <resource identifier="RES-1" type="webcontent" adlcp:scormtype="sco" href="index.html">
      <file href="index.html"/>
    </resource>
  </resources>
</manifest>
XML;
        file_put_contents($dir . '/imsmanifest.xml', $manifest);
        file_put_contents($dir . '/index.html', '<html><body>' . $title . '</body></html>');

        $zippath = $dir . '/package.zip';
        $zip = new \ZipArchive();
        $zip->open($zippath, \ZipArchive::CREATE);
        $zip->addFile($dir . '/imsmanifest.xml', 'imsmanifest.xml');
        $zip->addFile($dir . '/index.html', 'index.html');
        $zip->close();

        return $zippath;
    }

    /**
     * Stages a local zip as a user draft item — the exact shape
     * scorm_package::fetch_to_draft()/url_file::fetch_to_draft() produce after
     * a real HTTPS download, minus the HTTP hop.
     *
     * @param string $zippath
     * @return int Draft item id.
     */
    private function stage_draft(string $zippath): int {
        global $USER;
        $usercontext = \context_user::instance($USER->id);
        $draftitemid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_pathname([
            'contextid' => $usercontext->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => 'package.zip',
        ], $zippath);
        return $draftitemid;
    }

    public function test_create_scorm_grademethod_defaults_to_gradescoes_when_omitted(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        $draftid = $this->stage_draft($this->make_scorm_zip('Lesson 1', 'ITEM-1'));
        $created = create_module::add($course, 'scorm', 0, 'SCORM sin grademethod', '', 1,
            ['scormtype' => \SCORM_TYPE_LOCAL, 'packagefile' => $draftid, 'packageurl' => '',
                'updatefreq' => \SCORM_UPDATE_NEVER]
                + create_scorm::settings_from_params([], get_config('scorm')));

        $scorm = $DB->get_record('scorm', ['id' => $created->instance], '*', MUST_EXIST);
        $this->assertSame((int) \GRADESCOES, (int) $scorm->grademethod);
    }

    public function test_create_scorm_grademethod_is_overridable(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        $draftid = $this->stage_draft($this->make_scorm_zip('Lesson 1', 'ITEM-1'));
        $created = create_module::add($course, 'scorm', 0, 'SCORM con grademethod', '', 1,
            ['scormtype' => \SCORM_TYPE_LOCAL, 'packagefile' => $draftid, 'packageurl' => '',
                'updatefreq' => \SCORM_UPDATE_NEVER]
                + create_scorm::settings_from_params(['grademethod' => \GRADEHIGHEST, 'maxattempt' => 3], get_config('scorm')));

        $scorm = $DB->get_record('scorm', ['id' => $created->instance], '*', MUST_EXIST);
        $this->assertSame((int) \GRADEHIGHEST, (int) $scorm->grademethod);
        $this->assertSame(3, (int) $scorm->maxattempt);
    }

    public function test_validate_settings_rejects_out_of_range_enums(): void {
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/grademethod must be one of/');
        create_scorm::validate_settings(['grademethod' => 99] + array_fill_keys(
            array_keys(create_scorm::settings_parameters()), null
        ));
    }

    public function test_update_scorm_replaces_package_preserves_cmid_and_bumps_revision(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        $draftid = $this->stage_draft($this->make_scorm_zip('Lesson 1', 'ITEM-1'));
        $created = create_module::add($course, 'scorm', 0, 'Unidad 1', '', 1,
            ['scormtype' => \SCORM_TYPE_LOCAL, 'packagefile' => $draftid, 'packageurl' => '',
                'updatefreq' => \SCORM_UPDATE_NEVER]
                + create_scorm::settings_from_params([], get_config('scorm')));
        $cmid = (int) $created->coursemodule;
        $instanceid = (int) $created->instance;

        $before = $DB->get_record('scorm', ['id' => $instanceid], '*', MUST_EXIST);
        $sco = $DB->get_record('scorm_scoes', ['scorm' => $instanceid, 'identifier' => 'ITEM-1'], '*', MUST_EXIST);

        // Same SCO identifier, corrected content + a settings change — this is
        // the IvanLabs scenario: fix a bug in index.html, keep re-uploading.
        $cm = get_coursemodule_from_id('scorm', $cmid, 0, false, MUST_EXIST);
        $modcontext = \context_module::instance($cm->id);
        global $PAGE;
        $PAGE->set_context($modcontext);
        [$cm, , , $data] = get_moduleinfo_data($cm, $course);
        $data->packagefile = $this->stage_draft($this->make_scorm_zip('Lesson 1 (corregida)', 'ITEM-1'));
        $data->grademethod = \GRADEHIGHEST;
        update_moduleinfo($cm, $data, $course, null);

        $after = $DB->get_record('scorm', ['id' => $instanceid], '*', MUST_EXIST);
        $this->assertSame($cmid, (int) $cm->id, 'cmid must not change on package replace');
        $this->assertSame($instanceid, (int) $cm->instance, 'instance must not change on package replace');
        $this->assertGreaterThan((int) $before->revision, (int) $after->revision,
            'revision must bump when the manifest is re-parsed');
        $this->assertNotSame($before->sha1hash, $after->sha1hash, 'sha1hash must change with new content');
        $this->assertSame((int) \GRADEHIGHEST, (int) $after->grademethod, 'settings passed in the same update must apply');

        $scoafter = $DB->get_record('scorm_scoes', ['scorm' => $instanceid, 'identifier' => 'ITEM-1'], '*', MUST_EXIST);
        $this->assertSame((int) $sco->id, (int) $scoafter->id,
            'a SCO whose manifest identifier is unchanged must keep its id — this is what keeps '
            . 'scorm_scoes_track (student attempts) attached after replacing the package');
    }

    public function test_update_scorm_rejects_when_nothing_to_update(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $draftid = $this->stage_draft($this->make_scorm_zip('Lesson 1', 'ITEM-1'));
        $created = create_module::add($course, 'scorm', 0, 'Unidad 1', '', 1,
            ['scormtype' => \SCORM_TYPE_LOCAL, 'packagefile' => $draftid, 'packageurl' => '',
                'updatefreq' => \SCORM_UPDATE_NEVER]
                + create_scorm::settings_from_params([], get_config('scorm')));

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/nothing to update/');
        update_scorm::execute((int) $created->coursemodule);
    }

    public function test_update_scorm_requires_capability(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $draftid = $this->stage_draft($this->make_scorm_zip('Lesson 1', 'ITEM-1'));
        $created = create_module::add($course, 'scorm', 0, 'Unidad 1', '', 1,
            ['scormtype' => \SCORM_TYPE_LOCAL, 'packagefile' => $draftid, 'packageurl' => '',
                'updatefreq' => \SCORM_UPDATE_NEVER]
                + create_scorm::settings_from_params([], get_config('scorm')));
        $cmid = (int) $created->coursemodule;

        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        update_scorm::execute($cmid, null, \GRADEHIGHEST);
    }
}
