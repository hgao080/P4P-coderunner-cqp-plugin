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

namespace local_coderunner_cqp_linter\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for local_coderunner_cqp_linter.
 *
 * The plugin stores one row per button click in local_crcqp_lint_event,
 * which contains the student's user id and lint results for research purposes.
 *
 * @package    local_coderunner_cqp_linter
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Describe the personal data stored by this plugin.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_crcqp_lint_event',
            [
                'userid'      => 'privacy:metadata:event:userid',
                'questionid'  => 'privacy:metadata:event:questionid',
                'attemptid'   => 'privacy:metadata:event:attemptid',
                'slot'        => 'privacy:metadata:event:slot',
                'issuecount'  => 'privacy:metadata:event:issuecount',
                'resultsjson' => 'privacy:metadata:event:resultsjson',
                'timecreated' => 'privacy:metadata:event:timecreated',
            ],
            'privacy:metadata:event'
        );
        return $collection;
    }

    /**
     * Return the contexts in which this plugin stores data for the given user.
     * All events are attributed to the system context.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        if ($DB->record_exists('local_crcqp_lint_event', ['userid' => $userid])) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * List all users who have data in the given context.
     */
    public static function get_users_in_context(userlist $userlist): void {
        global $DB;
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        $userids = $DB->get_fieldset_select('local_crcqp_lint_event', 'DISTINCT userid', '1=1');
        $userlist->add_users($userids);
    }

    /**
     * Export all lint events belonging to the approved user(s).
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_SYSTEM) {
                continue;
            }
            $events = $DB->get_records('local_crcqp_lint_event', ['userid' => $userid], 'timecreated ASC');
            $rows = array_values(array_map(function($e) {
                return (object)[
                    'questionid'  => $e->questionid,
                    'attemptid'   => $e->attemptid,
                    'slot'        => $e->slot,
                    'issuecount'  => $e->issuecount,
                    'resultsjson' => $e->resultsjson,
                    'timecreated' => \core_privacy\local\request\transform::datetime($e->timecreated),
                ];
            }, $events));
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_coderunner_cqp_linter')],
                (object)['lint_events' => $rows]
            );
        }
    }

    /**
     * Delete all data for all users in the given context.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context->contextlevel === CONTEXT_SYSTEM) {
            $DB->delete_records('local_crcqp_lint_event');
        }
    }

    /**
     * Delete all data for the approved user(s).
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel === CONTEXT_SYSTEM) {
                $DB->delete_records('local_crcqp_lint_event', ['userid' => $userid]);
            }
        }
    }

    /**
     * Delete data for a list of approved users within a context.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        list($sql, $params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_crcqp_lint_event', "userid $sql", $params);
    }
}
