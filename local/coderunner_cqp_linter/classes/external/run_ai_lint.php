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

namespace local_coderunner_cqp_linter\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use local_coderunner_cqp_linter\tools\ai\analyzer;
use local_coderunner_cqp_linter\question_helper;

/**
 * External function to run AI-based CQP analysis on student code.
 *
 * Called from cqp_linter.js after the static "Check Code Quality" results
 * render, when AI analysis is enabled site-wide (for the button trigger) and
 * for the specific question. Returns a separate, clearly-labelled payload.
 *
 * @package    local_coderunner_cqp_linter
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class run_ai_lint extends \external_api {

    /**
     * Describe the input parameters.
     */
    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'questionid' => new \external_value(PARAM_INT, 'Question ID'),
            'code'       => new \external_value(PARAM_RAW, 'Student Python source code'),
        ]);
    }

    /**
     * Run AI CQP analysis and return a JSON result string.
     *
     * @param int    $questionid
     * @param string $code
     * @return string JSON-encoded payload (same shape as run_lint, source='ai').
     */
    public static function execute(int $questionid, string $code): string {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'questionid' => $questionid,
            'code'       => $code,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_login();

        $empty = ['success' => false, 'total_issues' => 0, 'messages' => [], 'principles' => []];

        if (!analyzer::is_globally_enabled() || !analyzer::runs_on('button')) {
            return json_encode(array_merge($empty, ['error' => 'AI analysis is not enabled.']));
        }
        if (!$DB->record_exists('question', ['id' => $params['questionid']])) {
            return json_encode(array_merge($empty, ['error' => 'Question not found.']));
        }
        if (!question_helper::is_lint_enabled($params['questionid'])
                || !question_helper::is_ai_enabled($params['questionid'])) {
            return json_encode(array_merge($empty, ['error' => 'AI analysis not enabled for this question.']));
        }

        $analyzer = new analyzer();
        $principles  = question_helper::get_ai_principles($params['questionid']);
        $problemtext = question_helper::get_problem_text($params['questionid']);
        $result = $analyzer->analyze($params['code'], $principles, $problemtext);

        return json_encode($result);
    }

    /**
     * Describe the return value.
     */
    public static function execute_returns(): \external_value {
        return new \external_value(PARAM_RAW, 'JSON-encoded AI CQP analysis result');
    }
}
