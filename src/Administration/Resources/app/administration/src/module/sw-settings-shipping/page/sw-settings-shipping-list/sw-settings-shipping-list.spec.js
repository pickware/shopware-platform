import { mount } from '@vue/test-utils';
import Criteria from 'src/core/data/criteria.data';
import { searchRankingPoint } from 'src/app/service/search-ranking.service';

/**
 * @sw-package checkout
 */

async function createWrapper(privileges = []) {
    const shippingMethod = {};
    shippingMethod.getEntityName = () => 'shipping_method';
    shippingMethod.isNew = () => false;

    return mount(
        await wrapTestComponent('sw-settings-shipping-list', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                mocks: {
                    $route: {
                        query: '',
                    },
                },
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            search: jest.fn(() => {
                                return Promise.resolve([]);
                            }),
                        }),
                    },
                    acl: {
                        can: (identifier) => {
                            if (!identifier) {
                                return true;
                            }

                            return privileges.includes(identifier);
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
                },
                stubs: {
                    'sw-page': {
                        template: '<div><slot name="content"></slot><slot name="smart-bar-actions"></slot></div>',
                    },
                    'sw-entity-listing': true,
                    'sw-empty-state': true,
                    'router-link': true,
                    'sw-search-bar': true,
                    'sw-language-switch': true,
                    'sw-checkbox-field': true,
                    'sw-single-select': true,
                    'sw-context-menu-item': true,
                    'sw-sidebar-item': true,
                    'sw-sidebar': true,
                },
            },
        },
    );
}

describe('module/sw-settings-shipping/page/sw-settings-shipping-list', () => {
    it('should be a vue js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have all fields disabled', async () => {
        const wrapper = await createWrapper();

        const entityListing = wrapper.find('sw-entity-listing-stub');
        const button = wrapper.findByText('button', 'sw-settings-shipping.list.buttonAddShippingMethod');

        expect(entityListing.attributes()['allow-edit']).toBeFalsy();
        expect(entityListing.attributes()['allow-delete']).toBeFalsy();
        expect(entityListing.attributes()['show-selection']).toBeFalsy();
        expect(button.attributes('disabled')).toBeDefined();
    });

    it('should have edit fields enabled', async () => {
        const wrapper = await createWrapper([
            'shipping.editor',
        ]);

        const entityListing = wrapper.find('sw-entity-listing-stub');
        const button = wrapper.findByText('button', 'sw-settings-shipping.list.buttonAddShippingMethod');

        expect(entityListing.attributes()['allow-edit']).toBe('true');
        expect(entityListing.attributes()['allow-delete']).toBeFalsy();
        expect(entityListing.attributes()['show-selection']).toBeFalsy();

        expect(button.attributes('disabled')).toBeDefined();
    });

    it('should have delete fields enabled', async () => {
        const wrapper = await createWrapper([
            'shipping.editor',
            'shipping.deleter',
        ]);

        const entityListing = wrapper.find('sw-entity-listing-stub');
        const button = wrapper.findByText('button', 'sw-settings-shipping.list.buttonAddShippingMethod');

        expect(entityListing.attributes()['allow-edit']).toBe('true');
        expect(entityListing.attributes()['allow-delete']).toBe('true');

        expect(button.attributes('disabled')).toBeDefined();
    });

    it('should have creator fields enabled', async () => {
        const wrapper = await createWrapper([
            'shipping.editor',
            'shipping.deleter',
            'shipping.creator',
        ]);

        const entityListing = wrapper.find('sw-entity-listing-stub');
        const button = wrapper.findByText('button', 'sw-settings-shipping.list.buttonAddShippingMethod');

        expect(entityListing.attributes()['allow-edit']).toBe('true');
        expect(entityListing.attributes()['allow-delete']).toBe('true');

        expect(button.attributes('disabled')).toBeUndefined();
    });

    it('should add query score to the criteria', async () => {
        const wrapper = await createWrapper();
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
        const wrapper = await createWrapper();
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
        const wrapper = await createWrapper();
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
        const wrapper = await createWrapper();
        await wrapper.setData({
            term: 'foo',
        });
        await wrapper.vm.$nextTick();
        wrapper.vm.searchRankingService.getSearchFieldsByEntity = jest.fn(() => {
            return {};
        });
        await wrapper.vm.getList();

        const emptyState = wrapper.find('sw-empty-state-stub');

        expect(wrapper.vm.searchRankingService.getSearchFieldsByEntity).toHaveBeenCalledTimes(1);
        expect(emptyState.exists()).toBeTruthy();
        expect(emptyState.attributes().title).toBe('sw-empty-state.messageNoResultTitle');
        expect(wrapper.find('sw-entity-listing-stub').exists()).toBeFalsy();
        expect(wrapper.vm.entitySearchable).toBe(false);

        wrapper.vm.searchRankingService.getSearchFieldsByEntity.mockRestore();
    });
});
