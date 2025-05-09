/**
 * @sw-package checkout
 */

import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-order-create-base', { sync: true }), {
        global: {
            stubs: {
                'sw-card-view': await wrapTestComponent('sw-card-view', {
                    sync: true,
                }),
                'mt-card': {
                    template: `
                        <div class="sw-card__content">
                            <slot name="grid"></slot>
                        </div>
                    `,
                },
                'sw-order-user-card': true,
                'sw-container': await wrapTestComponent('sw-container', {
                    sync: true,
                }),
                'sw-order-state-select': true,
                'sw-number-field': await wrapTestComponent('sw-number-field', { sync: true }),
                'sw-card-section': await wrapTestComponent('sw-card-section', { sync: true }),
                'sw-description-list': await wrapTestComponent('sw-description-list', { sync: true }),
                'sw-order-saveable-field': await wrapTestComponent('sw-order-saveable-field', { sync: true }),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-field-copyable': true,
                'sw-field-error': true,
                'sw-help-text': true,
                'sw-ai-copilot-badge': true,
                'sw-inheritance-switch': true,
                'sw-order-state-history-card': true,
                'sw-order-delivery-metadata': true,
                'sw-order-document-card': true,
                'sw-order-create-details-header': true,
                'sw-order-create-details-body': true,
                'sw-order-create-details-footer': true,
                'sw-order-promotion-tag-field': true,
                'sw-order-line-items-grid-sales-channel': true,

                'sw-order-create-address-modal': true,
                'sw-order-create-promotion-modal': true,
                'sw-error-summary': true,
                'sw-loader': true,
                'router-link': true,
                'sw-number-field-deprecated': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            get: () => {},
                        };
                    },
                },
            },
        },
    });
}

describe('src/module/sw-order/view/sw-order-create-base', () => {
    beforeEach(() => {
        Shopware.Store.get('swOrder').$reset();
    });

    it('should be show successful notification', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.createNotificationSuccess = jest.fn();

        Shopware.Store.get('swOrder').setCart({
            token: null,
            lineItems: [],
            errors: {
                'promotion-not-found': {
                    code: 0,
                    key: 'promotion-discount-added-1b8d2c67e3cf435ab3cb64ec394d4339',
                    level: 0,
                    message: 'Discount discount has been added',
                    messageKey: 'promotion-discount-added',
                },
            },
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.createNotificationSuccess).toHaveBeenCalled();

        wrapper.vm.createNotificationSuccess.mockRestore();
    });

    it('should be show error notification', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.createNotificationError = jest.fn();

        Shopware.Store.get('swOrder').setCart({
            token: null,
            lineItems: [],
            errors: {
                'promotion-discount-added-1b8d2c67e3cf435ab3cb64ec394d4339': {
                    code: 'promotion-code',
                    key: 'promotion-discount-added-1b8d2c67e3cf435ab3cb64ec394d4339',
                    level: 20,
                    message: 'Promotion with code promotion-code not found!',
                    messageKey: 'promotion-discount-added-1b8d2c67e3cf435ab3cb64ec394d4339',
                },
            },
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalled();

        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should be show warning notification', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.createNotificationWarning = jest.fn();

        Shopware.Store.get('swOrder').setCart({
            token: null,
            lineItems: [],
            errors: {
                'promotion-warning': {
                    code: 10,
                    key: 'promotion-warning',
                    level: 10,
                    message: 'Promotion with code promotion-code warning!',
                    messageKey: 'promotion-warning',
                },
            },
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.createNotificationWarning).toHaveBeenCalled();

        wrapper.vm.createNotificationWarning.mockRestore();
    });

    it('should only display Total row when status is tax free', async () => {
        const wrapper = await createWrapper();

        Shopware.Store.get('swOrder').setCart({
            token: null,
            lineItems: [],
            price: {
                taxStatus: 'tax-free',
            },
        });

        await wrapper.vm.$nextTick();
        const orderSummary = wrapper.find('.sw-order-create-summary__data');
        expect(orderSummary.html()).not.toContain('sw-order.createBase.summaryLabelAmountWithoutTaxes');
        expect(orderSummary.html()).not.toContain('sw-order.createBase.summaryLabelAmountTotal');
        expect(orderSummary.html()).toContain('sw-order.createBase.summaryLabelAmount');
    });

    it('should display Total excluding VAT and Total including VAT row when tax status is not tax free', async () => {
        const wrapper = await createWrapper();

        Shopware.Store.get('swOrder').setCart({
            token: null,
            lineItems: [],
        });

        await wrapper.vm.$nextTick();

        const orderSummary = wrapper.find('.sw-order-create-summary__data');
        expect(orderSummary.html()).toContain('sw-order.createBase.summaryLabelAmountWithoutTaxes');
        expect(orderSummary.html()).toContain('sw-order.createBase.summaryLabelAmountTotal');
        expect(orderSummary.html()).not.toContain('sw-order.createBase.summaryLabelAmountGrandTotal');
    });

    it('should able to edit shipping cost', async () => {
        const wrapper = await createWrapper();

        Shopware.Store.get('swOrder').setCart({
            token: null,
            lineItems: [],
            price: {
                taxStatus: 'tax-free',
            },
            deliveries: [
                {
                    shippingCosts: {
                        totalPrice: 50,
                        calculatedTaxes: [],
                    },
                },
            ],
        });

        await wrapper.vm.$nextTick();

        const onShippingChargeEditedSpy = jest.spyOn(wrapper.vm, 'onShippingChargeEdited').mockImplementation(() => {});

        let button = wrapper.find('.sw-order-create-summary__data div[role="button"]');
        await button.trigger('click');
        await flushPromises();

        const saveableField = wrapper.find('.sw-order-saveable-field input');
        await saveableField.setValue(20);
        await saveableField.trigger('input');

        button = wrapper.findByAriaLabel('button', 'global.default.save');
        await button.trigger('click');

        expect(wrapper.vm.cartDelivery.shippingCosts.totalPrice).toBe(20);
        expect(wrapper.vm.cartDelivery.shippingCosts.unitPrice).toBe(20);

        expect(onShippingChargeEditedSpy).toHaveBeenCalled();
    });
});
