/**
 * @sw-package inventory
 */

import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';

const parentProductData = {
    id: 'productId',
    purchasePrices: [
        {
            currencyId: '1',
            linked: true,
            gross: 0,
            net: 0,
        },
    ],
    price: [
        {
            currencyId: '1',
            linked: true,
            gross: 100,
            net: 84.034,
            listPrice: {
                currencyId: '1',
                linked: true,
                gross: 200,
                net: 168.07,
            },
            regulationPrice: {
                currencyId: '1',
                linked: true,
                gross: 100,
                net: 93.45,
            },
        },
    ],
};

describe('module/sw-product/component/sw-product-price-form', () => {
    async function createWrapper(productEntityOverride, parentProductOverride) {
        const productEntity = {
            metaTitle: 'Product1',
            id: 'productId1',
            taxId: 'taxId',
            purchasePrices: null,
            price: null,
            ...productEntityOverride,
        };

        const parentProduct = {
            ...parentProductData,
            ...parentProductOverride,
        };

        const store = Shopware.Store.get('swProductDetail');
        store.$reset();
        store.product = productEntity;
        store.parentProduct = parentProduct;
        store.advancedModeSetting = {
            value: {
                settings: [
                    {
                        key: 'prices',
                        label: 'sw-product.detailBase.cardTitlePrices',
                        enabled: true,
                        name: 'general',
                    },
                ],
                advancedMode: {
                    enabled: true,
                    label: 'sw-product.general.textAdvancedMode',
                },
            },
        };
        store.currencies.push({
            id: '1',
            name: 'Euro',
            isoCode: 'EUR',
            isSystemDefault: true,
        });

        return mount(await wrapTestComponent('sw-product-price-form', { sync: true }), {
            global: {
                mocks: {
                    $route: {
                        name: 'sw.product.detail.base',
                        params: {
                            id: 1,
                        },
                    },
                },
                // eslint-disable max-len
                stubs: {
                    'sw-container': {
                        template: '<div><slot></slot></div>',
                    },
                    'sw-inherit-wrapper': await wrapTestComponent('sw-inherit-wrapper'),
                    'sw-list-price-field': await wrapTestComponent('sw-list-price-field'),
                    'sw-inheritance-switch': {
                        props: [
                            'isInherited',
                            'disabled',
                        ],
                        template: `
                          <div class="sw-inheritance-switch">
                          <div v-if="isInherited"
                               class="sw-inheritance-switch--is-inherited"
                               @click="onClickRemoveInheritance">
                          </div>
                          <div v-else
                               class="sw-inheritance-switch--is-not-inherited"
                               @click="onClickRestoreInheritance">
                          </div>
                          </div>`,
                        methods: {
                            onClickRestoreInheritance() {
                                this.$emit('inheritance-restore');
                            },
                            onClickRemoveInheritance() {
                                this.$emit('inheritance-remove');
                            },
                        },
                    },
                    'sw-price-field': true,
                    'sw-help-text': true,
                    'sw-select-field': true,
                    'sw-internal-link': true,
                    'router-link': true,
                    'sw-maintain-currencies-modal': true,
                },
                // eslint-enable max-len
            },
        });
    }

    /** @type Wrapper */
    let wrapper;

    // eslint-disable-next-line max-len
    it('should disable all price fields and toggle inheritance switch on if product price and purchase price are null', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const priceInheritance = wrapper.find('.sw-product-price-form__price-list');
        const priceSwitchInheritance = priceInheritance.find('.sw-inheritance-switch');
        const priceFields = priceInheritance.findAll('sw-price-field-stub');

        expect(priceSwitchInheritance.find('.sw-inheritance-switch--is-inherited').exists()).toBeTruthy();

        priceFields.forEach((priceField) => {
            expect(priceField.attributes().disabled).toBeTruthy();
        });

        expect(wrapper.vm.prices).toEqual({ price: [], purchasePrices: [] });
    });

    it('should enable all price fields and toggle inheritance switch off if product variant price exists', async () => {
        wrapper = await createWrapper({
            price: [
                {
                    currencyId: '1',
                    linked: true,
                    gross: 80,
                    net: 67.27,
                },
            ],
        });
        await flushPromises();

        const priceInheritance = wrapper.find('.sw-product-price-form__price-list');
        const priceSwitchInheritance = priceInheritance.find('.sw-inheritance-switch');

        expect(priceSwitchInheritance.find('.sw-inheritance-switch--is-not-inherited').exists()).toBeTruthy();

        const priceFields = priceInheritance.findAll('sw-price-field-stub');
        priceFields.forEach((priceField) => {
            expect(priceField.attributes().disabled).toBeFalsy();
        });

        expect(wrapper.vm.prices).toEqual({
            price: [
                {
                    currencyId: '1',
                    linked: true,
                    gross: 80,
                    net: 67.27,
                },
            ],
            purchasePrices: [],
        });
    });

    // eslint-disable-next-line max-len
    it('should enable all price fields and toggle inheritance switch off when user clicks on remove inheritance button', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const priceInheritance = wrapper.find('.sw-product-price-form__price-list');
        const priceSwitchInheritance = priceInheritance.find('.sw-inheritance-switch');

        await priceSwitchInheritance.find('.sw-inheritance-switch--is-inherited').trigger('click');
        await nextTick();

        expect(priceSwitchInheritance.find('.sw-inheritance-switch--is-inherited').exists()).toBeFalsy();
        expect(priceSwitchInheritance.find('.sw-inheritance-switch--is-not-inherited').exists()).toBeTruthy();

        const priceFields = priceInheritance.findAll('sw-price-field-stub');
        priceFields.forEach((priceField) => {
            expect(priceField.attributes().disabled).toBeFalsy();
        });

        expect(wrapper.vm.prices).toEqual({
            price: parentProductData.price,
            purchasePrices: parentProductData.purchasePrices,
        });
        expect(wrapper.vm.prices.price[0].listPrice.gross).toBe(200);
        expect(wrapper.vm.prices.price[0].regulationPrice.gross).toBe(100);
    });

    // eslint-disable-next-line max-len
    it('should enable all price fields and toggle inheritance switch off when user clicks on remove inheritance button (using empty purchasePrices)', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        // remove purchasePrices of parent
        wrapper.vm.parentProduct.purchasePrices = undefined;
        await wrapper.vm.$nextTick();

        const priceInheritance = wrapper.find('.sw-product-price-form__price-list');
        const priceSwitchInheritance = priceInheritance.find('.sw-inheritance-switch');

        await priceSwitchInheritance.find('.sw-inheritance-switch--is-inherited').trigger('click');

        expect(priceSwitchInheritance.find('.sw-inheritance-switch--is-inherited').exists()).toBeFalsy();
        expect(priceSwitchInheritance.find('.sw-inheritance-switch--is-not-inherited').exists()).toBeTruthy();

        const priceFields = priceInheritance.findAll('sw-price-field-stub');
        priceFields.forEach((priceField) => {
            expect(priceField.attributes().disabled).toBeFalsy();
        });

        expect(wrapper.vm.prices).toEqual({
            price: parentProductData.price,
            purchasePrices: [],
        });
    });

    // eslint-disable-next-line max-len
    it('should disable all price fields and toggle inheritance switch on when user clicks on restore inheritance button', async () => {
        wrapper = await createWrapper({
            price: [
                {
                    currencyId: '1',
                    linked: true,
                    gross: 80,
                    net: 67.27,
                },
            ],
        });
        await flushPromises();

        const priceInheritance = wrapper.find('.sw-product-price-form__price-list');
        const priceSwitchInheritance = priceInheritance.find('.sw-inheritance-switch');

        await priceSwitchInheritance.find('.sw-inheritance-switch--is-not-inherited').trigger('click');

        expect(priceSwitchInheritance.find('.sw-inheritance-switch--is-not-inherited').exists()).toBeFalsy();
        expect(priceSwitchInheritance.find('.sw-inheritance-switch--is-inherited').exists()).toBeTruthy();

        const priceFields = priceInheritance.findAll('sw-price-field-stub');
        priceFields.forEach((priceField) => {
            expect(priceField.attributes().disabled).toBeTruthy();
        });

        expect(wrapper.vm.prices).toEqual({ price: [], purchasePrices: [] });
    });

    it('should show price item fields when advanced mode is on', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const priceFieldsClassName = [
            '.sw-purchase-price-field',
            '.sw-list-price-field__list-price sw-price-field-stub',
        ];

        priceFieldsClassName.forEach((item) => {
            expect(wrapper.find(item).exists()).toBeTruthy();
        });
    });

    it('should hide price item fields when advanced mode is off', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const advancedModeSetting = Shopware.Store.get('swProductDetail').advancedModeSetting;

        Shopware.Store.get('swProductDetail').advancedModeSetting = {
            value: {
                ...advancedModeSetting.value,
                advancedMode: {
                    enabled: false,
                    label: 'sw-product.general.textAdvancedMode',
                },
            },
        };

        await nextTick();

        const priceFieldsClassName = [
            '.sw-purchase-price-field',
            '.sw-list-price-field__list-price sw-price-field-stub',
        ];

        priceFieldsClassName.forEach((item) => {
            expect(wrapper.find(item).exists()).toBeFalsy();
        });
    });
});
