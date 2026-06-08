"""
CQP (Code Quality Principles) — Pylint, pycodestyle, and custom code mappings
for each principle.

Each principle is a dict keyed by tool code, with a tuple of:
    (symbolic_name, explanation)

The explanation is the pedagogical "why this matters" shown to students.

Tool prefixes:
    Pylint codes:      C0xxx, W0xxx, R0xxx, E0xxx  (4-digit)
    pycodestyle codes: Exxx, Wxxx                   (3-digit, run by _run_pycodestyle)
    Custom codes:      W90xx                         (run by cqp_custom_checkers)

Deduplication: where both tools detect the same violation, only the Pylint
code is kept (Pylint explanations are richer). The suppressed pycodestyle
equivalents are noted in comments.
    C0301  supersedes  E501   (line too long)
    C0321  supersedes  E701/E702  (multiple statements)
    C0113  supersedes  E714   (not ... is → is not)
    C0121  supersedes  E711   (== None)
    C0121  supersedes  E712   (== True/False)
"""

# ---------------------------------------------------------------------------
# Tool routing — cqp_checker.py uses this to decide which runner to call.
# Any code not listed here is assumed to be a Pylint code.
# ---------------------------------------------------------------------------
PYCODESTYLE_CODES = frozenset({
    'E101', 'W191',                    # tabs vs spaces
    'E201', 'E202', 'E203',            # whitespace inside brackets
    'E211',                            # whitespace before bracket
    'E221', 'E222', 'E225',            # whitespace around operators
    'E231',                            # whitespace after separator
    'E241',                            # multiple spaces after separator
    'E251', 'E252',                    # spaces around default/keyword =
    'E261', 'E262',                    # inline comment spacing
    'E265',                            # block comment format
    'E302', 'E303', 'E305', 'E306',    # blank lines
    'E502',                            # redundant backslash
    'E121', 'E122', 'E123', 'E124',    # continuation line alignment
    'E125', 'E126', 'E127', 'E128', 'E129',
    # W503/W504 (line break around binary operator) are intentionally omitted.
    # They are checked indirectly by the W9004 custom checker, which only flags
    # a file when BOTH styles appear (mixed), not when one style is used
    # consistently throughout.
})

# ---------------------------------------------------------------------------
# Custom checker routing — cqp_checker.py sends these to cqp_custom_checkers.
# Any code not in PYCODESTYLE_CODES and not in CUSTOM_CODES is a Pylint code.
# ---------------------------------------------------------------------------
CUSTOM_CODES = frozenset({
    'W9001',   # docstring-closing-quote-placement
    'W9002',   # avoidable-backslash-in-string
    'W9003',   # inconsistent-quote-style
    'W9004',   # inconsistent-operator-line-break
    'W9005',   # constant-in-function-scope
    'W9006',   # ambiguous-variable-name
    'W9007',   # block-comment-wrong-indent
})


# ---------------------------------------------------------------------------
# 1. Clear Presentation
#    Principle: Different elements are easy to recognise and distinguish and
#               the relationships between them are apparent.
#    Rationale: Clear layout improves our shared understanding by making the
#               individual elements easy to identify and signalling the
#               elements the author considers to be related.
# ---------------------------------------------------------------------------
CLEAR_PRESENTATION = {

    # --- Pylint codes ---

    'C0301': (
        'line-too-long',
        "This line exceeds the recommended length of 79 characters. Long lines "
        "force the reader to scroll horizontally and make it harder to see the "
        "structure of the code at a glance."
        # Supersedes pycodestyle E501.
    ),
    'C0321': (
        'multiple-statements',
        "Multiple statements appear on one line. Placing each statement on its "
        "own line makes the structure of the code easier to follow."
        # Supersedes pycodestyle E701/E702.
    ),
    'W0311': (
        'bad-indentation',
        "The indentation on this line does not match the expected level. "
        "Consistent indentation is how Python signals code structure — "
        "incorrect indentation misleads the reader about which block a line "
        "belongs to."
    ),
    # --- pycodestyle codes ---

    'E101': (
        'indentation-contains-mixed-spaces-and-tabs',
        "This line mixes tabs and spaces for indentation. Python requires "
        "consistent indentation — mixing the two causes confusing errors and "
        "makes the structure of the code unpredictable across different editors."
    ),
    'W191': (
        'indentation-contains-tabs',
        "This line uses a tab character for indentation. PEP 8 requires spaces "
        "— specifically 4 spaces per level. Tabs render differently in different "
        "editors, making the code look inconsistent to different readers."
    ),
    'E201': (
        'whitespace-after-bracket',
        "There is a space immediately after an opening bracket. Extra "
        "whitespace inside brackets creates visual noise that draws the eye "
        "without adding information — it makes it harder to see where the "
        "bracket's content begins. Brackets should sit flush against their "
        "contents, for example: my_list[0] not my_list[ 0 ]."
    ),
    'E202': (
        'whitespace-before-bracket',
        "There is a space immediately before a closing bracket. Extra "
        "whitespace inside brackets creates visual noise and makes it harder "
        "to see where the bracket's content ends. Brackets should sit flush "
        "against their contents, for example: my_list[0] not my_list[ 0 ]."
    ),
    'E203': (
        'whitespace-before-punctuation',
        "There is a space before a comma, semicolon, or colon. Punctuation "
        "attaches to what comes before it, so a space in front makes the "
        "preceding token look detached from the punctuation that belongs to "
        "it. Write (a, b) not (a , b)."
    ),
    'E211': (
        'whitespace-before-bracket',
        "There is a space between this name and the opening bracket. Without "
        "the space, the bracket signals to the reader that the name is being "
        "called or indexed. Adding a space breaks that visual connection and "
        "makes the call or index look like a separate element. Write "
        "my_func() not my_func ()."
    ),
    'E221': (
        'multiple-spaces-before-operator',
        "There are multiple spaces before this operator. Multiple spaces "
        "suggest intentional column alignment, implying a deliberate pattern "
        "— but when only this operator is padded, the reader will look for "
        "a pattern that is not there. Use a single space on each side of "
        "all operators."
    ),
    'E222': (
        'multiple-spaces-after-operator',
        "There are multiple spaces after this operator. Extra whitespace "
        "after an operator creates uneven visual rhythm that slows reading "
        "and looks unintentional. Use a single space on each side of an "
        "operator."
    ),
    'E225': (
        'missing-whitespace-around-operator',
        "There is no space around this operator. Surrounding operators with a "
        "single space on each side makes expressions easier to read, for "
        "example: x = a + b not x=a+b."
    ),
    'E231': (
        'missing-whitespace-after-separator',
        "There is no space after this comma, semicolon, or colon. A space "
        "after a separator helps the reader distinguish the separate items, "
        "for example: (a, b, c) not (a,b,c)."
    ),
    'E241': (
        'multiple-spaces-after-separator',
        "There are multiple spaces after this separator. A single space after "
        "a separator is the expected visual pattern — multiple spaces suggest "
        "intentional column alignment, but when that alignment does not "
        "continue consistently, it just looks like a typo. Use a single "
        "space after commas, colons, and semicolons."
    ),
    'E251': (
        'unexpected-spaces-around-keyword-equals',
        "There are spaces around the '=' in a keyword argument or default "
        "value. In a regular assignment, spaces around '=' are expected — so "
        "adding them here makes keyword arguments look like assignment "
        "statements, blurring the distinction between the two. Write "
        "def f(x=1) and f(x=1), not def f(x = 1) and f(x = 1)."
    ),
    'E252': (
        'missing-whitespace-around-parameter-default',
        "There is no space around the '=' for an annotated parameter default. "
        "When a type annotation is present, the '=' sits between the "
        "annotation and the default value — without spaces, the three elements "
        "run together and become harder to distinguish from one another. "
        "Write def f(x: int = 1) not def f(x: int=1)."
    ),
    'E261': (
        'at-least-two-spaces-before-inline-comment',
        "There is less than two spaces before this inline comment. "
        "Inline comments should be separated from the statement by at least "
        "two spaces so they are visually distinct from the code."
    ),
    'E262': (
        'inline-comment-should-start-with-hash-space',
        "This inline comment does not start with '# ' (hash followed by a "
        "space). All comments should use this format so they are immediately "
        "recognisable as comments."
    ),
    'E265': (
        'block-comment-should-start-with-hash-space',
        "This block comment does not start with '# ' (hash followed by a "
        "space). Block comments should use this format — the space separates "
        "the marker from the text and is a widely expected convention."
    ),
    'E302': (
        'expected-two-blank-lines',
        "There should be two blank lines before this top-level function "
        "definition. The extra whitespace visually separates major sections "
        "of the file and makes the structure clear at a glance."
    ),
    'E303': (
        'too-many-blank-lines',
        "There are too many consecutive blank lines here. Use one blank line "
        "to separate logical sections within a function, and two blank lines "
        "between top-level definitions. More than that adds unnecessary space."
    ),
    'E305': (
        'expected-two-blank-lines-after-definition',
        "There should be two blank lines after the last top-level function "
        "definition before module-level code. This separation makes it clear "
        "where the definitions end and the main code begins."
    ),
    'E306': (
        'expected-one-blank-line-before-nested-definition',
        "There should be one blank line before this nested function definition. "
        "The blank line makes it visually distinct from the surrounding code."
    ),
    'E502': (
        'redundant-backslash',
        "This backslash for line continuation is unnecessary because the line "
        "is already inside brackets. Remove the backslash and let the brackets "
        "handle the continuation — it is cleaner and less error-prone."
    ),
    'E121': (
        'continuation-line-under-indented',
        "This continuation line is under-indented. Continuation lines should "
        "be indented further than the opening line to make it clear they are "
        "part of the same expression."
    ),
    'E122': (
        'continuation-line-missing-indentation',
        "This continuation line is missing indentation. Align it with the "
        "opening delimiter or use a hanging indent so it is visually distinct "
        "from the next statement."
    ),
    'E123': (
        'closing-bracket-does-not-match-indentation',
        "The closing bracket does not match the indentation of the opening "
        "line. Consistent bracket placement makes it easy to find the end of "
        "a multiline construct at a glance — without it, the reader must "
        "scan the content rather than the structure to know where the "
        "expression ends. It should line up under the first non-whitespace "
        "character of the last item, or under the start of the line that "
        "begins the multiline construct."
    ),
    'E124': (
        'closing-bracket-does-not-match-visual-indentation',
        "The closing bracket does not match the visual indentation of the "
        "opening delimiter. A misaligned closing bracket makes it harder to "
        "find where the multiline expression ends — the reader cannot scan "
        "the left edge to find the matching bracket. Align the closing "
        "bracket with the opener."
    ),
    'E125': (
        'continuation-line-with-same-indent-as-next-logical-line',
        "This continuation line has the same indentation as the next logical "
        "line. Add extra indentation so the continuation is visually distinct "
        "from the block body that follows."
    ),
    'E126': (
        'continuation-line-over-indented-for-hanging-indent',
        "This continuation line is over-indented for a hanging indent. "
        "Over-indentation pushes the line deeper than expected, making it "
        "look like the start of a new nested block rather than a continuation "
        "of the expression above. Reduce the indentation to align with the "
        "expected level."
    ),
    'E127': (
        'continuation-line-over-indented-for-visual-indent',
        "This continuation line is over-indented. Extra indentation makes "
        "the continuation look like it belongs to a deeper block, misleading "
        "the reader about where the current expression ends. Align it with "
        "the opening delimiter."
    ),
    'E128': (
        'continuation-line-under-indented-for-visual-indent',
        "This continuation line is under-indented. Without enough indentation, "
        "the continuation looks like a new statement rather than part of the "
        "expression above, misleading the reader about where the expression "
        "ends. Align it with the opening delimiter."
    ),
    'E129': (
        'visually-indented-line-with-same-indent-as-next-logical-line',
        "This visually indented continuation line has the same indentation as "
        "the next logical line. Add extra indentation to distinguish the "
        "continuation from the block body."
    ),

    # --- Custom codes ---

    'W9001': (
        'docstring-closing-quote-placement',
        "The closing '\"\"\"' of this docstring is in the wrong position. "
        "Consistent docstring layout signals at a glance whether a docstring "
        "spans one line or many — a reader should not have to read the "
        "content to find out. For a one-line docstring, everything — opening "
        "quotes, text, and closing quotes — should be on a single line. For "
        "a multiline docstring, the closing '\"\"\"' should be on a line of "
        "its own, making it easy to see where the docstring ends."
    ),
    'W9007': (
        'block-comment-wrong-indent',
        "This block comment is not indented to the same level as the code it "
        "applies to. A block comment should be indented to match the "
        "indentation of the code that follows it, so the structure of the "
        "code is visually clear."
    ),

    # NOTE: The following pycodestyle codes are intentionally omitted because
    # they duplicate Pylint checks already mapped above:
    #   E501  (line-too-long)              superseded by C0301
    #   E701  (multiple-statements-colon)  superseded by C0321
    #   E702  (multiple-statements-semi)   superseded by C0321
    #   E703  (statement-ends-semicolon)   superseded by W0301
    #   E711  (comparison to None)         superseded by C0121
    #   E712  (comparison-to-singleton)    superseded by C0121
    #   E714  (not ... is ...)             superseded by C0113
    # W503/W504 (line-break around binary operator) are handled by the W9004
    # custom checker in CONSISTENT_CODE rather than individually here.
}

# ---------------------------------------------------------------------------
# 2. Explanatory Language
#    Principle: The rationale, intent and meaning of code is explicit.
#    Rationale: Being explicit in describing the purpose of the code elements
#               helps us understand the author's intention, thus improving
#               understandability.
# ---------------------------------------------------------------------------
EXPLANATORY_LANGUAGE = {
    # C0103 / C0104 need to be checked
    'C0103': (
        'invalid-name',
        "This name doesn't follow the expected naming convention. "
        "Names should communicate the purpose of a variable or function "
        "so a reader doesn't have to trace through the code to understand it. "
        "Use lowercase_with_underscores for variables and functions, and "
        "ALL_CAPS_WITH_UNDERSCORES for constants."
    ),
    'C0104': (
        'disallowed-name',
        "This name (e.g. 'foo', 'bar', 'x') is a placeholder that carries no "
        "meaning. Choose a name that describes what the element represents or "
        "stores."
    ),
    'W9006': (
        'ambiguous-variable-name',
        "The names 'l' (lowercase el), 'O' (uppercase oh), and 'I' (uppercase "
        "eye) are easily confused with the digits 1, 0, and 1 respectively. "
        "Choose a more distinctive name that cannot be misread at a glance."
    ),
    'W0622': (
        'redefined-builtin',
        "This name shadows a Python built-in such as 'list', 'id', or 'type'. "
        "Using a built-in name as a variable or parameter hides the original "
        "meaning and can cause confusing errors elsewhere in the code. Choose "
        "a more specific name that describes what the value actually represents."
    ),
}

# ---------------------------------------------------------------------------
# 3. Consistent Code
#    Principle: Elements that are similar in nature are presented and
#               used in a similar way.
#    Rationale: Consistency leverages familiarity to reduce the mental
#               effort required to understand the code.
# ---------------------------------------------------------------------------
CONSISTENT_CODE = {
    'R1710': (
        'inconsistent-return-statements',
        "Some return statements in this function return a value and others "
        "return nothing. Either all return statements should return an "
        "expression, or none should. Where nothing is returned, write "
        "'return None' explicitly to make the function's behaviour consistent "
        "and clear."
    ),
    'W9003': (
        'inconsistent-quote-style',
        "This file uses both single-quoted and double-quoted strings without "
        "a clear reason. When both styles appear together, a reader will look "
        "for a pattern — trying to work out whether the choice means something "
        "— and find there is none. Pick one quote style and use it "
        "consistently throughout the file. The only exception is when a string "
        "contains the quote character itself — use the other style then to "
        "avoid backslash escapes."
    ),
    'W9004': (
        'inconsistent-operator-line-break',
        "This file breaks long expressions both before and after binary "
        "operators in different places. A consistent style lets a reader know "
        "where to look for operators — either reliably at the end of each "
        "line or reliably at the start of the next. When the style varies, "
        "the reader cannot scan predictably and must re-read lines to find "
        "where each expression continues. Pick one style and apply it "
        "throughout the file."
    ),
}

# ---------------------------------------------------------------------------
# 4. Used Content
#    Principle: All elements that are introduced are meaningfully used.
#    Rationale: Non-contributing code elements require unnecessary mental
#               effort.
# ---------------------------------------------------------------------------
USED_CONTENT = {
    # No CS1 primer guidelines (Kirk et al., Tables 5 & 6) map to this
    # principle. Codes that were here have been removed to keep mappings
    # consistent with the documented guidelines.
}

# ---------------------------------------------------------------------------
# 5. Simple Constructs
#    Principle: Coding constructs are selected to minimise complexity
#               for the intended reader.
#    Rationale: Code that is perceived by the reader as simple is easier
#               to understand.
# ---------------------------------------------------------------------------
SIMPLE_CONSTRUCTS = {
    'C0113': (
        'unnecessary-negation',
        "This condition uses 'not ... is' instead of 'is not'. The 'is not' "
        "form is the simpler, more direct way to express this comparison and "
        "is easier to read correctly at a glance."
        # Supersedes pycodestyle E714.
    ),
    'C0121': (
        'singleton-comparison',
        "This comparison against True, False, or None is more complex than "
        "necessary. Instead of 'if x == True', write 'if x'; instead of "
        "'if x == False', write 'if not x'; instead of 'if x == None', write "
        "'if x is None'. The simpler form is easier to read and is the Python "
        "idiom."
        # Supersedes pycodestyle E712.
    ),
    'W9002': (
        'avoidable-backslash-in-string',
        "This string uses a backslash to escape a quote character, but "
        "switching to the other quote style would eliminate the escape "
        "entirely. For example, 'it\\'s here' is clearer as \"it's here\". "
        "Avoiding unnecessary backslashes makes strings easier to read."
    ),
}

# ---------------------------------------------------------------------------
# 6. Minimal Duplication
#    Principle: Code repetition is avoided.
#    Rationale: Repeated code can be difficult to change because changes
#               need to be made multiple times, there is a risk that not all
#               items are changed and/or difficult to understand because you
#               have to read more of it.
# ---------------------------------------------------------------------------
MINIMAL_DUPLICATION = {
    # No CS1 primer guidelines (Kirk et al., Tables 5 & 6) map to this
    # principle.
}

# ---------------------------------------------------------------------------
# 7. Modular Structure
#    Principle: Related code is grouped together and dependencies between
#               groups minimised.
#    Rationale: Placing related elements together makes code easier to
#               understand. Reducing inter-connectedness means that isolated
#               pieces can be more easily understood and can be modified
#               independently.
# ---------------------------------------------------------------------------
MODULAR_STRUCTURE = {
    'W9005': (
        'constant-in-function-scope',
        "This name uses ALL_CAPS_WITH_UNDERSCORES, which signals a constant, "
        "but it is defined inside a function rather than at module level. "
        "Constants are usually defined at the top of the file so they are "
        "easy to find and can be shared across functions."
    ),
}

# ---------------------------------------------------------------------------
# 8. Problem Alignment
#    Principle: Implementation choices are consistent with the problem
#               to be solved.
#    Rationale: An implementation that reflects the problem is easier to
#               understand and change.
# ---------------------------------------------------------------------------
PROBLEM_ALIGNMENT = {
    # TODO: No Pylint or pycodestyle codes map cleanly to the CS1 primer
    # guidelines for this principle. The primer's Problem Alignment guidelines
    # concern choosing appropriate data types and control constructs for the
    # task — judgements that static analysis tools cannot make automatically.
    # Candidates for future custom checkers: detecting misused data structures
    # or overly indirect patterns for a given problem type.
}

# ---------------------------------------------------------------------------
# Registry — maps string keys (used in the template) to principle dicts.
# This is what cqp_checker.py imports.
# ---------------------------------------------------------------------------
PRINCIPLES = {
    'clear_presentation': {
        'name': 'Clear Presentation',
        'principle': (
            'Different elements are easy to recognise and distinguish and '
            'the relationships between them are apparent.'
        ),
        'rationale': (
            'Clear layout improves our shared understanding by making the '
            'individual elements easy to identify and signalling the elements '
            'the author considers to be related.'
        ),
        'codes': CLEAR_PRESENTATION,
    },
    'explanatory_language': {
        'name': 'Explanatory Language',
        'principle': 'The rationale, intent and meaning of code is explicit.',
        'rationale': (
            'Being explicit in describing the purpose of the code elements '
            "helps us understand the author's intention, thus improving "
            'understandability.'
        ),
        'codes': EXPLANATORY_LANGUAGE,
    },
    'consistent_code': {
        'name': 'Consistent Code',
        'principle': (
            'Elements that are similar in nature are presented and used in '
            'a similar way.'
        ),
        'rationale': (
            'Consistency leverages familiarity to reduce the mental effort '
            'required to understand the code.'
        ),
        'codes': CONSISTENT_CODE,
    },
    'used_content': {
        'name': 'Used Content',
        'principle': 'All elements that are introduced are meaningfully used.',
        'rationale': (
            'Non-contributing code elements require unnecessary mental effort.'
        ),
        'codes': USED_CONTENT,
    },
    'simple_constructs': {
        'name': 'Simple Constructs',
        'principle': (
            'Coding constructs are selected to minimise complexity for the '
            'intended reader.'
        ),
        'rationale': (
            'Code that is perceived by the reader as simple is easier to '
            'understand.'
        ),
        'codes': SIMPLE_CONSTRUCTS,
    },
    'minimal_duplication': {
        'name': 'Minimal Duplication',
        'principle': 'Code repetition is avoided.',
        'rationale': (
            'Repeated code can be difficult to change because changes need '
            'to be made multiple times, there is a risk that not all items '
            'are changed and/or difficult to understand because you have to '
            'read more of it.'
        ),
        'codes': MINIMAL_DUPLICATION,
    },
    'modular_structure': {
        'name': 'Modular Structure',
        'principle': (
            'Related code is grouped together and dependencies between groups '
            'minimised.'
        ),
        'rationale': (
            'Placing related elements together makes code easier to understand. '
            'Reducing inter-connectedness means that isolated pieces can be '
            'more easily understood and can be modified independently.'
        ),
        'codes': MODULAR_STRUCTURE,
    },
    'problem_alignment': {
        'name': 'Problem Alignment',
        'principle': (
            'Implementation choices are consistent with the problem to be '
            'solved.'
        ),
        'rationale': (
            'An implementation that reflects the problem is easier to '
            'understand and change.'
        ),
        'codes': PROBLEM_ALIGNMENT,
    },
}
