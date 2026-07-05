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
     * Process a quiz attempt to pre-cache lint results and record submission events.
     *
     * @param \mod_quiz\event\attempt_submitted $event
     */
    private static function process_attempt(\mod_quiz\event\attempt_submitted $event): void {
        global $DB;

        $attemptid = $event->objectid;

        $attemptobj = $DB->get_record('quiz_attempts', ['id' => $attemptid]);
        if (!$attemptobj) {
            return;
        }

        $quba = \question_engine::load_questions_usage_by_activity($attemptobj->uniqueid);
        $now  = time();

        // Pre-cache lint results for everyone (so the review page is fast), but
        // only store research events for genuine student attempts.
        $recordevents = question_helper::should_record_event(
            (int)$attemptobj->userid, (int)$attemptid);

        foreach ($quba->get_slots() as $slot) {
            $qa = $quba->get_question_attempt($slot);

            $result = question_helper::lint_question_attempt($qa);
            if ($result === null) {
                continue;
            }

            if (!$recordevents) {
                continue;
            }

            $question = $qa->get_question();

            $record              = new \stdClass();
            $record->userid      = (int)$attemptobj->userid;
            $record->questionid  = (int)$question->id;
            $record->attemptid   = (int)$attemptid;
            $record->slot        = (int)$slot;
            $record->issuecount  = $result->count_filtered();
            $record->resultsjson = question_helper::build_results_json($result);
            $record->code        = self::capture_code($qa);
            $record->eventtype   = 'submit';
            $record->timecreated = $now;

            $DB->insert_record('local_crcqp_lint_event', $record);

            // Optional AI analysis on submission, recorded as a separate event.
            self::maybe_record_ai_event($qa, $question, $attemptobj, $attemptid, $slot, $now);
        }
    }

    /**
     * Run AI analysis on a submitted attempt and record it as an 'ai' research
     * event, when AI is enabled site-wide for submissions and for the question.
     *
     * @param \question_attempt $qa
     * @param object $question
     * @param object $attemptobj
     * @param int $attemptid
     * @param int $slot
     * @param int $now
     */
    private static function maybe_record_ai_event($qa, $question, $attemptobj, int $attemptid, int $slot, int $now): void {
        global $DB;

        if (!\local_coderunner_cqp_linter\tools\ai\analyzer::runs_on('submit')
                || !question_helper::is_ai_enabled($question->id)) {
            return;
        }

        $code = question_helper::get_student_code($qa);
        if ($code === null) {
            return;
        }

        try {
            $analyzer = new \local_coderunner_cqp_linter\tools\ai\analyzer();
            $principles  = question_helper::get_ai_principles((int)$question->id);
            $problemtext = question_helper::get_problem_text((int)$question->id);
            $airesult = $analyzer->analyze($code, $principles, $problemtext);
        } catch (\Throwable $e) {
            debugging('CQP AI observer error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return;
        }

        if (empty($airesult['success'])) {
            return;
        }

        $record              = new \stdClass();
        $record->userid      = (int)$attemptobj->userid;
        $record->questionid  = (int)$question->id;
        $record->attemptid   = (int)$attemptid;
        $record->slot        = (int)$slot;
        $record->issuecount  = (int)$airesult['total_issues'];
        $record->resultsjson = self::build_ai_results_json($airesult);
        $record->code        = self::cap_code($code);
        $record->airesponse  = json_encode($airesult);
        $record->eventtype   = 'ai';
        $record->timecreated = $now;

        $DB->insert_record('local_crcqp_lint_event', $record);
    }

    /**
     * Fetch the student's submitted code for a question attempt, capped for
     * storage. Returns null when no code is available.
     *
     * @param \question_attempt $qa
     * @return string|null
     */
    private static function capture_code(\question_attempt $qa): ?string {
        $code = question_helper::get_student_code($qa);
        return ($code === null) ? null : self::cap_code($code);
    }

    /**
     * Cap a code string for storage in the TEXT column (CS1 code is tiny; this
     * only trims pathological input). Multibyte-aware to keep it valid.
     *
     * @param string $code
     * @return string
     */
    private static function cap_code(string $code): string {
        if (\core_text::strlen($code) > 60000) {
            return \core_text::substr($code, 0, 60000);
        }
        return $code;
    }

    /**
     * Build resultsjson for an AI payload, matching the principles+violations
     * shape used elsewhere so report.php renders it uniformly.
     *
     * @param array $airesult Payload from analyzer::analyze().
     * @return string JSON.
     */
    private static function build_ai_results_json(array $airesult): string {
        $principles = [];
        foreach ($airesult['principles'] as $p) {
            $principles[] = ['n' => $p['number'], 'name' => $p['name'], 'count' => $p['count']];
        }
        $violations = [];
        foreach ($airesult['messages'] as $m) {
            $violations[] = [
                'code'   => 'AI',
                'symbol' => $m['symbol'] ?? ('ai-cqp' . $m['cqp_number']),
                'line'   => (int)($m['line'] ?? 0),
                'cqp'    => (int)$m['cqp_number'],
            ];
        }
        return json_encode(['principles' => $principles, 'violations' => $violations]);
    }
}
