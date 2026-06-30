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

namespace local_coderunner_cqp_linter\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Per-question pylint configuration form.
 *
 * @package    local_coderunner_cqp_linter
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manage_form extends \moodleform {

    /** Field-name prefix for the per-code checkboxes. */
    public const CHECK_PREFIX = 'cqpcheck_';

    /** Field-name prefix for the per-question AI principle checkboxes. */
    public const AI_PRINCIPLE_PREFIX = 'ai_principle_';

    /**
     * Load cqp_codes.json once and return the decoded array.
     *
     * @return array Decoded JSON: {'codes': {code: {symbol, principle, type, explanation}}, 'aliases': {...}}
     */
    private static function load_codes_json(): array {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $path = dirname(__DIR__, 2) . '/python/cqp_codes.json';
        $cache = json_decode(file_get_contents($path), true);
        return $cache;
    }

    /**
     * Return CQP-active codes grouped by principle number.
     * Loaded from python/cqp_codes.json — single source of truth with cqp_principles.py.
     *
     * @return array{int: array{string: string}} principle_number => [code => symbol]
     */
    public static function get_all_codes(): array {
        $data = self::load_codes_json();
        $grouped = [];
        foreach ($data['codes'] as $code => $info) {
            $grouped[(int)$info['principle']][$code] = $info['symbol'];
        }
        ksort($grouped);
        return $grouped;
    }

    protected function definition() {
        $mform = $this->_form;
        $customdata = $this->_customdata;
        $principles = \local_coderunner_cqp_linter\cqp_mapper::PRINCIPLES;

        $mform->addElement('header', 'general', get_string('manage_heading', 'local_coderunner_cqp_linter'));

        $mform->addElement('static', 'questionname_static',
            get_string('manage_question', 'local_coderunner_cqp_linter'),
            format_string($customdata['questionname']));

        $mform->addElement('advcheckbox', 'enabled',
            get_string('manage_enabled', 'local_coderunner_cqp_linter'),
            get_string('manage_enabled_label', 'local_coderunner_cqp_linter'));
        $mform->setDefault('enabled', 0);

        // AI analysis toggle sits directly beside the linting enable toggle so
        // both per-question on/off switches are in one place.
        $mform->addElement('advcheckbox', 'ai_enabled',
            get_string('manage_ai_enabled', 'local_coderunner_cqp_linter'),
            get_string('manage_ai_enabled_label', 'local_coderunner_cqp_linter'));
        $mform->setDefault('ai_enabled', 0);
        $mform->addHelpButton('ai_enabled', 'manage_ai_enabled', 'local_coderunner_cqp_linter');

        // Which CQP principles the AI assesses — chosen per question. Disabled
        // until AI analysis is ticked for this question.
        $first = true;
        foreach (\local_coderunner_cqp_linter\tools\ai\analyzer::SEMANTIC_PRINCIPLES as $pnum) {
            $pname = $principles[$pnum]['name'] ?? ('CQP ' . $pnum);
            $field = self::AI_PRINCIPLE_PREFIX . $pnum;
            $mform->addElement('advcheckbox', $field,
                $first ? get_string('manage_ai_principles', 'local_coderunner_cqp_linter') : '',
                'CQP ' . (int)$pnum . ': ' . $pname);
            $mform->setDefault($field, 1);
            $mform->disabledIf($field, 'ai_enabled', 'notchecked');
            if ($first) {
                $mform->addHelpButton($field, 'manage_ai_principles', 'local_coderunner_cqp_linter');
                $first = false;
            }
        }

        $mform->addElement('header', 'checks_header',
            get_string('manage_checks_header', 'local_coderunner_cqp_linter'));
        $mform->setExpanded('checks_header', true);

        // The per-code checkboxes are plain named inputs (not registered form
        // elements). They submit natively and are read in manage.php via
        // optional_param(). The JS (registered in manage.php via js_init_code)
        // only drives the group toggle and the per-card counters.
        $mform->addElement('html', $this->build_checks_html($principles));

        $mform->addElement('header', 'marks_header', get_string('manage_marks_header', 'local_coderunner_cqp_linter'));

        $mform->addElement('advcheckbox', 'marks_enabled',
            get_string('manage_marks_enabled', 'local_coderunner_cqp_linter'),
            get_string('manage_marks_enabled_label', 'local_coderunner_cqp_linter'));
        $mform->setDefault('marks_enabled', 0);
        $mform->addHelpButton('marks_enabled', 'manage_marks_enabled', 'local_coderunner_cqp_linter');

        $mform->addElement('text', 'marks_weight',
            get_string('manage_marks_weight', 'local_coderunner_cqp_linter'),
            ['size' => 8]);
        $mform->setType('marks_weight', PARAM_FLOAT);
        $mform->setDefault('marks_weight', 1.0);
        $mform->addRule('marks_weight', null, 'numeric', null, 'client');
        $mform->disabledIf('marks_weight', 'marks_enabled', 'notchecked');

        $mform->addElement('hidden', 'questionid', $customdata['questionid']);
        $mform->setType('questionid', PARAM_INT);

        $mform->addElement('hidden', 'returnurl', $customdata['returnurl'] ?? '');
        $mform->setType('returnurl', PARAM_LOCALURL);

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Build the per-principle checkbox grid as raw HTML with named inputs.
     *
     * Checked state comes from the submitted POST on a redisplay (e.g. after a
     * validation error), otherwise from the 'disabledcodes' customdata supplied
     * by manage.php.
     *
     * @param array $principles The CQP principle definitions.
     * @return string HTML.
     */
    private function build_checks_html(array $principles): string {
        $disabledcodes = $this->_customdata['disabledcodes'] ?? [];
        $resubmitted = $this->is_submitted();

        $html = '<div id="cqp-checks-grid" class="mb-2">';
        $html .= '<p class="text-muted small mb-3">' .
            s(get_string('manage_checks_desc', 'local_coderunner_cqp_linter')) .
            '</p>';

        foreach (self::get_all_codes() as $pnum => $codes) {
            $pname = $principles[$pnum]['name'] ?? ('CQP ' . $pnum);
            $groupid = 'cqpgroup_' . $pnum;
            $html .= '<div class="card mb-2">';
            $html .= '<div class="card-header p-2 d-flex justify-content-between align-items-center">';
            $html .= '<div class="form-check mb-0">';
            $html .= '<input type="checkbox" class="form-check-input cqp-group-check"';
            $html .= ' id="' . $groupid . '" data-pnum="' . (int)$pnum . '">';
            $html .= '<label class="form-check-label mb-0" for="' . $groupid . '">';
            $html .= '<strong>CQP ' . (int)$pnum . ': ' . s($pname) . '</strong>';
            $html .= '</label></div>';
            $html .= '<span class="text-muted small cqp-check-counter" data-pnum="' . (int)$pnum . '"></span>';
            $html .= '</div>';
            $html .= '<div class="card-body p-2"><div class="row">';

            foreach ($codes as $code => $symbol) {
                $fieldname = self::CHECK_PREFIX . $code;
                if ($resubmitted) {
                    $checked = optional_param($fieldname, 0, PARAM_BOOL) ? ' checked' : '';
                } else {
                    $checked = in_array($code, $disabledcodes, true) ? '' : ' checked';
                }
                $html .= '<div class="col-12 col-sm-6 col-md-4 mb-1"><div class="form-check">';
                $html .= '<input type="checkbox" class="form-check-input cqp-code-check"';
                $html .= ' id="' . $fieldname . '"';
                $html .= ' name="' . $fieldname . '"';
                $html .= ' value="1"';
                $html .= ' data-code="' . s($code) . '"';
                $html .= ' data-pnum="' . (int)$pnum . '"';
                $html .= $checked . '>';
                $html .= '<label class="form-check-label small" for="' . $fieldname . '">';
                $html .= '<code>' . s($code) . '</code> ' . s($symbol);
                $html .= '</label></div></div>';
            }

            $html .= '</div></div></div>'; // row, card-body, card
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Return the JS body for the group toggle and counters.
     *
     * Registered in manage.php via $PAGE->requires->js_init_code() so it runs
     * reliably on DOM ready.
     *
     * @return string JavaScript (no <script> tags).
     */
    public static function get_checks_js(): string {
        return <<<'JS'
(function() {
    var grid = document.getElementById('cqp-checks-grid');
    if (!grid) { return; }

    function updateGroupCheck(pnum) {
        var cbs = grid.querySelectorAll('.cqp-code-check[data-pnum="' + pnum + '"]');
        var on = 0;
        cbs.forEach(function(cb) { if (cb.checked) { on++; } });
        var gcb = grid.querySelector('.cqp-group-check[data-pnum="' + pnum + '"]');
        if (!gcb) { return; }
        if (on === 0) {
            gcb.checked = false;
            gcb.indeterminate = false;
        } else if (on === cbs.length) {
            gcb.checked = true;
            gcb.indeterminate = false;
        } else {
            gcb.checked = false;
            gcb.indeterminate = true;
        }
    }

    function updateCounters() {
        var counts = {};
        grid.querySelectorAll('.cqp-code-check').forEach(function(cb) {
            var p = cb.dataset.pnum;
            if (!counts[p]) { counts[p] = {total: 0, on: 0}; }
            counts[p].total++;
            if (cb.checked) { counts[p].on++; }
        });
        grid.querySelectorAll('.cqp-check-counter').forEach(function(el) {
            var p = el.dataset.pnum;
            if (counts[p]) {
                el.textContent = counts[p].on + ' / ' + counts[p].total + ' enabled';
            }
        });
    }

    grid.addEventListener('change', function(e) {
        var t = e.target;
        if (t.classList.contains('cqp-group-check')) {
            var pnum = t.dataset.pnum;
            grid.querySelectorAll('.cqp-code-check[data-pnum="' + pnum + '"]').forEach(function(cb) {
                cb.checked = t.checked;
            });
            updateCounters();
        } else if (t.classList.contains('cqp-code-check')) {
            updateGroupCheck(t.dataset.pnum);
            updateCounters();
        }
    });

    grid.querySelectorAll('.cqp-group-check').forEach(function(gcb) {
        updateGroupCheck(gcb.dataset.pnum);
    });
    updateCounters();
})();
JS;
    }
}
