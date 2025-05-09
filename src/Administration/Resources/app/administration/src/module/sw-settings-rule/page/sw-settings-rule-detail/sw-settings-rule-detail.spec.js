import { config, mount } from '@vue/test-utils';
import { kebabCase } from 'lodash';
import { createRouter, createWebHistory } from 'vue-router';

/**
 * @sw-package fundamentals@after-sales
 */

const { EntityCollection, Criteria } = Shopware.Data;
const { Context } = Shopware;

const ruleMock = {
    id: 'uuid1',
    name: 'Test rule',
    isNew: () => false,
    getEntityName: () => 'rule',
    conditions: new EntityCollection('foo/rule', 'rule_condition'),
    someRuleRelation: ['some-value'],
};

const conditionTreeMock = {
    conditionTree: [
        {
            id: 'some-condition',
            children: [
                {
                    id: 'some-child-condition',
                    children: [
                        {
                            id: 'some-grand-child-condition',
                        },
                    ],
                },
                {
                    id: 'some-other-child-condition',
                },
            ],
        },
        {
            id: 'some-other-condition',
        },
    ],
};

const defaultAggregations = {
    testRelation: {
        buckets: [
            {
                testRelation: {
                    count: 0,
                },
            },
        ],
    },
};

function getCollection(repository, entities = [], aggregations = defaultAggregations, total = 0) {
    return new EntityCollection(
        `/${kebabCase(repository)}`,
        repository,
        Context.api,
        { isShopwareContext: true, page: 1, limit: 25 },
        entities,
        total,
        aggregations,
    );
}

const ruleConditionDataProviderServiceMock = {
    getModuleTypes: jest.fn(() => []),
    addScriptConditions: jest.fn(() => {}),
    getAwarenessKeysWithEqualsAnyConfig: jest.fn(() => []),
};

const ruleConditionsConfigApiServiceMock = {
    load: jest.fn(() => Promise.resolve()),
};

const ruleRepositoryMock = {
    create: jest.fn(() => ({
        ...ruleMock,
        isNew: () => true,
    })),
    hasChanges: jest.fn(() => false),
    save: jest.fn(() => Promise.resolve()),
    search: jest.fn(() => Promise.resolve(getCollection('rule', [ruleMock]))),
    clone: jest.fn(() =>
        Promise.resolve({
            id: 'duplicated-rule-id',
        }),
    ),
};

const languageRepositoryMock = {
    get: jest.fn(() => Promise.resolve({})),
    search: jest.fn(() =>
        Promise.resolve(
            getCollection('language', [
                {
                    id: 'uuid1',
                    name: 'English',
                    label: 'English',
                },
            ]),
        ),
    ),
};

const appConditionRepositoryMock = {
    search: jest.fn(() => Promise.resolve(getCollection('app_script_condition', []))),
};

const conditionRepositoryMock = {
    search: jest.fn(() => Promise.resolve(getCollection('rule_condition', []))),
    sync: jest.fn(() => Promise.resolve()),
    syncDeleted: jest.fn(() => Promise.resolve()),
};

const defaultProps = {
    ruleId: 'uuid1',
};

const routeLeaveOrUpdateTestCases = [
    {
        name: 'force discard changes',
        check: 0,
        from: 'sw.test.route',
        to: 'sw.test.route',
        discard: true,
    },
    {
        name: 'switching from base to assignments tab',
        check: 0,
        from: 'sw.settings.rule.detail.base',
        to: 'sw.settings.rule.detail.assignments',
        discard: false,
    },
    {
        name: 'switch to create tab',
        check: 0,
        from: 'sw.test.route',
        to: 'sw.settings.rule.create.base',
        discard: false,
    },
    {
        name: 'switch to base tab',
        check: 0,
        from: 'sw.test.route',
        to: 'sw.settings.rule.detail.base',
        discard: false,
    },
    {
        name: 'check unsaved data',
        check: 1,
        from: 'sw.test.route',
        to: 'sw.test.route',
        discard: false,
    },
];

async function createWrapper(props = defaultProps, provide = {}) {
    delete config.global.mocks.$router;
    delete config.global.mocks.$route;

    const router = createRouter({
        history: createWebHistory(),
        routes: [
            {
                name: 'sw.settings.rule.index',
                component: { template: '' },
                path: '/sw/settings/rule/index',
            },
            {
                name: 'sw.settings.rule.detail',
                component: { template: '' },
                path: '/sw/settings/rule/detail/:id',
                redirect: {
                    name: 'sw.settings.rule.detail.base',
                },
            },
            {
                name: 'sw.settings.rule.detail.base',
                component: await wrapTestComponent('sw-settings-rule-detail-base', { sync: true }),
                path: '/sw/settings/rule/detail/:id/base',
            },
            {
                name: 'sw.settings.rule.detail.assignments',
                component: await wrapTestComponent('sw-settings-rule-detail-assignments', { sync: true }),
                path: '/sw/settings/rule/detail/:id/assignments',
            },
        ],
    });

    await router.push({
        name: 'sw.settings.rule.detail.base',
        params: {
            id: ruleMock.id,
        },
    });

    return mount(await wrapTestComponent('sw-settings-rule-detail', { sync: true }), {
        props,
        global: {
            plugins: [router],
            stubs: {
                'sw-button-process': await wrapTestComponent('sw-button-process'),
                'sw-tabs': await wrapTestComponent('sw-tabs'),
                'sw-tabs-deprecated': await wrapTestComponent('sw-tabs-deprecated', { sync: true }),
                'sw-tabs-item': await wrapTestComponent('sw-tabs-item'),
                'sw-language-switch': await wrapTestComponent('sw-language-switch'),
                'sw-entity-single-select': await wrapTestComponent('sw-entity-single-select'),
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                'sw-select-result': await wrapTestComponent('sw-select-result'),
                'sw-popover': await wrapTestComponent('sw-popover'),
                'sw-popover-deprecated': await wrapTestComponent('sw-popover-deprecated', { sync: true }),
                'sw-discard-changes-modal': await wrapTestComponent('sw-discard-changes-modal'),
                'sw-page': {
                    template: `
                        <div>
                            <slot name="smart-bar-actions"></slot>
                            <slot name="language-switch"></slot>
                            <slot name="content"></slot>
                        </div>
                    `,
                },
                'sw-context-menu': await wrapTestComponent('sw-context-menu'),
                'sw-context-menu-item': await wrapTestComponent('sw-context-menu-item'),
                'sw-context-button': await wrapTestComponent('sw-context-button'),
                'sw-button-group': await wrapTestComponent('sw-button-group'),
                'sw-skeleton': true,
                'sw-card-view': await wrapTestComponent('sw-card-view'),
                'sw-loader': true,
                'sw-product-variant-info': true,
                'sw-highlight-text': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
                'sw-field-error': true,
                'sw-custom-field-set-renderer': true,
                'sw-error-summary': true,
                'sw-condition-tree': true,
                'sw-entity-tag-select': true,
                'sw-multi-select': true,
                'sw-textarea-field': true,
                'sw-extension-component-section': true,
                'sw-text-field': true,
                'sw-card-filter': true,
                'sw-settings-rule-assignment-listing': true,
                'sw-empty-state': true,
                'sw-settings-rule-add-assignment-modal': true,
                'sw-extension-teaser-popover': true,
            },
            provide: {
                ruleConditionDataProviderService: ruleConditionDataProviderServiceMock,
                ruleConditionsConfigApiService: ruleConditionsConfigApiServiceMock,
                repositoryFactory: {
                    create: jest.fn((repository) => {
                        switch (repository) {
                            case 'rule': {
                                return ruleRepositoryMock;
                            }
                            case 'app_script_condition': {
                                return appConditionRepositoryMock;
                            }
                            case 'rule_condition': {
                                return conditionRepositoryMock;
                            }
                            case 'language': {
                                return languageRepositoryMock;
                            }
                            default: {
                                return {
                                    search: () => Promise.resolve([]),
                                };
                            }
                        }
                    }),
                },
                customFieldDataProviderService: {
                    getCustomFieldSets: () => Promise.resolve([]),
                },
                ...provide,
            },
            mocks: {
                $device: {
                    getSystemKey: () => 'TEST',
                    onResize: () => {},
                    removeResizeListener: () => {},
                },
            },
        },
    });
}

describe('src/module/sw-settings-rule/page/sw-settings-rule-detail', () => {
    afterEach(() => {
        jest.clearAllMocks();
    });

    it('provides shortcuts for save and cancel', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.$options.shortcuts.ESCAPE).toBe('onCancel');
        expect(wrapper.vm.$options.shortcuts['SYSTEMKEY+S']).toBe('onSave');
    });

    it.each([
        { name: 'rule exists', rule: ruleMock, title: ruleMock.name },
        { name: 'rule not exists', rule: null, title: '' },
    ])('should return metaInfo: $name', async ({ rule, title }) => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setData({
            rule,
        });

        wrapper.vm.$createTitle = jest.fn(() => 'Title');
        const metaInfo = wrapper.vm.$options.metaInfo.call(wrapper.vm);

        expect(metaInfo.title).toBe('Title');
        expect(wrapper.vm.$createTitle).toHaveBeenNthCalledWith(1, title);
    });

    it('should create rule criteria with association and aggregations', async () => {
        await createWrapper();
        await flushPromises();

        const association = [
            'tags',
            'flowSequences',
        ];

        const aggregations = [
            'personaPromotions',
            'orderPromotions',
            'cartPromotions',
            'promotionDiscounts',
            'promotionSetGroups',
            'shippingMethodPriceCalculations',
            'shippingMethodPrices',
            'productPrices',
            'shippingMethods',
            'paymentMethods',
        ];

        expect(ruleRepositoryMock.search).toHaveBeenCalledTimes(1);
        const call = ruleRepositoryMock.search.mock.calls[0];

        expect(call[0].associations.map((a) => a.association)).toEqual(association);
        expect(call[0].aggregations.map((a) => a.name)).toEqual(aggregations);
    });

    it('should create rule condition repository: $name', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const expectedRepositories = [
            ['app_script_condition'],
            ['rule'],
            [
                ruleMock.conditions.entity,
                ruleMock.conditions.source,
            ],
            ['language'],
        ];

        expect(wrapper.vm.repositoryFactory.create).toHaveBeenCalledTimes(4);
        expect(wrapper.vm.repositoryFactory.create.mock.calls).toEqual(expectedRepositories);
    });

    it.each([
        {
            name: 'warning',
            roles: [],
            message: 'sw-privileges.tooltip.warning',
        },
        { name: 'save', roles: ['rule.editor'], message: 'TEST + S' },
    ])('should create tooltip for save button: $name', async ({ roles, message }) => {
        global.activeAclRoles = roles;

        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-settings-rule-detail__save-action').exists()).toBe(true);
        expect(wrapper.find('.sw-settings-rule-detail__save-action').attributes('tooltip-mock-message')).toBe(message);
    });

    it('should create tooltip for cancel button', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-settings-rule-detail__cancel-action').exists()).toBe(true);
        expect(wrapper.find('.sw-settings-rule-detail__cancel-action').attributes('tooltip-mock-message')).toBe('ESC');
    });

    it('should render tab items', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-settings-rule-detail__tab-item-general').exists()).toBe(true);
        expect(wrapper.find('.sw-settings-rule-detail__tab-item-assignments').exists()).toBe(true);
    });

    it.each([
        { name: 'product association', entity: 'product' },
        { name: 'no product association', entity: 'order' },
    ])('should load entity data and condition config on creation: $name', async ({ entity }) => {
        conditionRepositoryMock.search.mockResolvedValueOnce(
            getCollection(entity, [{ id: 'uuid1' }], defaultAggregations, 10),
        );

        await createWrapper();
        await flushPromises();

        expect(appConditionRepositoryMock.search).toHaveBeenCalledTimes(1);
        expect(ruleConditionsConfigApiServiceMock.load).toHaveBeenCalledTimes(1);
        expect(ruleConditionDataProviderServiceMock.addScriptConditions).toHaveBeenCalledTimes(1);
        expect(ruleRepositoryMock.search).toHaveBeenCalledTimes(1);

        const criteria = new Criteria(2, 25);

        if (entity === 'product') {
            criteria.addAssociation('options.group');
        }

        expect(conditionRepositoryMock.search).toHaveBeenCalledTimes(2);
        expect(conditionRepositoryMock.search.mock.calls[1]).toEqual([
            criteria,
            Context.api,
        ]);
    });

    it.each([
        { name: 'save', fails: false },
        { name: 'save fails', fails: true },
    ])('should save new rule: $name', async ({ fails }) => {
        global.activeAclRoles = ['rule.editor'];

        if (fails) {
            ruleRepositoryMock.save.mockRejectedValueOnce(new Error('Some error'));
        }

        const wrapper = await createWrapper({
            ...defaultProps,
            ruleId: null,
        });
        wrapper.vm.createNotificationError = jest.fn();

        const routerSpy = jest.spyOn(wrapper.vm.$router, 'push');

        await wrapper.setData(conditionTreeMock);
        await flushPromises();

        expect(wrapper.find('.sw-settings-rule-detail__save-action').exists()).toBe(true);
        await wrapper.find('.sw-settings-rule-detail__save-action').trigger('click');
        await flushPromises();

        expect(ruleRepositoryMock.save).toHaveBeenCalledTimes(1);
        expect(routerSpy).toHaveBeenCalledTimes(fails ? 0 : 1);
        expect(wrapper.vm.createNotificationError).toHaveBeenCalledTimes(fails ? 1 : 0);
    });

    it.each([
        { name: 'save', fails: false },
        { name: 'save fails', fails: true },
    ])('should save existing rule: $name', async ({ fails }) => {
        global.activeAclRoles = ['rule.editor'];

        if (fails) {
            ruleRepositoryMock.save.mockRejectedValueOnce(new Error('Some error'));
        }

        const wrapper = await createWrapper();
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.setData(conditionTreeMock);
        await flushPromises();

        expect(wrapper.find('.sw-settings-rule-detail__save-action').exists()).toBe(true);
        await wrapper.find('.sw-settings-rule-detail__save-action').trigger('click');
        await flushPromises();

        expect(ruleRepositoryMock.save).toHaveBeenCalledTimes(1);
        expect(conditionRepositoryMock.sync).toHaveBeenCalledTimes(fails ? 0 : 1);
        expect(wrapper.vm.createNotificationError).toHaveBeenCalledTimes(fails ? 1 : 0);
    });

    it('should update conditions on change and sync', async () => {
        global.activeAclRoles = ['rule.editor'];

        const wrapper = await createWrapper();
        await wrapper.setData(conditionTreeMock);
        await flushPromises();
        await wrapper.vm.$nextTick();

        const conditions = {
            ...conditionTreeMock,
            conditionTree: [
                ...conditionTreeMock.conditionTree,
                {
                    id: 'some-other-condition',
                },
            ],
        };

        // Emitting over the router view doesn't work with compat therefore emitting on the component directly
        const foo = wrapper.findComponent('sw-condition-tree-stub');
        await foo.vm.$emit('conditions-changed', {
            conditions,
            deletedIds: ['some-condition'],
        });

        expect(wrapper.vm.conditionTree).toEqual(conditions);
        expect(wrapper.vm.deletedIds).toEqual(['some-condition']);

        expect(wrapper.find('.sw-settings-rule-detail__save-action').exists()).toBe(true);
        await wrapper.find('.sw-settings-rule-detail__save-action').trigger('click');
        await flushPromises();

        expect(conditionRepositoryMock.sync).toHaveBeenCalledTimes(1);
        expect(conditionRepositoryMock.syncDeleted).toHaveBeenCalledTimes(1);
    });

    it.each([
        { name: 'rule changed', abort: false },
        { name: 'rule not changed', abort: true },
    ])('should change language switch', async ({ abort }) => {
        ruleRepositoryMock.hasChanges.mockReturnValueOnce(abort);
        const wrapper = await createWrapper();
        await flushPromises();

        const apiLanguageId = Shopware.Store.get('context').api.languageId;
        expect(Shopware.Store.get('context').api.languageId).not.toBe('uuid1');

        await wrapper.find('.sw-select__selection').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-select-result').trigger('click');
        await flushPromises();

        expect(ruleRepositoryMock.hasChanges).toHaveBeenCalledTimes(1);
        expect(ruleRepositoryMock.search).toHaveBeenCalledTimes(abort ? 1 : 2);
        expect(Shopware.Store.get('context').api.languageId).toBe(abort ? apiLanguageId : 'uuid1');

        // cleanup
        Shopware.Store.get('context').api.languageId = apiLanguageId;
    });

    it('should save language switch', async () => {
        ruleRepositoryMock.hasChanges.mockReturnValueOnce(true);
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-select__selection').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-select-result').trigger('click');
        await flushPromises();

        expect(wrapper.find('#sw-language-switch-save-changes-button').exists()).toBe(true);
        await wrapper.find('#sw-language-switch-save-changes-button').trigger('click');

        expect(ruleRepositoryMock.save).toHaveBeenCalledTimes(1);
    });

    it('should cancel rule edit', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const routerSpy = jest.spyOn(wrapper.vm.$router, 'push');

        await wrapper.find('.sw-settings-rule-detail__cancel-action').trigger('click');

        expect(routerSpy).toHaveBeenNthCalledWith(1, {
            name: 'sw.settings.rule.index',
        });
    });

    it('should clone duplicate rule', async () => {
        global.activeAclRoles = [
            'rule.editor',
            'rule.creator',
        ];

        const wrapper = await createWrapper();
        await wrapper.setData(conditionTreeMock);
        await flushPromises();

        const routerSpy = jest.spyOn(wrapper.vm.$router, 'push');

        await wrapper.find('.sw-settings-rule-detail__button-context-menu').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-settings-rule-detail__save-duplicate-action').trigger('click');
        await flushPromises();

        expect(ruleRepositoryMock.save).toHaveBeenCalledTimes(1);
        expect(ruleRepositoryMock.clone).toHaveBeenCalledTimes(1);
        expect(routerSpy).toHaveBeenNthCalledWith(1, {
            name: 'sw.settings.rule.detail',
            params: { id: 'duplicated-rule-id' },
        });
    });

    it('should reload rule when switching from assignments to base tab', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-settings-rule-detail-base').exists()).toBe(true);

        await wrapper.find('.sw-settings-rule-detail__tab-item-assignments').trigger('click');
        await flushPromises();
        expect(wrapper.find('.sw-settings-rule-detail-assignments').exists()).toBe(true);

        await wrapper.find('.sw-settings-rule-detail__tab-item-general').trigger('click');
        await flushPromises();
        expect(wrapper.find('.sw-settings-rule-detail-base').exists()).toBe(true);

        expect(ruleRepositoryMock.search).toHaveBeenCalledTimes(2);
    });

    it.each(routeLeaveOrUpdateTestCases)(
        'should check for unsaved data when route updates: $name',
        async ({ from, to, discard, check }) => {
            const wrapper = await createWrapper();
            await wrapper.setData({
                forceDiscardChanges: discard,
            });
            await flushPromises();

            const nextMock = jest.fn();

            wrapper.vm.$options.beforeRouteUpdate.call(
                wrapper.vm,
                { name: to, params: { id: 'uuid2' } },
                { name: from, params: { id: 'uuid1' } },
                nextMock,
            );
            await flushPromises();

            expect(nextMock).toHaveBeenCalledTimes(1);
            expect(ruleRepositoryMock.hasChanges).toHaveBeenCalledTimes(check);
        },
    );

    it.each(routeLeaveOrUpdateTestCases)(
        'should check for unsaved data when leaving route: $name',
        async ({ from, to, discard, check }) => {
            const wrapper = await createWrapper();
            await wrapper.setData({
                forceDiscardChanges: discard,
            });
            await flushPromises();

            const nextMock = jest.fn();

            wrapper.vm.$options.beforeRouteLeave.call(
                wrapper.vm,
                { name: to, params: { id: 'uuid2' } },
                { name: from, params: { id: 'uuid1' } },
                nextMock,
            );
            await flushPromises();

            expect(nextMock).toHaveBeenCalledTimes(1);
            expect(ruleRepositoryMock.hasChanges).toHaveBeenCalledTimes(check);
        },
    );

    it.each([
        {
            name: 'no changes',
            ruleHasChanges: false,
            containsUserChanges: false,
            openModal: false,
            nextArgs: [],
        },
        {
            name: 'rule changes',
            ruleHasChanges: true,
            containsUserChanges: false,
            openModal: true,
            nextArgs: [false],
        },
        {
            name: 'condition changes',
            ruleHasChanges: false,
            containsUserChanges: true,
            openModal: true,
            nextArgs: [false],
        },
        {
            name: 'rule and condition changes',
            ruleHasChanges: true,
            containsUserChanges: true,
            openModal: true,
            nextArgs: [false],
        },
    ])('should check for unsaved data: $name', async ({ ruleHasChanges, containsUserChanges, openModal, nextArgs }) => {
        ruleRepositoryMock.hasChanges.mockReturnValueOnce(ruleHasChanges);

        const wrapper = await createWrapper();
        await flushPromises();

        if (containsUserChanges) {
            await wrapper.setData(conditionTreeMock);
        }

        const nextMock = jest.fn();

        wrapper.vm.checkUnsavedData({
            to: { name: 'sw.test.route', params: { id: 'uuid2' } },
            next: nextMock,
        });
        await flushPromises();

        expect(nextMock).toHaveBeenNthCalledWith(1, ...nextArgs);
        expect(wrapper.find('.sw-discard-changes-modal-delete-text').exists()).toBe(openModal);
    });

    it('should cancel discard confirm modal', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setData({
            isDisplayingSaveChangesWarning: true,
        });
        await flushPromises();

        expect(wrapper.find('.sw-modal').exists()).toBe(true);
        expect(wrapper.find('.sw-discard-changes-modal-delete-text').exists()).toBe(true);

        await wrapper.findByText('button', 'sw-discard-changes-modal.actions.keepEditing').trigger('click');
        expect(wrapper.find('.sw-discard-changes-modal-delete-text').exists()).toBe(false);
    });

    it.each([
        {
            name: 'switch to assignments tab',
            to: 'sw.settings.rule.detail.assignments',
            loadCalls: 2,
        },
        {
            name: 'switch to base tab',
            to: 'sw.settings.rule.detail.base',
            loadCalls: 1,
        },
    ])('should confirm discard changes', async ({ to, loadCalls }) => {
        const wrapper = await createWrapper();
        await flushPromises();

        const nextRoute = {
            name: to,
            params: { id: 'uuid1' },
        };

        await wrapper.setData({
            isDisplayingSaveChangesWarning: true,
            nextRoute,
        });
        await flushPromises();

        const routerSpy = jest.spyOn(wrapper.vm.$router, 'push');

        expect(wrapper.find('.sw-modal').exists()).toBe(true);
        expect(wrapper.find('.sw-discard-changes-modal-delete-text').exists()).toBe(true);

        await wrapper.findByText('button', 'sw-discard-changes-modal.actions.discard').trigger('click');
        await flushPromises();

        expect(routerSpy).toHaveBeenNthCalledWith(1, nextRoute);
        expect(ruleRepositoryMock.search).toHaveBeenCalledTimes(loadCalls);
    });

    it('should have disabled fields', async () => {
        global.activeAclRoles = [];

        const wrapper = await createWrapper();
        await flushPromises();

        const buttonSave = wrapper.getComponent('.sw-settings-rule-detail__save-action');

        expect(buttonSave.attributes('disabled')).toBe('');
    });

    it('should have enabled fields', async () => {
        global.activeAclRoles = ['rule.editor'];

        const wrapper = await createWrapper();
        await flushPromises();

        const buttonSave = wrapper.get('.sw-settings-rule-detail__save-action');

        expect(buttonSave.attributes().disabled).toBeUndefined();
    });

    it('should render tabs in existing rule', async () => {
        global.activeAclRoles = ['rule.editor'];

        const wrapper = await createWrapper();

        await flushPromises();

        expect(wrapper.get('.sw-settings-rule-detail__tabs').exists()).toBeTruthy();
    });

    it('should not render tabs in new rule', async () => {
        global.activeAclRoles = ['rule.editor'];

        const wrapper = await createWrapper({
            ...defaultProps,
            ruleId: null,
        });

        await flushPromises();

        expect(wrapper.find('.sw-settings-rule-detail__tabs').exists()).toBeFalsy();
    });

    it('should prevent the user from saving the rule when rule awareness is violated', async () => {
        global.activeAclRoles = ['rule.editor'];

        const wrapper = await createWrapper(defaultProps, {
            ruleConditionDataProviderService: {
                getModuleTypes: () => [],
                addScriptConditions: () => {},
                getAwarenessKeysWithEqualsAnyConfig: () => ['someRuleRelation'],
                getRestrictionsByAssociation: () => ({
                    isRestricted: true,
                }),
                getTranslatedConditionViolationList: () => ['someSnippetPath'],
            },
        });

        await wrapper.setData(conditionTreeMock);
        await flushPromises();

        const saveButton = wrapper.get('.sw-settings-rule-detail__save-action');
        await saveButton.trigger('click');

        expect(wrapper.vm.ruleRepository.save).toHaveBeenCalledTimes(0);
    });

    it('should save without any awareness config', async () => {
        global.activeAclRoles = ['rule.editor'];

        const wrapper = await createWrapper(defaultProps, {
            ruleConditionDataProviderService: {
                getModuleTypes: () => [],
                addScriptConditions: () => {},
                getAwarenessKeysWithEqualsAnyConfig: () => [],
            },
        });

        await wrapper.setData(conditionTreeMock);
        await flushPromises();

        const saveButton = wrapper.get('.sw-settings-rule-detail__save-action');
        await saveButton.trigger('click');

        expect(wrapper.vm.ruleRepository.save).toHaveBeenCalledTimes(1);
    });

    it('should trigger rule awareness by association count', async () => {
        global.activeAclRoles = ['rule.editor'];
        defaultAggregations.testRelation.buckets[0].testRelation.count = 1;

        const awarenessFunc = jest.fn(() => ({
            isRestricted: false,
        }));

        const wrapper = await createWrapper(defaultProps, {
            ruleConditionDataProviderService: {
                getModuleTypes: () => [],
                addScriptConditions: () => {},
                getRestrictionsByAssociation: awarenessFunc,
                getAwarenessKeysWithEqualsAnyConfig: () => [
                    'testRelation',
                ],
            },
        });

        await wrapper.setData(conditionTreeMock);
        await flushPromises();

        const saveButton = wrapper.get('.sw-settings-rule-detail__save-action');
        await saveButton.trigger('click');

        expect(awarenessFunc).toHaveBeenCalledTimes(1);
    });

    it('should not trigger rule awareness by association count when no associations exist', async () => {
        global.activeAclRoles = ['rule.editor'];
        defaultAggregations.testRelation.buckets[0].testRelation.count = 0;

        const awarenessFunc = jest.fn(() => ({
            isRestricted: false,
        }));

        const wrapper = await createWrapper(defaultProps, {
            ruleConditionDataProviderService: {
                getModuleTypes: () => [],
                addScriptConditions: () => {},
                getRestrictionsByAssociation: awarenessFunc,
                getAwarenessKeysWithEqualsAnyConfig: () => [
                    'testRelation',
                ],
            },
        });

        await wrapper.setData(conditionTreeMock);
        await flushPromises();

        const saveButton = wrapper.get('.sw-settings-rule-detail__save-action');
        await saveButton.trigger('click');

        expect(awarenessFunc).toHaveBeenCalledTimes(0);
    });

    it('should not trigger rule awareness when rule is new and the entityCount was not generated', async () => {
        global.activeAclRoles = ['rule.editor'];

        const wrapper = await createWrapper(defaultProps, {
            ruleConditionDataProviderService: {
                getModuleTypes: () => [],
                addScriptConditions: () => {},
                getRestrictionsByAssociation: jest.fn(),
                getAwarenessKeysWithEqualsAnyConfig: () => [
                    'testRelation',
                ],
            },
        });
        await flushPromises();

        await wrapper.setData({
            entityCount: null,
            ...conditionTreeMock,
        });

        await flushPromises();
        expect(wrapper.vm.entityCount).toBeNull();

        wrapper.vm.getChildrenConditions = jest.fn(() => []);

        const saveButton = wrapper.get('.sw-settings-rule-detail__save-action');
        await saveButton.trigger('click');

        expect(wrapper.vm.getChildrenConditions).toHaveBeenCalledTimes(0);
    });

    it('should return conditions including nested conditions', async () => {
        const conditionTree = [
            { id: 1, children: [{ id: 2, children: [] }] },
            { id: 3, children: [{ id: 4, children: [{ id: 5, children: [] }] }] },
        ];
        const expectedFlatConditions = [
            { id: 1, children: [{ id: 2, children: [] }] },
            { id: 2, children: [] },
            { id: 3, children: [{ id: 4, children: [{ id: 5, children: [] }] }] },
            { id: 4, children: [{ id: 5, children: [] }] },
            { id: 5, children: [] },
        ];

        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setData({ conditionTree });
        expect(wrapper.vm.conditionTreeFlat).toEqual(expectedFlatConditions);
    });

    it('should validate date ranges successfully', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const conditionTreeWithDateRanges = {
            conditionTree: [
                {
                    id: 'date-range-condition',
                    type: 'dateRange',
                    value: { fromDate: '2023-01-01', toDate: '2023-12-31' },
                    children: [],
                },
            ],
        };
        await wrapper.setData(conditionTreeWithDateRanges);
        const isValid = wrapper.vm.validateDateRange();

        expect(isValid).toBe(true);
    });

    it('should invalidate incorrect date ranges', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const conditionTreeWithInvalidDateRanges = {
            conditionTree: [
                {
                    id: 'date-range-condition',
                    type: 'dateRange',
                    value: { fromDate: '2023-12-31', toDate: '2023-01-01' },
                    children: [],
                },
            ],
        };
        await wrapper.setData(conditionTreeWithInvalidDateRanges);
        const isValid = wrapper.vm.validateDateRange();

        expect(isValid).toBe(false);
    });

    it('should save rule with valid date ranges', async () => {
        global.activeAclRoles = ['rule.editor'];

        const wrapper = await createWrapper();
        await flushPromises();

        const conditionTreeWithDateRanges = {
            conditionTree: [
                {
                    id: 'date-range-condition',
                    type: 'dateRange',
                    value: { fromDate: '2023-01-01', toDate: '2023-12-31' },
                    children: [],
                },
            ],
        };
        await wrapper.setData(conditionTreeWithDateRanges);
        wrapper.vm.createNotificationError = jest.fn();

        expect(wrapper.find('.sw-settings-rule-detail__save-action').exists()).toBe(true);
        await wrapper.find('.sw-settings-rule-detail__save-action').trigger('click');
        await flushPromises();

        expect(wrapper.vm.ruleRepository.save).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.createNotificationError).toHaveBeenCalledTimes(0);
    });

    it('should not save rule with invalid date ranges', async () => {
        global.activeAclRoles = ['rule.editor'];

        const wrapper = await createWrapper();
        await flushPromises();

        const conditionTreeWithInvalidDateRanges = {
            conditionTree: [
                {
                    id: 'date-range-condition',
                    type: 'dateRange',
                    value: { fromDate: '2023-12-31', toDate: '2023-01-01' },
                    children: [],
                },
            ],
        };
        await wrapper.setData({
            ...conditionTreeWithInvalidDateRanges,
            conditions: [
                { id: 'some-id' },
                { id: 'another-id' },
                { id: 'date-range-condition' },
            ],
        });
        wrapper.vm.createNotificationError = jest.fn();

        const saveButton = wrapper.get('.sw-settings-rule-detail__save-action');
        await saveButton.trigger('click');
        await flushPromises();

        expect(wrapper.vm.ruleRepository.save).toHaveBeenCalledTimes(0);
        expect(wrapper.vm.createNotificationError).toHaveBeenCalledTimes(1);
    });
});
