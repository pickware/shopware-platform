/**
 * @sw-package inventory
 */

import { mount } from '@vue/test-utils';

const { EntityCollection } = Shopware.Data;

const createWrapper = async () => {
    return mount(
        await wrapTestComponent('sw-product-detail-context-prices', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-container': await wrapTestComponent('sw-container'),
                    'sw-loader': true,
                    'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field'),
                    'sw-checkbox-field-deprecated': await wrapTestComponent('sw-checkbox-field-deprecated', { sync: true }),
                    'sw-inheritance-switch': true,
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-field-error': true,
                    'sw-data-grid': await wrapTestComponent('sw-data-grid'),
                    'sw-data-grid-settings': true,
                    'sw-field': true,
                    'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                    'sw-context-button': true,
                    'sw-context-menu-item': true,
                    'sw-list-price-field': await wrapTestComponent('sw-list-price-field'),
                    'sw-price-field': await wrapTestComponent('sw-price-field'),
                    'sw-entity-single-select': await wrapTestComponent('sw-entity-single-select'),
                    'sw-select-base': await wrapTestComponent('sw-select-base'),
                    'sw-skeleton': true,
                    'sw-select-rule-create': true,
                    'sw-help-text': true,
                    'sw-ai-copilot-badge': true,
                    'router-link': true,
                    'sw-data-grid-inline-edit': true,
                    'sw-extension-component-section': true,
                    'sw-data-grid-column-boolean': true,
                    'sw-data-grid-skeleton': true,
                    'sw-field-copyable': true,
                    'sw-maintain-currencies-modal': true,
                    'sw-provide': { template: `<slot/>`, inheritAttrs: false },
                },
                provide: {
                    repositoryFactory: {
                        create: (repositoryName) => {
                            if (repositoryName === 'rule') {
                                const rules = [
                                    {
                                        id: 'ruleId',
                                        name: 'ruleName',
                                    },
                                ];
                                rules.total = rules.length;

                                return {
                                    search: () => Promise.resolve(rules),
                                    get: () => Promise.resolve(rules),
                                };
                            }

                            if (repositoryName === 'product_price') {
                                return {
                                    create: () => ({
                                        search: () => Promise.resolve(),
                                    }),
                                };
                            }

                            return {};
                        },
                    },
                    validationService: {},
                },
            },
        },
    );
};

describe('src/module/sw-product/view/sw-product-detail-context-prices', () => {
    /** @type Wrapper */
    let wrapper;

    beforeEach(() => {
        Shopware.Store.get('swProductDetail').$reset();

        if (Shopware.Store.get('context')) {
            Shopware.Store.unregister('context');
        }
        Shopware.Store.register({
            id: 'context',

            getters: {
                isSystemDefaultLanguage() {
                    return true;
                },
            },

            state: () => ({
                api: {
                    assetsPath: '/',
                },
            }),
        });
    });

    it('should show inherited state when product is a variant', async () => {
        Shopware.Store.get('swProductDetail').product = {
            id: 'productId',
            parentId: 'parentProductId',
            prices: [],
        };
        Shopware.Store.get('swProductDetail').parentProduct = {
            id: 'parentProductId',
        };

        wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.isChild).toBe(true);
        expect(wrapper.vm.isInherited).toBe(true);
    });

    it('should show empty state for main product', async () => {
        Shopware.Store.get('swProductDetail').product = {
            id: 'productId',
            parentId: null,
            prices: [],
        };
        Shopware.Store.get('swProductDetail').parentProduct = {
            id: 'parentProductId',
        };

        wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.isChild).toBe(false);
        expect(wrapper.vm.isInherited).toBe(false);
    });

    it('first start quantity input should be disabled', async () => {
        Shopware.Store.get('swProductDetail').product = {
            id: 'productId',
            parentId: 'parentProductId',
            prices: [
                {
                    ruleId: 'ruleId',
                    quantityStart: 1,
                    quantityEnd: 4,
                },
            ],
        };
        Shopware.Store.get('swProductDetail').parentProduct = {
            id: 'parentProductId',
        };

        wrapper = await createWrapper();
        await flushPromises();

        // get first quantity field
        const firstQuantityField = wrapper.find('.sw-data-grid__row--0 input[name="ruleId-1-quantityStart"]');

        // check if input field has a value of 1 and is disabled
        expect(firstQuantityField.element.value).toBe('1');
        expect(firstQuantityField.attributes('disabled')).toBeDefined();
    });

    it('second start quantity input should not be disabled', async () => {
        global.activeAclRoles = ['product.editor'];

        Shopware.Store.get('swProductDetail').product = {
            id: 'productId',
            parentId: null,
            prices: [
                {
                    ruleId: 'ruleId',
                    quantityStart: 1,
                    quantityEnd: 4,
                },
                {
                    ruleId: 'ruleId',
                    quantityStart: 5,
                    quantityEnd: null,
                },
            ],
        };
        Shopware.Store.get('swProductDetail').parentProduct = {
            id: 'parentProductId',
        };

        wrapper = await createWrapper();
        await flushPromises();

        // get second quantity field
        const secondQuantityField = wrapper.find('.sw-data-grid__row--1 input[name="ruleId-5-quantityStart"]');

        // check if input field has a value of 5 and is not disabled
        expect(secondQuantityField.element.value).toBe('5');
        expect(secondQuantityField.attributes('disabled')).toBeUndefined();
    });

    it('should show default price', async () => {
        const entities = [
            {
                ruleId: 'rule1',
                quantityStart: 1,
                quantityEnd: 4,
                price: [
                    {
                        currencyId: 'euro',
                        gross: 1,
                        linked: false,
                        net: 1,
                        listPrice: null,
                    },
                ],
            },
        ];

        Shopware.Store.get('swProductDetail').product = {
            id: 'productId',
            parentId: null,
            prices: new EntityCollection(
                '/test-price',
                'product_price',
                null,
                { isShopwareContext: true },
                entities,
                entities.length,
                null,
            ),
        };

        Shopware.Store.get('swProductDetail').parentProduct = {
            id: 'parentProductId',
        };

        Shopware.Store.get('swProductDetail').currencies = [
            {
                id: 'euro',
                translated: { name: 'Euro' },
                isSystemDefault: true,
                isoCode: 'EUR',
            },
        ];

        wrapper = await createWrapper();
        const rulesEntities = [
            {
                id: 'rule1',
                name: 'customers',
            },
            {
                id: 'rule2',
                name: 'products',
            },
        ];

        await wrapper.setData({
            rules: new EntityCollection(
                '/test-rule',
                'rule',
                null,
                { isShopwareContext: true },
                rulesEntities,
                rulesEntities.length,
                null,
            ),
        });

        await wrapper.setProps({
            isSetDefaultPrice: true,
        });

        wrapper.vm.$parent.$el.children.item(0).scrollTo = () => {};

        await flushPromises();

        const firstPriceFieldGross = wrapper.find(
            '.context-price-group-0 .sw-data-grid__row--0 .sw-data-grid__cell--price-EUR .sw-list-price-field__price input[name="sw-price-field-gross"]',
        );
        expect(firstPriceFieldGross.element.value).toBe('1');

        await wrapper.vm.onAddNewPriceGroup('rule2');
        await flushPromises();

        const secondPriceFieldGross = wrapper.find(
            '.context-price-group-1 .sw-data-grid__row--0 .sw-data-grid__cell--price-EUR .sw-list-price-field__price input[name="sw-price-field-gross"]',
        );
        expect(secondPriceFieldGross.element.value).toBe('0');
    });
});
