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
 * External function to record a student CQP lint button click.
 *
 * Called silently from cqp_linter.js each time a student clicks
 * "Check Code Quality". The row is written to local_crcqp_lint_event
 * for later research analysis.
 *
 * @package    local_coderunner_cqp_linter
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class record_lint_event extends \external_api {

    /** Allowed eventtype values from client JS. 'submit' is server-only. */
    private const ALLOWED_EVENTTYPES = ['cqp', 'check', 'precheck'];

    /**
     * Describe the input parameters.
     */
    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'questionid'  => new \external_value(PARAM_INT, 'Question ID'),
            'attemptid'   => new \external_value(PARAM_INT, 'Quiz attempt ID; 0 for previews', VALUE_DEFAULT, 0),
            'slot'        => new \external_value(PARAM_INT, 'Question slot in the quiz', VALUE_DEFAULT, 0),
            'issuecount'  => new \external_value(PARAM_INT, 'Total CQP issues found', VALUE_DEFAULT, 0),
            'resultsjson' => new \external_value(PARAM_RAW, 'JSON: principles violated and counts', VALUE_DEFAULT, '{}'),
            'eventtype'   => new \external_value(PARAM_ALPHA, 'What triggered this: cqp, check, or precheck', VALUE_DEFAULT, 'cqp'),
        ]);
    }

    /**
     * Record one lint interaction event.
     *
     * @param int    $questionid
     * @param int    $attemptid
     * @param int    $slot
     * @param int    $issuecount
     * @param string $resultsjson
     * @param string $eventtype
     * @return bool
     */
    public static function execute(
        int $questionid,
        int $attemptid,
        int $slot,
        int $issuecount,
        string $resultsjson,
        string $eventtype = 'cqp'
    ): bool {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'questionid'  => $questionid,
            'attemptid'   => $attemptid,
            'slot'        => $slot,
            'issuecount'  => $issuecount,
            'resultsjson' => $resultsjson,
            'eventtype'   => $eventtype,
        ]);

        // Validate context — require the user to be logged in (not a guest).
        $context = \context_system::instance();
        self::validate_context($context);
        require_login();

        // Verify the question exists and linting is enabled for it.
        if (!$DB->record_exists('question', ['id' => $params['questionid']])) {
            return false;
        }
        if (!\local_coderunner_cqp_linter\question_helper::is_lint_enabled($params['questionid'])) {
            return false;
        }

        // If an attemptid was given, verify it belongs to the current user.
        if ($params['attemptid'] > 0) {
            $attempt = $DB->get_record('quiz_attempts', ['id' => $params['attemptid']], 'userid', IGNORE_MISSING);
            if (!$attempt || (int)$attempt->userid !== (int)$USER->id) {
                return false;
            }
        }

        // Research data must contain genuine student interactions only — skip
        // site admins, staff previews (attemptid 0), and teacher test attempts.
        if (!\local_coderunner_cqp_linter\question_helper::should_record_event(
                (int)$USER->id, (int)$params['attemptid'])) {
            return false;
        }

        // Sanitise the JSON — re-encode to normalise and cap size. The cap is
        // generous because resultsjson now carries a per-violation list, which
        // can be sizeable for submissions with many issues.
        $rawjson = $params['resultsjson'];
        if (strlen($rawjson) > 65535) {
            $rawjson = '{}';
        }
        $decoded = json_decode($rawjson);
        $safejson = ($decoded !== null) ? json_encode($decoded) : '{}';

        $record = new \stdClass();
        $record->userid      = (int)$USER->id;
        $record->questionid  = $params['questionid'];
        $record->attemptid   = $params['attemptid'];
        $record->slot        = $params['slot'];
        $record->issuecount  = max(0, $params['issuecount']);
        $record->resultsjson = $safejson;
        $record->eventtype   = in_array($params['eventtype'], self::ALLOWED_EVENTTYPES, true)
                               ? $params['eventtype'] : 'cqp';
        $record->timecreated = time();

        $DB->insert_record('local_crcqp_lint_event', $record);

        return true;
    }

    /**
     * Describe the return value.
     */
    public static function execute_returns(): \external_value {
        return new \external_value(PARAM_BOOL, 'Whether the event was recorded');
    }
}
