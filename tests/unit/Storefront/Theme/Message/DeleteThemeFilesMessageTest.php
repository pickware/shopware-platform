<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Storefront\Theme\Message\DeleteThemeFilesMessage;

/**
 * @internal
 */
#[CoversClass(DeleteThemeFilesMessage::class)]
class DeleteThemeFilesMessageTest extends TestCase
{
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testStruct(): void
    {
        $message = new DeleteThemeFilesMessage('path', 'salesChannel', 'theme');

        static::assertEquals('path', $message->getThemePath());
        static::assertEquals('salesChannel', $message->getSalesChannelId());
        static::assertEquals('theme', $message->getThemeId());
    }
}
