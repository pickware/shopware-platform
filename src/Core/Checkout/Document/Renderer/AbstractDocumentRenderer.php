<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Renderer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('after-sales')]
abstract class AbstractDocumentRenderer
{
    abstract public function supports(): string;

    /**
     * @param array<string, DocumentGenerateOperation> $operations
     */
    abstract public function render(array $operations, Context $context, DocumentRendererConfig $rendererConfig): RendererResult;

    abstract public function getDecorated(): AbstractDocumentRenderer;

    /**
     * @deprecated tag:v6.7.0 - will be removed without replacement
     */
    public function finalize(DocumentGenerateOperation $operation, Context $context, DocumentRendererConfig $rendererConfig, RendererResult $result): void
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'Method will be removed without replacement');
    }

    /**
     * @param array<int, string> $ids
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getOrdersLanguageId(array $ids, string $versionId, Connection $connection): array
    {
        return $connection->fetchAllAssociative(
            '
            SELECT LOWER(HEX(language_id)) as language_id, GROUP_CONCAT(DISTINCT LOWER(HEX(id))) as ids
            FROM `order`
            WHERE `id` IN (:ids)
            AND `version_id` = :versionId
            AND `language_id` IS NOT NULL
            GROUP BY `language_id`',
            ['ids' => Uuid::fromHexToBytesList($ids), 'versionId' => Uuid::fromHexToBytes($versionId)],
            ['ids' => ArrayParameterType::BINARY]
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function isAllowIntraCommunityDelivery(array $config, OrderEntity $order): bool
    {
        if (empty($config['displayAdditionalNoteDelivery'])) {
            return false;
        }

        $customerType = $order->getOrderCustomer()?->getCustomer()?->getAccountType();
        if ($customerType !== CustomerEntity::ACCOUNT_TYPE_BUSINESS) {
            return false;
        }

        $orderDelivery = $order->getDeliveries()?->first();
        if (!$orderDelivery) {
            return false;
        }

        $shippingAddress = $orderDelivery->getShippingOrderAddress();
        $country = $shippingAddress?->getCountry();
        if ($country === null) {
            return false;
        }

        $isCompanyTaxFree = $country->getCompanyTax()->getEnabled();
        $isPartOfEu = $country->getIsEu();

        return $isCompanyTaxFree && $isPartOfEu;
    }
}
