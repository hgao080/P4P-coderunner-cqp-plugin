"""
CQP (Code Quality Principles) — code mappings and routing sets.

All active codes are defined in cqp_codes.json (same directory).
This module loads that file and exposes the same public API that
cqp_checker.py and the Jobe runner scripts import:

    PRINCIPLES           — dict keyed by principle name string
    PYCODESTYLE_CODES    — frozenset of codes routed to pycodestyle
    CUSTOM_CODES         — frozenset of codes routed to cqp_custom_checkers
    PYLINT_CODE_ALIASES  — dict of renamed pylint IDs to canonical IDs
    normalise_pylint_code(code) -> str
"""

import json
import os

_JSON_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'cqp_codes.json')

with open(_JSON_PATH, encoding='utf-8') as _f:
    _data = json.load(_f)

# ---------------------------------------------------------------------------
# Tool routing — derived from 'type' field in cqp_codes.json.
# ---------------------------------------------------------------------------
PYCODESTYLE_CODES = frozenset(c for c, v in _data['codes'].items() if v['type'] == 'pycodestyle')
CUSTOM_CODES      = frozenset(c for c, v in _data['codes'].items() if v['type'] == 'custom')

# ---------------------------------------------------------------------------
# Pylint version aliases — e.g. C0117 (pylint 3.x) → C0113 (canonical).
# ---------------------------------------------------------------------------
PYLINT_CODE_ALIASES = _data.get('aliases', {})


def normalise_pylint_code(code):
    """Map a pylint message ID to the canonical ID used as a principle key."""
    return PYLINT_CODE_ALIASES.get(code, code)


# ---------------------------------------------------------------------------
# Principle metadata — name, principle statement, rationale.
# The codes sub-dict is populated from cqp_codes.json so that adding or
# removing a code only requires editing that file.
# ---------------------------------------------------------------------------
_PRINCIPLE_META = [
    (
        'clear_presentation',
        'Clear Presentation',
        'Different elements are easy to recognise and distinguish and '
        'the relationships between them are apparent.',
        'Clear layout improves our shared understanding by making the '
        'individual elements easy to identify and signalling the elements '
        'the author considers to be related.',
    ),
    (
        'explanatory_language',
        'Explanatory Language',
        'The rationale, intent and meaning of code is explicit.',
        'Being explicit in describing the purpose of the code elements '
        "helps us understand the author's intention, thus improving "
        'understandability.',
    ),
    (
        'consistent_code',
        'Consistent Code',
        'Elements that are similar in nature are presented and used in a similar way.',
        'Consistency leverages familiarity to reduce the mental effort '
        'required to understand the code.',
    ),
    (
        'used_content',
        'Used Content',
        'All elements that are introduced are meaningfully used.',
        'Non-contributing code elements require unnecessary mental effort.',
    ),
    (
        'simple_constructs',
        'Simple Constructs',
        'Coding constructs are selected to minimise complexity for the intended reader.',
        'Code that is perceived by the reader as simple is easier to understand.',
    ),
    (
        'minimal_duplication',
        'Minimal Duplication',
        'Code repetition is avoided.',
        'Repeated code can be difficult to change because changes need '
        'to be made multiple times, there is a risk that not all items '
        'are changed and/or difficult to understand because you have to '
        'read more of it.',
    ),
    (
        'modular_structure',
        'Modular Structure',
        'Related code is grouped together and dependencies between groups minimised.',
        'Placing related elements together makes code easier to understand. '
        'Reducing inter-connectedness means that isolated pieces can be '
        'more easily understood and can be modified independently.',
    ),
    (
        'problem_alignment',
        'Problem Alignment',
        'Implementation choices are consistent with the problem to be solved.',
        'An implementation that reflects the problem is easier to understand and change.',
    ),
]

PRINCIPLES = {}
for _i, (_key, _name, _principle_text, _rationale) in enumerate(_PRINCIPLE_META, start=1):
    _codes = {
        code: (info['symbol'], info['explanation'])
        for code, info in _data['codes'].items()
        if info['principle'] == _i
    }
    PRINCIPLES[_key] = {
        'name':      _name,
        'principle': _principle_text,
        'rationale': _rationale,
        'codes':     _codes,
    }
