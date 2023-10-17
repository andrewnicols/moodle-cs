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

namespace MoodleHQ\MoodleCS\moodle\Sniffs\CodeAnalysis;

use MoodleHQ\MoodleCS\moodle\Util\MoodleUtil;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

// phpcs:disable moodle.NamingConventions

/**
 * Checks that calls to methods which consume a component have a fully-qualified component argument.
 *
 * @package    moodle-cs
 * @copyright  2023 Andrew Lyons <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ComponentNameFullyQualifiedSniff implements Sniff {
    protected array $componentFunctions = [
        'get_string' => 2,
        'lang_string' => 2,
        'moodle_exception' => 2,
    ];

    public function register()
    {
        return [
            T_STRING,
        ];
    }

    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $this->checkFunctions($phpcsFile, $stackPtr);
    }

    protected function checkFunctions(
        File $phpcsFile,
        $stackPtr,
    ): void {
        $tokens = $phpcsFile->getTokens();

        $ignore = [
            T_DOUBLE_COLON             => true,
            T_OBJECT_OPERATOR          => true,
            T_NULLSAFE_OBJECT_OPERATOR => true,
            T_FUNCTION                 => true,
            T_CONST                    => true,
            T_PUBLIC                   => true,
            T_PRIVATE                  => true,
            T_PROTECTED                => true,
            T_AS                       => true,
            T_INSTEADOF                => true,
            T_NS_SEPARATOR             => true,
            T_IMPLEMENTS               => true,
        ];

        $prevToken = $phpcsFile->findPrevious(T_WHITESPACE, ($stackPtr - 1), null, true);

        // If function call is directly preceded by a NS_SEPARATOR it points to the
        // global namespace, so we should still catch it.
        if ($tokens[$prevToken]['code'] === T_NS_SEPARATOR) {
            $prevToken = $phpcsFile->findPrevious(T_WHITESPACE, ($prevToken - 1), null, true);
            if ($tokens[$prevToken]['code'] === T_STRING) {
                // Not in the global namespace.
                return;
            }
        }

        if (isset($ignore[$tokens[$prevToken]['code']]) === true) {
            // Not a call to a PHP function.
            return;
        }

        $nextToken = $phpcsFile->findNext(T_WHITESPACE, ($stackPtr + 1), null, true);
        if (isset($ignore[$tokens[$nextToken]['code']]) === true) {
            // Not a call to a PHP function.
            return;
        }

        if ($tokens[$stackPtr]['code'] === T_STRING && $tokens[$nextToken]['code'] !== T_OPEN_PARENTHESIS) {
            // Not a call to a PHP function.
            return;
        }

        $function = strtolower($tokens[$stackPtr]['content']);

        if (array_key_exists($function, $this->componentFunctions)) {
            $this->checkCall($phpcsFile, $stackPtr, $tokens, $function, $this->componentFunctions[$function]);
        }
    }

    protected function checkCall(
        File $phpcsFile,
        $stackPtr,
        array $tokens,
        string $functionName,
        int $argumentPosition,
    ): void {
        $prevToken = $phpcsFile->findPrevious(T_WHITESPACE, ($stackPtr - 1), null, true);
        $openParens = $phpcsFile->findNext(
            T_OPEN_PARENTHESIS,
            $stackPtr + 1,
        );

        if ($openParens === false) {
            return;
        }

        $closeParens = $tokens[$openParens]['parenthesis_closer'];

        $beforeArgumentPtr = $openParens + 1;
        if ($argumentPosition > 1) {
            for ($i = 0; $i < $argumentPosition; $i++) {
                $beforeArgumentPtr = $phpcsFile->findNext(T_COMMA, ($openParens + 1), $closeParens);
                if ($beforeArgumentPtr === false) {
                    return;
                }
            }
        }

        $argumentPtr = $phpcsFile->findNext(
            types: [
                T_WHITESPACE,
                T_COMMA,
            ],
            start: $beforeArgumentPtr + 1,
            end: $closeParens,
            exclude: true,
        );

        if ($argumentPtr === false) {
            return;
        }

        if ($tokens[$argumentPtr]['code'] !== T_CONSTANT_ENCAPSED_STRING) {
            // TODO See if we can handle this a little.
            return;
        }

        $component = $tokens[$argumentPtr]['content'];
        if (str_contains($component, '_')) {
            // Component carries an underscore.
            return;
        }

        // Extract the value from the string.
        $componentValue = preg_replace([
            '/^"(.*)"$/', // Double quotes.
            "/^'(.*)'$/", // Single quotes.
        ], '$1', $component);

        $canFix = false;
        $componentExists = MoodleUtil::componentExists($phpcsFile, "core_{$componentValue}");
        if ($componentExists === true) {
            $newValue = "core_{$componentValue}";
            $canFix = true;
        } else if ($componentExists === false) {
            $newValue = "mod_{$componentValue}";
            $canFix = true;
        }

        $fix = false;
        if ($canFix) {
            $fix = $phpcsFile->addFixableWarning(
                "Component name should be '%s' in call to %s%s: '%s'",
                $argumentPtr,
                'ComponentNameFullyQualified',
                [
                    $newValue,
                    $tokens[$prevToken]['code'] === T_NEW ? 'new ' : '',
                    $functionName,
                    $componentValue,
                ]
            );
        } else {
            $phpcsFile->addWarning(
                "Component name not fully qualified in call to %s%s: '%s'",
                $argumentPtr,
                'ComponentNameFullyQualified',
                [
                    $tokens[$prevToken]['code'] === T_NEW ? 'new ' : '',
                    $functionName,
                    $componentValue,
                ]
            );
        }

        if ($fix) {
            $phpcsFile->fixer->beginChangeset();
            $phpcsFile->fixer->replaceToken($argumentPtr, "'$newValue'");
            $phpcsFile->fixer->endChangeset();
        }
    }
}
