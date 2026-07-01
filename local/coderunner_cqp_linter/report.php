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
               e.eventtype,
               e.code,
               e.airesponse,
               e.timecreated
          FROM {local_crcqp_lint_event} e
          JOIN {user}     u ON u.id = e.userid
          JOIN {question} q ON q.id = e.questionid
         WHERE $where
      ORDER BY e.timecreated ASC";

$rows = $DB->get_records_sql($sql, $params);

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
        'violations',            // per-check detail: code:symbol@line (CQPn)
        'resultsjson',
        'eventtype',
        'timecreated_unix',
        'timecreated_readable',
        'code',                  // the student's full source at this event
        'airesponse',            // full AI analysis response JSON (ai events)
    ]);

    foreach ($rows as $row) {
        $decoded = json_decode($row->resultsjson, true);
        $principlenumbers = [];
        if (!empty($decoded['principles'])) {
            foreach ($decoded['principles'] as $p) {
                $label = 'CQP' . $p['n'];
                if (!empty($p['name'])) {
                    $label .= ' ' . $p['name'];
                }
                $principlenumbers[] = $label . '×' . $p['count'];
            }
        }
        $violationlist = [];
        if (!empty($decoded['violations'])) {
            foreach ($decoded['violations'] as $v) {
                $violationlist[] = ($v['code'] ?? '') . ':' . ($v['symbol'] ?? '') .
                    '@' . ($v['line'] ?? '') . ' (CQP' . ($v['cqp'] ?? '') . ')';
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
            implode('; ', $violationlist),
            $row->resultsjson,
            $row->eventtype,
            $row->timecreated,
            userdate($row->timecreated, '%Y-%m-%d %H:%M:%S'),
            $row->code,
            $row->airesponse,
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
    get_string('report_col_violations', 'local_coderunner_cqp_linter'),
    get_string('report_col_eventtype', 'local_coderunner_cqp_linter'),
    get_string('report_col_code', 'local_coderunner_cqp_linter'),
    get_string('report_col_airesponse', 'local_coderunner_cqp_linter'),
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

    $checks = [];
    if (!empty($decoded['violations'])) {
        foreach ($decoded['violations'] as $v) {
            $checks[] = s(($v['code'] ?? '') . '@' . ($v['line'] ?? ''));
        }
    }

    // Compact one-line preview of the captured code; full source is in the CSV.
    $codepreview = trim((string)($row->code ?? ''));
    if ($codepreview === '') {
        $codecell = '—';
    } else {
        $codecell = html_writer::tag('code',
            s(shorten_text(preg_replace('/\s+/', ' ', $codepreview), 60)),
            ['title' => s($codepreview), 'style' => 'white-space:nowrap;']);
    }

    // Compact one-line preview of the AI response (ai events only); full JSON
    // is in the CSV.
    $aipreview = trim((string)($row->airesponse ?? ''));
    if ($aipreview === '') {
        $aicell = '—';
    } else {
        $aicell = html_writer::tag('code',
            s(shorten_text(preg_replace('/\s+/', ' ', $aipreview), 60)),
            ['title' => s($aipreview), 'style' => 'white-space:nowrap;']);
    }

    $table->data[] = [
        $row->id,
        s($row->username) . ' (' . s($row->firstname . ' ' . $row->lastname) . ')',
        s($row->question_name),
        $row->attemptid > 0 ? $row->attemptid : '—',
        $row->issuecount,
        implode(' ', $badges) ?: '—',
        implode(', ', $checks) ?: '—',
        s($row->eventtype ?? 'button'),
        $codecell,
        $aicell,
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
