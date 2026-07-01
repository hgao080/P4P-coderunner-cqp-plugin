# CS1 PEP 8 Guidelines organised by CQP Principle

Guidelines drawn from the CS1 Teaching Primer (Tables 5 & 6) in:
Kirk, Luxton-Reilly & Tempero, "Distilling PEP 8 for Teaching Introductory Programming", ACE 2025.

Principles use the CQP naming (renamed from CSM).

---

## 1. Clear Presentation
*Different elements are easy to recognise and distinguish and the relationships between them are apparent.*

| ID | Teaching Topic | Guideline |
|----|---------------|-----------|
| 5  | Alignment | Limit all lines to a maximum of 79 characters. |
| 6  | Alignment | Spaces are the preferred indentation method. |
| 7  | Alignment | Use 4 spaces per indentation level. |
| 8  | Alignment | The preferred way of wrapping long lines is by using Python's implied line continuation inside parentheses, brackets and braces. Long lines can be broken over multiple lines by wrapping expressions in parentheses. These should be used in preference to using a backslash for line continuation. |
| 9  | Alignment | Continuation lines should align wrapped elements either vertically using Python's implicit line joining inside parentheses, brackets and braces, or using a hanging indent. When using a hanging indent the following should be considered: there should be no arguments on the first line and further indentation should be used to clearly distinguish itself as a continuation line. |
| 10 | Alignment | The closing brace/bracket/parenthesis on multiline constructs may either line up under the first non-whitespace character of the last line of list or it may be lined up under the first character of the line that starts the multiline construct. |
| 11 | Docstring | The `"""` that ends a multiline docstring should be on a line by itself. |
| 12 | Docstring | For one liner docstrings, please keep the closing `"""` on the same line. |
| 18 | Comment | Block comments generally apply to some (or all) code that follows them, and are indented to the same level as that code. |
| 19 | Comment | Each line of a block comment starts with a `#` and a single space (unless it is indented text inside the comment). |
| 20 | Comment | Paragraphs inside a block comment are separated by a line containing a single `#`. |
| 21 | Comment | Inline comments should start with a `#` and a single space. |
| 22 | Comment | Inline comments should be separated by at least two spaces from the statement. |
| 30 | Operator | In Python code, it is permissible to break before or after a binary operator. For new code Knuth's style is suggested. |
| 31 | Operator | Always surround binary operators with a single space on either side. If operators with different priorities are used, consider adding whitespace around the operators with the lowest priority(ies). Never use more than one space, and always have the same amount of whitespace on both sides of a binary operator. |
| 32 | Operator | Compound statements (multiple statements on the same line) are generally discouraged. |
| 36 | Conditional / Loop | Avoid extraneous whitespace immediately inside parentheses, brackets or braces; between a trailing comma and a following close parenthesis; immediately before a comma, semicolon, or colon. In a slice the colon acts like a binary operator and should have equal amounts of space on either side. In an extended slice, both colons must have the same amount of spacing applied. |
| 38 | Function | Surround top-level function definitions with two blank lines. |
| 39 | Function | Use blank lines in functions, sparingly, to indicate logical sections. |
| 42 | Parameter | Don't use spaces around the `=` sign when used to indicate a keyword argument, or when used to indicate a default value for an unannotated function parameter. |

The linter additionally enforces no trailing whitespace after the code or
comment on a line (`trailing-whitespace`, pycodestyle W291 — a completely blank
line left for spacing is *not* flagged, since whitespace-only lines are W293
which is deliberately left disabled) and that imports are placed at the top of
the file (`wrong-import-position`), both drawn from the CQP principle
(cqp.html) — the
latter matches the CS1 checklist's "File" guideline that elements should be in
their expected place (imports at the top). File-end conventions (a final
newline, no trailing blank lines) are deliberately NOT checked: in a CodeRunner
submission box they are an artifact of how the answer is entered rather than a
code-quality choice the student made.

---

## 2. Explanatory Language
*The rationale, intent and meaning of code is explicit.*

| ID | Teaching Topic | Guideline |
|----|---------------|-----------|
| 1  | Naming | There are a lot of different naming styles. You should recognise which style you are using. |
| 2  | Naming | The following naming styles are commonly distinguished: (list of styles). In addition, the following special forms using leading or trailing underscores are recognised (these can generally be combined with any case convention). |
| 3  | Naming | Use names that describe the information you are storing. |
| 4  | Naming | Never use the characters `l` (lowercase letter el), `O` (uppercase letter oh), or `I` (uppercase letter eye) as single character variable names. These are easily confused with the numbers zero and one. |
| 13 | Comment | Comments that contradict the code are worse than no comments. Always make a priority of keeping the comments up-to-date when the code changes. |
| 14 | Comment | Comments should be complete sentences. The first word should be capitalised, unless it is an identifier that begins with a lower case letter (never alter the case of identifiers). |
| 15 | Comment | Ensure that your comments are clear and easily understandable to other speakers of the language you are writing in. |
| 16 | Comment | Block comments generally consist of one or more paragraphs built out of complete sentences, with each sentence ending in a period. |
| 17 | Comment | An inline comment is a comment on the same line as a statement. Use inline comments sparingly. |
| 24 | Variable | Variable names should be lowercase, with words separated by underscores as necessary to improve readability. |
| 25 | Variable | Global variable names should be lowercase, with words separated by underscores as necessary to improve readability. |
| 26 | Constant | Constants are usually written in all capital letters with underscores separating words. Examples include `MAX_OVERFLOW` and `TOTAL`. |
| 37 | Function | Function names should be lowercase, with words separated by underscores as necessary to improve readability. |
| 41 | Parameter | If a function argument's name clashes with a reserved keyword, replace it with another name. |

---

## 3. Consistent Code
*Elements that are similar in nature are presented and used in a similar way.*

| ID | Teaching Topic | Guideline |
|----|---------------|-----------|
| 23 | Comment | Comments for non-public methods should appear after the `def` line. |
| 29 | String | Single-quoted strings and double-quoted strings are the same. This PEP does not make a recommendation for this. Pick a rule and stick to it. |
| 35 | Operator | It's ok to break before or after a binary operator as long as the convention is consistent locally. |
| 40 | Function | Be consistent in return statements. Either all return statements in a function should return an expression, or none of them should. If any return statement returns an expression, any return statements where no value is returned should explicitly state this as `return None`, and an explicit return statement should be present at the end of the function (if reachable). |

---

## 4. Used Content
*All elements that are introduced are meaningfully used.*

| ID | Teaching Topic | Guideline |
|----|---------------|-----------|
| —  | —             | *(No CS1-level guidelines from PEP 8 map to this principle.)* |

PEP 8 itself has nothing to say here, but the CQP principle (cqp.html) does:
all constructs should be needed, every line should be executable, and code
that runs without affecting behaviour should be removed. These are basic and
statically checkable, so the **linter** enforces them (rather than the AI):
unused imports, unused variables, unused arguments, unreachable code, pointless
statements, and unnecessary `pass`.

---

## 5. Simple Constructs
*Coding constructs are selected to minimise complexity for the intended reader.*

| ID | Teaching Topic | Guideline |
|----|---------------|-----------|
| 28 | String | When a string contains single or double quote characters, use the other one to avoid backslashes in the string. |
| 33 | Operator | Use `is not` operator rather than `not ... is`. |
| 34 | Operator | When comparing boolean values, don't include the `== value`. This is unnecessary. |

---

## 6. Minimal Duplication
*Code repetition is avoided.*

| ID | Teaching Topic | Guideline |
|----|---------------|-----------|
| —  | —             | *(No CS1-level guidelines from PEP 8 map to this principle.)* |

---

## 7. Modular Structure
*Related code is grouped together and dependencies between groups minimised.*

| ID | Teaching Topic | Guideline |
|----|---------------|-----------|
| 27 | Constant | Constants are usually defined on a module level. |

Modular Structure is out of scope for an introductory course — CS1 lab exercises
are small enough that grouping and scope concerns carry little weight — so this
principle is no longer assessed by either the linter or the AI. Guideline 27 was
previously enforced by the `constant-in-function-scope` (W9005) check, which has
been removed.

---

## 8. Problem Alignment
*Implementation choices are consistent with the problem to be solved.*

| ID | Teaching Topic | Guideline |
|----|---------------|-----------|
| —  | —             | *(No CS1-level guidelines from PEP 8 map to this principle. PEP 8's Appropriate Implementation items were all removed as out of scope for CS1.)* |
