# CS101 Style Lab — Full Content

Companion to `cs101_style_lab_plan.md`. All Part A/B/C questions are `python3` type (function-call test
code, not `python3_w_input`). Standard Input is left empty for all of them. Part A and Part B preload
a working but poorly-styled (or, for Part B, well-styled) function into the answer box — students edit
it rather than writing from scratch. Part C is the only question where students write a function with
nothing preloaded. Precheck is enabled on every question below. The on-demand "Check Code Quality"
checker is enabled on all of them (informational/formative — no test mark is attached to style on any
question).

---

## Pre-attempt description (Moodle quiz-level "Description" field, untimed)

Shown to students on the pre-attempt page, before the quiz/attempt begins — costs none of the lab's
in-quiz time budget.

> **Welcome to the CS101 Style Lab**
>
> This lab gives you hands-on practice with the code style checker used in this course. Unlike most
> lab work, the focus here isn't on getting the "right" answer — it's specifically about code style:
> how readable, consistent, and well-organised your code is.
>
> **What to expect (about 90 minutes — estimate only, not yet validated against a pilot run):**
> - Five short exercises, each focused on a specific style principle, where you'll clean up the style
>   of a working function — with live feedback you can use to revise before submitting.
> - A short exercise where your goal is the opposite of usual — deliberately make a working function
>   as stylistically bad as you can.
> - One question where you write a function from scratch, with the checker available if you want it.
> - A few quick reflection questions at the end.
>
> **Marking:** your code's correctness is marked as usual. Style feedback throughout this lab is for
> your own learning — it is not graded.
>
> This lab is optional. *[Placeholder: consent/data-use note, to be finalised once ethics approval is
> confirmed.]*

## In-quiz orientation page (Moodle "Description" question, first page inside the attempt, before Q1)

> **Using the Check Code Quality button**
>
> As you write code in this lab, you'll see a "Check Code Quality" button next to the editor. Click
> it any time to see which style issues your current code triggers, grouped by principle, with an
> explanation of each. You can click it as often as you like and revise your code before final
> submission — it won't affect your mark.

---

## Part A, Question 1 — Clear Presentation

**Target principle:** Clear Presentation
**Target codes:** `E231`, `E225`, `E203`, `E221` (and related Clear Presentation codes from the
coverage doc, e.g. blank-line/indentation issues if introduced)

**Task description (shown to student):**
> This question focuses on **Clear Presentation** — formatting your code (spacing, indentation,
> layout) so it's easy for someone else to read at a glance. For example: `x=1` is harder to scan
> than `x = 1`.
>
> Below is a working function, `ticket_price(age, is_member)`, that calculates the price of a museum
> ticket (children under 13 pay $5; members aged 13–64 pay $8; non-members aged 13–64 pay $12;
> seniors 65 and over pay $6 regardless of membership). It already passes every test — your job is to
> **clean up its style** without changing what it does. Use "Check Code Quality" to see what's
> flagged, fix the issues, and resubmit. Don't add any `input()` calls or a main block — just edit the
> function as given.

**Starter code (preloaded into the answer box):**
```python
def ticket_price(age,is_member):
    if age<13:
        return 5
    if age  >=65:
        return 6
    if is_member :
        return 8
    return 12
```
Triggers: `E231` (no space after comma), `E225` (missing whitespace around `<` and `>=`), `E221`
(multiple spaces before `>=`), `E203` (space before `:`). (Note: an earlier draft used `return  12`
intending to trigger `E222`, but the actual pycodestyle code for extra space after a keyword like
`return` is `E271`, which isn't in the tool's configured code set — `E222` only covers spacing after
operators, not keywords. Verified directly against pycodestyle with the tool's actual code set: this
version triggers exactly the four codes listed above and nothing else.)

**Reference answer (clean version — for marking correctness only, not shown to students):**
```python
def ticket_price(age, is_member):
    if age < 13:
        return 5
    if age >= 65:
        return 6
    if is_member:
        return 8
    return 12
```

**Test cases:**

| # | Test code | Expected output | Visibility |
|---|---|---|---|
| 1 | `print(ticket_price(10, False))` | `5` | Visible |
| 2 | `print(ticket_price(13, True))` | `8` | Visible |
| 3 | `print(ticket_price(13, False))` | `12` | Hidden (boundary: just turned 13) |
| 4 | `print(ticket_price(64, True))` | `8` | Hidden (boundary: just under 65) |
| 5 | `print(ticket_price(65, False))` | `6` | Hidden (boundary: exactly 65, non-member) |

---

## Part A, Question 2 — Explanatory Language

**Target principle:** Explanatory Language
**Target codes:** `C0103`/`C0104` (placeholder/naming), `W9006` (`l`/`O`/`I` confusable names)

**Task description:**
> This question focuses on **Explanatory Language** — choosing names that make your code's purpose
> obvious without needing extra explanation. For example: `x = ...` tells a reader nothing, while
> `average_speed = ...` tells them exactly what the value is.
>
> Below is a working function, `summarise_trip(distance_km, hours)`, that returns the average speed
> in km/h, rounded to one decimal place, given a distance in kilometres and a travel time in hours.
> It already passes every test — your job is to **clean up its style** without changing what it does.
> Use "Check Code Quality" to see what's flagged, fix the issues, and resubmit. Don't add any
> `input()` calls or a main block — just edit the function as given.

**Starter code (preloaded into the answer box):**
```python
def summarise_trip(distance_km, hours):
    x = distance_km / hours
    l = round(x, 1)
    return l
```
Triggers: placeholder/uninformative naming on `x` (`C0103`/`C0104`), and `l` as a confusable
single-character name (`W9006`).

**Reference answer (clean version — for marking correctness only, not shown to students):**
```python
def summarise_trip(distance_km, hours):
    average_speed = distance_km / hours
    return round(average_speed, 1)
```

**Test cases:**

| # | Test code | Expected output | Visibility |
|---|---|---|---|
| 1 | `print(summarise_trip(100, 2))` | `50.0` | Visible |
| 2 | `print(summarise_trip(150, 3))` | `50.0` | Visible |
| 3 | `print(summarise_trip(10, 3))` | `3.3` | Hidden (tests rounding) |
| 4 | `print(summarise_trip(0, 5))` | `0.0` | Hidden (boundary: zero distance) |
| 5 | `print(summarise_trip(42, 4))` | `10.5` | Hidden |

---

## Part A, Question 3 — Consistent Code

**Target principle:** Consistent Code
**Target codes:** `W9003` (mixed quote style)

**Task description:**
> This question focuses on **Consistent Code** — making similar choices the same way throughout your
> code (e.g. picking one quote style and sticking with it), so readers aren't tripped up by needless
> variation. For example: mixing `'a' + "b"` is inconsistent — pick one quote style, e.g. `"a" + "b"`.
>
> Below is a working function, `format_greeting(name, time_of_day)`, that returns a greeting message
> — wishing the person good morning if `time_of_day` is `"morning"`, or saying hello otherwise, with
> the person's name included either way. It already passes every test — your job is to **clean up its
> style** without changing what it does. Use "Check Code Quality" to see what's flagged, fix the
> issues, and resubmit. Don't add any `input()` calls or a main block — just edit the function as
> given.

**Starter code (preloaded into the answer box):**
```python
def format_greeting(name, time_of_day):
    if time_of_day == "morning":
        return 'Good morning, ' + name + "!"
    return "Hello, " + name + '!'
```
Triggers: `W9003` — single- and double-quoted strings mixed without reason.

**Reference answer (clean version — for marking correctness only, not shown to students):**
```python
def format_greeting(name, time_of_day):
    if time_of_day == "morning":
        return "Good morning, " + name + "!"
    return "Hello, " + name + "!"
```

**Test cases:**

| # | Test code | Expected output | Visibility |
|---|---|---|---|
| 1 | `print(format_greeting("Alice", "morning"))` | `Good morning, Alice!` | Visible |
| 2 | `print(format_greeting("Bob", "evening"))` | `Hello, Bob!` | Visible |
| 3 | `print(format_greeting("Sam", "Morning"))` | `Hello, Sam!` | Hidden (case-sensitivity boundary) |
| 4 | `print(format_greeting("", "morning"))` | `Good morning, !` | Hidden (edge: empty name) |
| 5 | `print(format_greeting("Zoe", "afternoon"))` | `Hello, Zoe!` | Hidden |

---

## Part A, Question 4 — Simple Constructs

**Target principle:** Simple Constructs
**Target codes:** `C0121` (`== None`/`== True` simplification)

**Task description:**
> This question focuses on **Simple Constructs** — writing the simplest, most direct version of a
> check or condition, rather than an unnecessarily complicated way of saying the same thing. For
> example: `value == None` is more roundabout than the simpler, more direct `value is None`.
>
> Below is a working function, `is_valid_entry(value)`, that returns `True` if `value` is not `None`,
> and `False` if it is `None`. It already passes every test — your job is to **clean up its style**
> without changing what it does. Use "Check Code Quality" to see what's flagged, fix the issues, and
> resubmit. Don't add any `input()` calls or a main block — just edit the function as given.

**Starter code (preloaded into the answer box):**
```python
def is_valid_entry(value):
    if value != None:
        return True
    return False
```
Triggers: `C0121` — unnecessary comparison against `None`. (Note: an earlier draft of this starter
code used `value == None` with the branches swapped — that version is logically inverted relative to
the reference answer below and would fail the test suite outright now that this code is the prefilled
starting point rather than an illustrative example. Verified directly: this `!=` version produces
identical output to the reference answer across all five test cases below; the inverted `==` version
does not.)

**Reference answer (clean version — for marking correctness only, not shown to students):**
```python
def is_valid_entry(value):
    return value is not None
```

**Test cases:**

| # | Test code | Expected output | Visibility |
|---|---|---|---|
| 1 | `print(is_valid_entry(None))` | `False` | Visible |
| 2 | `print(is_valid_entry(5))` | `True` | Visible |
| 3 | `print(is_valid_entry(0))` | `True` | Hidden (boundary: falsy but not None) |
| 4 | `print(is_valid_entry(""))` | `True` | Hidden (boundary: falsy string, not None) |
| 5 | `print(is_valid_entry(False))` | `True` | Hidden (boundary: `False` is not `None`) |

These boundary cases catch the common bug of replacing `value != None` with `if value:` instead of
`value is not None` when a student "fixes" the style issue — keeping the correctness floor meaningful,
not just a style relabel.

---

## Part A, Question 5 — Used Content

**Target principle:** Used Content
**Target codes:** `W0612` (unused-variable), `W0104` (pointless-statement), `W0101` (unreachable)
(the other Used Content codes — `W0611` unused-import, `W0613` unused-argument, `W0107`
unnecessary-pass — don't fit naturally inside a single required-signature function and aren't
targeted by this question; see note below on `W0613` in particular)

**Task description (shown to student):**
> This question focuses on **Used Content** — every element you introduce (a variable, a line of
> code) should actually be used; anything left over makes a reader stop and wonder if they're missing
> something. For example: assigning `total = price * 2` and then never using `total` again is
> confusing — either use it or remove it.
>
> Below is a working function, `calculate_late_fee(days_late, is_student)`, that calculates a library
> late fee: `0` if `days_late` is 0 or negative; otherwise `days_late * 0.5`, capped at `5` if
> `is_student` is `True`. It already passes every test — your job is to **clean up its style** without
> changing what it does. Use "Check Code Quality" to see what's flagged, fix the issues, and resubmit.
> Don't add any `input()` calls or a main block — just edit the function as given.

**Starter code (preloaded into the answer box):**
```python
def calculate_late_fee(days_late, is_student):
    if days_late <= 0:
        return 0
    fee = days_late * 0.5
    doubled_fee = fee * 2
    fee > 0
    if is_student:
        return min(fee, 5)
    return fee
    print("fee calculated")
```
Triggers: `W0612` (`doubled_fee` is assigned but never used), `W0104` (the bare `fee > 0` line
computes a value and throws it away), `W0101` (the `print` after the final `return` can never run).
Verified against pylint directly — all three fire, and behaviour matches the clean reference answer
below.

**Reference answer (clean version — for marking correctness only, not shown to students):**
```python
def calculate_late_fee(days_late, is_student):
    if days_late <= 0:
        return 0
    fee = days_late * 0.5
    if is_student:
        return min(fee, 5)
    return fee
```

**Test cases:**

| # | Test code | Expected output | Visibility |
|---|---|---|---|
| 1 | `print(calculate_late_fee(0, False))` | `0` | Visible |
| 2 | `print(calculate_late_fee(4, False))` | `2.0` | Visible |
| 3 | `print(calculate_late_fee(-3, True))` | `0` | Hidden (boundary: negative days, non-zero branch avoided) |
| 4 | `print(calculate_late_fee(20, True))` | `5` | Hidden (boundary: cap binds for a student) |
| 5 | `print(calculate_late_fee(6, True))` | `3.0` | Hidden (boundary: student, but cap doesn't bind) |

A note on `W0613` (unused-argument): it's deliberately excluded here. The only way to make the starter
code trigger it while staying functionally correct would be to require a parameter the logic never
needs — which would make the *clean reference answer* carry the same unused parameter as the starter
code, collapsing the contrast this question relies on. `W0613` is better suited to a question (or a
Part B violation) where an unused parameter is unambiguously avoidable, not load-bearing in the
signature.

---

## Reference card (Moodle "Description" page, shown before Part B)

A single non-interactive recap page, shown once between Part A and Part B. Quick scan, not a re-read
of the full task descriptions.

> Before you try to deliberately trigger as many different style issues as possible, here's a quick
> recap of the principles you've practiced so far:

| Principle | What it means | Quick example |
|---|---|---|
| Clear Presentation | Formatting — spacing, indentation, layout — so code is easy to read at a glance | `x=1` → `x = 1` |
| Explanatory Language | Names that make purpose obvious without extra explanation | `x = ...` → `average_speed = ...` |
| Consistent Code | Making similar choices the same way throughout (e.g. one quote style) | `'a' + "b"` → `"a" + "b"` |
| Simple Constructs | The simplest, most direct way to express a check or condition | `value == None` → `value is None` |

**Note:** this card lists the four principles Part B currently targets, matching the original
four-question design. Used Content (Part A, Question 5) is not yet included here or in Part B's
target list below — see the flag at the end of Part B for the open decision on whether to fold it in.

---

## Part B — Maximise-violations exercise

**Shared starter function** (preloaded into the answer box, identical for every student). Confirmed
clean — passes the CQP checker with zero violations as given, and has enough structure (2 parameters,
a 4-way conditional, an intermediate variable, multi-piece string assembly) to support violations
across Clear Presentation, Explanatory Language, Consistent Code, and Simple Constructs.

```python
def describe_temperature(celsius, location):
    if celsius < 0:
        condition = "freezing"
    elif celsius < 15:
        condition = "cool"
    elif celsius < 25:
        condition = "mild"
    else:
        condition = "hot"
    return "It is " + condition + " in " + location + "."
```

**Task description (shown to student):**
> Below is a working function. Your job is **not** to fix it — it's to make it as stylistically bad
> as possible while keeping it working exactly the same. Edit the code to introduce as many
> *different kinds* of style violation as you can. Use "Check Code Quality" as often as you like to
> see what you've triggered so far. Try to trigger violations across as many of these 4 principles as
> possible: Clear Presentation, Explanatory Language, Consistent Code, and Simple Constructs.
> Repeating the same trick many times (e.g. making every line too long) won't count for much; variety
> is what we're after.
>
> Your function must still behave exactly as before — it will be tested against the same inputs and
> outputs as the original.

**Test cases** (single fixed set, checks behaviour preservation — not style):

| # | Test code | Expected output |
|---|---|---|
| 1 | `print(describe_temperature(-5, "Auckland"))` | `It is freezing in Auckland.` |
| 2 | `print(describe_temperature(10, "Wellington"))` | `It is cool in Wellington.` |
| 3 | `print(describe_temperature(20, "Hamilton"))` | `It is mild in Hamilton.` |
| 4 | `print(describe_temperature(30, "Tauranga"))` | `It is hot in Tauranga.` |
| 5 | `print(describe_temperature(0, "Dunedin"))` | `It is cool in Dunedin.` (boundary: 0 → "cool" branch) |
| 6 | `print(describe_temperature(15, "Rotorua"))` | `It is mild in Rotorua.` (boundary: 15 → "mild" branch) |

No test mark is attached to style; functional correctness is the pass condition. Data capture is via
`recordLintEvent` on every "Check Code Quality" click — analyse using distinct-principle/distinct-code
counts from `resultsjson`, not raw `issuecount`.

**Open decision — does Part B stay at 4 principles or expand to 5?** This exercise (and the reference
card above it) still target only the original four principles from when Part A had four questions.
Now that Used Content (Part A, Q5) exists, there are two consistent options: (a) leave Part B as-is —
a deliberate scoping choice, e.g. because Used Content's violations (unused variable, pointless
statement, unreachable code) are harder to "deliberately introduce" in a natural-looking way inside a
single small function than the other four principles' violations are; or (b) expand Part B's target
list to all 5. The starter function has already been checked directly against the Used Content codes
(`W0612`, `W0104`, `W0101`, `W0611`, `W0613`, `W0107`) and is clean, so (b) would only require text
changes to the reference card and task description above, not a different starter function. Not
resolved yet — left as-is pending that decision.

---

## Part C — Synthesis exercise (write from scratch, full linting)

**Target principles:** all five taught in this lab — Clear Presentation, Explanatory Language,
Consistent Code, Simple Constructs, Used Content. Unlike Part A, no single principle's codes are
isolated; the full set is active simultaneously, the same as Part B.

**Why this question exists:** Part A and Part B both measure students working *with* the tool —
fixing flagged issues, or deliberately reverse-engineering what gets flagged. Neither tells us
whether anything has actually transferred to how a student writes code unprompted. This question is
the lab's only "write from scratch" moment, and it exists specifically to generate one piece of
tool-independent evidence: a function written with no prior interaction with the checker on this
particular piece of code.

**Task description (shown to student):**
> This is the only question in the lab where you're writing a function from scratch, with no
> starting code provided. Write it however you normally would. The "Check Code Quality" button is
> still available if you want to use it, and it still won't affect your mark — but there's no
> obligation to use it before your first submission.
>
> Write a function `describe_order(item_count, unit_price, is_member)` that summarises a shopping
> order.
> - The subtotal is `item_count * unit_price`.
> - If `is_member` is `True` **and** the subtotal is at least `50`, apply a 10% discount to the
>   subtotal (i.e. multiply it by `0.9`).
> - Round the final cost to two decimal places using `round(value, 2)`.
> - Return a string in the form `"Your order of <item_count> items costs $<final_cost>."` — for
>   example, `"Your order of 5 items costs $54.0."`
>
> Write only the function — do not include any `input()` calls or a main block.

**Reference answer (for marking correctness only — not shown to students as a style exemplar):**
```python
def describe_order(item_count, unit_price, is_member):
    discount_rate = 0.9 if is_member and item_count * unit_price >= 50 else 1.0
    final_cost = round(item_count * unit_price * discount_rate, 2)
    return "Your order of " + str(item_count) + " items costs $" + str(final_cost) + "."
```

**Test cases:**

| # | Test code | Expected output | Visibility |
|---|---|---|---|
| 1 | `print(describe_order(5, 12, True))` | `Your order of 5 items costs $54.0.` | Visible |
| 2 | `print(describe_order(4, 15, False))` | `Your order of 4 items costs $60.0.` | Visible |
| 3 | `print(describe_order(2, 10, True))` | `Your order of 2 items costs $20.0.` | Hidden (boundary: member, but subtotal below the $50 threshold — no discount) |
| 4 | `print(describe_order(10, 5, True))` | `Your order of 10 items costs $45.0.` | Hidden (boundary: subtotal exactly $50 — discount applies) |
| 5 | `print(describe_order(1, 49.99, False))` | `Your order of 1 items costs $49.99.` | Hidden (non-member at near-threshold subtotal — discount must not apply; also exercises decimal input) |

Reference answer and all five test cases verified directly: identical output across both the clean
and a deliberately bad-style version of the function, confirming the test set checks behaviour only.

**Methodology note — how this question is analysed (distinct from Parts A and B):**

The research-relevant artifact is the student's code at their **first** "Check Code Quality" click on
this question, not their final submission. Because the tool only fires on demand, that first click
captures code written with no prior tool interaction on this specific problem — the closest this lab
gets to a tool-independent sample of how a student writes code after the rest of the lab. (If a
student submits without ever clicking the button, their final submitted code serves the same role.)
Revision activity after that first click still happens and is still logged via `recordLintEvent`, but
it answers a different, less interesting question — "can they fix what's flagged" — which Parts A and
B already cover.

That first-click artifact is evaluated against each student's own **pre-lab violation history**, not
against an absolute violation count or against other students. Separately, each student's pre-lab
regular CS101 submissions are linted offline for the same codes covered by the five taught
principles, giving a personal "opportunity set" — the specific codes that student has actually
demonstrated a propensity to write. Only codes in a student's own opportunity set are scored for
that student here: a violation that never appeared in their pre-lab work isn't evidence either way if
it's also absent from this exercise, since there was no behaviour to change in the first place. Within
the opportunity set, the question is binary per code: present pre-lab and absent here counts as
transfer; present in both does not.

This is an *immediate*-transfer signal only — captured minutes after the rest of the lab, in the same
session. It does not establish durable internalisation; that claim still depends on linting students'
regular submissions in subsequent labs/assignments after this one, which is the longitudinal
comparison already noted as a separate piece of future work.

---

## Part D — Qualitative survey (3–4 questions, end of lab)

1. Did the Code Quality feedback help you understand **why** certain style choices are better, not
   just **what** to change? Give an example if you can.
2. Is there a specific style habit from today that you think you'll carry into future labs or
   assignments? What is it?
3. Was any part of the feedback confusing, or did you ever disagree with what it flagged? Describe
   what happened.
4. What would make this feedback more useful to you as a learning tool?

These are deliberately open-ended rather than Likert-scale, to capture evidence of genuine
understanding versus surface compliance — directly relevant to the project's research question.
