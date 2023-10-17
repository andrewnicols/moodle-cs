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

namespace MoodleHQ\MoodleCS\moodle\Tests\Sniffs\CodeAnalysis;

use MoodleHQ\MoodleCS\moodle\Tests\MoodleCSBaseTestCase;

// phpcs:disable moodle.NamingConventions

/**
 * Test the NoLeadingSlash sniff.
 *
 * @package    moodle-cs
 * @category   test
 * @copyright  2023 Andrew Lyons <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \MoodleHQ\MoodleCS\moodle\Sniffs\Namespaces\NamespaceStatementSniff
 */
class ComponentNameFullyQualifiedSniffTest extends MoodleCSBaseTestCase
{
    public static function provider(): array
    {
        return [
            [
                'fixture' => 'get_string',
                'warnings' => [
                    4 => "Component name should be 'mod_forum' in call to get_string: 'forum'",
                    5 => "Component name should be 'core_grades' in call to get_string: 'grades'",
                    7 => "Component name should be 'mod_forum' in call to get_string: 'forum'",
                    8 => "Component name should be 'core_grades' in call to get_string: 'grades'",
                    12 => "Component name should be 'mod_forum' in call to new lang_string: 'forum'",
                    13 => "Component name should be 'mod_forum' in call to new moodle_exception: 'forum'",
                ],
                'errors' => [],
            ],
        ];
    }
    /**
     * @dataProvider provider
     */
    public function test_component_name(
        string $fixture,
        array $warnings,
        array $errors
    ): void
    {
        $this->set_standard('moodle');
        $this->set_sniff('moodle.CodeAnalysis.ComponentNameFullyQualified');
        $this->set_fixture(sprintf("%s/fixtures/mocked/%s.php", __DIR__, $fixture));

        $base = dirname(__DIR__, 3);
        $this->set_component_mapping([
            'core' => "{$base}/lib",
            'core_grades' => "{$base}/grades",
            'mod_forum' => "{$base}/mod/forum",
        ]);
        $this->set_warnings($warnings);
        $this->set_errors($errors);

        $this->verify_cs_results();
    }

    public static function components_unavailable_provider(): array
    {
        return [
            [
                'fixture' => 'get_string',
                'warnings' => [
                    4 => "Component name not fully qualified in call to get_string: 'forum'",
                    5 => "Component name not fully qualified in call to get_string: 'grades'",
                    7 => "Component name not fully qualified in call to get_string: 'forum'",
                    8 => "Component name not fully qualified in call to get_string: 'grades'",
                    12 => "Component name not fully qualified in call to new lang_string: 'forum'",
                    13 => "Component name not fully qualified in call to new moodle_exception: 'forum'",
                ],
                'errors' => [],
            ],
        ];
    }
    /**
     * @dataProvider components_unavailable_provider
     */
    public function test_component_name_unavailable(
        string $fixture,
        array $warnings,
        array $errors
    ): void
    {
        $this->set_standard('moodle');
        $this->set_sniff('moodle.CodeAnalysis.ComponentNameFullyQualified');
        $this->set_fixture(sprintf("%s/fixtures/unmocked/%s.php", __DIR__, $fixture));
        $this->set_warnings($warnings);
        $this->set_errors($errors);

        $this->verify_cs_results();
    }
}
