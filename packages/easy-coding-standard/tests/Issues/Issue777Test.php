<?php

declare(strict_types=1);

namespace Symplify\EasyCodingStandard\Tests\Issues;

use Iterator;
use Symplify\EasyCodingStandardTester\Testing\AbstractCheckerTestCase;
use Symplify\SmartFileSystem\SmartFileInfo;

/**
 * @see https://github.com/symplify/symplify/issues/777
 */
final class Issue777Test extends AbstractCheckerTestCase
{
    /**
     * @dataProvider provideData()
     */
    public function test(SmartFileInfo $fileInfo): void
    {
        $this->doTestFileInfo($fileInfo);
    }

    /**
     * @return Iterator<SmartFileInfo[]>
     */
    public function provideData(): Iterator
    {
        yield [new SmartFileInfo(__DIR__ . '/Fixture/fixture777.php.inc')];
    }

    public function provideConfig(): string
    {
        return __DIR__ . '/config/config777.php';
    }
}
