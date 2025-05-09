/**
 * @sw-package inventory
 */
import { mount } from '@vue/test-utils';

const customFields = mockCustomFieldData();

function mockCustomFieldData() {
    const _customFields = [];

    for (let i = 0; i < 10; i += 1) {
        const customField = {
            id: `id${i}`,
            name: `custom_additional_field_${i}`,
            config: {
                label: { 'en-GB': `Special field ${i}` },
                customFieldType: 'checkbox',
                customFieldPosition: i + 1,
            },
        };

        _customFields.push(customField);
    }

    return _customFields;
}

const responses = global.repositoryFactoryMock.responses;

responses.addResponse({
    method: 'Post',
    url: '/search/custom-field',
    status: 200,
    response: {
        data: customFields,
    },
});

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-settings-search-searchable-content-customfields', {
            sync: true,
        }),
        {
            global: {
                mocks: {
                    $route: {
                        query: {
                            page: 1,
                            limit: 25,
                        },
                    },
                },

                stubs: {
                    'sw-empty-state': true,
                    'sw-entity-listing': await wrapTestComponent('sw-entity-listing'),
                    'sw-data-grid': await wrapTestComponent('sw-data-grid'),
                    'sw-pagination': true,
                    'sw-data-grid-skeleton': await wrapTestComponent('sw-data-grid-skeleton'),
                    'sw-context-button': {
                        template: `
                    <div class="sw-context-button">
                        <slot name="button"></slot>
                        <slot />
                        <slot name="context-menu"></slot>
                    </div>
                    `,
                    },
                    'sw-context-menu-item': await wrapTestComponent('sw-context-menu-item'),
                    'sw-entity-single-select': true,
                    'mt-number-field': true,
                    'sw-checkbox-field': true,
                    'sw-bulk-edit-modal': true,
                    'sw-data-grid-settings': true,
                    'sw-data-grid-column-boolean': true,
                    'sw-data-grid-inline-edit': true,
                    'router-link': true,
                    'sw-provide': true,
                },
            },

            props: {
                isEmpty: false,
                columns: [],
                repository: {},
                fieldConfigs: [],
            },
        },
    );
}

describe('module/sw-settings-search/component/sw-settings-search-searchable-content-customfields', () => {
    beforeEach(async () => {
        Shopware.Application.view.deleteReactive = () => {};
        global.activeAclRoles = [];
    });

    it('should be a Vue.JS component', async () => {
        global.activeAclRoles = ['product_search_config.viewer'];

        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should render empty state when isEmpty variable is true', async () => {
        global.activeAclRoles = ['product_search_config.viewer'];

        const wrapper = await createWrapper();

        await wrapper.setProps({
            isEmpty: true,
        });

        await flushPromises();

        expect(wrapper.find('sw-empty-state-stub').exists()).toBeTruthy();
    });

    it('Should not able to remove item without editor privilege', async () => {
        global.activeAclRoles = ['product_search_config.viewer'];

        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.onRemove = jest.fn();
        const searchConfigs = [
            {
                apiAlias: null,
                createdAt: '2021-01-29T02:18:11.171+00:00',
                customFieldId: '123456',
                field: 'categories.customFields',
                id: '8bafeb17b2494781ac44dce2d3ecfae5',
                ranking: 0,
                searchConfigId: '61168b0c1f97454cbee670b12d045d32',
                searchable: false,
                tokenize: false,
            },
        ];
        searchConfigs.criteria = { page: 1, limit: 25 };

        await wrapper.setProps({
            searchConfigs,
            isLoading: false,
        });

        await flushPromises();

        const firstRow = wrapper.find('.sw-data-grid__row.sw-data-grid__row--0');

        const buttonContext = await firstRow.find('.sw-settings-search__searchable-content-list-remove');
        expect(buttonContext.isVisible()).toBe(true);
        expect(buttonContext.classes()).toContain('is--disabled');
    });

    it('Should able to remove item when click to remove action if having deleter privilege', async () => {
        global.activeAclRoles = ['product_search_config.deleter'];

        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.onRemove = jest.fn();

        const searchConfigs = [
            {
                apiAlias: null,
                createdAt: '2021-01-29T02:18:11.171+00:00',
                customFieldId: '123456',
                field: 'categories.customFields',
                id: '8bafeb17b2494781ac44dce2d3ecfae5',
                ranking: 0,
                searchConfigId: '61168b0c1f97454cbee670b12d045d32',
                searchable: false,
                tokenize: false,
            },
        ];
        searchConfigs.criteria = { page: 1, limit: 25 };

        await wrapper.setProps({
            searchConfigs,
            isLoading: false,
        });

        await flushPromises();

        const firstRow = wrapper.find('.sw-data-grid__row.sw-data-grid__row--0');

        await firstRow.find('.sw-settings-search__searchable-content-list-remove').trigger('click');

        expect(wrapper.vm.onRemove).toHaveBeenCalled();
    });

    it('Should emitted to delete-config when call the remove function if having deleter privilege', async () => {
        global.activeAclRoles = ['product_search_config.deleter'];

        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const searchConfigs = [
            {
                apiAlias: null,
                createdAt: '2021-01-29T02:18:11.171+00:00',
                customFieldId: '8bafeb17b2494781ac44dce2d3ecfae2',
                field: 'categories.customFields',
                id: '8bafeb17b2494781ac44dce2d3ecfae5',
                ranking: 0,
                searchConfigId: '61168b0c1f97454cbee670b12d045d32',
                searchable: false,
                tokenize: false,
            },
        ];
        searchConfigs.criteria = { page: 1, limit: 25 };

        await wrapper.setProps({
            searchConfigs,
            isLoading: false,
        });

        await wrapper.vm.onRemove({
            field: 'categories.customFields',
            id: '8bafeb17b2494781ac44dce2d3ecfae5',
        });
        expect(wrapper.emitted('config-delete')).toBeTruthy();
    });

    it('Should call to reset ranking function when click to reset ranking action if having editor privilege', async () => {
        global.activeAclRoles = ['product_search_config.editor'];

        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.onResetRanking = jest.fn();
        const searchConfigs = [
            {
                apiAlias: null,
                createdAt: '2021-01-29T02:18:11.171+00:00',
                customFieldId: '3bafeb17b2494781ac44dce2d3ecfae4',
                field: 'categories.customFields',
                id: '8bafeb17b2494781ac44dce2d3ecfae5',
                ranking: 0,
                searchConfigId: '61168b0c1f97454cbee670b12d045d32',
                searchable: false,
                tokenize: false,
            },
        ];
        searchConfigs.criteria = { page: 1, limit: 25 };

        await wrapper.setProps({
            searchConfigs,
            isLoading: false,
        });
        await flushPromises();

        const firstRow = wrapper.find('.sw-data-grid__row.sw-data-grid__row--0');

        await firstRow.find('.sw-settings-search__searchable-content-list-reset').trigger('click');

        expect(wrapper.vm.onResetRanking).toHaveBeenCalled();
    });

    it('Should emitted to save-config when call the reset ranking function if having the editor privilege', async () => {
        global.activeAclRoles = ['product_search_config.editor'];

        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const searchConfigs = [
            {
                apiAlias: null,
                createdAt: '2021-01-29T02:18:11.171+00:00',
                customFieldId: '23168b0c1f97454cbee670b12d045d32',
                field: 'categories.customFields',
                id: '8bafeb17b2494781ac44dce2d3ecfae5',
                ranking: 0,
                searchConfigId: '61168b0c1f97454cbee670b12d045d32',
                searchable: false,
                tokenize: false,
            },
        ];
        searchConfigs.criteria = { page: 1, limit: 25 };

        await wrapper.setProps({
            searchConfigs,
            isLoading: false,
        });

        await wrapper.vm.onResetRanking({
            field: 'categories.customFields',
            id: '8bafeb17b2494781ac44dce2d3ecfae5',
        });

        expect(wrapper.emitted('config-save')).toBeTruthy();
    });
});
