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

        $filecontents = $this->read_support_files();
        if ($filecontents === null) {
            return new result([], 0.0, -1, microtime(true) - $starttime,
                'CQP support files missing from plugin python/ directory.');
        }

        $runnerscript = $this->build_runner_script($disable, $filecontents['cqp_principles.py'], $filecontents['cqp_custom_checkers.py'] ?? '');

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
     * Sends cqp_principles.py as a Jobe file_list support file alongside the runner script. Returns the
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
     * Embeds cqp_principles.py as base64 and uses pylint's Python API
     * (not subprocess) to run checks. Using the API avoids the subprocess
     * PATH issue on Jobe where `pylint` may not be in the shell PATH even
     * though the library is importable.
     *
     * @param string $disable      Comma-separated codes/names to suppress.
     * @param array  $filecontents ['filename' => base64string] for support files.
     * @return string Python source.
     */
    private function build_button_runner_script(string $disable, array $filecontents): string {
        $safedisable   = addslashes($disable);
        $principlesb64 = $filecontents['cqp_principles.py'] ?? '';
        $checkerb64    = $filecontents['cqp_custom_checkers.py'] ?? '';

        return <<<PYTHON
import base64, io, json, os, re, sys, tempfile

with open('cqp_principles.py', 'wb') as _f:
    _f.write(base64.b64decode('$principlesb64'))

with open('cqp_custom_checkers.py', 'wb') as _f:
    _f.write(base64.b64decode('$checkerb64'))

from cqp_principles import (PRINCIPLES, PYCODESTYLE_CODES, CUSTOM_CODES,
                            normalise_pylint_code)

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
    # Build flat code -> {sym, expl, key} map from all active principles.
    code_map = {}
    for key in PRINCIPLE_KEYS:
        if key not in PRINCIPLES:
            continue
        for code, (sym, expl) in PRINCIPLES[key]['codes'].items():
            if code not in DISABLED and sym not in DISABLED:
                code_map[code] = {'sym': sym, 'expl': expl, 'key': key}

    pylint_codes = [c for c in code_map if c not in PYCODESTYLE_CODES and c not in CUSTOM_CODES]

    student_code = sys.stdin.read()

    # Precheck: pylint cannot analyse code that has syntax errors and will
    # silently report zero CQP issues.  Catch this before running pylint so
    # the student sees an honest "fix your syntax first" message rather than
    # the misleading "No issues found. Well done!".
    try:
        compile(student_code, '<student>', 'exec')
    except SyntaxError as _e:
        print(json.dumps({
            'success': False,
            'error': 'Syntax error at line {}: {}. Fix syntax errors before checking code quality.'.format(
                _e.lineno or 0, _e.msg
            ),
            'total_issues': 0, 'messages': [], 'principles': [],
        }))
        sys.exit(0)

    all_messages = []

    if pylint_codes:
        with tempfile.NamedTemporaryFile(suffix='.py', mode='w', delete=False, encoding='utf-8') as f:
            f.write(student_code)
            tmppath = f.name
        try:
            from pylint.lint import Run
            # Capture sys.stdout so pylint's JSON output doesn't reach Jobe's
            # captured stdout before we wrap it. pylint writes directly to
            # sys.stdout when --output-format=json2 is used, ignoring any
            # custom reporter buffer.
            _real_stdout = sys.stdout
            _captured = io.StringIO()
            sys.stdout = _captured
            try:
                Run([
                    '--disable=all',
                    '--enable=' + ','.join(pylint_codes),
                    '--output-format=json2',
                    '--score=n',
                    tmppath,
                ], exit=False)
            finally:
                sys.stdout = _real_stdout
            _pylint_out = _captured.getvalue().strip()
            if not _pylint_out:
                raise RuntimeError('pylint produced no output — it may have crashed or failed to start')
            result = json.loads(_pylint_out)
            for msg in result.get('messages', []):
                code = msg.get('messageId') or msg.get('message-id', '')
                code = normalise_pylint_code(code)
                if code in code_map:
                    info = code_map[code]
                    num = PRINCIPLE_NUMBERS.get(info['key'], 0)
                    pdata = PRINCIPLES[info['key']]
                    all_messages.append({
                        'line':          int(msg.get('line', 0)),
                        'type':          code_to_type(code),
                        'symbol':        info['sym'],
                        'message':       info['expl'],
                        'cqp_number':    num,
                        'cqp_name':      pdata['name'],
                        'cqp_guideline': pdata['rationale'],
                    })
        finally:
            try:
                os.unlink(tmppath)
            except OSError:
                pass

    pycs_codes = [c for c in code_map if c in PYCODESTYLE_CODES]
    if pycs_codes:
        import pycodestyle as _pycodestyle
        _pycs_items = []
        class _PCSCollector(_pycodestyle.BaseReport):
            def error(self, line_number, offset, text, check):
                _code = text[:4]
                _pycs_items.append((line_number, _code))
                return super().error(line_number, offset, text, check)
        with tempfile.NamedTemporaryFile(suffix='.py', mode='w', delete=False, encoding='utf-8') as _f:
            _f.write(student_code)
            _tmppath = _f.name
        _saved = sys.stdout
        sys.stdout = io.StringIO()
        try:
            _pycodestyle.StyleGuide(select=pycs_codes, ignore=(), reporter=_PCSCollector).check_files([_tmppath])
        finally:
            sys.stdout = _saved
            try:
                os.unlink(_tmppath)
            except OSError:
                pass
        for _lineno, _code in _pycs_items:
            if _code in code_map:
                _info = code_map[_code]
                _num = PRINCIPLE_NUMBERS.get(_info['key'], 0)
                _pdata = PRINCIPLES[_info['key']]
                all_messages.append({
                    'line':          _lineno,
                    'type':          'convention',
                    'symbol':        _info['sym'],
                    'message':       _info['expl'],
                    'cqp_number':    _num,
                    'cqp_name':      _pdata['name'],
                    'cqp_guideline': _pdata['rationale'],
                })

    custom_codes = [c for c in code_map if c in CUSTOM_CODES]
    if custom_codes:
        from cqp_custom_checkers import run_custom_checks
        custom_out = run_custom_checks(student_code, custom_codes)
        for _line in custom_out.strip().splitlines():
            if not _line:
                continue
            _m = re.match(r'source\.py:(\d+):(\d+): (\w+)', _line)
            if _m:
                _lineno, _col, _code = int(_m.group(1)), int(_m.group(2)), _m.group(3)
                if _code in code_map:
                    _info = code_map[_code]
                    _num = PRINCIPLE_NUMBERS.get(_info['key'], 0)
                    _pdata = PRINCIPLES[_info['key']]
                    all_messages.append({
                        'line':          _lineno,
                        'type':          'convention',
                        'symbol':        _info['sym'],
                        'message':       _info['expl'],
                        'cqp_number':    _num,
                        'cqp_name':      _pdata['name'],
                        'cqp_guideline': _pdata['rationale'],
                    })

    all_messages.sort(key=lambda m: m['line'])

    by_key = {}
    for m in all_messages:
        by_key.setdefault(m['cqp_number'], []).append(m)

    principles_out = []
    for key in PRINCIPLE_KEYS:
        num = PRINCIPLE_NUMBERS.get(key, 0)
        if num not in by_key:
            continue
        pdata = PRINCIPLES[key]
        msgs = by_key[num]
        principles_out.append({
            'number':    num,
            'name':      pdata['name'],
            'short':     pdata['principle'],
            'guideline': pdata['rationale'],
            'count':     len(msgs),
            'messages':  msgs,
        })

    print(json.dumps({
        'success':      True,
        'total_issues': len(all_messages),
        'messages':     all_messages,
        'principles':   principles_out,
    }))

except Exception as exc:
    print(json.dumps({
        'success': False, 'error': str(exc),
        'total_issues': 0, 'messages': [], 'principles': [],
    }))
PYTHON;
    }

    /**
     * Read cqp_principles.py from the plugin's python/ directory.
     *
     * Only cqp_principles.py is needed — the button runner uses the pylint
     * Python API directly rather than cqp_checker.py's subprocess approach.
     *
     * @return array|null ['cqp_principles.py' => base64string], or null if missing.
     */
    private function read_support_files(): ?array {
        $dir = dirname(__DIR__, 3) . '/python/';
        $principlespath = $dir . 'cqp_principles.py';
        $checkerpath    = $dir . 'cqp_custom_checkers.py';
        if (!file_exists($principlespath) || !file_exists($checkerpath)) {
            return null;
        }
        return [
            'cqp_principles.py'      => base64_encode(file_get_contents($principlespath)),
            'cqp_custom_checkers.py' => base64_encode(file_get_contents($checkerpath)),
        ];
    }

    /**
     * Build the Python runner script that Jobe will execute (review-page pylint path).
     *
     * Restricts pylint to only the codes defined in cqp_principles.py so the
     * review panel shows the same violations as the "Check Code Quality" button.
     * Outputs raw pylint JSON2 format for parser.php to consume.
     *
     * @param string $disable       Comma-separated pylint checks to suppress.
     * @param string $principlesb64 Base64-encoded cqp_principles.py content.
     * @return string Python source code for the runner script.
     */
    private function build_runner_script(string $disable, string $principlesb64, string $checkerb64): string {
        $safedisable = addslashes($disable);

        return <<<PYTHON
import base64, io, json, os, re, sys, tempfile

with open('cqp_principles.py', 'wb') as _f:
    _f.write(base64.b64decode('$principlesb64'))

with open('cqp_custom_checkers.py', 'wb') as _f:
    _f.write(base64.b64decode('$checkerb64'))

from cqp_principles import PRINCIPLES, PYCODESTYLE_CODES, CUSTOM_CODES

DISABLED = set(s.strip() for s in '$safedisable'.split(',') if s.strip())

PRINCIPLE_KEYS = [
    'clear_presentation', 'explanatory_language', 'consistent_code',
    'used_content', 'simple_constructs', 'minimal_duplication',
    'modular_structure', 'problem_alignment',
]

code_map = {}
for key in PRINCIPLE_KEYS:
    if key not in PRINCIPLES:
        continue
    for code, (sym, expl) in PRINCIPLES[key]['codes'].items():
        if code not in DISABLED and sym not in DISABLED:
            code_map[code] = {'sym': sym, 'expl': expl, 'key': key}

pylint_codes = [c for c in code_map if c not in PYCODESTYLE_CODES and c not in CUSTOM_CODES]

student_code = sys.stdin.read()
with tempfile.NamedTemporaryFile(suffix='.py', mode='w', delete=False, encoding='utf-8') as f:
    f.write(student_code)
    path = f.name

try:
    from pylint.lint import Run
    _real_stdout = sys.stdout
    _captured = io.StringIO()
    sys.stdout = _captured
    try:
        args = ['--output-format=json2', '--score=n']
        if pylint_codes:
            args.extend(['--disable=all', '--enable=' + ','.join(pylint_codes)])
        Run(args + [path], exit=False)
    finally:
        sys.stdout = _real_stdout

    result = json.loads(_captured.getvalue() or '{"messages": [], "statistics": {}}')

    pycs_codes = [c for c in code_map if c in PYCODESTYLE_CODES]
    if pycs_codes:
        import pycodestyle as _pycodestyle
        _pycs_items = []
        class _PCSCollector(_pycodestyle.BaseReport):
            def error(self, line_number, offset, text, check):
                _code = text[:4]
                _pycs_items.append((line_number, offset, _code))
                return super().error(line_number, offset, text, check)
        _saved = sys.stdout
        sys.stdout = io.StringIO()
        try:
            _pycodestyle.StyleGuide(select=pycs_codes, ignore=(), reporter=_PCSCollector).check_files([path])
        finally:
            sys.stdout = _saved
        for _lineno, _col, _code in _pycs_items:
            if _code in code_map:
                _info = code_map[_code]
                result.setdefault('messages', []).append({
                    'type':       'convention',
                    'symbol':     _info['sym'],
                    'message-id': _code,
                    'message':    _info['expl'],
                    'line':       _lineno,
                    'column':     _col,
                })

    custom_codes = [c for c in code_map if c in CUSTOM_CODES]
    if custom_codes:
        from cqp_custom_checkers import run_custom_checks
        custom_out = run_custom_checks(student_code, custom_codes)
        for _line in custom_out.strip().splitlines():
            if not _line:
                continue
            _m = re.match(r'source\.py:(\d+):(\d+): (\w+)', _line)
            if _m:
                _lineno, _col, _code = int(_m.group(1)), int(_m.group(2)), _m.group(3)
                if _code in code_map:
                    _info = code_map[_code]
                    result.setdefault('messages', []).append({
                        'type':       'convention',
                        'symbol':     _info['sym'],
                        'message-id': _code,
                        'message':    _info['expl'],
                        'line':       _lineno,
                        'column':     _col,
                    })

    print(json.dumps(result))

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
