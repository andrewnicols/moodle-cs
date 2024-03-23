<?php

// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace MoodleHQ\MoodleCS\moodle\Sniffs\Commenting;

use MoodleHQ\MoodleCS\moodle\Util\Docblocks;
use MoodleHQ\MoodleCS\moodle\Util\TypeUtil;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;
use PHPCSUtils\Utils\FunctionDeclarations;

/**
 * Checks that function parameters are correct.
 *
 * @copyright  2024 Andrew Lyons <andrew@nicols.co.uk>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class FunctionParamsSniff implements Sniff
{
    /**
     * Register for open tag (only process once per file).
     */
    public function register() {
        return [
            T_OPEN_TAG,
        ];
    }

    /**
     * Processes php files and perform various checks with file.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int $stackPtr The position in the stack.
     */
    public function process(File $phpcsFile, $stackPtr) {
        while ($stackPtr = $phpcsFile->findNext(T_FUNCTION, $stackPtr + 1)) {
            $docPtr = Docblocks::getDocBlockPointer($phpcsFile, $stackPtr);
            if ($docPtr) {
                $this->processDocblock($phpcsFile, $stackPtr, $docPtr);
            }
        }
    }

    protected function processDocblock(File $phpcsFile, int $methodPtr, int $docPtr) {
        // Check returns first. They should be more simple.
        $this->processReturns($phpcsFile, $methodPtr, $docPtr);
        // Check the params.
        $docParams = Docblocks::getMatchingDocTags($phpcsFile, $docPtr, '@param');
        $methodParams = FunctionDeclarations::getParameters($phpcsFile, $methodPtr);
    }

    protected function processReturns(File $phpcsFile, int $methodPtr, int $docPtr) {
        $tokens = $phpcsFile->getTokens();

        $docReturns = Docblocks::getMatchingDocTags($phpcsFile, $docPtr, '@return');
        $methodInfo = FunctionDeclarations::getProperties($phpcsFile, $methodPtr);

        [
            'returnTypePointer' => $returnTypePtr,
            'returnType' => $returnType,
            'returnTypeDescription' => $returnTypeDescription,
        ] = $this->getDocumentedReturnType($phpcsFile, $docReturns);

        if ($methodInfo['return_type'] === 'void') {
            $fix = false;
            if ($returnType === 'void') {
                $fix = $phpcsFile->addFixableWarning(
                    'Method has void return type, but @return void tag found in docblock.',
                    $docPtr,
                    'VoidReturnFound'
                );
            } else if (!empty($returnType)) {
                $fix = $phpcsFile->addFixableError(
                    'Method has void return type, but @return %s tag found in docblock.',
                    $docPtr,
                    'VoidReturnMismatch',
                    [$returnType]
                );
            }

            if ($fix) {
                $phpcsFile->fixer->beginChangeset();
                // Replace from the start of the line to the next line.
                $startOfLine = $phpcsFile->findPrevious(T_DOC_COMMENT_STAR, $docPtr - 1, null, true);
                $endOfLine = $phpcsFile->findNext(T_DOC_COMMENT_STAR, $docPtr + 1, null, false);
                for ($token = $startOfLine; $token < $endOfLine; $token++) {
                    $phpcsFile->fixer->replaceToken($token, '');
                }
                $phpcsFile->fixer->endChangeset();
            }

            return;
        }

        if ($methodInfo['return_type'] === '') {
            // The method has no return type, so we have nothing to compare against.
            // The most we can do is to check the type.
            if (count($docReturns) > 0) {
                $suggestedType = TypeUtil::suggestType(
                    $phpcsFile,
                    $returnTypePtr,
                    $returnType,
                );
                if ($suggestedType !== $returnType) {
                    $fix = $phpcsFile->addFixableError(
                        'Return type should be %s, but %s was found.',
                        $returnTypePtr,
                        'ReturnTypeMismatch',
                        [$suggestedType, $returnType]
                    );

                    if ($fix === true) {
                        $phpcsFile->fixer->beginChangeset();
                        $phpcsFile->fixer->replaceToken($returnTypePtr, rtrim(implode(' ', [
                            $suggestedType,
                            $returnTypeDescription,
                        ])));
                        $phpcsFile->fixer->endChangeset();
                    }
                }
            }
            return;
        }

        // The type hint is there. Check it.
        $methodReturnType = $methodInfo['return_type'];
        if (count($docReturns) === 0) {
            $fix = $phpcsFile->addFixableError(
                'Missing @return tag in docblock. Expected "%s"',
                $docPtr,
                'MissingReturnTag',
                [$methodReturnType]
            );

            if ($fix === true) {
                $previousWhitespace = $phpcsFile->findPrevious(T_WHITESPACE, $docPtr - 1);
                $phpcsFile->fixer->beginChangeset();
                $newContent = "* @return $methodReturnType" . PHP_EOL . ' ' . $tokens[$previousWhitespace]['content'];
                $phpcsFile->fixer->addContentBefore($tokens[$docPtr]['comment_closer'], $newContent);
                $phpcsFile->fixer->endChangeset();
            }
            return;
        }

        if (count($docReturns) > 1) {
            $phpcsFile->addError(
                'Only one @return tag is allowed in docblock.',
                $docPtr,
                'MultipleReturnTags'
            );
            return;
        }

        if ($returnType !== $methodReturnType) {
            $fix = $phpcsFile->addFixableError(
                'Return type should be %s, but %s was found.',
                $returnTypePtr,
                'ReturnTypeMismatch',
                [$methodReturnType, $returnType]
            );

            if ($fix === true) {
                $phpcsFile->fixer->beginChangeset();
                $phpcsFile->fixer->replaceToken($returnTypePtr, rtrim(implode(' ', [
                    $methodReturnType,
                    $returnTypeDescription,
                ])));
                $phpcsFile->fixer->endChangeset();
            }
        }
    }

    protected function getDocumentedReturnType(File $phpcsFile, array $docReturnPointers) {
        if (count($docReturnPointers) === 0) {
            return [
                'returnTypePointer' => null,
                'returnType' => '',
                'returnTypeDescription' => '',
            ];
        }

        $docReturnPtr = $docReturnPointers[0];
        $tokens = $phpcsFile->getTokens();
        $returnTypePtr = $phpcsFile->findNext(
            T_DOC_COMMENT_STRING,
            ($docReturnPtr + 1),
            $phpcsFile->findNext(
                [
                    T_DOC_COMMENT_STAR,
                    T_DOC_COMMENT_CLOSE_TAG,
                ],
                ($docReturnPtr + 1),
            )
        );

        $returnDescription = $tokens[$returnTypePtr]['content'];
        preg_match('/([^ ]*)(.*)$/', $returnDescription, $matches);
        return [
            'returnTypePointer' => $returnTypePtr,
            'returnType' => $matches[1],
            'returnTypeDescription' => $matches[2],
        ];
    }
}
