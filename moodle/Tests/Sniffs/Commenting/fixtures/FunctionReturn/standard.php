<?php

use stdClass;

class multiple_artifact_has_file_docblock
{
    /**
     * Void return type.
     */
    public function testReturnVoid(): void {}

    /**
     * Return type is missing from docblock but present in test.
     */
    public function testUndocumentedReturn(): int {}

    /**
     * Return type is documented but not present in test.
     *
     * @return int
     */
    public function testUnnecessaryReturn() {}

    /**
     * Return type is documented and present in test but different.
     *
     * @return int
     */
    public function testMismatchedReturn(): string {}

    /**
     * Return type is documented and present in test correct.
     *
     * @return int
     */
    public function testMatchedReturnInt(): int {}

    /**
     * Return type is documented and present in test correct.
     *
     * @return \stdClass
     */
    public function testMatchedReturnStdClass(): stdClass {}
}
