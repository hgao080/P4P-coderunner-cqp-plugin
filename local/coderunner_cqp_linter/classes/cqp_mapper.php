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

namespace local_coderunner_cqp_linter;

/**
 * Maps pylint messages to Code Quality Principles (CQP).
 *
 * Each of the 8 CQP principles captures a language-neutral aspect of code quality.
 * This class maps pylint symbol names to the most relevant CQP principle, providing
 * the principle name and a short guideline explanation for student feedback.
 *
 * @package    local_coderunner_cqp_linter
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cqp_mapper {

    /** @var array CQP principle definitions keyed by number. */
    const PRINCIPLES = [
        1 => [
            'name' => 'Clear Presentation',
            'short' => 'Different elements are easy to recognise and distinguish and the relationships between them are apparent.',
            'guideline' => 'Formatting (spacing, indentation, brackets, line length) should make it easy to distinguish separate elements.',
        ],
        2 => [
            'name' => 'Explanatory Language',
            'short' => 'The rationale, intent and meaning of code is explicit.',
            'guideline' => 'Names should be descriptive, comments should clarify intent, and symbolic constants should convey meaning of literals.',
        ],
        3 => [
            'name' => 'Consistent Code',
            'short' => 'Elements that are similar in nature are presented and used in a similar way.',
            'guideline' => 'Be consistent in documentation, notation, and implementation choices throughout your code.',
        ],
        4 => [
            'name' => 'Used Content',
            'short' => 'All elements that are introduced are meaningfully used.',
            'guideline' => 'All code constructs should be needed. Remove code that is executed but does not affect functionality.',
        ],
        5 => [
            'name' => 'Simple Constructs',
            'short' => 'Coding constructs are selected to minimise complexity for the intended reader.',
            'guideline' => 'Minimise nesting, keep control flow easy to follow, and keep expressions appropriately simple.',
        ],
        6 => [
            'name' => 'Minimal Duplication',
            'short' => 'Code repetition is avoided.',
            'guideline' => 'Consolidate repeated groups of statements; replace adjacent repeated statements with a loop.',
        ],
        7 => [
            'name' => 'Modular Structure',
            'short' => 'Related code is grouped together and dependencies between groups minimised.',
            'guideline' => 'Functions should implement a single task. Minimise scope of variables. Organise related elements together.',
        ],
        8 => [
            'name' => 'Problem Alignment',
            'short' => 'Implementation choices are consistent with the problem to be solved.',
            'guideline' => 'Data structures should align with the data represented. Algorithms should reflect the process they represent.',
        ],
    ];

    /**
     * Load cqp_codes.json and return a symbol → principle-number map.
     * Symbols not in the map fall back to get_default_principle().
     *
     * @return array{string: int} symbol => principle_number
     */
    private static function get_loaded_symbol_map(): array {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $path = dirname(__DIR__) . '/python/cqp_codes.json';
        $data = json_decode(file_get_contents($path), true);
        $map = [];
        foreach ($data['codes'] as $info) {
            $map[$info['symbol']] = (int)$info['principle'];
        }
        $cache = $map;
        return $cache;
    }

    /**
     * Get the CQP principle for a given pylint message.
     *
     * @param lint_message $msg The pylint lint message.
     * @return array{number: int, name: string, short: string, guideline: string}
     */
    public static function get_principle(lint_message $msg): array {
        $number = self::get_loaded_symbol_map()[$msg->symbol] ?? self::get_default_principle($msg->type);
        $principle = self::PRINCIPLES[$number];
        return array_merge(['number' => $number], $principle);
    }

    /**
     * Get a default CQP principle based on pylint message type when no specific mapping exists.
     *
     * @param string $type The pylint message type (error, warning, convention, refactor, etc.)
     * @return int CQP principle number.
     */
    private static function get_default_principle(string $type): int {
        switch ($type) {
            case 'fatal':
            case 'error':
                return 5; // Errors often relate to construct complexity or misuse.
            case 'warning':
                return 4; // Warnings often relate to unused or problematic content.
            case 'refactor':
                return 7; // Refactor suggestions relate to modular structure.
            case 'convention':
                return 1; // Convention messages relate to presentation.
            default:
                return 1;
        }
    }

    /**
     * Enrich a lint_message template data array with CQP principle information.
     *
     * @param array $templatedata The output of lint_message::to_template_data().
     * @param lint_message $msg The original message.
     * @return array Enriched template data with cqp_number, cqp_name, cqp_short, cqp_guideline.
     */
    public static function enrich_template_data(array $templatedata, lint_message $msg): array {
        $principle = self::get_principle($msg);
        $templatedata['cqp_number'] = $principle['number'];
        $templatedata['cqp_name'] = $principle['name'];
        $templatedata['cqp_short'] = $principle['short'];
        $templatedata['cqp_guideline'] = $principle['guideline'];
        return $templatedata;
    }

    /**
     * Get all CQP principles.
     *
     * @return array Keyed by principle number.
     */
    public static function get_all_principles(): array {
        return self::PRINCIPLES;
    }
}
