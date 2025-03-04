<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Metrics\Factory;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Config\TransportConfig;
use Shopware\Core\Framework\Telemetry\Metrics\MetricTransportInterface;

/**
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 */
#[Package('framework')]
interface MetricTransportFactoryInterface
{
    public function create(TransportConfig $transportConfig): MetricTransportInterface;
}
