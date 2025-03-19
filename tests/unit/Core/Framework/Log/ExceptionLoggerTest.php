<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Log;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Shopware\Core\Framework\Log\ExceptionLogger;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(ExceptionLogger::class)]
#[Package('checkout')]
class ExceptionLoggerTest extends TestCase
{
    #[DataProvider('loggerProvider')]
    public function testLoggerThrows(string $environment, bool $enforceThrow, bool $expectException): void
    {
        $psrLogger = $this->createMock(LoggerInterface::class);
        $psrLogger
            ->expects($this->once())
            ->method('log')
            ->with(LogLevel::ERROR, 'test');

        if ($expectException) {
            $this->expectException(\Exception::class);
            $this->expectExceptionMessage('test');
        }

        $logger = new ExceptionLogger($environment, $enforceThrow, $psrLogger);
        $logger->logOrThrowException(new \Exception('test'));
    }

    public function testLogLevel(): void
    {
        $psrLogger = $this->createMock(LoggerInterface::class);
        $psrLogger
            ->expects($this->once())
            ->method('log')
            ->with(LogLevel::WARNING, 'test');

        $logger = new ExceptionLogger('prod', false, $psrLogger);
        $logger->logOrThrowException(new \Exception('test'), LogLevel::WARNING);
    }

    public static function loggerProvider(): \Generator
    {
        yield ['prod', true, true];
        yield ['prod', false, false];
        yield ['dev', true, true];
        yield ['dev', false, true];
        yield ['test', true, true];
        yield ['test', false, true];
    }
}
