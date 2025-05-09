import 'src/app/store/in-app-purchase-checkout.store';

/**
 * @sw-package checkout
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-in-app-purchase-checkout', { sync: true }), {
        global: {
            stubs: {
                'sw-modal': await wrapTestComponent('sw-modal'),
                'sw-extension-store-landing-page': true,
                'sw-loader': true,
                'sw-label': true,
            },
            provide: {
                shortcutService: {
                    stopEventListener: () => {},
                    startEventListener: () => {},
                },
                extensionHelperService: {},
            },
        },
    });
}

describe('src/app/component/structure/sw-in-app-purchase-checkout', () => {
    let wrapper = null;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    afterEach(() => {
        wrapper.vm.closeModal();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render the modal when modal data is set', async () => {
        const requestCheckout = {
            featureId: 'Test Feature',
        };

        Shopware.Context.app.config.bundles = {
            TestExtension: {
                identifier: 'TestExtension',
            },
        };

        Shopware.Store.get('inAppPurchaseCheckout').request(requestCheckout, 'TestExtension');

        await flushPromises();

        expect(wrapper.find('.sw-in-app-purchase-checkout').exists()).toBe(true);
        expect(wrapper.find('.sw-in-app-purchase-checkout').text()).toContain('sw-in-app-purchase-checkout.exsTitle');
    });

    it('should not render the modal when modal data is not set', () => {
        expect(wrapper.vm.entry).toBeNull();

        expect(wrapper.find('.sw-in-app-purchase-checkout').exists()).toBe(false);
    });

    it('should close the modal when closeModal method is called', async () => {
        const requestCheckout = {
            featureId: 'Test Feature',
        };

        Shopware.Context.app.config.bundles = {
            TestExtension: {
                identifier: 'TestExtension',
            },
        };

        Shopware.Store.get('inAppPurchaseCheckout').request(requestCheckout, 'TestExtension');

        await flushPromises();

        await wrapper.find('.sw-modal__close').trigger('click');

        await flushPromises();

        expect(wrapper.find('.sw-in-app-purchase-checkout').exists()).toBe(false);
    });
});
