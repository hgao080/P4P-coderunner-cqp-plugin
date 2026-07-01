"""
Violation examples for CQP checker testing.

Every checker code mapped in cqp_principles.py is deliberately triggered
somewhere in this file. Not valid production code.

NOTE: E101 (mixed tabs/spaces) and W191 (tab indentation) cannot be triggered
alongside space-indented code in Python 3 — mixing causes TabError (a syntax
error). A standalone file using consistent tab indentation throughout would
trigger W191. E101 requires a file that mixes both within the same block,
which Python 3 rejects outright.
"""

# ── W0611: unused-import ──────────────────────────────────────────────────────
# 'math' is imported but never referenced anywhere in this file.
import math

# ── W9003: inconsistent-quote-style ──────────────────────────────────────────
# Both quote styles used throughout this file (see SINGLE_QUOTED / DOUBLE_QUOTED
# and strings elsewhere).
SINGLE_QUOTED = 'hello'
DOUBLE_QUOTED = "world"

# ── W291: trailing-whitespace ─────────────────────────────────────────────────
# The assignment line below ends with spaces after the value. (W291 flags only
# content lines like this — a blank line containing spaces is W293, which is
# deliberately not enabled.)
trailing_ws_demo = 1   

# ── C0103: invalid-name ───────────────────────────────────────────────────────
# PascalCase at module level — should be ALL_CAPS_WITH_UNDERSCORES (constant)
# or lowercase_with_underscores (variable).
BadlyNamedVariable = 42

# ── C0104: disallowed-name ────────────────────────────────────────────────────
foo = 1
bar = 2

# ── W9006: ambiguous-variable-name ───────────────────────────────────────────
l = 10
O = 0
I = 1

# ── W0622: redefined-builtin ──────────────────────────────────────────────────
list = [1, 2, 3]
id = 5

# ── C0413: wrong-import-position ──────────────────────────────────────────────
# This import sits after the module-level assignments above, so it is not at
# the top of the file where imports belong.
import os


# ── E302: expected-two-blank-lines ────────────────────────────────────────────
# Only one blank line above — two required before a top-level function.

def badly_named_Function(x = 1, y: int=2):
  # E251: spaces around = in keyword arg  →  x = 1  (should be x=1)
  # E252: no spaces around = for annotated param  →  y: int=2  (should be y: int = 2)
  # C0103: function name mixes cases (should be snake_case)
  # W0311: 2-space indentation used throughout this function (expected 4)
  """
  Multiline docstring with closing quotes on the wrong line."""
  # W9001: closing """ must be on a line of its own

  a = 1; b = 2                        # C0321: two statements on one line
  my_list = [ 1, 2, 3 ]               # E201 (space after [), E202 (space before ])
  pair = (my_list[0] , my_list[1])    # E203: space before comma
  length = len (my_list)              # E211: space between name and bracket
  total=a+b                           # E225: no spaces around operator
  val  = 5                            # E221: multiple spaces before operator
  other =  10                         # E222: multiple spaces after operator
  coords = (1,2,3)                    # E231: no space after comma
  padded = (1,  2)                    # E241: multiple spaces after comma
  z = 99 # comment                    # E261: fewer than two spaces before comment
  w = 88  ## wrong format             # E262: comment must start with '# '
  apostrophe = 'it\'s here'           # W9002: switch to "it's here" to avoid backslash
  very_long_variable_name_exceeding_seventy_nine_chars = total + val + other  # C0301
  combined = (a + \
              b)                      # E502: backslash redundant inside parentheses

  #missing space after hash           # E265: block comment must start with '# '
# comment at wrong indentation level  # W9007: block comment not indented to match code

  # W9004: inconsistent operator line break
  # Some expressions break after the operator, some before — mixed in one file.
  result_a = (total +                 # breaks after operator
              val)
  result_b = (total                   # breaks before operator
              + val)

  if z == True:                       # C0121: use 'if z:' instead
    pass
  if w == None:                       # C0121: use 'if w is None:' instead
    pass
  if not z is None:                   # C0113: use 'if z is not None:'
    pass

  def nested():                       # E306: blank line required before nested def
    pass

  if total > 0:
    return total                      # R1710: some paths return a value, this one doesn't
  # implicit return None — inconsistent with the return above


# ── E303: too-many-blank-lines ────────────────────────────────────────────────
# Three consecutive blank lines below — maximum between definitions is two.



def continuation_examples():
    """Demonstrates continuation line indentation violations E121–E129."""

    a = 1
    b = 2

    # E122: continuation line missing indentation (starts at column 0)
    r_e122 = (
a + b
    )

    # E121: continuation under-indented for a hanging indent
    # (base indent is col 4; hanging indent expects col 8; got col 6)
    r_e121 = (
      a +
      b
    )

    # E123: closing bracket indent does not match the opening line's indent
    r_e123 = [
        a,
        b,
        ]

    # E124: closing bracket does not match the visual (opener) indent
    r_e124 = [a,
              b,
        ]

    # E125: continuation line has same indent as the next logical line (the body)
    if (a == 1 and
        b == 2):
        pass

    # E126: continuation over-indented for a hanging indent
    r_e126 = (
            a +
            b
    )

    # E127: continuation over-indented for a visual indent
    r_e127 = [a,
                  b]

    # E128: continuation under-indented for a visual indent
    r_e128 = [a,
        b]

    # E129: visually indented continuation has same indent as the next logical line
    if (a == 1
        and b == 2):
        pass


# ── CQP 4: Used Content — W0613, W0612, W0104, W0101 ─────────────────────────
def used_content_examples(scale):       # W0613: 'scale' parameter is never used
    """Demonstrate Used Content violations (CQP 4)."""
    result = 0
    spare = 42                          # W0612: 'spare' assigned but never used
    result                              # W0104: pointless statement — value discarded
    return result
    result = result + 1                 # W0101: unreachable code after the return


# ── W0107: unnecessary-pass ──────────────────────────────────────────────────
def unnecessary_pass_example(values):
    """The pass below is redundant — the loop body already has a statement."""
    for item in values:
        print(item)
        pass                            # W0107: unnecessary pass; block isn't empty

# ── E305: expected-two-blank-lines after last definition ─────────────────────
# Only one blank line above — two required before module-level code.

module_level_code = True
