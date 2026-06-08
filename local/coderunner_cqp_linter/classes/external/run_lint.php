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

/**
 * External function to run CQP lint analysis on student code via Jobe.
 *
 * Called from cqp_linter.js when a student clicks "Check Code Quality".
 * Submits code to Jobe with cqp_checker.py support files, applies per-question
 * config, and returns I.json-shaped payload for client-side rendering.
 *
 * @package    local_coderunner_cqp_linter
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class run_lint extends \external_api {

    /** Severity ranking — lower number = less severe. */
    const SEVERITY_ORDER = [
        'convention' => 0,
        'refactor'   => 1,
        'warning'    => 2,
        'error'      => 3,
        'fatal'      => 4,
    ];

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
     * Run CQP lint on the given code and return JSON result string.
     *
     * @param int    $questionid
     * @param string $code
     * @return string JSON-encoded I.json payload.
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

        if (trim($params['code']) === '') {
            return json_encode(array_merge($empty, ['error' => 'No code provided.']));
        }

        if (!$DB->record_exists('question', ['id' => $params['questionid']])) {
            return json_encode(array_merge($empty, ['error' => 'Question not found.']));
        }

        if (!\local_coderunner_cqp_linter\question_helper::is_lint_enabled($params['questionid'])) {
            return json_encode(array_merge($empty, ['error' => 'Linting not enabled for this question.']));
        }

        $config = \local_coderunner_cqp_linter\question_helper::get_lint_config($params['questionid']);

        $runner = new \local_coderunner_cqp_linter\tools\pylint\runner();
        $result = $runner->lint_for_button($params['code'], [
            'disable' => $config['disable'],
        ]);

        if (empty($result['success'])) {
            return json_encode($result);
        }

        $result = self::apply_min_severity($result, $config['min_severity'] ?? 'convention');

        return json_encode($result);
    }

    /**
     * Strip messages below the minimum severity threshold.
     *
     * @param array  $payload     Decoded I.json payload.
     * @param string $minseverity One of: convention, refactor, warning, error, fatal.
     * @return array Filtered payload with updated counts.
     */
    private static function apply_min_severity(array $payload, string $minseverity): array {
        $minrank = self::SEVERITY_ORDER[$minseverity] ?? 0;

        if ($minrank === 0) {
            return $payload;
        }

        $payload['messages'] = array_values(array_filter(
            $payload['messages'],
            fn($m) => (self::SEVERITY_ORDER[$m['type']] ?? 0) >= $minrank
        ));

        $principles = [];
        foreach ($payload['principles'] as $p) {
            $p['messages'] = array_values(array_filter(
                $p['messages'],
                fn($m) => (self::SEVERITY_ORDER[$m['type']] ?? 0) >= $minrank
            ));
            $p['count'] = count($p['messages']);
            if ($p['count'] > 0) {
                $principles[] = $p;
            }
        }
        $payload['principles']   = $principles;
        $payload['total_issues'] = count($payload['messages']);

        return $payload;
    }

    /**
     * Describe the return value.
     */
    public static function execute_returns(): \external_value {
        return new \external_value(PARAM_RAW, 'JSON-encoded CQP lint result (I.json shape)');
    }
}
