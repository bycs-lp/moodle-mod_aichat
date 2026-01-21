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
 * Library for mod_aichat
 *
 * @package     mod_aichat
 * @copyright   2025 ISB Bayern
 * @author      Philipp Memmel
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adds a new aichat instance
 *
 * @param stdClass $data aichat record
 * @return int the aichat instance id
 */
function aichat_add_instance($data): int {
    global $DB;
    return $DB->insert_record('aichat', $data);
}

/**
 * Updates an aichat instance
 *
 * @param stdClass $data aichat record
 * @return int the aichat instance id
 */
function aichat_update_instance($data): int {
    global $DB;
    $data->id = $data->instance;
    return $DB->update_record('aichat', $data);
}

/**
 * Deletes an aichat instance.
 *
 * @param integer $id aichat record id
 * @return bool true on success
 */
function aichat_delete_instance($id): bool {
    global $DB;

    $cm = get_coursemodule_from_instance('aichat', $id);
    $context = \context_module::instance($cm->id);
    $DB->delete_records('block_ai_chat_personas_selected', ['contextid' => $context->id]);
    $DB->delete_records('block_ai_chat_options', ['contextid' => $context->id]);
    return $DB->delete_records('aichat', ['id' => $id]);
}

/**
 * Returns whether a feature is supported by this module.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know or string for the module purpose.
 * @uses FEATURE_GROUPINGS
 * @uses FEATURE_MOD_INTRO
 * @uses FEATURE_COMPLETION_TRACKS_VIEWS
 * @uses FEATURE_GRADE_HAS_GRADE
 * @uses FEATURE_GRADE_OUTCOMES
 * @uses FEATURE_IDNUMBER
 * @uses FEATURE_GROUPS
 */
function aichat_supports($feature) {
    switch ($feature) {
        case FEATURE_IDNUMBER:
            return true;
        case FEATURE_GROUPS:
            return false;
        case FEATURE_GROUPINGS:
            return false;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_INTERACTIVECONTENT;
        default:
            return null;
    }
}

/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the aichat activity.
 *
 * @param object $mform form passed by reference
 */
// TODO Implement
/*function aichat_reset_course_form_definition(&$mform): void {
    $mform->addElement('header', 'aichatactivityheader', get_string('modulenameplural', 'mod_aichat'));
    $mform->addElement('advcheckbox', 'reset_aichat', get_string('reset_aichat', 'mod_aichat'));
}*/

/**
 * Course reset form defaults.
 *
 * @param stdClass $course the course object
 * @return array
 */
// TODO Implement
/*function aichat_reset_course_form_defaults(stdClass $course): array {
    return [
        'reset_aichat' => 1,
    ];
}*/

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 *
 * @param object $data the data submitted from the reset course.
 * @return array status array
 */
// TODO Needs to implement a new function in \local_ai_manager\local\data_wiper to anonymize log records for a specific context.
/*function aichat_reset_userdata($data) {
    $status[] = [
        'component' => get_string('modulenameplural', 'mod_aichat'),
        'item' => get_string('reset_personal', 'mod_aichat'),
        'error' => false,
    ];
    return $status;
}*/

/**
 * Add custom completion.
 *
 * @param stdClass $cm coursemodule record.
 * @return cached_cm_info
 */
function aichat_get_coursemodule_info(stdClass $cm): cached_cm_info {
    global $DB;

    $aichat = $DB->get_record('aichat', ['id' => $cm->instance]);

    $result = new cached_cm_info();
    if ($aichat) {
        $result->name = $aichat->name;

        if ($cm->showdescription) {
            $result->content = format_module_intro('aichat', $aichat, $cm->id, false);
        }

        if ($cm->completion == COMPLETION_TRACKING_AUTOMATIC) {
            $result->customdata['customcompletionrules']['completioncreate'] = $aichat->completioncreate;
            $result->customdata['customcompletionrules']['completioncomplete'] = $aichat->completioncomplete;
        }
    }
    return $result;
}
