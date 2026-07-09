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

namespace local_coderunner_cqp_linter\tools\ai;

use local_coderunner_cqp_linter\cqp_mapper;

/**
 * Assesses student code against the "semantic" Code Quality Principles that a
 * static linter cannot check well (naming and comment quality, duplication,
 * and problem alignment) using a configurable AI chat provider (OpenAI,
 * Azure OpenAI / AI Foundry, or Google Gemini — see {@see provider}).
 *
 * Disabled unless an administrator enables it and supplies an API key. All
 * failures are returned as a structured error; this class never throws so a
 * flaky API can never disrupt linting or quiz submission.
 *
 * The returned payload mirrors the static linter shape so it can flow through
 * the same UI and research recording:
 *   ['success' => bool, 'total_issues' => int,
 *    'messages' => [{line, type, code, symbol, message, cqp_number, cqp_name,
 *                    cqp_guideline, source, title}],
 *    'principles' => [{number, name, short, guideline, count, messages[]}],
 *    'error' => string]
 *
 * @package    local_coderunner_cqp_linter
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class analyzer {

    /**
     * CQP principles the AI can assess — the "semantic" ones a static linter
     * cannot check well. The static linter handles principles 1, 3, 4, and 5.
     * Used Content (4) is now covered by the linter (unused/unreachable/dead
     * code), and Modular Structure (7) is out of scope for an introductory
     * course, so neither is assessed by the AI.
     * @var int[]
     */
    public const SEMANTIC_PRINCIPLES = [2, 6, 8];

    /** @var int Max characters of question text to send as context. */
    public const MAX_PROBLEM_TEXT = 2000;

    /** @var provider The configured AI chat provider. */
    private provider $provider;

    /** @var int Max code size in bytes to send. */
    private int $maxcodesize;

    /**
     * Read configuration from plugin settings.
     *
     * @param provider|null $provider Provider to use; null builds the one
     *        selected in the admin settings.
     */
    public function __construct(?provider $provider = null) {
        $this->provider = $provider ?? provider::create();
        $this->maxcodesize = (int)(get_config('local_coderunner_cqp_linter', 'ai_max_code_size') ?: 8000);
    }

    /**
     * Whether AI analysis is usable site-wide.
     *
     * The site-wide on/off toggle was removed: AI is enabled globally whenever
     * an API key is configured (it cannot run without one), and is then opted in
     * per question. Leaving the API key blank keeps AI off everywhere.
     *
     * @return bool
     */
    public static function is_globally_enabled(): bool {
        return trim((string)get_config('local_coderunner_cqp_linter', 'ai_api_key')) !== '';
    }

    /**
     * Whether AI analysis runs for the given trigger.
     *
     * AI now always runs on both triggers (the "Check Code Quality" button and
     * quiz submission); the admin-configurable "when to run" setting was removed.
     * The $trigger parameter is retained for call-site clarity and API stability.
     *
     * @param string $trigger 'button' or 'submit'.
     * @return bool Always true.
     */
    public static function runs_on(string $trigger): bool {
        unset($trigger);
        return true;
    }

    /**
     * Reduce a caller-supplied principle list to the valid semantic set.
     * Null means "all semantic principles".
     *
     * @param int[]|null $principles
     * @return int[] Sorted, deduplicated, valid principle numbers.
     */
    private static function normalise_principles(?array $principles): array {
        if ($principles === null) {
            return self::SEMANTIC_PRINCIPLES;
        }
        $valid = array_flip(self::SEMANTIC_PRINCIPLES);
        $nums = array_values(array_unique(array_filter(
            array_map('intval', $principles),
            fn($n) => isset($valid[$n])
        )));
        sort($nums);
        return $nums;
    }

    /**
     * Run AI analysis on the given code for the given principles.
     *
     * @param string $code Student Python source.
     * @param int[]|null $principles Principle numbers to assess; null = all semantic.
     * @param string $problemtext Plain-text problem statement for context (optional).
     * @return array Payload (see class docblock).
     */
    public function analyze(string $code, ?array $principles = null, string $problemtext = ''): array {
        $empty = ['success' => false, 'total_issues' => 0, 'messages' => [], 'principles' => []];

        if (!self::is_globally_enabled()) {
            return array_merge($empty, ['error' => 'AI analysis is not enabled.']);
        }
        if (trim($code) === '') {
            return array_merge($empty, ['error' => 'No code provided.']);
        }
        if (strlen($code) > $this->maxcodesize) {
            return array_merge($empty, ['error' => 'Code too large for AI analysis.']);
        }

        $principles = self::normalise_principles($principles);
        if (empty($principles)) {
            return array_merge($empty, ['error' => 'No principles configured for AI analysis.']);
        }

        // Bound the problem statement so it can never dominate token cost.
        $problemtext = trim($problemtext);
        if (\core_text::strlen($problemtext) > self::MAX_PROBLEM_TEXT) {
            $problemtext = \core_text::substr($problemtext, 0, self::MAX_PROBLEM_TEXT);
        }

        try {
            $content = $this->call_api($code, $principles, $problemtext);
        } catch (\Throwable $e) {
            debugging('CQP AI analyzer error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return array_merge($empty, ['error' => 'AI request failed.']);
        }

        if ($content === null) {
            return array_merge($empty, ['error' => 'AI request failed.']);
        }

        return $this->build_payload($content, $principles);
    }

    /**
     * Build the system prompt describing the principles to assess.
     *
     * @param int[] $principles
     * @return string
     */
    private function build_system_prompt(array $principles): string {
        $lines = [];
        $lines[] = 'You are a careful code-quality reviewer for an introductory Python course.';
        $lines[] = 'Assess the student code ONLY against the Code Quality Principles listed below.';
        $lines[] = 'Do NOT comment on formatting, whitespace, line length, or syntax — those are handled separately.';
        $lines[] = 'Be conservative and prefer to under-report: only raise clear, specific, actionable '
                 . 'issues that would genuinely help the student. When in doubt, say nothing. If the '
                 . 'code is reasonable, return an empty list.';
        $lines[] = 'Each issue must reference a real line number in the provided code and explain the problem in one or two sentences, addressed to the student.';
        $lines[] = 'A problem statement may be provided. Use it ONLY as context for judging code quality '
                 . '(e.g. whether names and structure fit the task, and whether implementation choices align with the problem). '
                 . 'Do NOT assess correctness, test results, or compare against any reference solution. '
                 . 'Names that appear in, or are dictated by, the problem statement are fixed by the task — '
                 . 'never suggest renaming them.';
        $lines[] = '';
        $lines[] = 'Principles to assess:';

        $all = cqp_mapper::PRINCIPLES;
        foreach ($principles as $num) {
            if (!isset($all[$num])) {
                continue;
            }
            $p = $all[$num];
            $lines[] = sprintf('- CQP %d (%s): %s Guidance: %s',
                $num, $p['name'], $p['short'], $p['guideline']);
        }

        // Sharper boundaries for the principles the model most often confuses.
        // Only emit guidance for principles actually being assessed.
        $boundaries = [
            2 => 'CQP 2 (Explanatory Language) covers clarity of meaning: names, comments, and '
               . 'unexplained literal values. Apply it sparingly. '
               . '(a) Names: only flag a name a competent reader genuinely cannot understand '
               . '(e.g. a single letter that is not a loop counter, or a meaningless name like '
               . '"temp" or "data1"). Do NOT flag a name merely for being short, and NEVER flag a '
               . 'name that comes from or is required by the problem statement. Common conventions '
               . '(i/j/k loop indices, widely understood domain abbreviations) are acceptable — do '
               . 'not demand longer names when the meaning is already clear. '
               . '(b) Comments: introductory code is usually simple, so the ABSENCE of comments is '
               . 'NOT an issue by itself. Only raise a comment finding when a specific, non-obvious '
               . 'piece of logic would genuinely be hard to follow without one, or when an existing '
               . 'comment is misleading or contradicts the code. Never say "add comments" for '
               . 'straightforward, self-explanatory code. '
               . '(c) Literals: a magic number or other unexplained literal that would be clearer as '
               . 'a named constant belongs HERE, under CQP 2 — never under CQP 8. '
               . 'CQP 2 is NOT about whether code is used — unused or dead code is handled by the '
               . 'linter, so do not report it.',
            6 => 'CQP 6 (Minimal Duplication) covers repeated or near-identical code that should be '
               . 'consolidated, e.g. into a loop or a helper function.',
            8 => 'CQP 8 (Problem Alignment) covers ONLY the choice of data structure or algorithm '
               . 'relative to the task — e.g. using a list where the problem calls for a dictionary, '
               . 'or an approach that does not match what was asked. It does NOT cover naming, '
               . 'comments, or magic numbers / unexplained literals — every one of those is CQP 2, '
               . 'not CQP 8.',
        ];
        $boundarylines = [];
        foreach ($principles as $num) {
            if (isset($boundaries[$num])) {
                $boundarylines[] = '- ' . $boundaries[$num];
            }
        }
        if ($boundarylines) {
            $lines[] = '';
            $lines[] = 'Assign each finding to the SINGLE most appropriate principle. Boundaries:';
            $lines = array_merge($lines, $boundarylines);
            $lines[] = 'Do not relabel an issue just to make it fit an assessed principle. If an issue '
                     . 'does not clearly belong to one of the principles above, omit it.';
        }

        $lines[] = '';
        $lines[] = 'Respond with a single JSON object of this exact form:';
        $lines[] = '{"findings": [{"cqp": <principle number>, "line": <1-based line number>, '
                 . '"title": "<short label>", "message": "<explanation for the student>"}]}';
        $lines[] = 'The "cqp" value must be one of: ' . implode(', ', $principles) . '.';
        $lines[] = 'Return {"findings": []} if there are no issues.';

        return implode("\n", $lines);
    }

    /**
     * Call the configured AI provider and return the raw assistant content string.
     *
     * @param string $code
     * @param int[]  $principles
     * @param string $problemtext Plain-text problem statement for context (may be empty).
     * @return string|null Assistant message content, or null on failure.
     */
    private function call_api(string $code, array $principles, string $problemtext = ''): ?string {
        // Number the lines so the model can reference them reliably.
        $numbered = '';
        foreach (explode("\n", $code) as $i => $line) {
            $numbered .= ($i + 1) . ': ' . $line . "\n";
        }

        $usercontent = '';
        if ($problemtext !== '') {
            $usercontent .= "Problem statement (the task the student was asked to solve):\n\n"
                          . $problemtext . "\n\n";
        }
        $usercontent .= "Student code (with line numbers):\n\n" . $numbered;

        return $this->provider->complete($this->build_system_prompt($principles), $usercontent);
    }

    /**
     * Deterministic correction for the principle assignment the model most
     * often gets wrong: unused/dead content belongs to CQP 4 (Used Content),
     * which the model sometimes files under CQP 2 (naming/clarity).
     *
     * CQP 4 is now enforced by the linter and is not in the AI's assessed set,
     * so routing a finding to 4 makes the allowed-set filter in build_payload
     * drop it — this stops the model from re-reporting unused/dead code (often
     * mislabelled as CQP 2) on top of the linter.
     *
     * Conservative — only moves a finding when its text clearly signals a
     * specific, well-known category:
     *   - unused/dead/unreachable content -> CQP 4 (Used Content, linter-owned),
     *     so the allowed-set filter drops it rather than re-reporting;
     *   - magic numbers / unexplained literals -> CQP 2 (Explanatory Language),
     *     which the model often mis-files under CQP 8 (Problem Alignment).
     *
     * @param int $num The model-assigned principle number.
     * @param string $text Finding title + message.
     * @return int Corrected principle number.
     */
    private static function reclassify(int $num, string $text): int {
        $t = \core_text::strtolower($text);
        if (preg_match('/\b(unused|never used|not used|defined but never|dead code|unreachable)\b/', $t)) {
            return 4;
        }
        if (preg_match('/\b(magic number|magic value|named constant|should be a constant|hard[- ]?coded (?:number|value|constant))\b/', $t)) {
            return 2;
        }
        return $num;
    }

    /**
     * Strip a markdown code fence around the model's JSON, if present.
     *
     * Providers are asked for JSON output, but not every model honours a JSON
     * mode (and some OpenAI-compatible endpoints ignore response_format), in
     * which case the JSON often arrives wrapped in ```json ... ``` fences.
     *
     * @param string $content Raw assistant content.
     * @return string Content with any surrounding fence removed.
     */
    private static function strip_json_fence(string $content): string {
        $content = trim($content);
        if (preg_match('/^```[a-zA-Z]*\s*(.*?)\s*```$/s', $content, $m)) {
            return $m[1];
        }
        return $content;
    }

    /**
     * Parse the model's JSON content into the standard linter payload.
     *
     * @param string $content Assistant JSON content.
     * @param int[]  $allowed Allowed principle numbers.
     * @return array
     */
    private function build_payload(string $content, array $allowed): array {
        $empty = ['success' => false, 'total_issues' => 0, 'messages' => [], 'principles' => []];

        $data = json_decode(self::strip_json_fence($content), true);
        if (!is_array($data) || !isset($data['findings']) || !is_array($data['findings'])) {
            return array_merge($empty, ['error' => 'Could not parse AI response.']);
        }

        $allowedset = array_flip($allowed);
        $allprinciples = cqp_mapper::PRINCIPLES;
        $messages = [];

        foreach ($data['findings'] as $finding) {
            if (!is_array($finding)) {
                continue;
            }
            $num = (int)($finding['cqp'] ?? 0);
            $message = trim((string)($finding['message'] ?? ''));
            if ($message === '') {
                continue;
            }
            // Backstop the model's most common misclassification: unused/dead
            // content is CQP 4, not CQP 2. Reclassify before the allowed-set
            // filter, so it is correctly dropped if CQP 4 is not assessed here.
            $num = self::reclassify($num, trim((string)($finding['title'] ?? '')) . ' ' . $message);
            if (!isset($allowedset[$num]) || !isset($allprinciples[$num])) {
                continue;
            }
            $pdata = $allprinciples[$num];
            $messages[] = [
                'line'          => max(0, (int)($finding['line'] ?? 0)),
                'type'          => 'ai',
                'code'          => 'AI',
                'symbol'        => 'ai-cqp' . $num,
                'title'         => trim((string)($finding['title'] ?? '')),
                'message'       => $message,
                'cqp_number'    => $num,
                'cqp_name'      => $pdata['name'],
                'cqp_guideline' => $pdata['guideline'],
                'source'        => 'ai',
            ];
        }

        // Group by principle, mirroring the static linter's principles[] shape.
        $bynum = [];
        foreach ($messages as $m) {
            $bynum[$m['cqp_number']][] = $m;
        }
        ksort($bynum);

        $principlesout = [];
        foreach ($bynum as $num => $msgs) {
            $pdata = $allprinciples[$num];
            $principlesout[] = [
                'number'    => $num,
                'name'      => $pdata['name'],
                'short'     => $pdata['short'],
                'guideline' => $pdata['guideline'],
                'count'     => count($msgs),
                'messages'  => $msgs,
            ];
        }

        return [
            'success'      => true,
            'total_issues' => count($messages),
            'messages'     => $messages,
            'principles'   => $principlesout,
        ];
    }
}
