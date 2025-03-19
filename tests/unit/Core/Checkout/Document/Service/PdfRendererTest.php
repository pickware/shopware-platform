<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\Extension\PdfRendererExtension;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Service\PdfRenderer;
use Shopware\Core\Checkout\Document\Twig\DocumentTemplateRenderer;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(PdfRenderer::class)]
class PdfRendererTest extends TestCase
{
    public function testGetContentType(): void
    {
        $pdfRenderer = new PdfRenderer(
            [],
            $this->createMock(DocumentTemplateRenderer::class),
            '',
            new ExtensionDispatcher(new EventDispatcher())
        );

        static::assertEquals('application/pdf', $pdfRenderer->getContentType());
    }

    public function testExtensionIsDispatched(): void
    {
        $dispatcher = new EventDispatcher();
        $renderer = new PdfRenderer(
            [],
            $this->createMock(DocumentTemplateRenderer::class),
            '',
            new ExtensionDispatcher($dispatcher),
        );

        $rendered = new RenderedDocument('', '1001', InvoiceRenderer::TYPE);
        $rendered->setContext(Context::createDefaultContext());
        $rendered->setOrder($this->getOrder());

        $pre = $this->createMock(CallableClass::class);
        $pre->expects($this->once())->method('__invoke');
        $dispatcher->addListener(PdfRendererExtension::NAME . '.pre', $pre);

        $post = $this->createMock(CallableClass::class);
        $post->expects($this->once())->method('__invoke');
        $dispatcher->addListener(PdfRendererExtension::NAME . '.post', $post);

        $renderer->render($rendered);
    }

    public function testRenderWithoutHtml(): void
    {
        $rendered = new RenderedDocument(
            '1001',
            InvoiceRenderer::TYPE,
        );

        $rendered->setContext(Context::createDefaultContext());
        $rendered->setOrder($this->getOrder());

        $documentTemplateRenderer = $this->createMock(DocumentTemplateRenderer::class);
        $documentTemplateRenderer->expects($this->once())
            ->method('render')
            ->willReturn('html');

        $pdfRenderer = new PdfRenderer(
            [
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
            ],
            $documentTemplateRenderer,
            '',
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $generatorOutput = $pdfRenderer->render($rendered);
        static::assertNotEmpty($generatorOutput);

        static::assertSame($rendered->getFileExtension(), PdfRenderer::FILE_EXTENSION);
        static::assertSame($rendered->getContentType(), PdfRenderer::FILE_CONTENT_TYPE);

        $finfo = new \finfo(\FILEINFO_MIME_TYPE);
        static::assertEquals('application/pdf', $finfo->buffer($generatorOutput));
    }

    public function testRenderThrowException(): void
    {
        $this->expectException(DocumentException::class);

        $rendered = new RenderedDocument(
            '1001',
            InvoiceRenderer::TYPE,
        );

        $htmlRenderer = new PdfRenderer(
            [],
            $this->createMock(DocumentTemplateRenderer::class),
            '',
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $htmlRenderer->render($rendered);
    }

    private function getOrder(): OrderEntity
    {
        $locale = new LocaleEntity();
        $locale->setId(Uuid::randomHex());
        $locale->setCode('en-GB');

        $language = new LanguageEntity();
        $language->setId(Uuid::randomHex());
        $language->setLocale($locale);

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setSalesChannelId(Uuid::randomHex());
        $order->setLanguageId($language->getId());
        $order->setLanguage($language);

        return $order;
    }
}
