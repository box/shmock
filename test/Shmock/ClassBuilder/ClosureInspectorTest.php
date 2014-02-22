<?php

namespace Shmock\ClassBuilder;

class ClosureInspectorTest extends \PHPUnit_Framework_TestCase
{
    public function hintableFunctions()
    {
        return [
            [function ($a, $b) {}, ["", ""]],
            [function (array $a) {}, ["array"]],
            [function (array $a, ClosureInspectorTest $test) {}, ["array", "Shmock\ClassBuilder\ClosureInspectorTest"]],
        ];
    }

    /**
     * @dataProvider hintableFunctions
     */
    public function testMethodInspectorCanNameTheTypeHintsOnAFunction(callable $fn, array $typeHints)
    {
        $inspector = new ClosureInspector($fn);
        $this->assertSame($inspector->typeHints(), $typeHints);
    }
}
