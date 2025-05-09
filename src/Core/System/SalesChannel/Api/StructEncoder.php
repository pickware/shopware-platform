<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Api;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\Entity\DefinitionRegistryChain;
use Shopware\Core\System\SalesChannel\SalesChannelException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Contracts\Service\ResetInterface;

#[Package('framework')]
class StructEncoder implements ResetInterface
{
    /**
     * @var array<string, bool>
     */
    private array $protections = [];

    /**
     * @var ?array<string, string[]>
     */
    private ?array $blockedCustomFields = null;

    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionRegistryChain $registry,
        private readonly NormalizerInterface $serializer,
        private readonly Connection $connection
    ) {
    }

    public function reset(): void
    {
        $this->protections = [];
        $this->blockedCustomFields = [];
    }

    /**
     * @return array<array<string, mixed>|mixed>
     */
    public function encode(Struct $struct, ResponseFields $fields): array
    {
        $array = $this->serializer->normalize($struct);

        if (!\is_array($array)) {
            throw SalesChannelException::encodingInvalidStructException('Normalized struct must be an array');
        }

        return $this->loop($struct, $fields, $array);
    }

    /**
     * @param array<array-key, mixed> $array
     *
     * @return array<array<string, mixed>|mixed>
     */
    private function loop(Struct $struct, ResponseFields $fields, array $array): array
    {
        $data = $array;

        if ($struct instanceof AggregationResultCollection) {
            $mapped = [];
            foreach (\array_keys($struct->getElements()) as $index => $key) {
                if (!isset($data[$index]) || !\is_array($data[$index])) {
                    throw SalesChannelException::encodingMissingAggregationException($key, $index);
                }

                $entity = $struct->get($key);
                if (!$entity instanceof Struct) {
                    throw SalesChannelException::encodingInvalidStructException(\sprintf('Aggregation "%s" is not a valid struct', $key));
                }

                $mapped[$key] = $this->encodeStruct($entity, $fields, $data[$index]);
            }

            return $mapped;
        }

        if ($struct instanceof EntitySearchResult) {
            $data = $this->encodeStruct($struct, $fields, $data);

            if (isset($data['elements'])) {
                $entities = [];

                foreach (\array_values($data['elements']) as $index => $value) {
                    $entity = $struct->getAt($index);
                    if (!$entity instanceof Struct) {
                        throw SalesChannelException::encodingInvalidStructException(\sprintf('Entity at index "%d" is not a valid struct', $index));
                    }

                    $entities[] = $this->encodeStruct($entity, $fields, $value);
                }
                $data['elements'] = $entities;
            }

            return $data;
        }

        if ($struct instanceof ErrorCollection) {
            return array_map(static fn (Error $error) => $error->jsonSerialize(), $struct->getElements());
        }

        if ($struct instanceof Collection) {
            $new = [];
            foreach ($data as $index => $value) {
                $structItem = $struct->getAt($index);
                if ($structItem instanceof Struct) {
                    $new[] = $this->encodeStruct($structItem, $fields, $value);
                }
            }

            return $new;
        }

        return $this->encodeStruct($struct, $fields, $data);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function encodeStruct(Struct $struct, ResponseFields $fields, array $data, ?string $alias = null): array
    {
        $alias = $alias ?? $struct->getApiAlias();

        foreach ($data as $property => $value) {
            if ($property === 'customFields' && $value === []) {
                $data[$property] = $value = new \stdClass();
            }

            if ($property === 'extensions') {
                $data[$property] = $this->encodeExtensions($struct, $fields, $value);

                if (empty($data[$property])) {
                    unset($data[$property]);
                }

                continue;
            }

            if (!$this->isAllowed($alias, (string) $property, $fields) && !$fields->hasNested($alias, (string) $property)) {
                unset($data[$property]);

                continue;
            }

            if (!\is_array($value)) {
                continue;
            }

            $object = $value;
            if (\array_key_exists($property, $struct->getVars())) {
                $object = $struct->getVars()[$property];
            }

            if ($object instanceof Struct) {
                $data[$property] = $this->loop($object, $fields, $value);

                continue;
            }

            // simple array of structs case
            if ($this->isStructArray($object)) {
                $array = [];
                foreach ($object as $key => $item) {
                    $array[$key] = $this->encodeStruct($item, $fields, $value[$key]);
                }

                $data[$property] = $array;

                continue;
            }

            $data[$property] = $this->encodeNestedArray($struct->getApiAlias(), (string) $property, $value, $fields);
        }

        $data['apiAlias'] = $struct->getApiAlias();

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function encodeNestedArray(string $alias, string $prefix, array $data, ResponseFields $fields): array
    {
        if ($prefix === 'customFields' && $data) {
            if ($this->blockedCustomFields === null) {
                $this->fetchBlockedCustomFields();
            }

            $blockedFields = $this->blockedCustomFields[$alias] ?? [];
            $blockedFields = \array_merge($blockedFields, $this->blockedCustomFields['global'] ?? []);
            if ($blockedFields) {
                $blockedFieldsLookup = \array_flip($blockedFields);

                $data = \array_filter($data, static function ($key) use ($blockedFieldsLookup) {
                    return !isset($blockedFieldsLookup[$key]);
                }, \ARRAY_FILTER_USE_KEY);
            }
        }

        if ($prefix !== 'translated' && !$fields->hasNested($alias, $prefix)) {
            return $data;
        }

        foreach ($data as $property => &$value) {
            if ($property === 'customFields' && $value === []) {
                $value = new \stdClass();
            }

            $accessor = $prefix . '.' . $property;
            if ($prefix === 'translated') {
                $accessor = $property;
            }

            if (!$fields->isAllowed($alias, $accessor) && !$fields->hasNested($alias, $accessor)) {
                unset($data[$property]);

                continue;
            }

            if (!\is_array($value)) {
                continue;
            }

            $data[$property] = $this->encodeNestedArray($alias, $accessor, $value, $fields);
        }

        unset($value);

        return $data;
    }

    private function isAllowed(string $type, string $property, ResponseFields $fields): bool
    {
        if ($this->isProtected($type, $property)) {
            return false;
        }

        return $fields->isAllowed($type, $property);
    }

    private function isProtected(string $type, string $property): bool
    {
        $key = $type . '.' . $property;
        if (isset($this->protections[$key])) {
            return $this->protections[$key];
        }

        if (!$this->registry->has($type)) {
            return $this->protections[$key] = false;
        }

        $definition = $this->registry->getByEntityName($type);

        $field = $definition->getField($property);

        if ($property === 'translated') {
            return $this->protections[$key] = false;
        }

        if (!$field) {
            return $this->protections[$key] = true;
        }

        $flag = $field->getFlag(ApiAware::class);

        if ($flag === null) {
            return $this->protections[$key] = true;
        }

        if (!$flag->isSourceAllowed(SalesChannelApiSource::class)) {
            return $this->protections[$key] = true;
        }

        return $this->protections[$key] = false;
    }

    /**
     * @param array<string, mixed> $value
     *
     * @return array<string, mixed>
     */
    private function encodeExtensions(Struct $struct, ResponseFields $fields, array $value): array
    {
        $alias = $struct->getApiAlias();

        $extensions = array_keys($value);

        foreach ($extensions as $name) {
            if ($name === 'search') {
                if (!$fields->isAllowed($alias, $name)) {
                    unset($value[$name]);

                    continue;
                }

                $value[$name] = $this->encodeNestedArray($alias, 'search', $value[$name], $fields);

                continue;
            }
            if ($name === 'foreignKeys') {
                // loop the foreign keys array with the api alias of the original struct to scope the values within the same entity definition
                $extension = $struct->getExtension('foreignKeys');

                if (!$extension instanceof Struct) {
                    unset($value[$name]);

                    continue;
                }

                $value[$name] = $this->encodeStruct($extension, $fields, $value['foreignKeys'], $alias);

                // only api alias inside, remove it
                if (\count($value[$name]) === 1) {
                    unset($value[$name]);
                }

                continue;
            }

            if (!$this->isAllowed($alias, $name, $fields)) {
                unset($value[$name]);

                continue;
            }

            $extension = $struct->getExtension($name);
            if ($extension === null) {
                continue;
            }

            $value[$name] = $this->loop($extension, $fields, $value[$name]);
        }

        return $value;
    }

    private function isStructArray(mixed $object): bool
    {
        if (!\is_array($object)) {
            return false;
        }

        $values = array_values($object);
        if (!isset($values[0])) {
            return false;
        }

        return $values[0] instanceof Struct;
    }

    private function fetchBlockedCustomFields(): void
    {
        /** @var array<string, string>[] */
        $blockedCustomFields = $this->connection->fetchAllAssociative(
            '# struct-encoder::fetch-blocked-custom-fields
            SELECT
                COALESCE(cfsr.entity_name, "global") as entity_name,
                cf.name
            FROM custom_field cf
            LEFT JOIN custom_field_set_relation cfsr ON cfsr.set_id = cf.set_id
            WHERE cf.store_api_aware = 0
        '
        );

        $this->blockedCustomFields = [];

        foreach ($blockedCustomFields as $blockedCustomField) {
            $this->blockedCustomFields[$blockedCustomField['entity_name']][] = $blockedCustomField['name'];
        }
    }
}
