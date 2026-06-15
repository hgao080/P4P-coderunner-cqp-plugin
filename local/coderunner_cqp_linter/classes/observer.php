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

namespace local_coderunner_cqp_linter;

/**
 * Event observer for pre-caching pylint results.
 *
 * Listens for quiz attempt submission events and runs pylint on any
 * Python CodeRunner questions to pre-populate the cache. This means
 * the review page loads with lint results already available.
 *
 * @package    local_coderunner_cqp_linter
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * Handle question update — propagate CQP config to the new question version.
     *
     * Moodle 4.x creates a new question ID each time a question is saved as a
     * new version. This copies the per-question CQP config row to the new ID so
     * the linting toggle and marks settings survive a teacher's edit.
     *
     * @param \core\event\question_updated $event The event.
     */
    public static function question_updated(\core\event\question_updated $event): void {
        try {
            self::propagate_config_to_new_version($event->objectid);
        } catch (\Throwable $e) {
            debugging('CodeRunner CQP Linter question_updated error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Copy local_crcqp_qconfig from the previous question version to the new one.
     *
     * Does nothing when the question already has a config row (in-place update, not
     * a new version) or when there is no previous version to copy from.
     *
     * @param int $questionid The new question ID from the question_updated event.
     */
    private static function propagate_config_to_new_version(int $questionid): void {
        global $DB;

        // Already has config — either an in-place update or already propagated.
        if ($DB->record_exists('local_crcqp_qconfig', ['questionid' => $questionid])) {
            return;
        }

        // question_versions only exists in Moodle 4+.
        if (!$DB->get_manager()->table_exists('question_versions')) {
            return;
        }

        $ver = $DB->get_record('question_versions', ['questionid' => $questionid]);
        if (!$ver || (int)$ver->version <= 1) {
            return; // First version — nothing to copy.
        }

        $prevver = $DB->get_record_sql(
            'SELECT questionid FROM {question_versions}
              WHERE questionbankentryid = :entryid
                AND version = :ver',
            ['entryid' => $ver->questionbankentryid, 'ver' => (int)$ver->version - 1]
        );
        if (!$prevver) {
            return;
        }

        $oldconfig = $DB->get_record('local_crcqp_qconfig', ['questionid' => $prevver->questionid]);
        if (!$oldconfig) {
            return;
        }

        $now = time();
        $newconfig = clone $oldconfig;
        unset($newconfig->id);
        $newconfig->questionid  = $questionid;
        $newconfig->timecreated = $now;
        $newconfig->timemodified = $now;
        $DB->insert_record('local_crcqp_qconfig', $newconfig);
    }

    /**
     * Handle quiz attempt submission.
     *
     * Iterates through all question attempts in the quiz and runs pylint
     * on Python CodeRunner questions, caching the results.
     *
     * @param \mod_quiz\event\attempt_submitted $event The event.
     */
    public static function attempt_submitted(\mod_quiz\event\attempt_submitted $event): void {
        try {
            self::process_attempt($event);
        } catch (\Throwable $e) {
            // Never let lint errors break quiz submission.
            debugging('CodeRunner CQP Linter observer error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Process a quiz attempt to pre-cache lint results.
     *
     * @param \mod_quiz\event\attempt_submitted $event
     */
    private static function process_attempt(\mod_quiz\event\attempt_submitted $event): void {
        global $DB;

        // Linting is now opt-in per question — question_helper::lint_question_attempt()
        // short-circuits on questions without a per-question config row, so we can
        // safely iterate every slot without a global gate here.
        $attemptid = $event->objectid;

        // Load the quiz attempt.
        $attemptobj = $DB->get_record('quiz_attempts', ['id' => $attemptid]);
        if (!$attemptobj) {
            return;
        }

        // Load the question usage.
        $quba = \question_engine::load_questions_usage_by_activity($attemptobj->uniqueid);

        // Iterate through all question slots.
        foreach ($quba->get_slots() as $slot) {
            $qa = $quba->get_question_attempt($slot);

            // This method handles all checks (is python, is enabled, has code)
            // and uses the cache internally.
            question_helper::lint_question_attempt($qa);
        }
    }
}
