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

namespace mod_aichat\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use local_ai_manager\local\data_wiper;

/**
 * Privacy provider tests for mod_aichat.
 *
 * @package    mod_aichat
 * @copyright  2025 ISB Bayern
 * @author     Philipp Memmel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_aichat\privacy\provider
 */
final class provider_test extends provider_testcase {
    /** @var \local_ai_manager_generator The local_ai_manager data generator. */
    private \local_ai_manager_generator $aimanagergenerator;

    /**
     * Set up the test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->aimanagergenerator = $this->getDataGenerator()->get_plugin_generator('local_ai_manager');
    }

    /**
     * Test getting metadata.
     */
    public function test_get_metadata(): void {
        $collection = new \core_privacy\local\metadata\collection('mod_aichat');
        $newcollection = provider::get_metadata($collection);
        $this->assertNotEmpty($newcollection);

        $items = $newcollection->get_collection();
        $this->assertNotEmpty($items);

        // Check that we declare usage of block_ai_chat_personas_selected table.
        $found = false;
        foreach ($items as $item) {
            if ($item instanceof \core_privacy\local\metadata\types\database_table) {
                if ($item->get_name() === 'block_ai_chat_personas_selected') {
                    $found = true;
                    break;
                }
            }
        }
        $this->assertTrue($found, 'Should declare usage of block_ai_chat_personas_selected table');

        // Check that we declare usage of local_ai_manager_request_log table.
        $found = false;
        foreach ($items as $item) {
            if ($item instanceof \core_privacy\local\metadata\types\database_table) {
                if ($item->get_name() === 'local_ai_manager_request_log') {
                    $found = true;
                    break;
                }
            }
        }
        $this->assertTrue($found, 'Should declare usage of local_ai_manager_request_log table');
    }

    /**
     * Test getting contexts for a user.
     */
    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        // Create AI chat activity.
        $aichat = $this->getDataGenerator()->create_module('aichat', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('aichat', $aichat->id);
        $context = \context_module::instance($cm->id);

        // Create conversation data for user1 using the generator.
        $this->aimanagergenerator->create_request_log_entry([
            'userid' => $user1->id,
            'contextid' => $context->id,
            'component' => 'mod_aichat',
        ]);

        // Get contexts for user1.
        $contextlist = provider::get_contexts_for_userid($user1->id);
        $this->assertCount(1, $contextlist);
        $this->assertEquals($context->id, $contextlist->current()->id);

        // Get contexts for user2 (should be empty).
        $contextlist = provider::get_contexts_for_userid($user2->id);
        $this->assertCount(0, $contextlist);
    }

    /**
     * Test exporting user data.
     */
    public function test_export_user_data(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        // Create AI chat activity.
        $aichat = $this->getDataGenerator()->create_module('aichat', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('aichat', $aichat->id);
        $context = \context_module::instance($cm->id);

        // Create persona and selection.
        $personaid = $DB->insert_record('block_ai_chat_personas', [
            'userid' => $user->id,
            'name' => 'Test Persona',
            'prompt' => 'You are a helpful assistant',
            'userinfo' => '',
            'type' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $DB->insert_record('block_ai_chat_personas_selected', [
            'contextid' => $context->id,
            'personasid' => $personaid,
        ]);

        // Create conversation data using the generator.
        $this->aimanagergenerator->create_request_log_entry([
            'userid' => $user->id,
            'contextid' => $context->id,
            'component' => 'mod_aichat',
            'prompttext' => 'What is AI?',
            'promptcompletion' => 'AI stands for Artificial Intelligence',
        ]);

        // Export data.
        $contextlist = provider::get_contexts_for_userid($user->id);
        $approvedcontextlist = new approved_contextlist($user, 'mod_aichat', $contextlist->get_contextids());
        provider::export_user_data($approvedcontextlist);

        // Verify exported data.
        $writer = writer::with_context($context);
        $data = $writer->get_data([get_string('pluginname', 'mod_aichat'), get_string('privacy:path:personas', 'mod_aichat')]);
        $this->assertNotEmpty($data);
        $this->assertEquals('Test Persona', $data->persona_name);

        $data = $writer->get_data(
            [get_string('pluginname', 'mod_aichat'), get_string('privacy:path:conversations', 'mod_aichat')]
        );
        $this->assertNotEmpty($data);
        $this->assertNotEmpty($data->conversations);
        $this->assertCount(1, $data->conversations);
        $this->assertEquals('What is AI?', $data->conversations[0]['prompttext']);
    }

    /**
     * Test deleting data for a user.
     */
    public function test_delete_data_for_user(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        // Create AI chat activity.
        $aichat = $this->getDataGenerator()->create_module('aichat', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('aichat', $aichat->id);
        $context = \context_module::instance($cm->id);

        // Create a second AI chat activity for testing personas.
        $aichat2 = $this->getDataGenerator()->create_module('aichat', ['course' => $course->id]);
        $cm2 = get_coursemodule_from_instance('aichat', $aichat2->id);
        $context2 = \context_module::instance($cm2->id);

        // Create conversation data for both users using the generator.
        $record1 = $this->aimanagergenerator->create_request_log_entry([
            'userid' => $user1->id,
            'contextid' => $context->id,
            'component' => 'mod_aichat',
            'prompttext' => 'User 1 prompt',
            'promptcompletion' => 'User 1 completion',
        ]);

        $record2 = $this->aimanagergenerator->create_request_log_entry([
            'userid' => $user2->id,
            'contextid' => $context->id,
            'component' => 'mod_aichat',
            'prompttext' => 'User 2 prompt',
            'promptcompletion' => 'User 2 completion',
        ]);

        // Create personas for user1.
        // Persona 1: Used in a context (should be deleted along with the selection).
        $persona1id = $DB->insert_record('block_ai_chat_personas', [
            'userid' => $user1->id,
            'name' => 'Used Persona',
            'prompt' => 'You are a helpful assistant',
            'userinfo' => '',
            'type' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        // Select persona1 in context2.
        $selectionid = $DB->insert_record('block_ai_chat_personas_selected', [
            'contextid' => $context2->id,
            'personasid' => $persona1id,
        ]);

        // Persona 2: Not used anywhere (should also be deleted).
        $persona2id = $DB->insert_record('block_ai_chat_personas', [
            'userid' => $user1->id,
            'name' => 'Unused Persona',
            'prompt' => 'You are another assistant',
            'userinfo' => '',
            'type' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        // Persona by user2 (should not be affected).
        $persona3id = $DB->insert_record('block_ai_chat_personas', [
            'userid' => $user2->id,
            'name' => 'User 2 Persona',
            'prompt' => 'You are user 2 assistant',
            'userinfo' => '',
            'type' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        // Delete data for user1.
        $contextlist = provider::get_contexts_for_userid($user1->id);
        $approvedcontextlist = new approved_contextlist($user1, 'mod_aichat', $contextlist->get_contextids());
        provider::delete_data_for_user($approvedcontextlist);

        // Verify user1's conversation data is anonymized.
        $dbrecord1 = $DB->get_record('local_ai_manager_request_log', ['id' => $record1->id]);
        $this->assertNull($dbrecord1->userid);
        $this->assertEquals(data_wiper::ANONYMIZE_STRING, $dbrecord1->prompttext);
        $this->assertEquals(data_wiper::ANONYMIZE_STRING, $dbrecord1->promptcompletion);

        // Verify user2's data is not affected.
        $dbrecord2 = $DB->get_record('local_ai_manager_request_log', ['id' => $record2->id]);
        $this->assertEquals($user2->id, $dbrecord2->userid);
        $this->assertEquals('User 2 prompt', $dbrecord2->prompttext);
        $this->assertEquals('User 2 completion', $dbrecord2->promptcompletion);

        // Verify persona1 (used) is deleted.
        $this->assertFalse($DB->record_exists('block_ai_chat_personas', ['id' => $persona1id]));

        // Verify the persona selection is also deleted.
        $this->assertFalse($DB->record_exists('block_ai_chat_personas_selected', ['id' => $selectionid]));

        // Verify persona2 (unused) is also deleted.
        $this->assertFalse($DB->record_exists('block_ai_chat_personas', ['id' => $persona2id]));

        // Verify persona3 (user2's persona) is not affected.
        $this->assertTrue($DB->record_exists('block_ai_chat_personas', ['id' => $persona3id]));
    }

    /**
     * Test getting users in context.
     */
    public function test_get_users_in_context(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        // Create AI chat activity.
        $aichat = $this->getDataGenerator()->create_module('aichat', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('aichat', $aichat->id);
        $context = \context_module::instance($cm->id);

        // Create conversation data for user1 and user2 using the generator.
        $this->aimanagergenerator->create_request_log_entry([
            'userid' => $user1->id,
            'contextid' => $context->id,
            'component' => 'mod_aichat',
        ]);

        $this->aimanagergenerator->create_request_log_entry([
            'userid' => $user2->id,
            'contextid' => $context->id,
            'component' => 'mod_aichat',
        ]);

        // Debug: Check if records were actually inserted.
        $records = $DB->get_records('local_ai_manager_request_log', [
            'contextid' => $context->id,
            'component' => 'mod_aichat',
        ]);
        $this->assertCount(2, $records, 'Should have 2 records in database');

        // Get users in context.
        $userlist = new userlist($context, 'mod_aichat');
        provider::get_users_in_context($userlist);

        $this->assertCount(2, $userlist, 'Should find 2 users in context');
        $userids = $userlist->get_userids();

        // Use assertEqualsCanonicalizing to compare arrays regardless of order and type.
        $expecteduserids = [$user1->id, $user2->id];
        $this->assertEqualsCanonicalizing($expecteduserids, $userids, 'User IDs should match');
        $this->assertNotContainsEquals($user3->id, $userids);
    }

    /**
     * Test deleting data for all users in context.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        // Create AI chat activity.
        $aichat = $this->getDataGenerator()->create_module('aichat', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('aichat', $aichat->id);
        $context = \context_module::instance($cm->id);

        // Create a second AI chat activity (its data should not be affected).
        $aichat2 = $this->getDataGenerator()->create_module('aichat', ['course' => $course->id]);
        $cm2 = get_coursemodule_from_instance('aichat', $aichat2->id);
        $context2 = \context_module::instance($cm2->id);

        // Create conversation data for both users using the generator.
        $record1 = $this->aimanagergenerator->create_request_log_entry([
            'userid' => $user1->id,
            'contextid' => $context->id,
            'component' => 'mod_aichat',
            'prompttext' => 'User 1 prompt',
            'promptcompletion' => 'User 1 completion',
        ]);

        $record2 = $this->aimanagergenerator->create_request_log_entry([
            'userid' => $user2->id,
            'contextid' => $context->id,
            'component' => 'mod_aichat',
            'prompttext' => 'User 2 prompt',
            'promptcompletion' => 'User 2 completion',
        ]);

        // Create personas and selections.
        // Persona selected in the context being deleted.
        $persona1id = $DB->insert_record('block_ai_chat_personas', [
            'userid' => $user1->id,
            'name' => 'Persona in context',
            'prompt' => 'You are a helpful assistant',
            'userinfo' => '',
            'type' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $selection1id = $DB->insert_record('block_ai_chat_personas_selected', [
            'contextid' => $context->id,
            'personasid' => $persona1id,
        ]);

        // Persona selected in another context (should not be affected).
        $persona2id = $DB->insert_record('block_ai_chat_personas', [
            'userid' => $user2->id,
            'name' => 'Persona in other context',
            'prompt' => 'You are another assistant',
            'userinfo' => '',
            'type' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $selection2id = $DB->insert_record('block_ai_chat_personas_selected', [
            'contextid' => $context2->id,
            'personasid' => $persona2id,
        ]);

        // Delete all data in context.
        provider::delete_data_for_all_users_in_context($context);

        // Verify both users' conversation data is anonymized.
        $dbrecord1 = $DB->get_record('local_ai_manager_request_log', ['id' => $record1->id]);
        $this->assertNull($dbrecord1->userid);
        $this->assertEquals(data_wiper::ANONYMIZE_STRING, $dbrecord1->prompttext);
        $this->assertEquals(data_wiper::ANONYMIZE_STRING, $dbrecord1->promptcompletion);

        $dbrecord2 = $DB->get_record('local_ai_manager_request_log', ['id' => $record2->id]);
        $this->assertNull($dbrecord2->userid);
        $this->assertEquals(data_wiper::ANONYMIZE_STRING, $dbrecord2->prompttext);
        $this->assertEquals(data_wiper::ANONYMIZE_STRING, $dbrecord2->promptcompletion);

        // Verify persona1 and its selection in the deleted context are gone.
        $this->assertFalse($DB->record_exists('block_ai_chat_personas', ['id' => $persona1id]));
        $this->assertFalse($DB->record_exists('block_ai_chat_personas_selected', ['id' => $selection1id]));

        // Verify persona2 and its selection in other context are still there.
        $this->assertTrue($DB->record_exists('block_ai_chat_personas', ['id' => $persona2id]));
        $this->assertTrue($DB->record_exists('block_ai_chat_personas_selected', ['id' => $selection2id]));
    }

    /**
     * Test deleting data for users.
     */
    public function test_delete_data_for_users(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        // Create AI chat activity.
        $aichat = $this->getDataGenerator()->create_module('aichat', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('aichat', $aichat->id);
        $context = \context_module::instance($cm->id);

        // Create a second AI chat activity for testing personas.
        $aichat2 = $this->getDataGenerator()->create_module('aichat', ['course' => $course->id]);
        $cm2 = get_coursemodule_from_instance('aichat', $aichat2->id);
        $context2 = \context_module::instance($cm2->id);

        // Create conversation data for all users using the generator.
        $record1 = $this->aimanagergenerator->create_request_log_entry([
            'userid' => $user1->id,
            'contextid' => $context->id,
            'component' => 'mod_aichat',
            'prompttext' => 'User 1 prompt',
            'promptcompletion' => 'User 1 completion',
        ]);

        $record2 = $this->aimanagergenerator->create_request_log_entry([
            'userid' => $user2->id,
            'contextid' => $context->id,
            'component' => 'mod_aichat',
            'prompttext' => 'User 2 prompt',
            'promptcompletion' => 'User 2 completion',
        ]);

        $record3 = $this->aimanagergenerator->create_request_log_entry([
            'userid' => $user3->id,
            'contextid' => $context->id,
            'component' => 'mod_aichat',
            'prompttext' => 'User 3 prompt',
            'promptcompletion' => 'User 3 completion',
        ]);

        // Create personas for users.
        // Persona for user1 (used in context2).
        $persona1id = $DB->insert_record('block_ai_chat_personas', [
            'userid' => $user1->id,
            'name' => 'User 1 Persona',
            'prompt' => 'You are user 1 assistant',
            'userinfo' => '',
            'type' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $selection1id = $DB->insert_record('block_ai_chat_personas_selected', [
            'contextid' => $context2->id,
            'personasid' => $persona1id,
        ]);

        // Persona for user2 (not used).
        $persona2id = $DB->insert_record('block_ai_chat_personas', [
            'userid' => $user2->id,
            'name' => 'User 2 Persona',
            'prompt' => 'You are user 2 assistant',
            'userinfo' => '',
            'type' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        // Persona for user3 (should not be affected).
        $persona3id = $DB->insert_record('block_ai_chat_personas', [
            'userid' => $user3->id,
            'name' => 'User 3 Persona',
            'prompt' => 'You are user 3 assistant',
            'userinfo' => '',
            'type' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        // Delete data for user1 and user2.
        $userlist = new userlist($context, 'mod_aichat');
        $userlist->add_users([$user1->id, $user2->id]);
        $approveduserlist = new approved_userlist($context, 'mod_aichat', $userlist->get_userids());
        provider::delete_data_for_users($approveduserlist);

        // Verify user1 and user2 conversation data is anonymized.
        $dbrecord1 = $DB->get_record('local_ai_manager_request_log', ['id' => $record1->id]);
        $this->assertNull($dbrecord1->userid);
        $this->assertEquals(data_wiper::ANONYMIZE_STRING, $dbrecord1->prompttext);
        $this->assertEquals(data_wiper::ANONYMIZE_STRING, $dbrecord1->promptcompletion);

        $dbrecord2 = $DB->get_record('local_ai_manager_request_log', ['id' => $record2->id]);
        $this->assertNull($dbrecord2->userid);
        $this->assertEquals(data_wiper::ANONYMIZE_STRING, $dbrecord2->prompttext);
        $this->assertEquals(data_wiper::ANONYMIZE_STRING, $dbrecord2->promptcompletion);

        // Verify user3 conversation data is not affected.
        $dbrecord3 = $DB->get_record('local_ai_manager_request_log', ['id' => $record3->id]);
        $this->assertEquals($user3->id, $dbrecord3->userid);
        $this->assertEquals('User 3 prompt', $dbrecord3->prompttext);
        $this->assertEquals('User 3 completion', $dbrecord3->promptcompletion);

        // Verify user1's persona and its selection are deleted.
        $this->assertFalse($DB->record_exists('block_ai_chat_personas', ['id' => $persona1id]));
        $this->assertFalse($DB->record_exists('block_ai_chat_personas_selected', ['id' => $selection1id]));

        // Verify user2's persona is deleted.
        $this->assertFalse($DB->record_exists('block_ai_chat_personas', ['id' => $persona2id]));

        // Verify user3's persona is not affected.
        $this->assertTrue($DB->record_exists('block_ai_chat_personas', ['id' => $persona3id]));
    }
}
