<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Util\Exception\ComparatorException;
use Shopware\Core\Framework\Util\UtilException;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(UtilException::class)]
class UtilExceptionTest extends TestCase
{
    public function testInvalidJson(): void
    {
        $e = UtilException::invalidJson($p = new \JsonException('invalid'));

        static::assertEquals(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertEquals('UTIL_INVALID_JSON', $e->getErrorCode());
        static::assertEquals('JSON is invalid', $e->getMessage());
        static::assertEquals($p, $e->getPrevious());
    }

    public function testInvalidJsonNotList(): void
    {
        $e = UtilException::invalidJsonNotList();

        static::assertEquals(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertEquals('UTIL_INVALID_JSON_NOT_LIST', $e->getErrorCode());
        static::assertEquals('JSON cannot be decoded to a list', $e->getMessage());
    }

    public function testCannotFindFileInFilesystem(): void
    {
        $e = UtilException::cannotFindFileInFilesystem('some/file', 'some/folder');

        static::assertEquals(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertEquals('UTIL__FILESYSTEM_FILE_NOT_FOUND', $e->getErrorCode());
        static::assertEquals('The file "some/file" does not exist in the given filesystem "some/folder"', $e->getMessage());
    }

    public function testOperatorNotSupported(): void
    {
        $e = UtilException::operatorNotSupported('$');
        static::assertEquals(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertEquals('UTIL__OPERATOR_NOT_SUPPORTED', $e->getErrorCode());
        static::assertEquals('Operator "$" is not supported.', $e->getMessage());
    }

    /**
     * @deprecated tag:v6.8.0 - reason: see UtilException::operatorNotSupported - to be removed
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testOperatorNotSupportedDeprecated(): void
    {
        $e = UtilException::operatorNotSupported('$');
        static::assertInstanceOf(ComparatorException::class, $e);
        static::assertEquals(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertEquals('CONTENT__OPERATOR_NOT_SUPPORTED', $e->getErrorCode());
        static::assertEquals('Operator "$" is not supported.', $e->getMessage());
    }
}
