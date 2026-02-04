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

/**
 * Privacy provider for mod_aichat
 *
 * @package    mod_aichat
 * @copyright  2025 ISB Bayern
 * @author     Philipp Memmel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aichat\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_ai_manager\local\data_wiper;

/**
 * Privacy provider for mod_aichat.
 *
 * IMPORTANT: This is an unusual privacy provider implementation.
 * mod_aichat does not have its own database tables for user data.
 * Instead, it stores data in tables owned by other plugins:
 * - block_ai_chat_personas_selected: Persona selection per context
 * - local_ai_manager_request_log: AI conversation logs (with component='mod_aichat')
 *
 * This means we must declare these foreign tables and handle their data
 * even though they are technically managed by other plugins.
 *
 * @package    mod_aichat
 * @copyright  2025 ISB Bayern
 * @author     Philipp Memmel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\shared_userlist_provider {
    /**
     * Returns metadata about this plugin's data storage.
     *
     * Note: mod_aichat stores conversation data in tables managed by other plugins:
     * - block_ai_chat_personas_selected: Persona selection per context
     * - local_ai_manager_request_log: AI conversation logs (with component='mod_aichat')
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    #[\Override]
    public static function get_metadata(collection $collection): collection {
        // Declare that mod_aichat uses data structures from block_ai_chat.
        $collection->add_database_table(
            'block_ai_chat_personas_selected',
            [
                'contextid' => 'privacy:metadata:block_ai_chat_personas_selected:contextid',
                'personasid' => 'privacy:metadata:block_ai_chat_personas_selected:personasid',
            ],
            'privacy:metadata:block_ai_chat_personas_selected'
        );

        // Declare that mod_aichat stores conversation data in local_ai_manager_request_log.
        // Note: This table is managed by local_ai_manager, but we store our data there with component='mod_aichat'.
        $collection->add_database_table(
            'local_ai_manager_request_log',
            [
                'userid' => 'privacy:metadata:local_ai_manager_request_log:userid',
                'contextid' => 'privacy:metadata:local_ai_manager_request_log:contextid',
                'component' => 'privacy:metadata:local_ai_manager_request_log:component',
                'prompttext' => 'privacy:metadata:local_ai_manager_request_log:prompttext',
                'promptcompletion' => 'privacy:metadata:local_ai_manager_request_log:promptcompletion',
                'timecreated' => 'privacy:metadata:local_ai_manager_request_log:timecreated',
            ],
            'privacy:metadata:local_ai_manager_request_log'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used in this plugin.
     */
    #[\Override]
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        // Get contexts where the user has AI chat conversations stored in local_ai_manager.
        $sql = "SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'aichat'
                  JOIN {local_ai_manager_request_log} log ON log.contextid = ctx.id
                 WHERE log.userid = :userid
                   AND log.component = :component";

        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'userid' => $userid,
            'component' => 'mod_aichat',
        ]);

        return $contextlist;
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    #[\Override]
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if ($contextlist->count() === 0) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!is_a($context, \context_module::class)) {
                continue;
            }

            // Export persona selection (if any).
            $personaselection = $DB->get_record('block_ai_chat_personas_selected', ['contextid' => $context->id]);
            if ($personaselection) {
                $persona = $DB->get_record('block_ai_chat_personas', ['id' => $personaselection->personasid]);
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'mod_aichat'), get_string('privacy:path:personas', 'mod_aichat')],
                    (object) [
                        'persona_name' => $persona ? $persona->name : '',
                    ]
                );
            }

            // Export conversation data from local_ai_manager.
            $conversations = $DB->get_records('local_ai_manager_request_log', [
                'userid' => $userid,
                'contextid' => $context->id,
                'component' => 'mod_aichat',
            ]);

            if (!empty($conversations)) {
                $exportdata = [];
                foreach ($conversations as $conversation) {
                    $exportdata[] = [
                        'prompttext' => $conversation->prompttext,
                        'promptcompletion' => $conversation->promptcompletion,
                        'timecreated' => \core_privacy\local\request\transform::datetime($conversation->timecreated),
                    ];
                }

                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'mod_aichat'), get_string('privacy:path:conversations', 'mod_aichat')],
                    (object) ['conversations' => $exportdata]
                );
            }
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    #[\Override]
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        if ($contextlist->count() === 0) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        $datawiper = new data_wiper();

        foreach ($contextlist->get_contexts() as $context) {
            if (!is_a($context, \context_module::class)) {
                continue;
            }

            // Get all request log records for this user in this context and anonymize them.
            // We only anonymize request logs, but do not delete them. This process removes all user associated data from the
            // request log. We cannot delete the data completely, because log data and statistics aggregated from the logs
            // would be lost.
            $records = $DB->get_records('local_ai_manager_request_log', [
                'userid' => $userid,
                'contextid' => $context->id,
                'component' => 'mod_aichat',
            ]);

            foreach ($records as $record) {
                $datawiper->anonymize_request_log_record($record);
            }
        }

        // Handle personas created by this user.
        // Get all personas created by this user.
        $userpersonas = $DB->get_records('block_ai_chat_personas', ['userid' => $userid]);

        foreach ($userpersonas as $persona) {
            // Delete all selections of this persona first.
            $DB->delete_records('block_ai_chat_personas_selected', ['personasid' => $persona->id]);
            // Then delete the persona itself.
            $DB->delete_records('block_ai_chat_personas', ['id' => $persona->id]);
        }
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    #[\Override]
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if (!is_a($context, \context_module::class)) {
            return;
        }

        // Get all users who have conversation data in this context.
        $sql = "SELECT log.userid AS userid
                  FROM {local_ai_manager_request_log} log
                 WHERE log.contextid = :contextid
                   AND log.component = :component
                   AND log.userid IS NOT NULL";

        $userlist->add_from_sql('userid', $sql, [
            'contextid' => $context->id,
            'component' => 'mod_aichat',
        ]);
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    #[\Override]
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();

        if ($userlist->count() === 0) {
            return;
        }

        if (!is_a($context, \context_module::class)) {
            return;
        }

        $datawiper = new data_wiper();

        // Get all request log records for the users in this context and anonymize them.
        [$insql, $inparams] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params = array_merge($inparams, ['contextid' => $context->id, 'component' => 'mod_aichat']);
        $records = $DB->get_records_select(
            'local_ai_manager_request_log',
            "userid $insql AND contextid = :contextid AND component = :component",
            $params
        );

        foreach ($records as $record) {
            $datawiper->anonymize_request_log_record($record);
        }

        // Handle personas created by these users.
        foreach ($userlist->get_userids() as $userid) {
            $userpersonas = $DB->get_records('block_ai_chat_personas', ['userid' => $userid]);

            foreach ($userpersonas as $persona) {
                // Delete all selections of this persona first.
                $DB->delete_records('block_ai_chat_personas_selected', ['personasid' => $persona->id]);
                // Then delete the persona itself.
                $DB->delete_records('block_ai_chat_personas', ['id' => $persona->id]);
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     */
    #[\Override]
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if (!is_a($context, \context_module::class)) {
            return;
        }

        $datawiper = new data_wiper();

        // Get all request log records in this context and anonymize them.
        $records = $DB->get_records('local_ai_manager_request_log', [
            'contextid' => $context->id,
            'component' => 'mod_aichat',
        ]);

        foreach ($records as $record) {
            $datawiper->anonymize_request_log_record($record);
        }

        // Get all persona selections in this context and delete the personas and selections.
        $selections = $DB->get_records('block_ai_chat_personas_selected', ['contextid' => $context->id]);

        foreach ($selections as $selection) {
            // Delete the persona.
            $DB->delete_records('block_ai_chat_personas', ['id' => $selection->personasid]);
            // Delete the selection.
            $DB->delete_records('block_ai_chat_personas_selected', ['id' => $selection->id]);
        }
    }

    /**
     * Get shared contexts for a user.
     *
     * @param int $userid The user ID
     * @return contextlist The list of shared contexts
     */
    public static function get_shared_contexts_for_userid(int $userid): contextlist {
        // The plugin mod_aichat does not share data with other users.
        return new contextlist();
    }
}
