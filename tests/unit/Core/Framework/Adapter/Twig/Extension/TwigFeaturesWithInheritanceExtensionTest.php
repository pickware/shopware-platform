<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Extension\NodeExtension;
use Shopware\Core\Framework\Adapter\Twig\Extension\TwigFeaturesWithInheritanceExtension;
use Shopware\Core\Framework\Adapter\Twig\SwTwigFunction;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Adapter\Twig\TemplateScopeDetector;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Node;
use Twig\Node\Nodes;
use Twig\Parser;
use Twig\TwigFunction;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SwTwigFunction::class)]
#[CoversClass(TwigFeaturesWithInheritanceExtension::class)]
class TwigFeaturesWithInheritanceExtensionTest extends TestCase
{
    public function testRenderSourceReferencingFromInheritedTemplate(): void
    {
        static::assertSame(
            'start {% block inner %}content{% endblock %} end',
            $this->parseTemplate('{{ sw_source("foo.html.twig") }}')
        );
    }

    public function testRenderIncludeReferencingFromInheritedTemplate(): void
    {
        static::assertSame(
            'start content end',
            $this->parseTemplate('{{ sw_include("foo.html.twig") }}')
        );
    }

    public function testGetTag(): void
    {
        $extension = new TwigFeaturesWithInheritanceExtension($this->createMock(TemplateFinder::class));
        $functionNames = \array_map(
            fn (TwigFunction $function) => $function->getName(),
            $extension->getFunctions(),
        );

        static::assertContains('sw_source', $functionNames);
        static::assertContains('sw_include', $functionNames);
    }

    public function testAbstractExpressionIsThrown(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The first argument of the "sw_block" function must be an instance of AbstractExpression.');

        $extension = new TwigFeaturesWithInheritanceExtension($this->createMock(TemplateFinder::class));
        $extension->parseSwBlockFunction(
            $this->createMock(Parser::class),
            $this->createMock(AbstractExpression::class),
            new Nodes([$this->createMock(Node::class)]),
            100
        );
    }

    private function parseTemplate(string $template): string
    {
        $templateName = Uuid::randomHex() . '.html.twig';
        $templateFinder = $this->createMock(TemplateFinder::class);
        $templateFinder->expects($this->once())
            ->method('find')
            ->with('foo.html.twig', false, null)
            ->willReturn('bar.html.twig');

        $twig = new Environment(new ArrayLoader([
            $templateName => $template,
            'bar.html.twig' => 'start {% block inner %}content{% endblock %} end',
        ]));
        $twig->addExtension(new NodeExtension(
            $templateFinder,
            $this->createMock(TemplateScopeDetector::class),
        ));
        $twig->addExtension(new TwigFeaturesWithInheritanceExtension($templateFinder));

        return $twig->render($templateName);
    }
}
