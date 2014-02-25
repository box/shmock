<?php

namespace Shmock\ClassBuilder;

class MethodInspectorTest extends \PHPUnit_Framework_TestCase
{
    public function methods()
    {
        return [
            ["methodOneActingClinic", ["\$tobias", "\$lindsay"]],
            ["digitalWitness", ["\$annie = 1", "\$clark = 2"]],
            ["glassHandedKites", ["\$special = 'hey'", "\$apocalypso = NULL"]],
            ["halcyonDigest", ["array \$thing", "\Shmock\ClassBuilder\MethodInspectorTest \$test = NULL"]],
            ["yanquiUXO", ["array & \$thing"]],

        ];
    }

    /**
     * @dataProvider methods
     */
    public function testMethodInspectorShouldGetCorrectSignatures($method, $expectedSig)
    {
        $inspector = new MethodInspector(get_class($this), $method);
        $this->assertEquals($expectedSig, $inspector->signatureArgs());

    }

    public function methodOneActingClinic($tobias, $lindsay)
    {
    }

    public function digitalWitness($annie = 1, $clark = 2.0)
    {
    }

    public function glassHandedKites($special = "hey", $apocalypso = null)
    {
    }

    public function halcyonDigest(array $thing, MethodInspectorTest $test = null)
    {
    }

    public function yanquiUXO(array &$thing)
    {

    }
}
