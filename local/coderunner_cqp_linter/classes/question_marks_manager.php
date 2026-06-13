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
 * Injects and removes the CQP style check test case from CodeRunner questions.
 *
 * Manages three things on a target question:
 *  1. A block of Python code prepended to the CodeRunner template (writes support
 *     files as base64 and sets __cqp_disabled__, __cqp_min_severity__,
 *     __student_answer__ for the test case to consume).
 *  2. A row in question_coderunner_tests that calls check_style() and expects
 *     'Style OK'.
 *  3. allornothing=0 on the question (required for partial credit; the original
 *     value is saved in local_crcqp_qconfig and restored on disable).
 *
 * @package    local_coderunner_cqp_linter
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_marks_manager {

    /** Markers wrapping the injected prepend block in the CodeRunner template. */
    const MARKER_BEGIN = '# === CQP_STYLE_MARKS_BEGIN ===';
    const MARKER_END   = '# === CQP_STYLE_MARKS_END ===';

    /** Value stored in question_coderunner_tests.extra to identify injected rows. */
    const TEST_EXTRA = '__cqp_style_marks__';

    /**
     * Inject (or re-inject) the style check test case into a CodeRunner question.
     *
     * Safe to call on an already-enabled question — removes any previous injection
     * first so re-enabling with new config is clean.
     *
     * @param int    $questionid  Target question ID.
     * @param float  $weight      Mark weight for the style check test case.
     * @param string $disabled    Comma-separated pylint codes/names to suppress.
     * @param string $minseverity Minimum violation severity to count ('convention', 'warning', etc.)
     * @return int   The original allornothing value (before this plugin set it to 0).
     * @throws \moodle_exception If support files are missing or CodeRunner options not found.
     */
    public static function enable(int $questionid, float $weight, string $disabled, string $minseverity): int {
        global $DB;

        // Capture original allornothing before removing any existing injection.
        // If already enabled, the saved value in qconfig is the true original (CodeRunner's
        // current value would already be 0 from the previous enable call).
        $qconfig = $DB->get_record('local_crcqp_qconfig', ['questionid' => $questionid]);
        $opts    = $DB->get_record('question_coderunner_options', ['questionid' => $questionid], '*', MUST_EXIST);

        if ($qconfig && !empty($qconfig->marks_enabled) && $qconfig->original_allornothing !== null) {
            $originalaon = (int)$qconfig->original_allornothing;
        } else {
            $originalaon = (int)$opts->allornothing;
        }

        // Remove any previous injection (idempotent).
        self::remove_injection($questionid);

        // Re-read opts after stripping the old prepend from the template field.
        $opts = $DB->get_record('question_coderunner_options', ['questionid' => $questionid], '*', MUST_EXIST);

        // Set allornothing=0 to enable partial credit.
        $DB->set_field('question_coderunner_options', 'allornothing', 0, ['questionid' => $questionid]);

        // Determine base template. If the question has no custom template, copy the
        // prototype's template (and iscombinatortemplate flag) so our prepend has
        // something to attach to.
        if (empty($opts->template)) {
            [$basetemplate, $iscombinator] = self::fetch_prototype_template((string)$opts->coderunnertype);
            $DB->set_field('question_coderunner_options', 'iscombinatortemplate', $iscombinator, ['questionid' => $questionid]);
        } else {
            $basetemplate = $opts->template;
        }

        // Build and save the prepend + base template.
        $prepend = self::build_prepend($disabled, $minseverity);
        $DB->set_field('question_coderunner_options', 'template',
            $prepend . "\n" . $basetemplate, ['questionid' => $questionid]);

        // Insert the style check test case.
        $DB->insert_record('question_coderunner_tests', (object)[
            'questionid'      => $questionid,
            'testtype'        => 0,
            'testcode'        => "from cqp_style_check import check_style\n" .
                                 "print(check_style(__student_answer__, __cqp_disabled__, __cqp_min_severity__))",
            'stdin'           => '',
            'expected'        => 'Style OK',
            'extra'           => self::TEST_EXTRA,
            'useasexample'    => 0,
            'display'         => 'SHOW',
            'hiderestiffail'  => 0,
            'mark'            => $weight,
        ]);

        self::notify_question_edited($questionid);

        return $originalaon;
    }

    /**
     * Remove the style check injection and restore allornothing.
     *
     * @param int $questionid Target question ID.
     * @param int $originalaon The original allornothing value to restore.
     */
    public static function disable(int $questionid, int $originalaon): void {
        global $DB;
        self::remove_injection($questionid);
        $DB->set_field('question_coderunner_options', 'allornothing', $originalaon, ['questionid' => $questionid]);
        self::notify_question_edited($questionid);
    }

    /**
     * Strip the prepend block from the template and delete the injected test case.
     */
    private static function remove_injection(int $questionid): void {
        global $DB;

        $opts = $DB->get_record('question_coderunner_options', ['questionid' => $questionid]);
        if ($opts && !empty($opts->template)) {
            $cleaned = self::strip_prepend($opts->template);
            // If stripped leaves only whitespace, null out the template so the question
            // falls back to prototype inheritance.
            $DB->set_field('question_coderunner_options', 'template',
                trim($cleaned) !== '' ? $cleaned : null, ['questionid' => $questionid]);
        }

        // 'extra' is a text column — Moodle DML forbids plain array conditions on text.
        $DB->delete_records_select(
            'question_coderunner_tests',
            'questionid = :qid AND ' . $DB->sql_compare_text('extra') . ' = ' . $DB->sql_compare_text(':extra'),
            ['qid' => $questionid, 'extra' => self::TEST_EXTRA]
        );
    }

    /**
     * Remove the CQP prepend block from a template string.
     */
    private static function strip_prepend(string $template): string {
        $pattern = '/' . preg_quote(self::MARKER_BEGIN, '/') . '.*?' . preg_quote(self::MARKER_END, '/') . '\n?/s';
        return preg_replace($pattern, '', $template) ?? $template;
    }

    /**
     * Fetch the template and iscombinatortemplate from the closest prototype.
     *
     * Returns a two-element array: [template string, iscombinatortemplate int].
     * Falls back to a minimal per-test template if no prototype is found.
     */
    private static function fetch_prototype_template(string $coderunnertype): array {
        global $DB;

        $proto = $DB->get_record_select(
            'question_coderunner_options',
            'coderunnertype = :type AND prototypetype > 0',
            ['type' => $coderunnertype],
            'id, template, iscombinatortemplate',
            IGNORE_MULTIPLE
        );

        if ($proto && !empty($proto->template)) {
            return [$proto->template, (int)($proto->iscombinatortemplate ?? 0)];
        }

        // Fallback: a minimal per-test template that works for any python3 question.
        return ["{{ STUDENT_ANSWER }}\n\n{{ TEST.testcode }}", 0];
    }

    /**
     * Build the Python prepend block that writes support files and sets config vars.
     *
     * The block is bounded by MARKER_BEGIN / MARKER_END so it can be stripped later.
     * All three support files are embedded as base64 so Jobe receives a self-contained
     * program — no file_list uploads needed.
     */
    private static function build_prepend(string $disabled, string $minseverity): string {
        $dir = dirname(__DIR__) . '/python/';

        foreach (['cqp_principles.py', 'cqp_custom_checkers.py', 'cqp_style_check.py'] as $file) {
            if (!file_exists($dir . $file)) {
                throw new \moodle_exception('error', 'local_coderunner_cqp_linter', '',
                    "CQP support file missing: {$file}");
            }
        }

        $principlesb64  = base64_encode(file_get_contents($dir . 'cqp_principles.py'));
        $checkerb64     = base64_encode(file_get_contents($dir . 'cqp_custom_checkers.py'));
        $stylecheckb64  = base64_encode(file_get_contents($dir . 'cqp_style_check.py'));

        $safedisabled   = addslashes($disabled);
        $safeminsev     = addslashes($minseverity);

        return self::MARKER_BEGIN . "\n" .
               "import base64 as _b64\n" .
               "with open('cqp_principles.py', 'wb') as _f:\n" .
               "    _f.write(_b64.b64decode('{$principlesb64}'))\n" .
               "with open('cqp_custom_checkers.py', 'wb') as _f:\n" .
               "    _f.write(_b64.b64decode('{$checkerb64}'))\n" .
               "with open('cqp_style_check.py', 'wb') as _f:\n" .
               "    _f.write(_b64.b64decode('{$stylecheckb64}'))\n" .
               "__cqp_disabled__ = '{$safedisabled}'\n" .
               "__cqp_min_severity__ = '{$safeminsev}'\n" .
               "__student_answer__ = \"\"\"{{ STUDENT_ANSWER | e('py') }}\"\"\"\n" .
               self::MARKER_END;
    }

    /**
     * Notify Moodle that a question was edited so all caches are properly invalidated.
     */
    private static function notify_question_edited(int $questionid): void {
        global $CFG;
        require_once($CFG->dirroot . '/question/engine/bank.php');
        \question_bank::notify_question_edited($questionid);
    }
}
