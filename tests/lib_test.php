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

namespace mod_aichat;

/**
 * Tests for AI Chat module
 *
 * @package   mod_aichat
 * @copyright 2026 ISB Bayern
 * @author    Philipp Memmel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    ::aichat_delete_instance
 */
final class lib_test extends \advanced_testcase {
    /**
     * Test deleting a module instance.
     *
     * This test verifies that when a module instance is deleted, the corresponding
     * entries in the database are being properly removed.
     *
     * @covers ::aichat_delete_instance
     */
    public function test_delete_instance(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $testdata = $this->create_test_data($course);

        // Verify both persona selections exist before deletion.
        $this->assertTrue($DB->record_exists('block_ai_chat_personas_selected', ['contextid' => $testdata->context1->id]));
        $this->assertTrue($DB->record_exists('block_ai_chat_personas_selected', ['contextid' => $testdata->context2->id]));
        $this->assertEquals(2, $DB->count_records('block_ai_chat_personas_selected'));

        // Verify both options exist before deletion.
        $this->assertTrue($DB->record_exists('block_ai_chat_options', ['contextid' => $testdata->context1->id]));
        $this->assertTrue($DB->record_exists('block_ai_chat_options', ['contextid' => $testdata->context2->id]));
        $this->assertEquals(2, $DB->count_records('block_ai_chat_options'));

        // Verify both log entries exist and are not deleted before deletion using ai_manager_utils.
        $logentriesaichat1 = \local_ai_manager\ai_manager_utils::get_log_entries('block_ai_chat', $testdata->context1->id);
        $this->assertCount(1, $logentriesaichat1);
        $logentry1retrieved = reset($logentriesaichat1);
        $this->assertEquals($testdata->logentry1->id, $logentry1retrieved->id);
        $this->assertEquals(0, $logentry1retrieved->deleted);

        $logentriesaichat2 = \local_ai_manager\ai_manager_utils::get_log_entries('block_ai_chat', $testdata->context2->id);
        $this->assertCount(1, $logentriesaichat2);
        $logentry2retrieved = reset($logentriesaichat2);
        $this->assertEquals($testdata->logentry2->id, $logentry2retrieved->id);
        $this->assertEquals(0, $logentry2retrieved->deleted);

        // Verify both aichat instances exist before deletion.
        $this->assertTrue($DB->record_exists('aichat', ['id' => $testdata->aichat1->id]));
        $this->assertTrue($DB->record_exists('aichat', ['id' => $testdata->aichat2->id]));
        $this->assertEquals(2, $DB->count_records('aichat'));

        // Delete aichat 1.
        aichat_delete_instance($testdata->aichat1->id);

        // Verify that the aichat 1 instance is deleted.
        $this->assertFalse($DB->record_exists('aichat', ['id' => $testdata->aichat1->id]));
        // Verify that the aichat 2 instance still exists.
        $this->assertTrue($DB->record_exists('aichat', ['id' => $testdata->aichat2->id]));
        $this->assertEquals(1, $DB->count_records('aichat'));

        // Verify that the persona selection for aichat 1 is deleted.
        $this->assertFalse($DB->record_exists('block_ai_chat_personas_selected', ['contextid' => $testdata->context1->id]));
        // Verify that the persona selection for aichat 2 still exists.
        $this->assertTrue($DB->record_exists('block_ai_chat_personas_selected', ['contextid' => $testdata->context2->id]));
        $this->assertEquals(1, $DB->count_records('block_ai_chat_personas_selected'));
        // Verify the remaining record has the correct persona assigned.
        $remainingrecord = $DB->get_record('block_ai_chat_personas_selected', ['contextid' => $testdata->context2->id]);
        $this->assertEquals($testdata->persona2->id, $remainingrecord->personasid);

        // Verify that the options for aichat 1 are deleted.
        $this->assertFalse($DB->record_exists('block_ai_chat_options', ['contextid' => $testdata->context1->id]));
        // Verify that the options for aichat 2 still exist.
        $this->assertTrue($DB->record_exists('block_ai_chat_options', ['contextid' => $testdata->context2->id]));
        $this->assertEquals(1, $DB->count_records('block_ai_chat_options'));
        // Verify the remaining option has the correct value.
        $remainingoption = $DB->get_record('block_ai_chat_options', ['contextid' => $testdata->context2->id]);
        $this->assertEquals('10', $remainingoption->value);

        // Verify that the log entry for aichat 1 is marked as deleted using ai_manager_utils.
        $logentriesaichat1afterdelete = \local_ai_manager\ai_manager_utils::get_log_entries('block_ai_chat', $testdata->context1->id);
        $this->assertCount(1, $logentriesaichat1afterdelete);
        $logentry1afterdelete = reset($logentriesaichat1afterdelete);
        $this->assertEquals($testdata->logentry1->id, $logentry1afterdelete->id);
        $this->assertEquals(1, $logentry1afterdelete->deleted);

        // Verify that the log entry for aichat 2 is still not deleted using ai_manager_utils.
        $logentriesaichat2afterdelete = \local_ai_manager\ai_manager_utils::get_log_entries('block_ai_chat', $testdata->context2->id);
        $this->assertCount(1, $logentriesaichat2afterdelete);
        $logentry2afterdelete = reset($logentriesaichat2afterdelete);
        $this->assertEquals($testdata->logentry2->id, $logentry2afterdelete->id);
        $this->assertEquals(0, $logentry2afterdelete->deleted);
    }

    /**
     * Test resetting course data.
     *
     * This test verifies that when a course is reset with the aichat option enabled,
     * all conversation log entries are marked as deleted.
     *
     * @covers ::aichat_reset_userdata
     */
    public function test_reset_course_userdata(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $testdata = $this->create_test_data($course);

        // Verify both log entries exist and are not deleted before reset.
        $logentriesaichat1 = \local_ai_manager\ai_manager_utils::get_log_entries('block_ai_chat', $testdata->context1->id);
        $this->assertCount(1, $logentriesaichat1);
        $logentry1retrieved = reset($logentriesaichat1);
        $this->assertEquals($testdata->logentry1->id, $logentry1retrieved->id);
        $this->assertEquals(0, $logentry1retrieved->deleted);

        $logentriesaichat2 = \local_ai_manager\ai_manager_utils::get_log_entries('block_ai_chat', $testdata->context2->id);
        $this->assertCount(1, $logentriesaichat2);
        $logentry2retrieved = reset($logentriesaichat2);
        $this->assertEquals($testdata->logentry2->id, $logentry2retrieved->id);
        $this->assertEquals(0, $logentry2retrieved->deleted);

        // Verify both aichat instances and their associated data exist before reset.
        $this->assertTrue($DB->record_exists('aichat', ['id' => $testdata->aichat1->id]));
        $this->assertTrue($DB->record_exists('aichat', ['id' => $testdata->aichat2->id]));
        $this->assertTrue($DB->record_exists('block_ai_chat_personas_selected', ['contextid' => $testdata->context1->id]));
        $this->assertTrue($DB->record_exists('block_ai_chat_personas_selected', ['contextid' => $testdata->context2->id]));
        $this->assertTrue($DB->record_exists('block_ai_chat_options', ['contextid' => $testdata->context1->id]));
        $this->assertTrue($DB->record_exists('block_ai_chat_options', ['contextid' => $testdata->context2->id]));

        // Reset the course with reset_aichat option enabled.
        $data = new \stdClass();
        $data->courseid = $course->id;
        $data->reset_aichat = 1;
        $status = aichat_reset_userdata($data);

        // Verify that the reset status is returned correctly.
        $this->assertIsArray($status);
        $this->assertCount(1, $status);
        $this->assertEquals(get_string('modulenameplural', 'mod_aichat'), $status[0]['component']);
        $this->assertEquals(get_string('removeconversations', 'mod_aichat'), $status[0]['item']);
        $this->assertFalse($status[0]['error']);

        // Verify that both aichat instances still exist after reset.
        $this->assertTrue($DB->record_exists('aichat', ['id' => $testdata->aichat1->id]));
        $this->assertTrue($DB->record_exists('aichat', ['id' => $testdata->aichat2->id]));

        // Verify that personas and options are NOT deleted (only conversations should be marked as deleted).
        $this->assertTrue($DB->record_exists('block_ai_chat_personas_selected', ['contextid' => $testdata->context1->id]));
        $this->assertTrue($DB->record_exists('block_ai_chat_personas_selected', ['contextid' => $testdata->context2->id]));
        $this->assertTrue($DB->record_exists('block_ai_chat_options', ['contextid' => $testdata->context1->id]));
        $this->assertTrue($DB->record_exists('block_ai_chat_options', ['contextid' => $testdata->context2->id]));

        // Verify that log entries for both aichat instances are marked as deleted.
        $logentriesaichat1afterreset = \local_ai_manager\ai_manager_utils::get_log_entries('block_ai_chat', $testdata->context1->id);
        $this->assertCount(1, $logentriesaichat1afterreset);
        $logentry1afterreset = reset($logentriesaichat1afterreset);
        $this->assertEquals($testdata->logentry1->id, $logentry1afterreset->id);
        $this->assertEquals(1, $logentry1afterreset->deleted);

        $logentriesaichat2afterreset = \local_ai_manager\ai_manager_utils::get_log_entries('block_ai_chat', $testdata->context2->id);
        $this->assertCount(1, $logentriesaichat2afterreset);
        $logentry2afterreset = reset($logentriesaichat2afterreset);
        $this->assertEquals($testdata->logentry2->id, $logentry2afterreset->id);
        $this->assertEquals(1, $logentry2afterreset->deleted);
    }

    /**
     * Create test data for aichat tests.
     *
     * This helper function creates two aichat instances with associated personas,
     * options, and log entries for testing purposes.
     *
     * @param \stdClass $course The course object to create aichat instances in
     * @return \stdClass Object containing all created test data
     */
    private function create_test_data(\stdClass $course): \stdClass {
        global $DB;

        $testdata = new \stdClass();

        // Create two aichat module instances in the course.
        $testdata->aichat1 = $this->getDataGenerator()->create_module('aichat', [
            'course' => $course->id,
            'name' => 'AI Chat 1',
        ]);
        $testdata->aichat2 = $this->getDataGenerator()->create_module('aichat', [
            'course' => $course->id,
            'name' => 'AI Chat 2',
        ]);

        // Get course module contexts.
        $testdata->cm1 = get_coursemodule_from_instance('aichat', $testdata->aichat1->id);
        $testdata->cm2 = get_coursemodule_from_instance('aichat', $testdata->aichat2->id);
        $testdata->context1 = \context_module::instance($testdata->cm1->id);
        $testdata->context2 = \context_module::instance($testdata->cm2->id);

        /** @var \block_ai_chat_generator $blockgenerator */
        $blockgenerator = $this->getDataGenerator()->get_plugin_generator('block_ai_chat');

        // Create two personas.
        $testdata->persona1 = $blockgenerator->create_persona(['name' => 'Test Persona 1']);
        $testdata->persona2 = $blockgenerator->create_persona(['name' => 'Test Persona 2']);

        // Link persona 1 to aichat 1.
        $manager1 = new \block_ai_chat\manager($testdata->context1->id);
        $manager1->select_persona($testdata->persona1->id);

        // Link persona 2 to aichat 2.
        $manager2 = new \block_ai_chat\manager($testdata->context2->id);
        $manager2->select_persona($testdata->persona2->id);

        // Create options for both modules.
        $DB->insert_record('block_ai_chat_options', [
            'name' => 'historycontextmax',
            'value' => '5',
            'contextid' => $testdata->context1->id,
        ]);
        $DB->insert_record('block_ai_chat_options', [
            'name' => 'historycontextmax',
            'value' => '10',
            'contextid' => $testdata->context2->id,
        ]);

        // Create log entries for both modules using the local_ai_manager generator.
        /** @var \local_ai_manager_generator $aimanagergenerator */
        $aimanagergenerator = $this->getDataGenerator()->get_plugin_generator('local_ai_manager');

        $testdata->logentry1 = $aimanagergenerator->create_request_log_entry([
            'component' => 'block_ai_chat',
            'contextid' => $testdata->context1->id,
            'value' => 100,
            'deleted' => 0,
        ]);

        $testdata->logentry2 = $aimanagergenerator->create_request_log_entry([
            'component' => 'block_ai_chat',
            'contextid' => $testdata->context2->id,
            'value' => 200,
            'deleted' => 0,
        ]);

        return $testdata;
    }
}
