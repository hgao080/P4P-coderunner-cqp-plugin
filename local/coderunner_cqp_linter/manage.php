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
 * Per-question pylint configuration page.
 *
 * @package    local_coderunner_cqp_linter
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$questionid = required_param('questionid', PARAM_INT);
$returnurl  = optional_param('returnurl', '', PARAM_LOCALURL);

require_login();

$question = $DB->get_record('question', ['id' => $questionid], '*', MUST_EXIST);
$context  = \local_coderunner_cqp_linter\question_helper::get_question_context($questionid);
require_capability('local/coderunner_cqp_linter:configure', $context);

// A module-level context causes $OUTPUT->header() to trigger navigation's
// load_module_settings(), which calls $PAGE->set_cm() with a course mismatch
// (the page course defaults to site) and throws a coding_exception. The manage
// page doesn't need to be scoped inside a module, so walk up to the course
// context when the question lives in a module-level question bank.
$pagecontext = ($context->contextlevel === CONTEXT_MODULE)
    ? $context->get_parent_context()
    : $context;
$PAGE->set_context($pagecontext);
$PAGE->set_url(new moodle_url('/local/coderunner_cqp_linter/manage.php', [
    'questionid' => $questionid,
]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('manage_title', 'local_coderunner_cqp_linter'));
$PAGE->set_heading(get_string('manage_heading', 'local_coderunner_cqp_linter'));

$existing = $DB->get_record('local_crcqp_qconfig', ['questionid' => $questionid]);

// Codes disabled for this question, used to render the checkbox grid unticked.
$disabledcodes = $existing
    ? array_values(array_filter(array_map('trim', explode(',', $existing->disabled_checks ?? ''))))
    : [];

$form = new \local_coderunner_cqp_linter\form\manage_form(null, [
    'questionid'    => $questionid,
    'questionname'  => $question->name,
    'returnurl'     => $returnurl,
    'disabledcodes' => $disabledcodes,
]);

// Drives the group toggle and per-card counters on the checkbox grid.
$PAGE->requires->js_init_code(\local_coderunner_cqp_linter\form\manage_form::get_checks_js(), true);

if ($existing) {
    $data = [
        'enabled'       => (int)$existing->enabled,
        'ai_enabled'    => (int)($existing->ai_enabled ?? 0),
        'custom_codes'  => (string)($existing->custom_codes ?? ''),
        'marks_enabled' => (int)($existing->marks_enabled ?? 0),
        'marks_weight'  => !empty($existing->marks_weight) ? (float)$existing->marks_weight : 1.0,
        'questionid'    => $questionid,
        'returnurl'     => $returnurl,
    ];
    // Pre-tick the AI principle checkboxes from the stored selection.
    $selected = \local_coderunner_cqp_linter\question_helper::get_ai_principles($questionid);
    foreach (\local_coderunner_cqp_linter\tools\ai\analyzer::SEMANTIC_PRINCIPLES as $pnum) {
        $data[\local_coderunner_cqp_linter\form\manage_form::AI_PRINCIPLE_PREFIX . $pnum] =
            in_array($pnum, $selected, true) ? 1 : 0;
    }
    $form->set_data($data);
}

$redirecturl = $returnurl !== '' ? new moodle_url($returnurl) : $PAGE->url;

if ($form->is_cancelled()) {
    redirect($redirecturl);
} else if ($data = $form->get_data()) {
    $now = time();

    // Collect unchecked codes. These are plain named inputs (not form elements),
    // so read them from the submitted request: an unticked checkbox is absent.
    $uncheckedcodes = [];
    foreach (\local_coderunner_cqp_linter\form\manage_form::get_all_codes() as $pnum => $codes) {
        foreach ($codes as $code => $symbol) {
            $fieldname = \local_coderunner_cqp_linter\form\manage_form::CHECK_PREFIX . $code;
            if (!optional_param($fieldname, 0, PARAM_BOOL)) {
                $uncheckedcodes[] = $code;
            }
        }
    }
    $questdisable = implode(',', $uncheckedcodes);

    // Collect the AI principle selection. Stored as a comma list; an empty
    // string means "none" (distinct from NULL, which means "all / never set").
    $aiprinciples = [];
    foreach (\local_coderunner_cqp_linter\tools\ai\analyzer::SEMANTIC_PRINCIPLES as $pnum) {
        if (!empty($data->{\local_coderunner_cqp_linter\form\manage_form::AI_PRINCIPLE_PREFIX . $pnum})) {
            $aiprinciples[] = $pnum;
        }
    }
    $aiprinciplesstr = implode(',', $aiprinciples);

    // Normalise the custom-codes box to a clean CSV of valid codes. Validation
    // has already rejected malformed tokens, so only valid ones remain here.
    $customcodes = \local_coderunner_cqp_linter\form\manage_form::parse_custom_codes(
        (string)($data->custom_codes ?? ''))['valid'];
    $customcodesstr = implode(',', $customcodes);

    $marksenabledold = !empty($existing->marks_enabled);
    $marksenablednew = !empty($data->marks_enabled);
    $weight          = max(0.001, (float)($data->marks_weight ?? 1.0));
    $disabled        = $questdisable;

    $originalaon = isset($existing->original_allornothing) ? (int)$existing->original_allornothing : null;

    if ($marksenablednew) {
        // Enable or re-enable (re-injects with current config).
        $originalaon = \local_coderunner_cqp_linter\question_marks_manager::enable(
            $questionid, $weight, $disabled
        );
    } else if ($marksenabledold && !$marksenablednew) {
        // Disabling marks mode: restore allornothing.
        $restorevalue = $originalaon ?? 1;
        \local_coderunner_cqp_linter\question_marks_manager::disable($questionid, $restorevalue);
        $originalaon = null;
    }

    $record = (object)[
        'questionid'            => $questionid,
        'enabled'               => !empty($data->enabled) ? 1 : 0,
        'ai_enabled'            => !empty($data->ai_enabled) ? 1 : 0,
        'ai_principles'         => $aiprinciplesstr,
        'disabled_checks'       => $questdisable !== '' ? $questdisable : null,
        'custom_codes'          => $customcodesstr !== '' ? $customcodesstr : null,
        'marks_enabled'         => $marksenablednew ? 1 : 0,
        'marks_weight'          => $marksenablednew ? $weight : null,
        'original_allornothing' => $originalaon,
        'timemodified'          => $now,
    ];
    if ($existing) {
        $record->id = $existing->id;
        $DB->update_record('local_crcqp_qconfig', $record);
    } else {
        $record->timecreated = $now;
        $DB->insert_record('local_crcqp_qconfig', $record);
    }
    redirect($redirecturl, get_string('manage_saved', 'local_coderunner_cqp_linter'),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($question->name));
$form->display();
echo $OUTPUT->footer();
