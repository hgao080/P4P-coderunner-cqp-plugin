"""
cqp_checker.py — Core logic for CQP-based Pylint + pycodestyle feedback.

Intended to be uploaded as a CodeRunner support file so it is available
in the working directory when the grader template runs.

Public interface:
    check_principles(source_code, principle_keys) -> list[PrincipleResult]

Each PrincipleResult is a dict:
    {
        'name':      str,               # e.g. "Clear Presentation"
        'principle': str,               # one-line principle statement
        'rationale': str,               # why this principle matters
        'violations': list[Violation],  # may be empty
    }

Each Violation is a dict:
    {
        'code':        str,  # e.g. "W0611" or "E225"
        'line_no':     str,  # line number as string
        'raw':         str,  # the raw tool output line
        'explanation': str,  # pedagogical explanation for the student
    }

Tool routing:
    Codes in PYCODESTYLE_CODES  → pycodestyle subprocess
    Codes in CUSTOM_CODES       → cqp_custom_checkers.run_custom_checks()
    All other codes             → Pylint subprocess
    All three tools share the same source.py file and their output is parsed
    by the same regex, then merged before being attributed to a principle.
"""

import re
import subprocess

from cqp_principles import CUSTOM_CODES, PRINCIPLES, PYCODESTYLE_CODES


# ---------------------------------------------------------------------------
# Internal helpers
# ---------------------------------------------------------------------------

def _run_pylint(codes, source_code):
    """
    Run Pylint restricted to the given set of message codes against
    source_code supplied via stdin. Returns raw stdout+stderr string.

    Pylint exits non-zero when violations are found — this is expected and
    handled via CalledProcessError.
    """
    enabled = ','.join(codes)
    cmd = [
        'pylint', '--disable=all', f'--enable={enabled}',
        '--rcfile=/dev/null', '--persistent=n', '--from-stdin', 'source.py',
    ]
    try:
        return subprocess.check_output(
            cmd, input=source_code, universal_newlines=True,
            stderr=subprocess.STDOUT,
        )
    except subprocess.CalledProcessError as e:
        return e.output


def _run_pycodestyle(codes, source_code):
    """
    Run pycodestyle restricted to the given set of message codes against
    source_code supplied via stdin. Returns raw stdout string.

    pycodestyle exits non-zero when violations are found — handled via
    CalledProcessError. We pass --select to restrict to only the codes we
    care about so we don't surface codes that aren't mapped to any principle.
    """
    select = ','.join(codes)
    cmd = ['python3', '-m', 'pycodestyle', f'--select={select}', '-']
    try:
        return subprocess.check_output(
            cmd, input=source_code, universal_newlines=True,
            stderr=subprocess.STDOUT,
        )
    except subprocess.CalledProcessError as e:
        return e.output
    except FileNotFoundError:
        # pycodestyle not installed — return empty output gracefully so
        # Pylint-only principles still work.
        return ''


def _run_custom(source_code, codes):
    """
    Run custom checkers for the given set of W90xx code strings against
    source_code. Returns a raw output string in the same format as Pylint
    and pycodestyle so it can be fed directly into _parse_violations.

    Imports cqp_custom_checkers lazily so the rest of the tool continues
    to work if that support file is not deployed.
    """
    try:
        from cqp_custom_checkers import run_custom_checks
        return run_custom_checks(source_code, codes)
    except ImportError:
        return ''


def _parse_violations(output, codes_map):
    """
    Parse raw tool output and return a list of Violation dicts for codes
    that appear in codes_map.

    Both Pylint and pycodestyle use the same output format:
        filename:LINE:COL: CODE message-text

    The regex captures LINE and CODE regardless of which tool produced the
    line, so this function works identically for both.
    """
    violations = []
    pattern = re.compile(r':(\d+):\d+: ([A-Z]\d+)')

    for line in output.splitlines():
        match = pattern.search(line)
        if match:
            line_no = match.group(1)
            code = match.group(2)
            if code in codes_map:
                symbolic_name, explanation = codes_map[code]
                violations.append({
                    'code': code,
                    'symbolic_name': symbolic_name,
                    'line_no': line_no,
                    'raw': line.strip(),
                    'explanation': explanation,
                })

    return violations


# ---------------------------------------------------------------------------
# Public interface
# ---------------------------------------------------------------------------

def check_principles(source_code, principle_keys):
    """
    Run Pylint and pycodestyle against source_code for all active principles
    and return a list of PrincipleResult dicts (one per principle, regardless
    of whether violations were found).

    principle_keys: list of strings matching keys in PRINCIPLES, e.g.
        ['clear_presentation', 'used_content']

    Unknown keys are silently skipped.

    All three runners are each invoked at most once, covering all active codes
    in a single call per tool. Their violations are merged, then attributed
    back to the principle that owns each code.
    """
    valid = {k: PRINCIPLES[k] for k in principle_keys if k in PRINCIPLES}

    # Build a unified codes_map: code -> (symbolic_name, explanation)
    # and a reverse lookup:    code -> principle_key
    all_codes = {}
    code_to_principle = {}
    for key, principle in valid.items():
        for code, (symbolic_name, explanation) in principle['codes'].items():
            all_codes[code] = (symbolic_name, explanation)
            code_to_principle[code] = key

    # Split codes by tool
    pylint_codes = [c for c in all_codes if c not in PYCODESTYLE_CODES and c not in CUSTOM_CODES]
    pcs_codes = [c for c in all_codes if c in PYCODESTYLE_CODES]
    custom_codes = [c for c in all_codes if c in CUSTOM_CODES]

    # Collect violations from each tool into per-principle buckets
    by_principle = {key: [] for key in valid}

    if pylint_codes:
        output = _run_pylint(pylint_codes, source_code)
        for v in _parse_violations(output, all_codes):
            by_principle[code_to_principle[v['code']]].append(v)

    if pcs_codes:
        output = _run_pycodestyle(pcs_codes, source_code)
        for v in _parse_violations(output, all_codes):
            by_principle[code_to_principle[v['code']]].append(v)

    if custom_codes:
        output = _run_custom(source_code, custom_codes)
        for v in _parse_violations(output, all_codes):
            by_principle[code_to_principle[v['code']]].append(v)

    # Build results list in the order principle_keys were requested
    results = []
    for key in principle_keys:
        if key not in valid:
            continue
        principle = valid[key]
        results.append({
            'name': principle['name'],
            'principle': principle['principle'],
            'rationale': principle['rationale'],
            'violations': by_principle[key],
        })

    return results
