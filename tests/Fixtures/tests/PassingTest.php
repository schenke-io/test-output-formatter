<?php

use SchenkeIo\TestOutputFormatter\Tests\Fixtures\Src\WellCovered;

test('this test passes completely', function () {
    $class = new WellCovered;
    expect($class->coveredMethod())->toBe('This is covered.');
    expect($class->coveredMethodTwo())->toBe('This is also covered.');
});
