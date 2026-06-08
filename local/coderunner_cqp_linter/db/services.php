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

/**
 * External functions and web services for local_coderunner_cqp_linter.
 *
 * @package    local_coderunner_cqp_linter
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_coderunner_cqp_linter_record_lint_event' => [
        'classname'     => 'local_coderunner_cqp_linter\external\record_lint_event',
        'methodname'    => 'execute',
        'description'   => 'Record a student CQP lint button interaction for research.',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],
    'local_coderunner_cqp_linter_run_lint' => [
        'classname'     => 'local_coderunner_cqp_linter\external\run_lint',
        'methodname'    => 'execute',
        'description'   => 'Run CQP lint analysis on student code via Jobe and return structured results.',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => true,
    ],
];
