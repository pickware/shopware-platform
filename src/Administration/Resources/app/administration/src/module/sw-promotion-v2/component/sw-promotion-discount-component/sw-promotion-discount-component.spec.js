/**
 * @sw-package checkout
 */
import { mount } from '@vue/test-utils';

const { Criteria, EntityCollection } = Shopware.Data;

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-promotion-discount-component', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-container': {
                        template: '<div class="sw-container"><slot></slot></div>',
                    },
                    'sw-select-field': {
                        template:
                            '<select class="sw-field sw-select-field" :value="value" @change="$emit(\'update:value\', $event.target.value)"><slot></slot></select>',
                        props: [
                            'value',
                            'disabled',
                        ],
                    },
                    'sw-select-rule-create': {
                        template: '<div class="sw-select-rule-create"></div>',
                    },
                    'sw-loader': {
                        template: '<div class="sw-loader"></div>',
                    },
                    'mt-card': {
                        template: '<div class="mt-card"><slot name="headerRight"></slot><slot></slot></div>',
                    },
                    'sw-context-button': {
                        template: '<div class="sw-context-button"><slot></slot></div>',
                    },
                    'sw-context-menu-item': {
                        template: '<div class="sw-context-menu-item"><slot></slot></div>',
                        props: ['disabled'],
                    },
                    'sw-modal': {
                        template: '<div class="sw-modal"><slot></slot><slot name="footer"></slot></div>',
                    },
                    'sw-one-to-many-grid': {
                        template: '<div class="sw-one-to-many-grid"></div>',
                    },
                },
                provide: {
                    repositoryFactory: {
                        create: (entity) => {
                            if (entity === 'currency') {
                                return {
                                    search: () =>
                                        Promise.resolve(
                                            new EntityCollection('', 'currency', Shopware.Context.api, new Criteria(1, 25), [
                                                {
                                                    id: 'currencyId',
                                                    isSystemDefault: true,
                                                    factor: 3,
                                                },
                                            ]),
                                        ),
                                };
                            }

                            return {
                                search: () => Promise.resolve([{ id: 'promotionId1' }]),
                                create: () => ({}),
                            };
                        },
                    },

                    ruleConditionDataProviderService: {
                        getAwarenessConfigurationByAssignmentName: () => ({
                            snippet: 'fooBar',
                        }),
                        getRestrictedRules: () => Promise.resolve([]),
                    },
                },
            },
            props: {
                promotion: {
                    name: 'Test Promotion',
                    active: true,
                    validFrom: '2020-07-28T12:00:00.000+00:00',
                    validUntil: '2020-08-11T12:00:00.000+00:00',
                    maxRedemptionsGlobal: 45,
                    maxRedemptionsPerCustomer: 12,
                    exclusive: false,
                    code: null,
                    useCodes: true,
                    useIndividualCodes: true,
                    individualCodePattern: 'code-%d',
                    useSetGroups: false,
                    customerRestriction: true,
                    orderCount: 0,
                    ordersPerCustomerCount: null,
                    exclusionIds: ['d671d6d3efc74d2a8b977e3be3cd69c7'],
                    translated: {
                        name: 'Test Promotion',
                    },
                    apiAlias: null,
                    id: 'promotionId',
                    setgroups: [],
                    salesChannels: [
                        {
                            promotionId: 'promotionId',
                            salesChannelId: 'salesChannelId',
                            priority: 1,
                            createdAt: '2020-08-17T13:24:52.692+00:00',
                            id: 'promotionSalesChannelId',
                        },
                    ],
                    discounts: [],
                    individualCodes: [],
                    personaRules: new EntityCollection('', 'rule', Shopware.Context.api, new Criteria(1, 25)),
                    personaCustomers: [],
                    orderRules: new EntityCollection('', 'rule', Shopware.Context.api, new Criteria(1, 25)),
                    cartRules: new EntityCollection('', 'rule', Shopware.Context.api, new Criteria(1, 25)),
                    translations: [],
                    hasOrders: false,
                },
                discount: {
                    isNew: () => false,
                    promotionId: 'promotionId',
                    scope: 'cart',
                    type: 'absolute',
                    value: 100,
                    considerAdvancedRules: false,
                    maxValue: null,
                    sorterKey: 'PRICE_ASC',
                    applierKey: 'ALL',
                    usageKey: 'ALL',
                    apiAlias: null,
                    id: 'discountId',
                    discountRules: new EntityCollection('', 'rule', Shopware.Context.api, new Criteria(1, 25)),
                    promotionDiscountPrices: new EntityCollection(
                        '',
                        'promotion_discount_prices',
                        Shopware.Context.api,
                        new Criteria(1, 25),
                    ),
                },
            },
        },
    );
}

describe('src/module/sw-promotion-v2/component/sw-promotion-discount-component', () => {
    beforeAll(() => {
        Shopware.Service().register('syncService', () => {
            return {
                httpClient: {
                    get() {
                        return Promise.resolve([{}]);
                    },
                },
                getBasicHeaders() {
                    return {};
                },
            };
        });
    });

    it('should have disabled form fields', async () => {
        global.activeAclRoles = [];

        const wrapper = await createWrapper();

        expect(wrapper.vm.isEditingDisabled).toBe(true);

        let elements = wrapper.findAllComponents('.mt-field');
        expect(elements.length).toBeGreaterThan(0);
        elements.forEach((el) => expect(el.props('disabled')).toBe(true));

        elements = wrapper.findAllComponents('.sw-context-menu-item');
        expect(elements.length).toBeGreaterThan(0);
        elements.forEach((el) => expect(el.props('disabled')).toBe(true));
    });

    it('should not have disabled form fields', async () => {
        global.activeAclRoles = ['promotion.editor'];

        const wrapper = await createWrapper();

        expect(wrapper.vm.isEditingDisabled).toBe(false);

        let elements = wrapper.findAllComponents('.mt-field');
        expect(elements.length).toBeGreaterThan(0);
        elements.forEach((el) => expect(el.props('disabled')).toBe(false));

        elements = wrapper.findAllComponents('.sw-context-menu-item');
        expect(elements.length).toBeGreaterThan(0);
        elements.forEach((el) => expect(el.props('disabled')).toBe(false));
    });

    it('should show product rule selection, if considerAdvancedRules switch is checked', async () => {
        global.activeAclRoles = ['promotion.editor'];

        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-promotion-discount-component__select-discount-rules').exists()).toBeFalsy();
        await wrapper
            .find('.mt-switch input[aria-label="sw-promotion.detail.main.discounts.flagProductScopeLabel"]')
            .setChecked(true);
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-promotion-discount-component__select-discount-rules').exists()).toBeTruthy();
    });

    it('should create advanced prices and recalculate advanced prices when value changes', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.discount.value = 2;
        wrapper.vm.onClickAdvancedPrices();

        expect(wrapper.vm.discount.promotionDiscountPrices).toHaveLength(1);
        expect(wrapper.vm.discount.promotionDiscountPrices[0].currencyId).toBe('currencyId');
        expect(wrapper.vm.discount.promotionDiscountPrices[0].price).toBe(6);

        wrapper.vm.discount.value = 3;
        wrapper.vm.recalculatePrices();

        expect(wrapper.vm.discount.promotionDiscountPrices).toHaveLength(1);
        expect(wrapper.vm.discount.promotionDiscountPrices[0].currencyId).toBe('currencyId');
        expect(wrapper.vm.discount.promotionDiscountPrices[0].price).toBe(9);
    });
});
