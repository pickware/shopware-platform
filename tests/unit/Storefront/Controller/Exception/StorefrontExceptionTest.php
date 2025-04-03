<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Storefront\Controller\Exception\StorefrontException;
use Twig\Error\Error as TwigError;
use Twig\Source;

/**
 * @internal
 */
#[CoversClass(StorefrontException::class)]
class StorefrontExceptionTest extends TestCase
{
    public function testRenderViewException(): void
    {
        $parameters = [
            'param' => 'Param',
            'context' => Context::createDefaultContext(),
        ];

        $view = 'test.html.twig';

        $twigError = new TwigError('Error message', 5, new Source('<div>ExampleCode</div>', $view, $view));

        $res = StorefrontException::renderViewException($view, $twigError, $parameters);

        static::assertSame(500, $res->getStatusCode());
        static::assertSame('STOREFRONT__CAN_NOT_RENDER_VIEW', $res->getErrorCode());
        static::assertSame('Can not render test.html.twig view: Error message with these parameters: {"param":"Param"}', $res->getMessage());
        static::assertSame(5, $res->getLine());
        static::assertSame('test.html.twig', $res->getFile());
    }

    public function testRenderViewExceptionUsesCustomAppErrorCodeForExternalIssues(): void
    {
        $parameters = [
            'param' => 'Param',
            'context' => Context::createDefaultContext(),
        ];

        $view = 'test.html.twig';
        $path = 'platform/custom/apps/ElleChildTheme/Resources/views/storefront/layout/footer/footer.html.twig';

        $twigError = new TwigError('Error message', 5, new Source('<div>ExampleCode</div>', $view, $path));
        $exception = StorefrontException::renderViewException($view, $twigError, $parameters);

        static::assertSame(500, $exception->getStatusCode());
        static::assertSame('STOREFRONT__CAN_NOT_RENDER_CUSTOM_APP_VIEW', $exception->getErrorCode());
        static::assertSame('Can not render test.html.twig view: Error message with these parameters: {"param":"Param"}', $exception->getMessage());
        static::assertSame(5, $exception->getLine());
        static::assertSame($path, $exception->getFile());
    }

    public function testUnSupportStorefrontResponse(): void
    {
        $res = StorefrontException::unSupportStorefrontResponse();

        static::assertSame(500, $res->getStatusCode());
        static::assertSame('STOREFRONT__UN_SUPPORT_STOREFRONT_RESPONSE', $res->getErrorCode());
        static::assertSame('Symfony render implementation changed. Providing a response is no longer supported', $res->getMessage());
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testDontHaveTwigInjected(): void
    {
        $res = StorefrontException::dontHaveTwigInjected('Example\Class');

        static::assertSame(500, $res->getStatusCode());
        static::assertSame('STOREFRONT__CLASS_DONT_HAVE_TWIG_INJECTED', $res->getErrorCode());
        static::assertSame('Class Example\Class does not have twig injected. Add to your service definition a method call to setTwig with the twig instance', $res->getMessage());
    }

    public function testNoRequestProvided(): void
    {
        $res = StorefrontException::noRequestProvided();

        static::assertSame(500, $res->getStatusCode());
        static::assertSame('STOREFRONT__NO_REQUEST_PROVIDED', $res->getErrorCode());
        static::assertSame(
            'No request is available.This controller action require an active request context.',
            $res->getMessage()
        );
    }
}
