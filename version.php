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
 * Version information for mod_aichat.
 *
 * @package     mod_aichat
 * @copyright   2025 ISB Bayern
 * @author      Philipp Memmel
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'mod_aichat';
$plugin->release = '0.1';
$plugin->version = 2026011600;
$plugin->requires = 2022112800;
$plugin->supported = [501, 501];
$plugin->maturity = MATURITY_BETA;
$plugin->dependencies = [
    // We do not declare separate dependencies to local_ai_manager and tiny_ai,
    // because we get that transitively via block_ai_chat.
    'block_ai_chat' => 2025121301,
];
