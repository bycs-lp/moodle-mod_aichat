<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * View an ai_chat instance.
 *
 * @package     mod_aichat
 * @copyright   2025 ISB Bayern
 * @author      Philipp Memmel
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once('lib.php');

$id = required_param('id', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'aichat');

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/aichat:view', $context);

$aichat = $DB->get_record('aichat', ['id' => $cm->instance], '*', MUST_EXIST);

$PAGE->set_url(new moodle_url('/mod/aichat/view.php', ['id' => $id]));
$PAGE->set_title(get_string('pluginname', 'mod_aichat') . ' ' . $aichat->name);
$PAGE->set_heading($aichat->name);
$PAGE->add_body_class('limitedwidth');

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

echo $OUTPUT->header();

$groupselector = '';
$groupid = 0;

$groupmode = groups_get_activity_groupmode($cm, $course);

if (!empty($groupmode)) {
    $groupid = groups_get_activity_group($cm, true);
}

echo html_writer::tag('div', '', ['data-mod_aichat-element' => 'embeddingmodalcontainer']);
$PAGE->requires->js_call_amd('mod_aichat/embedded_modal', 'init', [
    'contextid' => $context->id,
    'cmid' => $cm->id,
]);


echo $OUTPUT->footer();
