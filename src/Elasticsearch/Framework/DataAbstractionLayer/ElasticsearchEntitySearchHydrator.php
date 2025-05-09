<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Package('framework')]
class ElasticsearchEntitySearchHydrator extends AbstractElasticsearchSearchHydrator
{
    public function getDecorated(): AbstractElasticsearchSearchHydrator
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @param array{ hits?: array{ hits: array<int, array{_id?: string, _score?: float, _source?: array<mixed>, inner_hits?: array{ inner?: array<mixed>}}>}, aggregations?: array<string, array<string, mixed>>} $result
     */
    public function hydrate(EntityDefinition $definition, Criteria $criteria, Context $context, array $result): IdSearchResult
    {
        if (!isset($result['hits'])) {
            return new IdSearchResult(0, [], $criteria, $context);
        }

        $hits = $this->extractHits($result);

        $data = [];
        foreach ($hits as $hit) {
            $id = $hit['_id'];

            $data[$id] = [
                'primaryKey' => $id,
                'data' => array_merge(
                    $hit['_source'] ?? [],
                    ['id' => $id, '_score' => $hit['_score']]
                ),
            ];
        }

        $total = $this->getTotalValue($criteria, $result);
        if ($criteria->useIdSorting()) {
            $data = $this->sortByIdArray($criteria->getIds(), $data);
        }

        return new IdSearchResult($total, $data, $criteria, $context);
    }

    /**
     * @param array{ hits: array{ hits: array<int, array{ inner_hits?: array{ inner?: array<mixed>}}>}} $result
     *
     * @return array<mixed>
     */
    private function extractHits(array $result): array
    {
        $records = [];
        $hits = $result['hits']['hits'];

        foreach ($hits as $hit) {
            if (!isset($hit['inner_hits']['inner'])) {
                $records[] = $hit;

                continue;
            }

            /** @var array{ hits: array{ hits: array<int, array<mixed>>}} $inner */
            $inner = $hit['inner_hits']['inner'];

            $nested = $this->extractHits($inner);

            foreach ($nested as $inner) {
                $records[] = $inner;
            }
        }

        return $records;
    }

    /**
     * @param array{ hits: array{ hits: array<mixed>, total?: array{ value: int } }, aggregations?: array<string, array<string, mixed>>} $result
     */
    private function getTotalValue(Criteria $criteria, array $result): int
    {
        if ($criteria->getTotalCountMode() !== Criteria::TOTAL_COUNT_MODE_EXACT) {
            return empty($result['hits']['hits']) ? 0 : \count($result['hits']['hits']);
        }

        if (!$criteria->getGroupFields()) {
            return (int) ($result['hits']['total']['value'] ?? 0);
        }

        if (!$criteria->getPostFilters()) {
            return empty($result['aggregations']['total-count']['value']) ? 0 : (int) $result['aggregations']['total-count']['value'];
        }

        return empty($result['aggregations']['total-filtered-count']['total-count']['value']) ? 0 : (int) $result['aggregations']['total-filtered-count']['total-count']['value'];
    }

    /**
     * @param array<string|array<string>> $ids
     * @param array<int|string, array<string, mixed>> $data
     *
     * @return array<string, array<mixed>>
     */
    private function sortByIdArray(array $ids, array $data): array
    {
        $sorted = [];

        foreach ($ids as $id) {
            if (\is_array($id)) {
                $id = implode('-', $id);
            }

            if (\array_key_exists($id, $data)) {
                $sorted[$id] = $data[$id];
            }
        }

        return $sorted;
    }
}
