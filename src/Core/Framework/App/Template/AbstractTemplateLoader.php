<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Template;

use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
abstract class AbstractTemplateLoader
{
    /**
     * Returns the list of template paths the given app ships
     *
     * @return array<string>
     */
    abstract public function getTemplatePathsForApp(Manifest $app): array;

    /**
     * Returns the content of the template
     */
    abstract public function getTemplateContent(string $path, Manifest $app): string;
}
