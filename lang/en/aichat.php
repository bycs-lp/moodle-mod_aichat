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
 * Language file for mod_aichat
 *
 * @package     mod_aichat
 * @copyright   2025 ISB Bayern
 * @author      Philipp Memmel
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


$string['aichat:addinstance'] = 'Add an AI chat activity';
$string['aichat:view'] = 'View the AI chat activity';
$string['modulename'] = 'AI chat';
$string['modulename_help'] = '###### Key features
- Standard chatbot functionalities
- Additional AI tools (image generation etc.) accessible by clicking the AI button in the input text area
- Creating and selecting of personas (system prompts) for guiding the AI behavior
- Configuration of the amount of messages that is being sent to the external AI system
- Conversation history for each user

###### Ways to use it
- Standard chatbot for answering questions on specific topics
- Tutor bot to help learners with exercises by defining a tutor persona
- chatbot for learning *about* AI by experimenting with different personas and limiting length of context';
$string['modulename_summary'] = 'This activity provides the students with an AI chatbot.';
$string['modulenameplural'] = 'AI chats';
$string['name'] = 'Name';
$string['name_help'] = 'Name of this AI chat activity shown on the course page';
$string['noaichatinstance'] = 'No instance of mod_aichat could be found.';
$string['pluginadministration'] = 'AI chat administration';
$string['pluginname'] = 'AI chat';
$string['pluginname_userfaced'] = 'AI chatbot activity';
$string['privacy:metadata:block_ai_chat_personas_selected'] = 'Stores which AI persona is selected for this AI chat activity';
$string['privacy:metadata:block_ai_chat_personas_selected:contextid'] = 'The context ID of the AI chat activity';
$string['privacy:metadata:block_ai_chat_personas_selected:personasid'] = 'The ID of the selected persona';
$string['privacy:metadata:local_ai_manager_request_log'] = 'Stores conversation data (prompts and AI responses) for the AI chat activity in the local_ai_manager request log table';
$string['privacy:metadata:local_ai_manager_request_log:component'] = 'The component identifier (block_ai_chat) that created this log entry';
$string['privacy:metadata:local_ai_manager_request_log:contextid'] = 'The context ID of the AI chat activity where the conversation took place';
$string['privacy:metadata:local_ai_manager_request_log:promptcompletion'] = 'The AI response to the user\'s prompt';
$string['privacy:metadata:local_ai_manager_request_log:prompttext'] = 'The prompt text sent by the user to the AI';
$string['privacy:metadata:local_ai_manager_request_log:timecreated'] = 'The time when the conversation entry was created';
$string['privacy:metadata:local_ai_manager_request_log:userid'] = 'The ID of the user who created the conversation';
$string['privacy:path:conversations'] = 'Conversations';
$string['privacy:path:personas'] = 'Persona selection';
$string['removeconversations'] = 'Delete all conversations';
