"""Style check module for CQP marks mode.

check_style(source, disabled_str) -> 'Style OK' | 'Style issues found: N violation(s)'

Intended to be imported by CodeRunner test case code after cqp_principles.py and
cqp_custom_checkers.py have been written to disk by the template prepend block.
"""
import io
import json
import os
import re
import sys
import tempfile


def check_style(source, disabled_str=''):
    """Run CQP pylint + custom checks on source code.

    Returns 'Style OK' if no violations exist, 'Style issues found: N violation(s)'
    if violations exist, or 'Style check failed' if the checker itself errors (so
    a checker crash costs the style mark rather than corrupting the combinator
    output).

    stdout is captured internally so pylint output never leaks into CodeRunner's
    graded output stream.
    """
    try:
        from cqp_principles import (PRINCIPLES, PYCODESTYLE_CODES, CUSTOM_CODES,
                                     normalise_pylint_code)

        principle_keys = [
            'clear_presentation', 'explanatory_language', 'consistent_code',
            'used_content', 'simple_constructs', 'minimal_duplication',
            'modular_structure', 'problem_alignment',
        ]

        disabled = set(s.strip() for s in disabled_str.split(',') if s.strip())

        code_map = {}
        for key in principle_keys:
            if key not in PRINCIPLES:
                continue
            for code, (sym, expl) in PRINCIPLES[key]['codes'].items():
                if code not in disabled and sym not in disabled:
                    code_map[code] = {'sym': sym, 'key': key}

        pylint_codes = [c for c in code_map if c not in PYCODESTYLE_CODES and c not in CUSTOM_CODES]
        violations = 0

        if pylint_codes:
            with tempfile.NamedTemporaryFile(suffix='.py', mode='w', delete=False, encoding='utf-8') as f:
                f.write(source)
                tmppath = f.name
            try:
                from pylint.lint import Run
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

                result = json.loads(_captured.getvalue() or '{}')
                for msg in result.get('messages', []):
                    code = msg.get('messageId') or msg.get('message-id', '')
                    code = normalise_pylint_code(code)
                    if code in code_map:
                        violations += 1
            finally:
                try:
                    os.unlink(tmppath)
                except OSError:
                    pass

        custom_codes = [c for c in code_map if c in CUSTOM_CODES]
        if custom_codes:
            try:
                from cqp_custom_checkers import run_custom_checks
                custom_out = run_custom_checks(source, custom_codes)
                for line in custom_out.strip().splitlines():
                    if not line:
                        continue
                    m = re.match(r'source\.py:\d+:\d+: (\w+)', line)
                    if m:
                        code = m.group(1)
                        if code in code_map:
                            violations += 1
            except ImportError:
                pass

        pycs_codes = [c for c in code_map if c in PYCODESTYLE_CODES]
        if pycs_codes:
            try:
                import pycodestyle as _pycodestyle
                _pycs_hits = []
                class _PCSCollector(_pycodestyle.BaseReport):
                    def error(self, line_number, offset, text, check):
                        _pycs_hits.append(text[:4])
                        return super().error(line_number, offset, text, check)
                with tempfile.NamedTemporaryFile(suffix='.py', mode='w', delete=False, encoding='utf-8') as _f:
                    _f.write(source)
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
                for _code in _pycs_hits:
                    if _code in code_map:
                        violations += 1
            except ImportError:
                pass

        if violations == 0:
            return 'Style OK'
        return f'Style issues found: {violations} violation(s)'

    except Exception:
        return 'Style check failed'
