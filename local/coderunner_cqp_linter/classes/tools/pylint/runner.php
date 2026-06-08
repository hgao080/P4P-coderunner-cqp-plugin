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

namespace local_coderunner_cqp_linter\tools\pylint;

/**
 * Executes pylint on student code via the Jobe sandbox server.
 *
 * Submits a Python runner script to Jobe over HTTP. Jobe handles sandboxing,
 * timeouts, and process isolation. Requires pylint to be installed on Jobe.
 *
 * @package    local_coderunner_cqp_linter
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class runner {

    /** @var int Jobe outcome code for a successful run. */
    const JOBE_OUTCOME_OK = 15;

    /** @var string Jobe REST API path for submitting runs. */
    const JOBE_RUNS_PATH = '/jobe/index.php/restapi/runs';

    /** @var string Jobe REST API path for checking a language. */
    const JOBE_LANGUAGES_PATH = '/jobe/index.php/restapi/languages';

    /** @var string Jobe base URL (with http://). */
    private string $jobeurl;

    /** @var string Optional Jobe API key. */
    private string $apikey;

    /** @var int Timeout in seconds (sent to Jobe as cputime). */
    private int $timeout;

    /** @var string Comma-separated pylint checks to disable. */
    private string $disablechecks;

    /**
     * Constructor. Reads settings from Moodle config.
     *
     * Jobe host is inherited from CodeRunner's settings unless overridden
     * in this plugin's own settings.
     */
    public function __construct() {
        $host = get_config('local_coderunner_cqp_linter', 'jobe_host') ?: '';
        if (empty($host)) {
            $host = get_config('qtype_coderunner', 'jobe_host') ?: '';
        }

        // Normalise: ensure http:// prefix, strip trailing slash.
        if (!empty($host) && !preg_match('#^https?://#', $host)) {
            $host = 'http://' . $host;
        }
        $this->jobeurl = rtrim($host, '/');

        $this->apikey = get_config('qtype_coderunner', 'jobe_apikey') ?: '';
        $this->timeout = (int)(get_config('local_coderunner_cqp_linter', 'timeout') ?: 10);
        $this->disablechecks = get_config('local_coderunner_cqp_linter', 'default_disable') ?: 'import-error';
    }

    /**
     * Run pylint on a code string via Jobe (used for server-side review panels).
     *
     * @param string $code The Python source code to lint.
     * @param array $options Override options: ['disable' => string].
     * @return result Structured lint result.
     */
    public function lint(string $code, array $options = []): result {
        $starttime = microtime(true);

        if (empty($this->jobeurl)) {
            return new result(
                [],
                0.0,
                -1,
                microtime(true) - $starttime,
                'Jobe server not configured. Set jobe_host in CodeRunner or plugin settings.'
            );
        }

        $disable = $options['disable'] ?? $this->disablechecks;
        $runnerscript = $this->build_runner_script($disable);

        $joberesult = $this->submit_to_jobe($runnerscript, $code);
        $executiontime = microtime(true) - $starttime;

        if ($joberesult['error'] !== '') {
            return new result([], 0.0, -1, $executiontime, $joberesult['error']);
        }

        return parser::parse(
            $joberesult['stdout'],
            $joberesult['stderr'],
            $joberesult['returncode'],
            $executiontime
        );
    }

    /**
     * Run CQP-mapped pylint + pycodestyle + custom checks via Jobe.
     *
     * Sends cqp_checker.py, cqp_principles.py, cqp_custom_checkers.py as
     * Jobe file_list support files alongside the runner script. Returns the
     * decoded JSON array matching I.json shape, or an error array on failure.
     *
     * @param string $code    Student Python source code.
     * @param array  $options ['disable' => string, 'min_severity' => string]
     * @return array Decoded JSON array: {success, total_issues, messages[], principles[]}
     */
    public function lint_for_button(string $code, array $options = []): array {
        $empty = ['success' => false, 'total_issues' => 0, 'messages' => [], 'principles' => []];

        if (empty($this->jobeurl)) {
            return array_merge($empty, ['error' => 'Jobe server not configured.']);
        }

        $disable = $options['disable'] ?? $this->disablechecks;

        $filecontents = $this->read_support_files();
        if ($filecontents === null) {
            return array_merge($empty, ['error' => 'CQP support files missing from plugin python/ directory.']);
        }

        $runnerscript = $this->build_button_runner_script($disable, $filecontents);

        $joberesult = $this->submit_to_jobe($runnerscript, $code);

        if ($joberesult['error'] !== '') {
            return array_merge($empty, ['error' => $joberesult['error']]);
        }

        $decoded = json_decode($joberesult['stdout'], true);
        if (!is_array($decoded)) {
            return array_merge($empty, ['error' => 'Invalid JSON from Jobe runner: ' . substr($joberesult['stdout'], 0, 200)]);
        }

        return $decoded;
    }

    /**
     * Build the Python runner script for the "Check Code Quality" button.
     *
     * Embeds cqp_checker.py, cqp_principles.py, cqp_custom_checkers.py as
     * base64 strings and writes them to the Jobe working directory before
     * importing. This avoids Jobe's file_list pre-upload requirement.
     *
     * @param string $disable      Comma-separated codes/names to suppress.
     * @param array  $filecontents ['filename' => base64string] for the 3 support files.
     * @return string Python source.
     */
    private function build_button_runner_script(string $disable, array $filecontents): string {
        $safedisable = addslashes($disable);

        // Build Python dict literal — base64 chars are safe in single-quoted strings.
        $filesdict = '';
        foreach ($filecontents as $name => $b64) {
            $filesdict .= "    '" . $name . "': '" . $b64 . "',\n";
        }

        return <<<PYTHON
import base64, json, os, sys

_SUPPORT_FILES = {
$filesdict}
for _name, _b64 in _SUPPORT_FILES.items():
    with open(_name, 'wb') as _f:
        _f.write(base64.b64decode(_b64))

DISABLED = set(s.strip() for s in '$safedisable'.split(',') if s.strip())



PRINCIPLE_KEYS = [
    'clear_presentation', 'explanatory_language', 'consistent_code',
    'used_content', 'simple_constructs', 'minimal_duplication',
    'modular_structure', 'problem_alignment',
]

PRINCIPLE_NUMBERS = {
    'clear_presentation': 1, 'explanatory_language': 2, 'consistent_code': 3,
    'used_content': 4,       'simple_constructs': 5,   'minimal_duplication': 6,
    'modular_structure': 7,  'problem_alignment': 8,
}

TYPE_MAP = {'C': 'convention', 'W': 'warning', 'R': 'refactor', 'E': 'error', 'F': 'fatal'}

def code_to_type(code):
    if code.startswith('W9'):
        return 'convention'
    return TYPE_MAP.get(code[0] if code else 'C', 'convention')

try:
    from cqp_checker import check_principles
    from cqp_principles import PRINCIPLES

    student_code = sys.stdin.read()
    results = check_principles(student_code, PRINCIPLE_KEYS)

    all_messages = []
    principles_out = []

    for result in results:
        key = next((k for k, v in PRINCIPLES.items() if v['name'] == result['name']), None)
        if key is None:
            continue
        num = PRINCIPLE_NUMBERS.get(key, 0)
        pdata = PRINCIPLES[key]

        msgs = []
        for v in result['violations']:
            if v['code'] in DISABLED or v.get('symbolic_name') in DISABLED:
                continue
            msg = {
                'line':         int(v['line_no']),
                'type':         code_to_type(v['code']),
                'symbol':       v['symbolic_name'],
                'message':      v['explanation'],
                'cqp_number':   num,
                'cqp_name':     result['name'],
                'cqp_guideline': pdata['rationale'],
            }
            msgs.append(msg)
            all_messages.append(msg)

        if msgs:
            principles_out.append({
                'number':   num,
                'name':     result['name'],
                'short':    pdata['principle'],
                'guideline': pdata['rationale'],
                'count':    len(msgs),
                'messages': msgs,
            })

    all_messages.sort(key=lambda m: m['line'])
    output = {
        'success':      True,
        'total_issues': len(all_messages),
        'messages':     all_messages,
        'principles':   sorted(principles_out, key=lambda p: p['number']),
    }
    print(json.dumps(output))

except Exception as exc:
    print(json.dumps({
        'success': False, 'error': str(exc),
        'total_issues': 0, 'messages': [], 'principles': [],
    }))
PYTHON;
    }

    /**
     * Read the three CQP support files from the plugin's python/ directory.
     *
     * @return array|null ['filename' => base64string] for each file, or null if any missing.
     */
    private function read_support_files(): ?array {
        $pythondir = dirname(__DIR__, 3) . '/python/';
        $files = ['cqp_checker.py', 'cqp_principles.py', 'cqp_custom_checkers.py'];
        $contents = [];

        foreach ($files as $filename) {
            $path = $pythondir . $filename;
            if (!file_exists($path)) {
                return null;
            }
            $contents[$filename] = base64_encode(file_get_contents($path));
        }

        return $contents;
    }

    /**
     * Build the Python runner script that Jobe will execute (review-page pylint path).
     *
     * Reads student code from stdin, writes it to a temp file, runs pylint
     * via the pylint Python API, and prints JSON output to stdout.
     *
     * @param string $disable Comma-separated pylint checks to disable.
     * @return string Python source code for the runner script.
     */
    private function build_runner_script(string $disable): string {
        // Only pylint check names go here — safe to embed directly.
        $safedisable = addslashes($disable);

        return <<<PYTHON
import sys, os, io, tempfile

DISABLE = '$safedisable'

code = sys.stdin.read()
with tempfile.NamedTemporaryFile(suffix='.py', mode='w', delete=False) as f:
    f.write(code)
    path = f.name

try:
    from pylint.lint import Run
    from pylint.reporters.json_reporter import JSON2Reporter
    buf = io.StringIO()
    reporter = JSON2Reporter(buf)
    args = ['--output-format=json2', '--load-plugins=']
    if DISABLE:
        args.append('--disable=' + DISABLE)
    args.append(path)
    Run(args, reporter=reporter, exit=False)
    print(buf.getvalue())
except Exception as e:
    print(str(e), file=sys.stderr)
    sys.exit(1)
finally:
    try:
        os.unlink(path)
    except OSError:
        pass
PYTHON;
    }

    /**
     * Submit a job to Jobe and return the raw output.
     *
     * @param string $runnerscript Python source code to execute on Jobe.
     * @param string $studentcode  Student code passed as stdin to the runner.
     * @return array{stdout: string, stderr: string, returncode: int, error: string}
     */
    private function submit_to_jobe(string $runnerscript, string $studentcode): array {
        $url = $this->jobeurl . self::JOBE_RUNS_PATH;

        $runspec = [
            'run_spec' => [
                'language_id'    => 'python3',
                'sourcecode'     => $runnerscript,
                'input'          => $studentcode,
                'parameters'     => [
                    'cputime'  => $this->timeout,
                    'numprocs' => 20,
                ],
            ],
        ];

        $payload = json_encode($runspec);

        $headers = [
            'Content-Type: application/json; charset=utf-8',
            'Accept: application/json',
        ];
        if (!empty($this->apikey)) {
            $headers[] = 'X-API-KEY: ' . $this->apikey;
        }

        $context = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => implode("\r\n", $headers),
                'content'       => $payload,
                'timeout'       => $this->timeout + 5,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            return [
                'stdout'     => '',
                'stderr'     => '',
                'returncode' => -1,
                'error'      => 'Failed to contact Jobe server at ' . $url,
            ];
        }

        $data = json_decode($response, true);

        if ($data === null) {
            return [
                'stdout'     => '',
                'stderr'     => '',
                'returncode' => -1,
                'error'      => 'Invalid JSON response from Jobe: ' . substr($response, 0, 200),
            ];
        }

        $outcome = $data['outcome'] ?? -1;

        if ($outcome !== self::JOBE_OUTCOME_OK) {
            $detail = $data['cmpinfo'] ?? $data['stderr'] ?? '';
            return [
                'stdout'     => '',
                'stderr'     => $detail,
                'returncode' => -1,
                'error'      => 'Jobe returned outcome ' . $outcome . ($detail ? ': ' . $detail : ''),
            ];
        }

        return [
            'stdout'     => $data['stdout'] ?? '',
            'stderr'     => $data['stderr'] ?? '',
            'returncode' => 0,
            'error'      => '',
        ];
    }

    /**
     * Check if Jobe is reachable and pylint is available on it.
     *
     * @return array{available: bool, version: string, error: string}
     */
    public function check_availability(): array {
        if (empty($this->jobeurl)) {
            return [
                'available' => false,
                'version'   => '',
                'error'     => 'Jobe server not configured.',
            ];
        }

        $versionscript = <<<'PYTHON'
try:
    import pylint
    print(pylint.__version__)
except ImportError:
    print('pylint not installed', end='')
    import sys
    sys.exit(1)
PYTHON;

        $result = $this->submit_to_jobe($versionscript, '');

        if ($result['error'] !== '') {
            return ['available' => false, 'version' => '', 'error' => $result['error']];
        }

        $version = trim($result['stdout']);
        if (empty($version)) {
            return ['available' => false, 'version' => '', 'error' => 'pylint not installed on Jobe server.'];
        }

        return ['available' => true, 'version' => $version, 'error' => ''];
    }
}
