import { searchRankingPoint } from 'src/app/service/search-ranking.service';

/**
 * @sw-package discovery
 */

const defaultSearchConfiguration = {
    _searchable: false,
    name: {
        _searchable: true,
        _score: searchRankingPoint.HIGH_SEARCH_RANKING,
    },
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defaultSearchConfiguration;
