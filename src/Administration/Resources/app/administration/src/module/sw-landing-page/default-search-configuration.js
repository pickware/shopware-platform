import { searchRankingPoint } from 'src/app/service/search-ranking.service';

const defaultSearchConfiguration = {
    _searchable: true,
    name: {
        _searchable: true,
        _score: searchRankingPoint.HIGH_SEARCH_RANKING,
    },
    tags: {
        name: {
            _searchable: true,
            _score: searchRankingPoint.HIGH_SEARCH_RANKING,
        },
    },
};

/**
 * @sw-package discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defaultSearchConfiguration;
