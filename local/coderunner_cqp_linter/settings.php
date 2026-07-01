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
 * Admin settings for local_coderunner_cqp_linter.
 *
 * @package    local_coderunner_cqp_linter
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_coderunner_cqp_linter', get_string('pluginname', 'local_coderunner_cqp_linter'));

    // Header.
    $settings->add(new admin_setting_heading(
        'local_coderunner_cqp_linter/heading',
        get_string('settings_heading', 'local_coderunner_cqp_linter'),
        get_string('settings_heading_desc', 'local_coderunner_cqp_linter')
    ));

    // Jobe server host (optional override — inherited from CodeRunner if empty).
    $settings->add(new admin_setting_configtext(
        'local_coderunner_cqp_linter/jobe_host',
        get_string('jobe_host', 'local_coderunner_cqp_linter'),
        get_string('jobe_host_desc', 'local_coderunner_cqp_linter'),
        '',
        PARAM_TEXT
    ));

    // Timeout.
    $settings->add(new admin_setting_configtext(
        'local_coderunner_cqp_linter/timeout',
        get_string('timeout', 'local_coderunner_cqp_linter'),
        get_string('timeout_desc', 'local_coderunner_cqp_linter'),
        '10',
        PARAM_INT
    ));

    // NOTE: "Default disabled checks", "Minimum severity" and "Max code size"
    // settings were intentionally removed. Disabled checks are configured
    // per question; severity is always 'convention' (report everything); and
    // there is no global code-size cap for the static linter.

    // Cache TTL.
    $settings->add(new admin_setting_configtext(
        'local_coderunner_cqp_linter/cache_ttl',
        get_string('cache_ttl', 'local_coderunner_cqp_linter'),
        get_string('cache_ttl_desc', 'local_coderunner_cqp_linter'),
        '3600',
        PARAM_INT
    ));


    // ── AI analysis (experimental) ───────────────────────────────────────────
    $settings->add(new admin_setting_heading(
        'local_coderunner_cqp_linter/ai_heading',
        get_string('ai_heading', 'local_coderunner_cqp_linter'),
        get_string('ai_heading_desc', 'local_coderunner_cqp_linter')
    ));

    // NOTE: The site-wide AI on/off toggle was removed. AI is enabled globally
    // as soon as an API key is set below, and is then turned on per question via
    // each question's "Enable AI analysis" option. Leave the API key blank to
    // keep AI off everywhere.

    $settings->add(new admin_setting_configpasswordunmask(
        'local_coderunner_cqp_linter/ai_api_key',
        get_string('ai_api_key', 'local_coderunner_cqp_linter'),
        get_string('ai_api_key_desc', 'local_coderunner_cqp_linter'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_coderunner_cqp_linter/ai_model',
        get_string('ai_model', 'local_coderunner_cqp_linter'),
        get_string('ai_model_desc', 'local_coderunner_cqp_linter'),
        'gpt-4o-mini',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_coderunner_cqp_linter/ai_base_url',
        get_string('ai_base_url', 'local_coderunner_cqp_linter'),
        get_string('ai_base_url_desc', 'local_coderunner_cqp_linter'),
        'https://api.openai.com/v1',
        PARAM_URL
    ));

    // NOTE: The AI "when to run" selector was intentionally removed. AI analysis
    // always runs on both the Check Code Quality button and on quiz submission
    // (see analyzer::runs_on(), which is hardcoded to run on both triggers).

    $settings->add(new admin_setting_configtext(
        'local_coderunner_cqp_linter/ai_timeout',
        get_string('ai_timeout', 'local_coderunner_cqp_linter'),
        get_string('ai_timeout_desc', 'local_coderunner_cqp_linter'),
        '30',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_coderunner_cqp_linter/ai_max_code_size',
        get_string('ai_max_code_size', 'local_coderunner_cqp_linter'),
        get_string('ai_max_code_size_desc', 'local_coderunner_cqp_linter'),
        '8000',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_coderunner_cqp_linter/ai_temperature',
        get_string('ai_temperature', 'local_coderunner_cqp_linter'),
        get_string('ai_temperature_desc', 'local_coderunner_cqp_linter'),
        '0.2',
        PARAM_TEXT
    ));

    $ADMIN->add('localplugins', $settings);

    // Research data export page — separate external page so it gets its own nav link.
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_coderunner_cqp_linter_report',
        get_string('report_title', 'local_coderunner_cqp_linter'),
        new moodle_url('/local/coderunner_cqp_linter/report.php'),
        'local/coderunner_cqp_linter:manageglobalsettings'
    ));
}
