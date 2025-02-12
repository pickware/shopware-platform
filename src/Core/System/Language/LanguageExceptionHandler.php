<?php declare(strict_types=1);

namespace Shopware\Core\System\Language;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\Exception\LanguageForeignKeyDeleteException;

#[Package('fundamentals@discovery')]
class LanguageExceptionHandler implements ExceptionHandlerInterface
{
    public function getPriority(): int
    {
        return ExceptionHandlerInterface::PRIORITY_LATE;
    }

    /**
     * @param \Exception $e - @deprecated tag:v6.7.0 - in v6.7.0 parameter type will be changed to \Throwable
     */
    public function matchException(\Exception $e): ?\Exception
    {
        if (preg_match('/SQLSTATE\[23000\]:.*(1217|1216).*a foreign key constraint/', $e->getMessage())) {
            return new LanguageForeignKeyDeleteException($e);
        }

        return null;
    }
}
