/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

const STORAGE_KEY_WAS_WRONG_APP_MODAL_SHOWN = 'sw-app-wrong-app-url-modal-shown';
let stubs = {};

describe('sw-app-wrong-app-url-modal', () => {
    let wrapper = null;
    let removeNotificationSpy;

    async function createWrapper() {
        stubs = {
            'sw-modal': {
                template: `
                    <div class="sw-modal">
                        <slot name="modal-header">
                            <slot name="modal-title"></slot>
                        </slot>
                        <slot name="modal-body">
                             <slot></slot>
                        </slot>
                        <slot name="modal-footer">
                        </slot>
                    </div>
                `,
            },
            'router-link': true,
            'sw-loader': true,
        };

        return mount(
            await wrapTestComponent('sw-app-wrong-app-url-modal', {
                sync: true,
            }),
            {
                global: {
                    stubs: {
                        ...stubs,
                    },
                    provide: {
                        shortcutService: {
                            startEventListener() {},
                            stopEventListener() {},
                        },
                    },
                },
            },
        );
    }

    beforeAll(() => {
        if (Shopware.Store.get('context')) {
            Shopware.Store.unregister('context');
        }

        Shopware.Store.register({
            id: 'context',
            state: () => ({
                app: {
                    config: {
                        settings: {
                            appUrlReachable: true,
                            appsRequireAppUrl: false,
                        },
                    },
                },
                api: {
                    assetPath: 'http://localhost:8000/bundles/administration/',
                },
            }),
        });

        removeNotificationSpy = jest.spyOn(Shopware.Store.get('notification'), 'removeNotification');
    });

    it('should be a Vue.js component', async () => {
        wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should show modal', async () => {
        Shopware.Store.get('context').app.config.settings.appUrlReachable = false;
        Shopware.Store.get('context').app.config.settings.appsRequireAppUrl = true;
        localStorage.removeItem(STORAGE_KEY_WAS_WRONG_APP_MODAL_SHOWN);

        wrapper = await createWrapper();

        const modal = wrapper.findComponent(stubs['sw-modal']);
        expect(modal.isVisible()).toBe(true);
        expect(removeNotificationSpy).toHaveBeenCalledTimes(0);
    });

    it('should not show modal if APP_URL is reachable', async () => {
        Shopware.Store.get('context').app.config.settings.appUrlReachable = true;
        Shopware.Store.get('context').app.config.settings.appsRequireAppUrl = true;
        localStorage.removeItem(STORAGE_KEY_WAS_WRONG_APP_MODAL_SHOWN);

        wrapper = await createWrapper();

        const modal = wrapper.findComponent(stubs['sw-modal']);
        expect(modal.exists()).toBe(false);
        expect(removeNotificationSpy).toHaveBeenCalledTimes(1);
    });

    it('should not show modal if no apps are require app url, but it should show notification', async () => {
        Shopware.Store.get('context').app.config.settings.appUrlReachable = false;
        Shopware.Store.get('context').app.config.settings.appsRequireAppUrl = false;
        localStorage.removeItem(STORAGE_KEY_WAS_WRONG_APP_MODAL_SHOWN);

        wrapper = await createWrapper();

        const modal = wrapper.findComponent(stubs['sw-modal']);
        expect(modal.exists()).toBe(false);
        expect(removeNotificationSpy).toHaveBeenCalledTimes(0);
    });

    it('should not show modal if it was shown, but it should show notification', async () => {
        Shopware.Store.get('context').app.config.settings.appUrlReachable = false;
        Shopware.Store.get('context').app.config.settings.appsRequireAppUrl = false;
        localStorage.setItem(STORAGE_KEY_WAS_WRONG_APP_MODAL_SHOWN, true);

        wrapper = await createWrapper();

        const modal = wrapper.findComponent(stubs['sw-modal']);
        expect(modal.exists()).toBe(false);
        expect(removeNotificationSpy).toHaveBeenCalledTimes(0);
    });

    it('should create notification and set localstorage on close', async () => {
        Shopware.Store.get('context').app.config.settings.appUrlReachable = false;
        Shopware.Store.get('context').app.config.settings.appsRequireAppUrl = true;
        localStorage.removeItem(STORAGE_KEY_WAS_WRONG_APP_MODAL_SHOWN);

        wrapper = await createWrapper();

        const modal = wrapper.findComponent(stubs['sw-modal']);
        expect(modal.isVisible()).toBe(true);

        modal.vm.$emit('modal-close');

        expect(wrapper.emitted('modal-close')).toBeTruthy();
        expect(removeNotificationSpy).toHaveBeenCalledTimes(0);
    });

    it('should return filters from filter registry', async () => {
        wrapper = await createWrapper();

        expect(wrapper.vm.assetFilter).toEqual(expect.any(Function));
    });
});
