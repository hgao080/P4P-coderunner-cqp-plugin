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

// Build the dataset — join to user and question for readable columns.
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
               e.timecreated
          FROM {local_crcqp_lint_event} e
          JOIN {user}     u ON u.id = e.userid
          JOIN {question} q ON q.id = e.questionid
      ORDER BY e.timecreated ASC";

$rows = $DB->get_records_sql($sql);

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
        'principles_violated',   // extracted from JSON for convenience
        'resultsjson',
        'timecreated_unix',
        'timecreated_readable',
    ]);

    foreach ($rows as $row) {
        $decoded = json_decode($row->resultsjson, true);
        $principlenumbers = [];
        if (!empty($decoded['principles'])) {
            foreach ($decoded['principles'] as $p) {
                $principlenumbers[] = 'CQP' . $p['n'] . '×' . $p['count'];
            }
        }

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
            implode('; ', $principlenumbers),
            $row->resultsjson,
            $row->timecreated,
            userdate($row->timecreated, '%Y-%m-%d %H:%M:%S'),
        ]);
    }

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

$total = count($rows);
echo html_writer::tag('p', get_string('report_rowcount', 'local_coderunner_cqp_linter', $total));

$csvurl = new moodle_url('/local/coderunner_cqp_linter/report.php', ['download' => 'csv']);
echo html_writer::link($csvurl,
    get_string('report_download_csv', 'local_coderunner_cqp_linter'),
    ['class' => 'btn btn-primary', 'style' => 'margin-bottom:1rem;']
);

if ($total === 0) {
    echo $OUTPUT->notification(get_string('report_nodata', 'local_coderunner_cqp_linter'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// Preview table — capped at 200 rows so the page stays manageable.
$preview = array_slice((array)$rows, 0, 200, true);

$table = new html_table();
$table->attributes['class'] = 'generaltable table-sm';
$table->head = [
    '#',
    get_string('report_col_user', 'local_coderunner_cqp_linter'),
    get_string('report_col_question', 'local_coderunner_cqp_linter'),
    get_string('report_col_attempt', 'local_coderunner_cqp_linter'),
    get_string('report_col_issues', 'local_coderunner_cqp_linter'),
    get_string('report_col_principles', 'local_coderunner_cqp_linter'),
    get_string('report_col_time', 'local_coderunner_cqp_linter'),
];

foreach ($preview as $row) {
    $decoded = json_decode($row->resultsjson, true);
    $badges = [];
    if (!empty($decoded['principles'])) {
        foreach ($decoded['principles'] as $p) {
            $badges[] = html_writer::tag('span',
                'CQP' . (int)$p['n'] . ' &times;' . (int)$p['count'],
                ['class' => 'badge badge-secondary', 'style' => 'margin-right:2px;']
            );
        }
    }

    $table->data[] = [
        $row->id,
        s($row->username) . ' (' . s($row->firstname . ' ' . $row->lastname) . ')',
        s($row->question_name),
        $row->attemptid > 0 ? $row->attemptid : '—',
        $row->issuecount,
        implode(' ', $badges) ?: '—',
        userdate($row->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
    ];
}

echo html_writer::table($table);

if ($total > 200) {
    echo html_writer::tag('p',
        get_string('report_truncated', 'local_coderunner_cqp_linter', $total),
        ['class' => 'text-muted']
    );
}

echo $OUTPUT->footer();
