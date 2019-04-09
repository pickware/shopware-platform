<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Dependency;

use Shopware\Core\Framework\Bundle;

class PluginDependencyBundle extends Bundle
{
    /**
     * @var string
     */
    private $version;

    public function getVersion(): string
    {
        return $this->version;
    }

    public function setVersion(string $version): void
    {
        $this->version = $version;
    }

    public function getIdentifier(): string
    {
        if (!$this->getVersion()) {
            return parent::getIdentifier();
        }

        return parent::getIdentifier() . ':' . $this->getVersion();
    }

    public function setPath($path): void
    {
        $this->path = $path;
    }
}
