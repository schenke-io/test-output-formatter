<?php
namespace SchenkeIo\TestOutputFormatter\Tests\Fixtures\Tests;
use PHPUnit\Framework\TestCase;
use SchenkeIo\TestOutputFormatter\Tests\Fixtures\Src\WellCovered;

class PassingTest extends TestCase {
    public function test_this_test_passes_completely() {
        $class = new WellCovered;
        $this->assertEquals('This is covered.', $class->coveredMethod());
        $this->assertEquals('This is also covered.', $class->coveredMethodTwo());
    }
}
