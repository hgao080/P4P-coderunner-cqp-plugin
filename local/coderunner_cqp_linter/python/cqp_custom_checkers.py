"""
cqp_custom_checkers.py — Custom code quality checks for CQP principles that
cannot be fully covered by Pylint or pycodestyle alone.

Intended to be uploaded as a CodeRunner support file alongside cqp_checker.py.

Public interface:
    run_custom_checks(source_code, codes) -> str

The return value is a raw violation string in the same format used by Pylint
and pycodestyle, so it can be parsed unchanged by _parse_violations in
cqp_checker.py:
    source.py:LINE:COL: CODE symbolic-name

Custom codes (W90xx range):
    W9001  docstring-closing-quote-placement   (Clear Presentation)
    W9002  avoidable-backslash-in-string       (Simple Constructs)
    W9003  inconsistent-quote-style            (Consistent Code)
    W9004  inconsistent-operator-line-break    (Consistent Code)
    W9005  constant-in-function-scope          (Modular Structure)
    W9006  ambiguous-variable-name             (Explanatory Language)
    W9007  block-comment-wrong-indent          (Clear Presentation)
"""

import ast
import io
import re
import subprocess
import tokenize


# ---------------------------------------------------------------------------
# Public interface
# ---------------------------------------------------------------------------

def run_custom_checks(source_code, codes):
    """
    Run custom checks against source_code for the given set of W90xx code
    strings (e.g. ['W9001', 'W9004']).

    Returns a raw output string in the format:
        source.py:LINE:COL: CODE symbolic-name
    """
    codes = set(codes)
    lines = []

    if 'W9001' in codes:
        for lineno, col, code in _check_docstring_placement(source_code):
            lines.append(
                f'source.py:{lineno}:{col}: {code} docstring-closing-quote-placement'
            )

    if 'W9002' in codes or 'W9003' in codes:
        w9002, w9003 = _check_quote_style(source_code)
        if 'W9002' in codes:
            for lineno, col, code in w9002:
                lines.append(
                    f'source.py:{lineno}:{col}: {code} avoidable-backslash-in-string'
                )
        if 'W9003' in codes:
            for lineno, col, code in w9003:
                lines.append(
                    f'source.py:{lineno}:{col}: {code} inconsistent-quote-style'
                )

    if 'W9004' in codes:
        for lineno, col, code in _check_operator_linebreak(source_code):
            lines.append(
                f'source.py:{lineno}:{col}: {code} inconsistent-operator-line-break'
            )

    if 'W9005' in codes:
        for lineno, col, code in _check_constant_scope(source_code):
            lines.append(
                f'source.py:{lineno}:{col}: {code} constant-in-function-scope'
            )

    if 'W9006' in codes:
        for lineno, col, code in _check_ambiguous_names(source_code):
            lines.append(
                f'source.py:{lineno}:{col}: {code} ambiguous-variable-name'
            )

    if 'W9007' in codes:
        for lineno, col, code in _check_block_comment_indent(source_code):
            lines.append(
                f'source.py:{lineno}:{col}: {code} block-comment-wrong-indent'
            )

    return '\n'.join(lines)


# ---------------------------------------------------------------------------
# W9001 — Docstring closing quote placement
# ---------------------------------------------------------------------------

def _check_docstring_placement(source_code):
    """
    For multiline docstrings the closing triple-quote must be on its own line.
    One-liner docstrings (opening and closing on the same line) are always
    correct and are not checked.
    """
    violations = []

    try:
        tree = ast.parse(source_code)
    except SyntaxError:
        return violations

    # Collect the start line numbers of actual docstring nodes.
    docstring_linenos = set()
    for node in ast.walk(tree):
        if not isinstance(node, (ast.Module, ast.FunctionDef,
                                 ast.AsyncFunctionDef, ast.ClassDef)):
            continue
        if not node.body:
            continue
        first = node.body[0]
        if (isinstance(first, ast.Expr) and
                isinstance(first.value, ast.Constant) and
                isinstance(first.value.value, str)):
            docstring_linenos.add(first.lineno)

    if not docstring_linenos:
        return violations

    try:
        toks = list(tokenize.generate_tokens(io.StringIO(source_code).readline))
    except tokenize.TokenError:
        return violations

    source_lines = source_code.splitlines()

    for tok in toks:
        if tok.type != tokenize.STRING:
            continue
        if tok.start[0] not in docstring_linenos:
            continue

        # Confirm it is a triple-quoted string.
        body = tok.string.lstrip('rRuU')
        if not (body.startswith('"""') or body.startswith("'''")):
            continue

        start_line, end_line = tok.start[0], tok.end[0]
        if start_line == end_line:
            # One-liner — correct by definition.
            continue

        # Multiline: the closing triple-quote must be alone on its final line.
        closing_text = source_lines[end_line - 1].strip()
        if closing_text not in ('"""', "'''"):
            violations.append((end_line, 0, 'W9001'))

    return violations


# ---------------------------------------------------------------------------
# W9002 / W9003 — Quote style checks (share one tokenize pass)
# ---------------------------------------------------------------------------

def _iter_plain_string_tokens(source_code):
    """
    Yield (tok, quote_char, is_triple) for every plain string token,
    excluding f-strings, raw strings, and byte strings.
    """
    try:
        toks = list(tokenize.generate_tokens(io.StringIO(source_code).readline))
    except tokenize.TokenError:
        return

    for tok in toks:
        if tok.type != tokenize.STRING:
            continue
        raw = tok.string

        # Strip any prefix characters to reach the opening delimiter.
        prefix = ''
        rest = raw
        while rest and rest[0].lower() in 'frbu':
            prefix += rest[0].lower()
            rest = rest[1:]

        if 'f' in prefix or 'b' in prefix or 'r' in prefix:
            continue

        is_triple = rest.startswith('"""') or rest.startswith("'''")
        quote_char = rest[0]  # ' or "
        yield tok, quote_char, is_triple


def _check_quote_style(source_code):
    """
    W9002: a string literal escapes its own delimiter character with a
           backslash when switching to the other quote style would remove
           the escape entirely.
    W9003: the file uses both single-quoted and double-quoted string literals
           (among strings free to use either style) starting from the point
           where a second style is first introduced.

    W9002 violations are reported per occurrence.
    W9003 violations are reported for every non-triple free string that
    deviates from the style established by the first such string in the file.

    Triple-quoted strings are excluded from W9003 — mixing `\"\"\"` docstrings
    with single-quoted short strings is standard practice.
    """
    w9002_violations = []

    # W9003 state: the quote style established by the first free single-line string.
    established_style = None
    w9003_violations = []

    for tok, quote_char, is_triple in _iter_plain_string_tokens(source_code):
        raw = tok.string
        other_char = '"' if quote_char == "'" else "'"

        try:
            value = ast.literal_eval(raw)
        except Exception:
            continue
        if not isinstance(value, str):
            continue

        contains_other = other_char in value
        escaped_current = '\\' + quote_char
        has_avoidable_escape = (escaped_current in raw) and not contains_other

        if has_avoidable_escape:
            # W9002: switching quote style would eliminate this escape.
            w9002_violations.append((tok.start[0], tok.start[1], 'W9002'))
            # Exclude from W9003: this string should just switch style.
            continue

        if is_triple:
            # Triple-quoted strings are exempt from W9003.
            continue

        if contains_other:
            # Pinned: must use current style to avoid a new escape.
            continue

        # Free single-line string: contributes to the W9003 consistency check.
        if established_style is None:
            established_style = quote_char
        elif quote_char != established_style:
            w9003_violations.append((tok.start[0], tok.start[1], 'W9003'))

    return w9002_violations, w9003_violations


# ---------------------------------------------------------------------------
# W9004 — Consistent binary-operator line-break style
# ---------------------------------------------------------------------------

def _check_operator_linebreak(source_code):
    """
    Run pycodestyle with W503 and W504 enabled against source_code via stdin.
    If violations of BOTH codes appear, the file mixes styles — emit one W9004
    violation pointing to the first line where the minority style appears. If
    only one style is used consistently, no violation is emitted.
    """
    try:
        result = subprocess.check_output(
            ['python3', '-m', 'pycodestyle', '--select=W503,W504', '-'],
            input=source_code,
            universal_newlines=True,
            stderr=subprocess.STDOUT,
        )
        output = result
    except subprocess.CalledProcessError as e:
        output = e.output
    except FileNotFoundError:
        return []

    pattern = re.compile(r':(\d+):\d+: (W503|W504)')
    w503_lines, w504_lines = [], []

    for line in output.splitlines():
        m = pattern.search(line)
        if m:
            lineno = int(m.group(1))
            (w503_lines if m.group(2) == 'W503' else w504_lines).append(lineno)

    if not (w503_lines and w504_lines):
        return []

    # Point to the first occurrence of whichever style appears less often.
    if len(w503_lines) >= len(w504_lines):
        minority_line = min(w504_lines)
    else:
        minority_line = min(w503_lines)

    return [(minority_line, 0, 'W9004')]


# ---------------------------------------------------------------------------
# W9006 — Ambiguous single-character variable names
# ---------------------------------------------------------------------------

def _check_ambiguous_names(source_code):
    """
    Flag uses of 'l', 'O', or 'I' as variable/parameter names.
    These single-character names are visually indistinguishable from the
    digits 1, 0, and 1 in most fonts (PEP 8, guideline 4).
    """
    violations = []
    ambiguous = {'l', 'O', 'I'}

    try:
        tree = ast.parse(source_code)
    except SyntaxError:
        return violations

    for node in ast.walk(tree):
        if isinstance(node, ast.Name) and isinstance(node.ctx, ast.Store):
            if node.id in ambiguous:
                violations.append((node.lineno, node.col_offset, 'W9006'))
        elif isinstance(node, (ast.FunctionDef, ast.AsyncFunctionDef)):
            all_args = (node.args.args + node.args.posonlyargs
                        + node.args.kwonlyargs)
            for arg in all_args:
                if arg.arg in ambiguous:
                    violations.append((arg.lineno, arg.col_offset, 'W9006'))
            if node.args.vararg and node.args.vararg.arg in ambiguous:
                violations.append(
                    (node.args.vararg.lineno, node.args.vararg.col_offset, 'W9006')
                )
            if node.args.kwarg and node.args.kwarg.arg in ambiguous:
                violations.append(
                    (node.args.kwarg.lineno, node.args.kwarg.col_offset, 'W9006')
                )
        elif isinstance(node, ast.Lambda):
            all_args = (node.args.args + node.args.posonlyargs
                        + node.args.kwonlyargs)
            for arg in all_args:
                if arg.arg in ambiguous:
                    violations.append((arg.lineno, arg.col_offset, 'W9006'))

    return violations


# ---------------------------------------------------------------------------
# W9007 — Block comment indentation
# ---------------------------------------------------------------------------

def _check_block_comment_indent(source_code):
    """
    A block comment must be indented to the same level as the code it
    applies to (the next non-blank, non-comment line that follows it).
    Uses tokenize to avoid false positives from '#' inside string literals.
    """
    violations = []
    lines = source_code.splitlines()

    try:
        toks = list(tokenize.generate_tokens(io.StringIO(source_code).readline))
    except tokenize.TokenError:
        return violations

    # Collect block comment line numbers (1-based) and their indent columns.
    block_comment_lines = {}
    for tok in toks:
        if tok.type != tokenize.COMMENT:
            continue
        lineno, col = tok.start
        if not lines[lineno - 1][:col].strip():  # only whitespace before #
            block_comment_lines[lineno] = col

    # For each block comment, find the next non-blank, non-comment line.
    for lineno in sorted(block_comment_lines):
        comment_indent = block_comment_lines[lineno]
        # Start scanning from the line immediately after this comment (0-based
        # index lineno, since lineno is 1-based).
        for j in range(lineno, len(lines)):
            next_line = lines[j]
            next_stripped = next_line.lstrip()
            if not next_stripped:
                continue  # blank line — keep looking
            if (j + 1) in block_comment_lines:
                continue  # another block comment line — keep looking
            code_indent = len(next_line) - len(next_stripped)
            if comment_indent != code_indent:
                violations.append((lineno, comment_indent, 'W9007'))
            break

    return violations


# ---------------------------------------------------------------------------
# W9005 — Module-level constant placement
# ---------------------------------------------------------------------------

def _check_constant_scope(source_code):
    """
    Flag any assignment inside a function body where the target name matches
    the ALL_CAPS_WITH_UNDERSCORES convention (at least two characters, first
    must be an uppercase letter), indicating the author intended a constant
    but placed it in the wrong scope.

    ast.walk visits every FunctionDef in the tree (including nested ones), so
    checking only the direct body statements of each node avoids duplicates.
    """
    violations = []
    constant_re = re.compile(r'^[A-Z][A-Z0-9_]+$')

    try:
        tree = ast.parse(source_code)
    except SyntaxError:
        return violations

    for node in ast.walk(tree):
        if not isinstance(node, (ast.FunctionDef, ast.AsyncFunctionDef)):
            continue
        for stmt in node.body:
            if isinstance(stmt, ast.Assign):
                for target in stmt.targets:
                    if (isinstance(target, ast.Name) and
                            constant_re.match(target.id)):
                        violations.append((stmt.lineno, 0, 'W9005'))
            elif isinstance(stmt, ast.AnnAssign):
                if (isinstance(stmt.target, ast.Name) and
                        constant_re.match(stmt.target.id)):
                    violations.append((stmt.lineno, 0, 'W9005'))

    return violations
