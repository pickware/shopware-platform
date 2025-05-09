/**
 * @sw-package inventory
 */

import { mount, config } from '@vue/test-utils';
import { createRouter, createWebHashHistory } from 'vue-router';
import { searchRankingPoint } from 'src/app/service/search-ranking.service';
import Criteria from 'src/core/data/criteria.data';

const CURRENCY_ID = {
    EURO: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
    POUND: 'fce3465831e8639bb2ea165d0fcf1e8b',
};

function mockContext() {
    return {
        apiPath: 'http://shopware.local/api',
        apiResourcePath: 'http://shopware.local/api/v2',
        assetsPath: 'http://shopware.local/bundles/',
        basePath: '',
        host: 'shopware.local',
        inheritance: false,
        installationPath: 'http://shopware.local',
        languageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
        liveVersionId: '0fa91ce3e96a4bc2be4bd9ce752c3425',
        pathInfo: '/admin',
        port: 80,
        scheme: 'http',
        schemeAndHttpHost: 'http://shopware.local',
        uri: 'http://shopware.local/admin',
    };
}

function mockPrices() {
    return [
        {
            currencyId: CURRENCY_ID.POUND,
            net: 373.83,
            gross: 400,
            linked: true,
        },
        {
            currencyId: CURRENCY_ID.EURO,
            net: 560.75,
            gross: 600,
            linked: true,
        },
    ];
}

function mockCriteria() {
    return {
        limit: 25,
        page: 1,
        sortings: [],
        resetSorting() {
            this.sortings = [];
        },
        addSorting(sorting) {
            this.sortings.push(sorting);
        },
    };
}

function getProductData(criteria) {
    const products = [
        {
            active: true,
            stock: 333,
            availableStock: 333,
            available: true,
            price: [
                {
                    currencyId: CURRENCY_ID.POUND,
                    net: 373.83,
                    gross: 400,
                    linked: true,
                },
                {
                    currencyId: CURRENCY_ID.EURO,
                    net: 560.75,
                    gross: 600,
                    linked: true,
                },
            ],
            productNumber: 'SW10001',
            name: 'Product 2',
            id: 'dcc37f845b664e24b5b2e6e77c078e6c',
            manufacturer: {
                name: 'Manufacturer B',
            },
        },
        {
            active: true,
            stock: 333,
            availableStock: 333,
            available: true,
            price: [
                {
                    currencyId: CURRENCY_ID.POUND,
                    net: 20.56,
                    gross: 22,
                    linked: true,
                },
                {
                    currencyId: CURRENCY_ID.EURO,
                    net: 186.89,
                    gross: 200,
                    linked: true,
                },
            ],
            productNumber: 'SW10000',
            name: 'Product 1',
            id: 'bc5ff49955be4b919053add552c2815d',
            childCount: 8,
            manufacturer: {
                name: 'Manufacturer A',
            },
        },
    ];

    // check if grid is sorting for currency
    const sortingForCurrency = criteria.sortings.some((sortAttr) => sortAttr.field.startsWith('price'));

    if (sortingForCurrency) {
        const sortBy = criteria.sortings[0].field;
        const sortDirection = criteria.sortings[0].order;

        products.sort((productA, productB) => {
            const currencyId = sortBy.split('.')[1];

            const currencyValueA = productA.price.find((price) => price.currencyId === currencyId).gross;
            const currencyValueB = productB.price.find((price) => price.currencyId === currencyId).gross;

            if (sortDirection === 'DESC') {
                return currencyValueB - currencyValueA;
            }

            return currencyValueA - currencyValueB;
        });
    }

    // check if grid is sorting for name
    const sortingForName = criteria.sortings.some((sortAttr) => sortAttr.field.startsWith('name'));

    if (sortingForName) {
        const sortDirection = criteria.sortings[0].order;
        products.sort((productA, productB) => {
            const nameA = productA.name.toLowerCase();
            const nameB = productB.name.toLowerCase();

            if (sortDirection === 'DESC') {
                return nameA > nameB ? -1 : 1;
            }

            return nameA < nameB ? -1 : 1;
        });
    }

    // check if grid is sorting for manufacturer name
    const sortingForManufacturer = criteria.sortings.some((sortAttr) => sortAttr.field.startsWith('manufacturer'));

    if (sortingForManufacturer) {
        const sortDirection = criteria.sortings[0].order;
        products.sort((productA, productB) => {
            const nameA = productA.manufacturer.name.toLowerCase();
            const nameB = productB.manufacturer.name.toLowerCase();

            if (sortDirection === 'DESC') {
                return nameA > nameB ? -1 : 1;
            }

            return nameA < nameB ? -1 : 1;
        });
    }

    products.sortings = [];
    products.total = products.length;
    products.criteria = mockCriteria();
    products.context = mockContext();

    return products;
}

function getCurrencyData() {
    return [
        {
            factor: 1,
            symbol: '€',
            isoCode: 'EUR',
            shortName: 'EUR',
            name: 'Euro',
            decimalPrecision: 2,
            position: 1,
            isSystemDefault: true,
            id: CURRENCY_ID.EURO,
        },
        {
            factor: 1.045738495,
            symbol: '£',
            isoCode: 'GBP',
            shortName: 'GBP',
            name: 'Pound',
            decimalPrecision: 2,
            position: 1,
            isSystemDefault: true,
            id: CURRENCY_ID.POUND,
        },
    ];
}

async function createWrapper() {
    // delete global $router and $routes mocks
    delete config.global.mocks.$router;
    delete config.global.mocks.$route;

    const router = createRouter({
        history: createWebHashHistory(),
        routes: [
            {
                name: 'sw.product.list',
                path: '/sw/product/list',
                component: await wrapTestComponent('sw-product-list', {
                    sync: true,
                }),
                meta: {
                    $module: {
                        entity: 'product',
                    },
                },
            },
        ],
    });

    router.push({ name: 'sw.product.list' });
    await router.isReady();

    return {
        wrapper: mount(await wrapTestComponent('sw-product-list', { sync: true }), {
            global: {
                plugins: [
                    router,
                ],
                provide: {
                    numberRangeService: {},
                    repositoryFactory: {
                        create: (name) => {
                            if (name === 'product') {
                                return {
                                    search: (criteria) => {
                                        const productData = getProductData(criteria);

                                        return Promise.resolve(productData);
                                    },
                                };
                            }
                            if (name === 'user_config') {
                                return {
                                    search: () => Promise.resolve([]),
                                };
                            }

                            return {
                                search: () => Promise.resolve(getCurrencyData()),
                            };
                        },
                    },
                    searchRankingService: {
                        getSearchFieldsByEntity: () => {
                            return Promise.resolve({
                                name: searchRankingPoint.HIGH_SEARCH_RANKING,
                            });
                        },
                        buildSearchQueriesForEntity: (searchFields, term, criteria) => {
                            return criteria;
                        },
                    },
                    filterFactory: {
                        create: () => [],
                    },
                    acl: {
                        can: () => true,
                    },
                },
                stubs: {
                    'sw-page': {
                        template: '<div><slot name="content"></slot></div>',
                    },
                    'sw-entity-listing': await wrapTestComponent('sw-entity-listing', { sync: true }),
                    'sw-context-button': {
                        template: '<div></div>',
                    },
                    'sw-context-menu-item': {
                        template: '<div></div>',
                    },
                    'sw-data-grid-settings': {
                        template: '<div></div>',
                    },
                    'sw-empty-state': {
                        template: '<div class="sw-empty-state"></div>',
                    },
                    'sw-pagination': {
                        template: '<div></div>',
                    },
                    'sw-sidebar': {
                        template: '<div></div>',
                    },
                    'sw-sidebar-item': {
                        template: '<div></div>',
                    },
                    'router-link': {
                        template: '<div class="sw-router-link"><slot></slot></div>',
                        props: ['to'],
                    },
                    'sw-language-switch': {
                        template: '<div></div>',
                    },
                    'sw-notification-center': {
                        template: '<div></div>',
                    },
                    'sw-search-bar': {
                        template: '<div></div>',
                    },
                    'sw-loader': {
                        template: '<div></div>',
                    },
                    'sw-data-grid-skeleton': {
                        template: '<div class="sw-data-grid-skeleton"></div>',
                    },
                    'sw-checkbox-field': {
                        template: '<div></div>',
                    },
                    'sw-media-preview-v2': {
                        template: '<div></div>',
                    },
                    'sw-color-badge': {
                        template: '<div></div>',
                    },
                    'sw-button-group': true,
                    'sw-text-field': true,
                    'sw-label': true,
                    'mt-number-field': true,
                    'sw-bulk-edit-modal': true,
                    'sw-product-clone-modal': true,
                    'sw-product-variant-modal': true,
                    'sw-sidebar-filter-panel': true,
                    'sw-data-grid-column-boolean': true,
                    'sw-data-grid-inline-edit': true,
                    'sw-provide': { template: '<slot/>', inheritAttrs: false },
                },
            },
        }),
        router: router,
    };
}

Shopware.Service().register('filterService', () => {
    return {
        mergeWithStoredFilters: (storeKey, criteria) => criteria,
    };
});

describe('module/sw-product/page/sw-product-list', () => {
    let wrapper;
    let router;

    beforeEach(async () => {
        const data = await createWrapper();
        wrapper = data.wrapper;
        router = data.router;
    });

    it('should be a Vue.JS component', async () => {
        await router.push({
            name: 'sw.product.list',
        });
        expect(wrapper.vm).toBeTruthy();
    });

    it('should sort grid when sorting for price', async () => {
        // load content of grid
        await wrapper.vm.getList();

        // get header which sorts grid when clicking on it
        const currencyColumnHeader = wrapper.find('.sw-data-grid__cell--header.sw-data-grid__cell--5');

        const priceCells = wrapper.findAll('.sw-data-grid__cell--price-EUR');
        const firstPriceCell = priceCells.at(0);
        const secondPriceCell = priceCells.at(1);

        // assert cells have correct values
        expect(firstPriceCell.text()).toBe('€600.00');
        expect(secondPriceCell.text()).toBe('€200.00');

        // sort grid after price ASC
        await currencyColumnHeader.trigger('click');
        await flushPromises();

        const sortedPriceCells = wrapper.findAll('.sw-data-grid__cell--price-EUR');
        const firstSortedPriceCell = sortedPriceCells.at(0);
        const secondSortedPriceCell = sortedPriceCells.at(1);

        // assert that order of values has changed
        expect(firstSortedPriceCell.text()).toBe('€200.00');
        expect(secondSortedPriceCell.text()).toBe('€600.00');

        // verify that grid did not crash when sorting for prices
        const skeletonElement = wrapper.find('.sw-data-grid-skeleton');
        expect(skeletonElement.exists()).toBe(false);
    });

    it('should sort products by different currencies', async () => {
        await wrapper.vm.getList();

        // get header which sorts grid when clicking on it
        const currencyColumnHeader = wrapper.find('.sw-data-grid__cell--header.sw-data-grid__cell--5');

        // sort grid after price ASC
        await currencyColumnHeader.trigger('click');

        await flushPromises();

        const euroCells = wrapper.findAll('.sw-data-grid__cell--price-EUR');
        const [
            firstEuroCell,
            secondEuroCell,
        ] = euroCells;

        expect(firstEuroCell.text()).toBe('€200.00');
        expect(secondEuroCell.text()).toBe('€600.00');

        const poundCells = wrapper.findAll('.sw-data-grid__cell--price-GBP');
        const [
            firstPoundCell,
            secondPoundCell,
        ] = poundCells;

        expect(firstPoundCell.text()).toBe('£22.00');
        expect(secondPoundCell.text()).toBe('£400.00');

        const columnHeaders = wrapper.findAll('.sw-data-grid__cell.sw-data-grid__cell--header');
        const poundColumn = columnHeaders.at(7);

        await poundColumn.trigger('click');

        await flushPromises();

        let sortedPoundCells = wrapper.findAll('.sw-data-grid__cell--price-GBP');
        let [
            firstSortedPoundCell,
            secondSortedPoundCell,
        ] = sortedPoundCells;

        expect(firstSortedPoundCell.text()).toBe('£22.00');
        expect(secondSortedPoundCell.text()).toBe('£400.00');

        await poundColumn.trigger('click');

        await flushPromises();

        sortedPoundCells = wrapper.findAll('.sw-data-grid__cell--price-GBP');
        [
            firstSortedPoundCell,
            secondSortedPoundCell,
        ] = sortedPoundCells;

        expect(firstSortedPoundCell.text()).toBe('£400.00');
        expect(secondSortedPoundCell.text()).toBe('£22.00');
    });

    it('should sort products by name', async () => {
        await wrapper.vm.getList();

        const currencyColumnHeader = wrapper.find('.sw-data-grid__cell--header.sw-data-grid__cell--0');

        await currencyColumnHeader.trigger('click');
        await flushPromises();

        const productNamesASCSorted = wrapper.findAll('.sw-data-grid__cell--name');
        const [
            firstProductNameASCSorted,
            secondProductNameASCSorted,
        ] = productNamesASCSorted;

        expect(firstProductNameASCSorted.text()).toBe('Product 1');
        expect(secondProductNameASCSorted.text()).toBe('Product 2');

        await currencyColumnHeader.trigger('click');
        await flushPromises();

        const productNamesDESCSorted = wrapper.findAll('.sw-data-grid__cell--name');
        const [
            firstProductNameDESCSorted,
            secondProductNameDESCSorted,
        ] = productNamesDESCSorted;

        expect(firstProductNameDESCSorted.text()).toBe('Product 2');
        expect(secondProductNameDESCSorted.text()).toBe('Product 1');

        const params = new URLSearchParams(window.location.href);
        expect(params.get('naturalSorting')).toBe('false');
        expect(wrapper.vm.naturalSorting).toBe(false);
    });

    it('should sort products by Manufacturer name', async () => {
        await wrapper.vm.getList();

        const currencyColumnHeader = wrapper.find('.sw-data-grid__cell--header.sw-data-grid__cell--2');

        await currencyColumnHeader.trigger('click');
        await flushPromises();

        const manufacturerNamesASCSorted = wrapper.findAll('.sw-data-grid__cell--manufacturer-name');
        const [
            firstManufacturerNameASCSorted,
            secondManufacturerNameASCSorted,
        ] = manufacturerNamesASCSorted;

        expect(firstManufacturerNameASCSorted.text()).toBe('Manufacturer A');
        expect(secondManufacturerNameASCSorted.text()).toBe('Manufacturer B');

        await currencyColumnHeader.trigger('click');
        await flushPromises();

        const manufacturerNamesDESCSorted = wrapper.findAll('.sw-data-grid__cell--manufacturer-name');
        const [
            firstManufacturerNameDESCSorted,
            secondManufacturerNameDESCSorted,
        ] = manufacturerNamesDESCSorted;

        expect(firstManufacturerNameDESCSorted.text()).toBe('Manufacturer B');
        expect(secondManufacturerNameDESCSorted.text()).toBe('Manufacturer A');
    });

    it('should return price when given currency id', async () => {
        const currencyId = CURRENCY_ID.EURO;
        const prices = mockPrices();

        const foundPriceData = wrapper.vm.getCurrencyPriceByCurrencyId(currencyId, prices);
        const expectedPriceData = {
            currencyId: CURRENCY_ID.EURO,
            net: 560.75,
            gross: 600,
            linked: true,
        };

        expect(foundPriceData).toEqual(expectedPriceData);
    });

    it('should return fallback when no price was found', async () => {
        const currencyId = 'no-valid-id';
        const prices = mockPrices();

        const foundPriceData = wrapper.vm.getCurrencyPriceByCurrencyId(currencyId, prices);
        const expectedPriceData = {
            currencyId: null,
            gross: null,
            linked: true,
            net: null,
        };

        expect(foundPriceData).toEqual(expectedPriceData);
    });

    it('should return false if product has no variants', async () => {
        const [product] = getProductData(mockCriteria());
        const productHasVariants = wrapper.vm.productHasVariants(product);

        expect(productHasVariants).toBe(false);
    });

    it('should return true if product has variants', async () => {
        const [
            ,
            product,
        ] = getProductData(mockCriteria());
        const productHasVariants = wrapper.vm.productHasVariants(product);

        expect(productHasVariants).toBe(true);
    });

    it('should add query score to the criteria', async () => {
        await wrapper.setData({
            term: 'foo',
        });
        await wrapper.vm.$nextTick();
        wrapper.vm.searchRankingService.buildSearchQueriesForEntity = jest.fn(() => {
            return new Criteria(1, 25);
        });

        wrapper.vm.searchRankingService.getSearchFieldsByEntity = jest.fn(() => {
            return { name: 500 };
        });

        await wrapper.vm.getList();

        expect(wrapper.vm.searchRankingService.buildSearchQueriesForEntity).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.searchRankingService.getSearchFieldsByEntity).toHaveBeenCalledTimes(1);

        wrapper.vm.searchRankingService.buildSearchQueriesForEntity.mockRestore();
        wrapper.vm.searchRankingService.getSearchFieldsByEntity.mockRestore();
    });

    it('should not get search ranking fields when term is null', async () => {
        await wrapper.vm.$nextTick();
        wrapper.vm.searchRankingService.buildSearchQueriesForEntity = jest.fn(() => {
            return new Criteria(1, 25);
        });

        wrapper.vm.searchRankingService.getSearchFieldsByEntity = jest.fn(() => {
            return {};
        });

        await wrapper.vm.getList();

        expect(wrapper.vm.searchRankingService.buildSearchQueriesForEntity).toHaveBeenCalledTimes(0);
        expect(wrapper.vm.searchRankingService.getSearchFieldsByEntity).toHaveBeenCalledTimes(0);

        wrapper.vm.searchRankingService.buildSearchQueriesForEntity.mockRestore();
        wrapper.vm.searchRankingService.getSearchFieldsByEntity.mockRestore();
    });

    it('should not build query score when search ranking field is null', async () => {
        await wrapper.setData({
            term: 'foo',
        });

        await wrapper.vm.$nextTick();
        wrapper.vm.searchRankingService.buildSearchQueriesForEntity = jest.fn(() => {
            return new Criteria(1, 25);
        });

        wrapper.vm.searchRankingService.getSearchFieldsByEntity = jest.fn(() => {
            return {};
        });

        await wrapper.vm.getList();

        expect(wrapper.vm.searchRankingService.buildSearchQueriesForEntity).toHaveBeenCalledTimes(0);
        expect(wrapper.vm.searchRankingService.getSearchFieldsByEntity).toHaveBeenCalledTimes(1);

        wrapper.vm.searchRankingService.buildSearchQueriesForEntity.mockRestore();
        wrapper.vm.searchRankingService.getSearchFieldsByEntity.mockRestore();
    });

    it('should show empty state when there is not item after filling search term', async () => {
        await wrapper.setData({
            term: 'foo',
        });
        await flushPromises();
        wrapper.vm.searchRankingService.getSearchFieldsByEntity = jest.fn(() => {
            return {};
        });
        await wrapper.vm.getList();

        const emptyState = wrapper.find('.sw-empty-state');

        expect(wrapper.vm.searchRankingService.getSearchFieldsByEntity).toHaveBeenCalledTimes(1);
        expect(emptyState.exists()).toBeTruthy();
        expect(emptyState.attributes().title).toBe('sw-empty-state.messageNoResultTitle');
        expect(wrapper.find('sw-entity-listing-stub').exists()).toBeFalsy();
        expect(wrapper.vm.entitySearchable).toBe(false);

        wrapper.vm.searchRankingService.getSearchFieldsByEntity.mockRestore();
    });

    it('should push to a new route when editing items', async () => {
        wrapper.vm.$router.push = jest.fn();
        await wrapper.setData({
            selection: {
                foo: { states: ['is-download'] },
            },
        });

        await wrapper.vm.onBulkEditItems();
        expect(wrapper.vm.$router.push).toHaveBeenCalledWith(
            expect.objectContaining({
                name: 'sw.bulk.edit.product',
                params: expect.objectContaining({
                    parentId: 'null',
                    includesDigital: '1',
                }),
            }),
        );

        wrapper.vm.$router.push.mockRestore();
    });

    it('should return filters from filter registry', async () => {
        expect(wrapper.vm.assetFilter).toEqual(expect.any(Function));
        expect(wrapper.vm.currencyFilter).toEqual(expect.any(Function));
        expect(wrapper.vm.dateFilter).toEqual(expect.any(Function));
        expect(wrapper.vm.stockColorVariantFilter).toEqual(expect.any(Function));
    });

    it('should not have media and configuratorSettings association when loading product list', async () => {
        await wrapper.vm.getList();

        expect(
            wrapper.vm.productCriteria.associations.some((association) => association.association === 'media'),
        ).toBeFalsy();
        expect(
            wrapper.vm.productCriteria.associations.some(
                (association) => association.association === 'configuratorSettings',
            ),
        ).toBeFalsy();
    });
});
