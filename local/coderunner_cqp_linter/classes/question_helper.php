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

use local_coderunner_cqp_linter\tools\pylint\result as pylint_result;
use local_coderunner_cqp_linter\tools\pylint\runner as pylint_runner;

/**
 * Helper for extracting student code and configuration from question attempts.
 *
 * @package    local_coderunner_cqp_linter
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_helper {

    /**
     * Check if a question attempt is for a Python CodeRunner question.
     *
     * @param \question_attempt $qa The question attempt.
     * @return bool True if this is a Python CodeRunner question.
     */
    public static function is_python_coderunner(\question_attempt $qa): bool {
        $question = $qa->get_question();

        // Check it's a CodeRunner question.
        if ($question->get_type_name() !== 'coderunner') {
            return false;
        }

        // Check the language is Python.
        // CodeRunner stores language in the question object.
        if (isset($question->language)) {
            $lang = strtolower($question->language);
            return strpos($lang, 'python') !== false;
        }

        // Fallback: check the coderunnertype field.
        if (isset($question->coderunnertype)) {
            $type = strtolower($question->coderunnertype);
            return strpos($type, 'python') !== false;
        }

        return false;
    }

    /**
     * Get the student's submitted code from a question attempt.
     *
     * @param \question_attempt $qa The question attempt.
     * @return string|null The student's code, or null if no answer submitted.
     */
    public static function get_student_code(\question_attempt $qa): ?string {
        $code = $qa->get_last_qt_var('answer');
        if ($code === null || trim($code) === '') {
            return null;
        }
        return $code;
    }

    /**
     * Check if a question attempt has been graded (has feedback to show).
     *
     * @param \question_attempt $qa The question attempt.
     * @return bool True if the question has been graded at least once.
     */
    public static function has_been_graded(\question_attempt $qa): bool {
        // CodeRunner stores test outcome in _testoutcome after Check,
        // or _precheckoutcome after Precheck.
        return $qa->get_last_qt_var('_testoutcome') !== null ||
               $qa->get_last_qt_var('_precheckoutcome') !== null;
    }

    /**
     * Check if linting is enabled for a specific question.
     *
     * @param int $questionid The question ID.
     * @return bool True if linting is enabled for this question.
     */
    public static function is_lint_enabled(int $questionid): bool {
        $config = self::get_qconfig($questionid);
        return $config ? (bool)$config->enabled : false;
    }

    /**
     * Check if AI analysis is enabled for a specific question.
     *
     * Requires the per-question opt-in and a configured site-wide AI API key.
     *
     * @param int $questionid The question ID.
     * @return bool True if AI analysis should run for this question.
     */
    public static function is_ai_enabled(int $questionid): bool {
        if (!\local_coderunner_cqp_linter\tools\ai\analyzer::is_globally_enabled()) {
            return false;
        }
        $config = self::get_qconfig($questionid);
        return $config ? !empty($config->ai_enabled) : false;
    }

    /**
     * The CQP principle numbers AI should assess for a specific question.
     *
     * A NULL stored value (e.g. a question upgraded before per-question
     * principles existed, or never saved through the form) means "all semantic
     * principles". An empty string means the teacher deliberately selected none.
     *
     * @param int $questionid The question ID.
     * @return int[] Sorted principle numbers (may be empty).
     */
    public static function get_ai_principles(int $questionid): array {
        $semantic = \local_coderunner_cqp_linter\tools\ai\analyzer::SEMANTIC_PRINCIPLES;
        $config = self::get_qconfig($questionid);
        if (!$config || !property_exists($config, 'ai_principles') || $config->ai_principles === null) {
            return $semantic;
        }
        $valid = array_flip($semantic);
        $nums = array_values(array_unique(array_filter(
            array_map('intval', array_filter(explode(',', (string)$config->ai_principles), 'strlen')),
            fn($n) => isset($valid[$n])
        )));
        sort($nums);
        return $nums;
    }

    /**
     * Plain-text problem statement for a question, for use as AI context.
     *
     * Strips the question text HTML and caps the length so it can never dominate
     * the AI request. Returns '' when there is no usable text.
     *
     * @param int $questionid The question ID.
     * @return string Plain text (possibly empty).
     */
    public static function get_problem_text(int $questionid): string {
        global $DB;

        $q = $DB->get_record('question', ['id' => $questionid],
            'questiontext, questiontextformat', IGNORE_MISSING);
        if (!$q || trim((string)$q->questiontext) === '') {
            return '';
        }

        $text = trim(content_to_text($q->questiontext, $q->questiontextformat));
        $cap = \local_coderunner_cqp_linter\tools\ai\analyzer::MAX_PROBLEM_TEXT;
        if (\core_text::strlen($text) > $cap) {
            $text = \core_text::substr($text, 0, $cap);
        }
        return $text;
    }

    /**
     * Decide whether a lint interaction should be recorded as research data.
     *
     * Only genuine student interactions are kept. Excludes:
     *  - site admins;
     *  - previews and other interactions with no real quiz attempt (attemptid 0),
     *    which on this plugin only ever come from staff preview pages;
     *  - users with teacher-level capability in the quiz (e.g. a teacher doing a
     *    test attempt).
     *
     * @param int $userid The user performing the interaction.
     * @param int $attemptid The quiz attempt ID, or 0 for previews.
     * @return bool True if the event should be stored.
     */
    public static function should_record_event(int $userid, int $attemptid): bool {
        global $DB;

        // Site admins are never research subjects.
        if (is_siteadmin($userid)) {
            return false;
        }

        // No real quiz attempt → staff preview/ad-hoc run, not student data.
        if ($attemptid <= 0) {
            return false;
        }

        // Resolve the quiz module context and exclude teachers/managers.
        $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid], 'quiz', IGNORE_MISSING);
        if (!$attempt) {
            return false;
        }
        try {
            $cm = get_coursemodule_from_instance('quiz', $attempt->quiz, 0, false, MUST_EXIST);
            $context = \context_module::instance($cm->id);
        } catch (\Throwable $e) {
            // Context can't be resolved; it's still a real attempt, so keep it.
            return true;
        }

        // Anyone who can grade the quiz is staff, not a student.
        return !has_capability('mod/quiz:grade', $context, $userid);
    }

    /**
     * Get the CQP config record for a question, falling back to previous versions.
     *
     * In Moodle 4.x, editing a question creates a new question ID (new version).
     * This method checks the direct row first, then walks back through
     * question_versions to find the most recent version that has a config row,
     * copies it to the current question ID, and returns it — so subsequent reads
     * are always fast direct lookups.
     *
     * @param int $questionid The question ID.
     * @return \stdClass|false The config record, or false if none exists.
     */
    public static function get_qconfig(int $questionid) {
        global $DB;

        $config = $DB->get_record('local_crcqp_qconfig', ['questionid' => $questionid]);
        if ($config) {
            return $config;
        }

        // Moodle 4+: check previous versions of the same bank entry.
        if (!$DB->get_manager()->table_exists('question_versions')) {
            return false;
        }

        $ver = $DB->get_record('question_versions', ['questionid' => $questionid]);
        if (!$ver) {
            return false;
        }

        $rows = $DB->get_records_sql(
            'SELECT qc.* FROM {local_crcqp_qconfig} qc
              JOIN {question_versions} qv ON qv.questionid = qc.questionid
             WHERE qv.questionbankentryid = :entryid
             ORDER BY qv.version DESC',
            ['entryid' => $ver->questionbankentryid],
            0, 1
        );
        $prevconfig = $rows ? reset($rows) : false;
        if (!$prevconfig) {
            return false;
        }

        // Lazily copy the config to the current question ID.
        $now = time();
        $newconfig = clone $prevconfig;
        unset($newconfig->id);
        $newconfig->questionid   = $questionid;
        $newconfig->timecreated  = $now;
        $newconfig->timemodified = $now;
        try {
            $newconfig->id = $DB->insert_record('local_crcqp_qconfig', $newconfig);
        } catch (\dml_exception $e) {
            // Race condition: another request already inserted the row.
            $existing = $DB->get_record('local_crcqp_qconfig', ['questionid' => $questionid]);
            return $existing ?: $prevconfig;
        }

        return $newconfig;
    }

    /**
     * Resolve the Moodle context that a given question lives in.
     *
     * Walks question → question_versions → question_bank_entries →
     * question_categories → context. Used for capability checks on the
     * per-question management page.
     *
     * @param int $questionid The question ID.
     * @return \context The containing context.
     * @throws \dml_exception If the question cannot be located.
     */
    public static function get_question_context(int $questionid): \context {
        global $DB;

        $sql = "SELECT ctx.id AS ctxid
                  FROM {question} q
                  JOIN {question_versions} qv ON qv.questionid = q.id
                  JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                  JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                  JOIN {context} ctx ON ctx.id = qc.contextid
                 WHERE q.id = :qid";
        $record = $DB->get_record_sql($sql, ['qid' => $questionid], MUST_EXIST);
        return \context::instance_by_id($record->ctxid);
    }

    /**
     * Get the effective lint configuration for a question.
     *
     * Merges admin defaults with per-question overrides.
     *
     * @param int $questionid The question ID.
     * @return array Configuration array with keys: disable, min_severity, rcfile.
     */
    public static function get_lint_config(int $questionid): array {
        // Start with admin defaults.
        $config = [
            'disable'      => get_config('local_coderunner_cqp_linter', 'default_disable') ?: 'import-error',
            'min_severity' => get_config('local_coderunner_cqp_linter', 'min_severity') ?: 'convention',
            'rcfile'       => get_config('local_coderunner_cqp_linter', 'pylintrc_path') ?: '',
        ];

        // Merge per-question disabled check overrides.
        $qconfig = self::get_qconfig($questionid);
        if ($qconfig && !empty($qconfig->disabled_checks)) {
            $config['disable'] .= ',' . $qconfig->disabled_checks;
        }

        return $config;
    }

    /**
     * Build the resultsjson string for a lint_event row from a pylint result.
     *
     * Produces:
     *   {"principles":[{"n":1,"name":"Clear Presentation","count":3}],
     *    "violations":[{"code":"C0301","symbol":"line-too-long","line":19,"cqp":1}]}
     * which matches the format the JS sends via record_lint_event so both button
     * and submit rows are queryable the same way in report.php.
     *
     * @param \local_coderunner_cqp_linter\tools\pylint\result $result
     * @param string $minseverity
     * @return string JSON string.
     */
    public static function build_results_json(
        \local_coderunner_cqp_linter\tools\pylint\result $result,
        string $minseverity = 'convention'
    ): string {
        $counts = [];
        $names = [];
        $violations = [];
        foreach ($result->get_filtered($minseverity) as $msg) {
            $principle = cqp_mapper::get_principle($msg);
            $n = $principle['number'];
            $counts[$n] = ($counts[$n] ?? 0) + 1;
            $names[$n] = $principle['name'];
            $violations[] = [
                'code'   => $msg->messageid,
                'symbol' => $msg->symbol,
                'line'   => (int)$msg->line,
                'cqp'    => $n,
            ];
        }
        $principles = [];
        foreach ($counts as $n => $count) {
            $principles[] = ['n' => $n, 'name' => $names[$n], 'count' => $count];
        }
        usort($principles, fn($a, $b) => $a['n'] - $b['n']);
        return json_encode(['principles' => $principles, 'violations' => $violations]);
    }

    /**
     * Run pylint on a question attempt's code, using cache.
     *
     * @param \question_attempt $qa The question attempt.
     * @return pylint_result|null The lint result, or null if linting is not applicable.
     */
    public static function lint_question_attempt(\question_attempt $qa): ?pylint_result {
        if (!self::is_python_coderunner($qa)) {
            return null;
        }

        $question = $qa->get_question();
        if (!self::is_lint_enabled($question->id)) {
            return null;
        }

        $code = self::get_student_code($qa);
        if ($code === null) {
            return null;
        }

        $config = self::get_lint_config($question->id);

        // Check cache first.
        $cached = result_cache::get($code, $config);
        if ($cached !== null) {
            return $cached;
        }

        // Run pylint via Jobe.
        $runner = new pylint_runner();
        $result = $runner->lint($code, $config);

        // Cache the result.
        result_cache::set($code, $config, $result);

        return $result;
    }
}
