import { mount } from '@vue/test-utils';
import FilterService from 'src/app/service/filter.service';

const { Criteria } = Shopware.Data;

/**
 * @sw-package fundamentals@after-sales
 */

async function createWrapper(privileges = []) {
    const wrapper = mount(await wrapTestComponent('sw-settings-rule-list', { sync: true }), {
        global: {
            stubs: {
                'sw-page': {
                    template: `
    <div>
        <slot name="smart-bar-actions"></slot>
        <slot name="content"></slot>
    </div>`,
                },
                'sw-empty-state': true,
                'sw-loader': true,
                'sw-entity-listing': {
                    template: `
    <div class="sw-entity-listing">
        <slot name="more-actions"></slot>
    </div>
    `,
                },
                'sw-context-menu-item': await wrapTestComponent('sw-context-menu-item'),
                'sw-search-bar': true,
                'sw-language-switch': true,
                'sw-label': true,
                'sw-sidebar-item': true,
                'sw-sidebar-filter-panel': true,
                'sw-sidebar': true,
                'router-link': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: () => Promise.resolve([]),
                        clone: (id) => Promise.resolve({ id }),
                    }),
                },
                filterFactory: {
                    create: (name, filters) => filters,
                },
                filterService: new FilterService({
                    userConfigRepository: {
                        search: () => Promise.resolve({ length: 0 }),
                        create: () => ({}),
                    },
                }),
                ruleConditionDataProviderService: {
                    getConditions: () => {
                        return [{ type: 'foo', label: 'bar' }];
                    },
                    getGroups: () => {
                        return [{ id: 'foo', name: 'bar' }];
                    },
                    getByGroup: () => {
                        return [{ type: 'foo' }];
                    },
                },
                acl: {
                    can: (identifier) => {
                        if (!identifier) {
                            return true;
                        }

                        return privileges.includes(identifier);
                    },
                },
                searchRankingService: {},
            },
            mocks: {
                $route: {
                    query: 'foo',
                },
            },
        },
    });
    await flushPromises();

    const buttonAddRule = wrapper.findByText('button', 'sw-settings-rule.list.buttonAddRule');
    const entityListing = wrapper.get('.sw-entity-listing');
    const contextMenuItemDuplicate = wrapper.get('.sw-context-menu-item');

    return {
        wrapper,
        buttonAddRule,
        entityListing,
        contextMenuItemDuplicate,
    };
}

describe('src/module/sw-settings-rule/page/sw-settings-rule-list', () => {
    beforeEach(() => {
        Shopware.Application.view.router = {
            currentRoute: {
                value: {
                    query: '',
                },
            },
            push: () => {},
            replace: () => {},
        };
    });

    it('should have disabled fields', async () => {
        const { buttonAddRule, entityListing, contextMenuItemDuplicate } = await createWrapper();

        expect(buttonAddRule.attributes('disabled') !== undefined).toBe(true);
        expect(entityListing.attributes()['show-selection']).toBeUndefined();
        expect(entityListing.attributes()['allow-edit']).toBeUndefined();
        expect(entityListing.attributes()['allow-delete']).toBeUndefined();
        expect(contextMenuItemDuplicate.attributes().class).toContain('is--disabled');
    });

    it('should have enabled fields for creator', async () => {
        const { buttonAddRule, entityListing, contextMenuItemDuplicate } = await createWrapper([
            'rule.creator',
        ]);

        expect(buttonAddRule.attributes('disabled')).toBeUndefined();
        expect(entityListing.attributes()['show-selection']).toBeUndefined();
        expect(entityListing.attributes()['allow-edit']).toBeUndefined();
        expect(entityListing.attributes()['allow-delete']).toBeUndefined();
        expect(contextMenuItemDuplicate.attributes().class).not.toContain('is--disabled');
    });

    it('only should have enabled fields for editor', async () => {
        const { buttonAddRule, entityListing, contextMenuItemDuplicate } = await createWrapper([
            'rule.editor',
        ]);

        expect(buttonAddRule.attributes('disabled') !== undefined).toBe(true);
        expect(entityListing.attributes()['show-selection']).toBeUndefined();
        expect(entityListing.attributes()['allow-edit']).toBe('true');
        expect(entityListing.attributes()['allow-delete']).toBeUndefined();
        expect(contextMenuItemDuplicate.attributes().class).toContain('is--disabled');
    });

    it('should have enabled fields for deleter', async () => {
        const { buttonAddRule, entityListing, contextMenuItemDuplicate } = await createWrapper([
            'rule.deleter',
        ]);

        expect(buttonAddRule.attributes('disabled') !== undefined).toBe(true);
        expect(entityListing.attributes()['show-selection']).toBe('true');
        expect(entityListing.attributes()['allow-edit']).toBeUndefined();
        expect(entityListing.attributes()['allow-delete']).toBe('true');
        expect(contextMenuItemDuplicate.attributes().class).toContain('is--disabled');
    });

    it('should duplicate a rule and should overwrite name and createdAt values', async () => {
        const { wrapper } = await createWrapper(['rule.creator']);

        const ruleToDuplicate = {
            id: 'ruleId',
            name: 'ruleToDuplicate',
        };

        await wrapper.vm.onDuplicate(ruleToDuplicate);
        expect(wrapper.vm.$router.push).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.$router.push).toHaveBeenCalledWith({
            name: 'sw.settings.rule.detail',
            params: {
                id: ruleToDuplicate.id,
            },
        });
    });

    it('should get filter options for conditions', async () => {
        const { wrapper } = await createWrapper(['rule.creator']);
        await flushPromises();
        const conditionFilterOptions = wrapper.vm.conditionFilterOptions;

        expect(conditionFilterOptions).toEqual([
            { label: 'bar', value: 'foo' },
        ]);
    });

    it('should get filter options for groups', async () => {
        const { wrapper } = await createWrapper(['rule.creator']);
        await flushPromises();
        const groupFilterOptions = wrapper.vm.groupFilterOptions;

        expect(groupFilterOptions).toEqual([{ label: 'bar', value: 'foo' }]);
    });

    it('should get filter options for associations', async () => {
        const { wrapper } = await createWrapper(['rule.creator']);
        await flushPromises();
        const associationFilterOptions = wrapper.vm.associationFilterOptions;

        expect(associationFilterOptions.map((option) => option.value)).toContain('productPrices');
        expect(associationFilterOptions.map((option) => option.value)).toContain('paymentMethods');
    });

    it('should get list filters', async () => {
        const { wrapper } = await createWrapper(['rule.creator']);
        await flushPromises();
        const listFilters = wrapper.vm.listFilters;

        expect(Object.keys(listFilters)).toContain('conditionGroups');
        expect(Object.keys(listFilters)).toContain('conditions');
        expect(Object.keys(listFilters)).toContain('assignments');
        expect(Object.keys(listFilters)).toContain('tags');
    });

    it('should return filters from filter registry', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.dateFilter).toEqual(expect.any(Function));
    });

    it('should consider criteria filters via updateCriteria (triggered by sw-sidebar-filter-panel)', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();

        const filter = Criteria.equals('foo', 'bar');
        wrapper.vm.updateCriteria([filter]);
        await flushPromises();

        expect(wrapper.vm.listCriteria.filters).toContainEqual(filter);
    });

    it('should return a meta title', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();

        wrapper.vm.$createTitle = jest.fn(() => 'Title');
        const metaInfo = wrapper.vm.$options.metaInfo.call(wrapper.vm);

        expect(metaInfo.title).toBe('Title');
        expect(wrapper.vm.$createTitle).toHaveBeenNthCalledWith(1);
    });

    it('should notify on inline edit save error', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.vm.onInlineEditSave(Promise.reject());

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-settings-rule.detail.messageSaveError',
        });
    });

    it('should notify on inline edit save success', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();
        wrapper.vm.createNotificationSuccess = jest.fn();

        const rule = {
            name: 'foo',
        };
        await wrapper.vm.onInlineEditSave(Promise.resolve(), rule);

        expect(wrapper.vm.createNotificationSuccess).toHaveBeenCalledWith({
            message: 'sw-settings-rule.detail.messageSaveSuccess',
        });
    });

    it('should set loading state to false on getList error', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();
        wrapper.vm.ruleRepository.search = jest.fn();
        wrapper.vm.ruleRepository.search.mockRejectedValueOnce(false);

        await wrapper.vm.getList();
        await flushPromises();

        expect(wrapper.vm.ruleRepository.search).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('should set languageId on language switch change', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();

        await wrapper.vm.onChangeLanguage('foo');
        expect(Shopware.Store.get('context').api.languageId).toBe('foo');
    });
});
