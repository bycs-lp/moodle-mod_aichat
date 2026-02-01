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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/aichat/backup/moodle2/backup_aichat_stepslib.php');

/**
 * Backup class for mod_aichat
 *
 * @package    mod_aichat
 * @copyright  2026 ISB Bayern
 * @author     Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_aichat_activity_task extends backup_activity_task {
    /**
     * Does nothing.
     *
     * @return void
     */
    protected function define_my_settings() {
    }

    /**
     * Add step.
     *
     * @return void
     */
    protected function define_my_steps() {
        $this->add_step(new backup_aichat_structure_step('aichat', 'aichat.xml'));
    }

    /**
     * This plugin has no fileareas yet.
     *
     * @return array
     */
    public function get_fileareas() {
        return [];
    }

    /**
     * Returns the unchanged parameter.
     *
     * @param string $content
     * @return string
     */
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');

        $search = '/(' . $base . '\/mod\/aichat\/view.php\?id=)([0-9]+)/';
        $content = preg_replace($search, '$@AICHATVIEWBYID*$2@$', $content);

        return $content;
    }
}
