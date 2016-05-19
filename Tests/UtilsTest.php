<?php

namespace Mathielen\ImportEngineBundle\Tests;

use Mathielen\ImportEngineBundle\Utils;

class UtilsTest extends \PHPUnit_Framework_TestCase
{
    public function testIsCli()
    {
        $this->assertTrue(Utils::isCli());
    }

    public function testWhoAmI()
    {
        $processUser = posix_getpwuid(posix_geteuid());
        $actualUser = $processUser['name'];

        $this->assertEquals($actualUser, Utils::whoAmI());
    }

    /**
     * @dataProvider getNumbersToRangeTextData
     */
    public function testNumbersToRangeText(array $range, $expected)
    {
        $this->assertEquals($expected, Utils::numbersToRangeText($range));
    }

    public function getNumbersToRangeTextData()
    {
        return [
            [[], []],
            [[1], ['1']],
            [[1, 2, 3], ['1-3']],
            [[1, 2, 4, 5], ['1-2', '4-5']],
            [[1, 3, 5, 7], ['1', '3', '5', '7']],
        ];
    }
}
