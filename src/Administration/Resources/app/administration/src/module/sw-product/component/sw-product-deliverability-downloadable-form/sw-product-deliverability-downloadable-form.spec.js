/* eslint-disable max-len */
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';

/**
 * @sw-package inventory
 */

describe('module/sw-product/component/sw-product-deliverability-downloadable-form', () => {
    async function createWrapper(productEntityOverride, parentProductOverride) {
        const productEntity = {
            metaTitle: 'Product1',
            id: 'productId1',
            isCloseout: false,
            ...productEntityOverride,
        };

        const parentProduct = {
            id: 'productId',
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
                        key: 'deliverability',
                        label: 'sw-product.detailBase.cardTitleDeliverabilityInfo',
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
        store.creationStates = 'is-physical';

        return mount(await wrapTestComponent('sw-product-deliverability-downloadable-form', { sync: true }), {
            global: {
                mocks: {
                    $route: {
                        name: 'sw.product.detail.base',
                        params: {
                            id: 1,
                        },
                    },
                },
                provide: {
                    validationService: {},
                },
                stubs: {
                    'sw-container': {
                        template: '<div><slot></slot></div>',
                    },
                    'sw-inherit-wrapper': await wrapTestComponent('sw-inherit-wrapper'),
                    'sw-entity-single-select': true,
                    'sw-inheritance-switch': true,
                    'sw-field-error': true,
                    'sw-text-field': await wrapTestComponent('sw-text-field'),
                    'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),

                    'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field'),
                    'sw-checkbox-field-deprecated': await wrapTestComponent('sw-checkbox-field-deprecated', { sync: true }),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-help-text': true,
                    'sw-field-copyable': true,
                    'sw-ai-copilot-badge': true,
                },
            },
        });
    }

    let wrapper;

    it('should show Deliverability item fields when advanced mode is on', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const deliveryFieldsClassName = [
            '.product-deliverability-downloadable-form__delivery-time',
        ];

        deliveryFieldsClassName.forEach((item) => {
            expect(wrapper.find(item).exists()).toBe(true);
        });
    });

    it('should hide Deliverability item fields when advanced mode is off', async () => {
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

        const deliveryFieldsClassName = [
            '.product-deliverability-downloadable-form__delivery-time',
        ];

        await nextTick();

        deliveryFieldsClassName.forEach((item) => {
            expect(wrapper.find(item).exists()).toBeFalsy();
        });
    });

    it('should pre-fill stock value', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('input[name="sw-field--product-stock"]').element.value).toBe('0');
    });

    it('should set stock to before value if stock was not saved and isCloseout is set to false', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const isCloseoutSwitch = wrapper.find('input[name="sw-field--product-is-closeout"]');
        await isCloseoutSwitch.setChecked(true);

        const stockElement = wrapper.find('input[name="sw-field--product-stock"]');
        await stockElement.setValue('5');

        await isCloseoutSwitch.setChecked(false);
        await wrapper.vm.$nextTick();

        expect(stockElement.element.value).toBe('0');
    });

    it('should set stock to persisted product stock if stock was saved and stock deliverability menu is reopened', async () => {
        wrapper = await createWrapper({
            stock: 10,
        });
        await flushPromises();

        const isCloseoutSwitch = wrapper.find('input[name="sw-field--product-is-closeout"]');
        await isCloseoutSwitch.setChecked(true);

        const stockElement = wrapper.find('input[name="sw-field--product-stock"]');
        expect(stockElement.element.value).toBe('10');

        await stockElement.setValue('20');
        expect(stockElement.element.value).toBe('20');

        await isCloseoutSwitch.setChecked(false);
        await wrapper.vm.$nextTick();

        await isCloseoutSwitch.setChecked(true);
        await wrapper.vm.$nextTick();

        expect(stockElement.element.value).toBe('10');
    });
});
