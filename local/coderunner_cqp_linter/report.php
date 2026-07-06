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
 * Research data export for local_coderunner_cqp_linter.
 *
 * Accessible at: Site administration > Local plugins > CodeRunner CQP Linter > Export research data
 *
 * @package    local_coderunner_cqp_linter
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$download = optional_param('download', '', PARAM_ALPHA);

$context = context_system::instance();
require_login();
require_capability('local/coderunner_cqp_linter:manageglobalsettings', $context);

// Defensive: research output must contain genuine student interactions only.
// Exclude previews / interactions with no real quiz attempt, and any site
// administrators — even if older rows pre-date the recording-time guard.
$params = [];
$where = 'e.attemptid > 0';
$adminids = array_filter(array_map('trim', explode(',', (string)$CFG->siteadmins)));
if (!empty($adminids)) {
    [$notinsql, $notinparams] = $DB->get_in_or_equal($adminids, SQL_PARAMS_NAMED, 'adm', false);
    $where .= " AND e.userid $notinsql";
    $params += $notinparams;
}

// Shared FROM/WHERE — join to user and question for readable columns and to
// keep the page's count consistent with what the CSV actually exports.
$fromwhere = "FROM {local_crcqp_lint_event} e
              JOIN {user}     u ON u.id = e.userid
              JOIN {question} q ON q.id = e.questionid
             WHERE $where";

// ── CSV download ─────────────────────────────────────────────────────────────
if ($download === 'csv') {
    $filename = 'cqp_lint_events_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store');

    $out = fopen('php://output', 'w');

    // Header row.
    fputcsv($out, [
        'id',
        'username',
        'firstname',
        'lastname',
        'question_name',
        'questionid',
        'attemptid',
        'slot',
        'issuecount',
        'resultsjson',
        'eventtype',
        'timecreated_unix',
        'timecreated_readable',
        'code',                  // the student's full source at this event
        'airesponse',            // full AI analysis response JSON (ai events)
    ]);

    // Stream via a recordset so the full dataset (including the large code and
    // airesponse TEXT columns) is never all held in PHP memory at once.
    $sql = "SELECT e.id,
                   u.username,
                   u.firstname,
                   u.lastname,
                   q.name        AS question_name,
                   e.questionid,
                   e.attemptid,
                   e.slot,
                   e.issuecount,
                   e.resultsjson,
                   e.eventtype,
                   e.code,
                   e.airesponse,
                   e.timecreated
              $fromwhere
          ORDER BY e.timecreated ASC";

    $rs = $DB->get_recordset_sql($sql, $params);
    foreach ($rs as $row) {
        fputcsv($out, [
            $row->id,
            $row->username,
            $row->firstname,
            $row->lastname,
            $row->question_name,
            $row->questionid,
            $row->attemptid,
            $row->slot,
            $row->issuecount,
            $row->resultsjson,
            $row->eventtype,
            $row->timecreated,
            userdate($row->timecreated, '%Y-%m-%d %H:%M:%S'),
            $row->code,
            $row->airesponse,
        ]);
    }
    $rs->close();

    fclose($out);
    exit;
}

// ── HTML page ─────────────────────────────────────────────────────────────────
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/coderunner_cqp_linter/report.php'));
$PAGE->set_title(get_string('report_title', 'local_coderunner_cqp_linter'));
$PAGE->set_heading(get_string('report_title', 'local_coderunner_cqp_linter'));
$PAGE->set_pagelayout('admin');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('report_title', 'local_coderunner_cqp_linter'));

// Count only — deliberately do not load the event rows (which include the large
// code and airesponse columns) into memory just to render this page. The full
// dataset is streamed on demand via the CSV download.
$total = $DB->count_records_sql("SELECT COUNT(1) $fromwhere", $params);
echo html_writer::tag('p', get_string('report_rowcount', 'local_coderunner_cqp_linter', $total));

if ($total === 0) {
    echo $OUTPUT->notification(get_string('report_nodata', 'local_coderunner_cqp_linter'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$csvurl = new moodle_url('/local/coderunner_cqp_linter/report.php', ['download' => 'csv']);
echo html_writer::link($csvurl,
    get_string('report_download_csv', 'local_coderunner_cqp_linter'),
    ['class' => 'btn btn-primary']
);

echo $OUTPUT->footer();
