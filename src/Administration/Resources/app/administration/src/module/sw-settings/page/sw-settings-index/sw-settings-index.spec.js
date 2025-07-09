/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

async function createWrapper(
    privileges = [
        'store.viewer',
        'user.viewer',
        'foo.viewer',
        'snippet.viewer',
        'store.viewer',
        'listing.viewer',
        'shipping.viewer',
    ],
) {
    const settingsItemsMock = [
        {
            group: 'system',
            to: 'sw.settings.store.index',
            icon: 'regular-laptop',
            id: 'sw-settings-store',
            name: 'settings-store',
            label: 'c',
            privilege: 'store.viewer',
        },
        {
            group: 'system',
            to: 'sw.settings.user.list',
            icon: 'regular-user',
            id: 'sw-settings-user',
            name: 'settings-user',
            label: 'a',
            privilege: 'user.viewer',
        },
        {
            group: 'system',
            to: 'sw.settings.foo.list',
            icon: 'regular-user',
            id: 'sw-settings-foo',
            name: 'settings-foo',
            label: 'b',
            privilege: 'foo.viewer',
        },
        {
            group: 'shop',
            to: 'sw.settings.snippet.index',
            icon: 'regular-globe',
            id: 'sw-settings-snippet',
            name: 'settings-snippet',
            label: 'h',
            privilege: 'snippet.viewer',
        },
        {
            group: 'shop',
            to: 'sw.settings.listing.index',
            icon: 'regular-products',
            id: 'sw-settings-listing',
            name: 'settings-listing',
            label: 's',
            privilege: 'listing.viewer',
        },
        {
            group: 'shop',
            to: 'sw.settings.shipping.index',
            icon: 'regular-truck',
            id: 'sw-settings-shipping',
            name: 'settings-shipping',
            label: 'a',
            privilege: 'shipping.viewer',
        },
        {
            group: 'plugins',
            to: {
                name: 'sw.extension.sdk.index',
                params: {
                    id: Shopware.Utils.createId(),
                },
            },
            icon: 'regular-books',
            id: 'sw-extension-books',
            name: 'settings-app-book',
            label: {
                translated: true,
                label: 'extension-sdk',
            },
        },
        {
            group: 'plugins',
            to: {
                name: 'sw.extension.sdk.index',
                params: {
                    id: Shopware.Utils.createId(),
                },
            },
            icon: 'regular-books',
            id: 'sw-extension-briefcase',
            name: 'settings-app-briefcase',
            label: {
                translated: false,
                label: 'general.no',
            },
        },
    ];

    settingsItemsMock.forEach((settingsItem) => {
        Shopware.Store.get('settingsItems').addItem(settingsItem);
    });

    return mount(
        await wrapTestComponent('sw-settings-index', {
            sync: true,
        }),
        {
            global: {
                mocks: {
                    $tc: (path) => {
                        if (typeof path !== 'string') {
                            return `${path}`;
                        }
                        return path;
                    },
                },
                stubs: {
                    'sw-page': {
                        template: '<div><slot name="content"></slot></div>',
                    },
                    'sw-card-view': {
                        template: '<div class="sw-card-view"><slot></slot></div>',
                    },
                    'sw-tabs': await wrapTestComponent('sw-tabs'),
                    'sw-tabs-deprecated': await wrapTestComponent('sw-tabs-deprecated', { sync: true }),
                    'sw-tabs-item': await wrapTestComponent('sw-tabs-item'),
                    'mt-card': {
                        template: '<div class="mt-card"><slot></slot></div>',
                    },
                    'sw-settings-item': await wrapTestComponent('sw-settings-item'),
                    'router-link': {
                        template: '<a><slot></slot></a>',
                    },
                    'sw-extension-component-section': true,
                },
                provide: {
                    acl: {
                        can: (key) => {
                            if (!key) return true;

                            return privileges.includes(key);
                        },
                    },
                    userConfigService: {
                        search: jest.fn().mockResolvedValue({ data: {} }),
                        upsert: jest.fn().mockResolvedValue(),
                    },
                },
            },
        },
    );
}

describe('module/sw-settings/page/sw-settings-index', () => {
    beforeEach(async () => {
        Shopware.Store.get('settingsItems').settingsGroups = {};
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should contain any settings items', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm.settingsGroups).not.toEqual({});
    });

    it('should return settings items alphabetically sorted', async () => {
        const wrapper = await createWrapper();
        const settingsGroups = Object.entries(wrapper.vm.settingsGroups);

        settingsGroups.forEach(
            ([
                ,
                settingsItems,
            ]) => {
                settingsItems.forEach((settingsItem, index) => {
                    let elementsSorted = true;

                    if (index < settingsItems.length - 1 && typeof settingsItems[index].label === 'string') {
                        elementsSorted = settingsItems[index].label.localeCompare(settingsItems[index + 1].label) === -1;
                    }

                    expect(elementsSorted).toBe(true);
                });
            },
        );
    });

    it('should render settings items in alphabetical order', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        const settingsGroups = Object.entries(wrapper.vm.settingsGroups);

        settingsGroups.forEach(
            ([
                settingsGroup,
                settingsItems,
            ]) => {
                const settingsGroupWrapper = wrapper.find(`#sw-settings__content-group-${settingsGroup}`);
                const settingsItemsWrappers = settingsGroupWrapper.findAll('.sw-settings-item');

                // check, that all settings items were rendered
                expect(settingsItemsWrappers).toHaveLength(settingsItems.length);

                // check, that settings items were rendered in alphabetical order
                settingsItemsWrappers.forEach((settingsItemsWrapper, index) => {
                    expect(settingsItemsWrapper.attributes().id).toEqual(settingsItems[index].id);
                });
            },
        );
    });

    it('should render settings items in alphabetical order with updated items', async () => {
        const settingsItemToAdd = {
            group: 'shop',
            to: 'sw.bar.index',
            icon: 'regular-storefront',
            id: 'sw-settings-bar',
            name: 'settings-bar',
            label: 'b',
        };

        Shopware.Store.get('settingsItems').addItem(settingsItemToAdd);

        const wrapper = await createWrapper();
        await flushPromises();
        const settingsGroups = Object.entries(wrapper.vm.settingsGroups);

        settingsGroups.forEach(
            ([
                settingsGroup,
                settingsItems,
            ]) => {
                const settingsGroupWrapper = wrapper.find(`#sw-settings__content-group-${settingsGroup}`);
                const settingsItemsWrappers = settingsGroupWrapper.findAll('.sw-settings-item');

                expect(settingsItemsWrappers).toHaveLength(settingsItems.length);

                settingsItemsWrappers.forEach((settingsItemsWrapper, index) => {
                    expect(settingsItemsWrapper.attributes().id).toEqual(settingsItems[index].id);
                });
            },
        );
    });

    it('should add the setting to the settingsGroups in store', async () => {
        const settingsItemToAdd = {
            group: 'shop',
            to: 'sw.bar.index',
            icon: 'regular-storefront',
            id: 'sw-settings-bar',
            name: 'settings-bar',
            label: 'b',
        };

        Shopware.Store.get('settingsItems').addItem(settingsItemToAdd);

        const wrapper = await createWrapper();

        const settingsGroups = wrapper.vm.settingsGroups.shop;
        const barSetting = settingsGroups.find((setting) => setting.id === 'sw-settings-bar');

        expect(barSetting).toBeDefined();
    });

    it('should show the setting with the privileges', async () => {
        const settingsItemToAdd = {
            privilege: 'system.foo_bar',
            group: 'shop',
            to: 'sw.bar.index',
            icon: 'regular-storefront',
            id: 'sw-settings-bar',
            name: 'settings-bar',
            label: 'b',
        };

        Shopware.Store.get('settingsItems').addItem(settingsItemToAdd);

        const wrapper = await createWrapper('system.foo_bar');

        const settingsGroups = wrapper.vm.settingsGroups.shop;
        const barSetting = settingsGroups.find((setting) => setting.id === 'sw-settings-bar');

        expect(barSetting).toBeDefined();
    });

    it('should not show the setting with the privileges', async () => {
        const settingsItemToAdd = {
            privilege: 'system.foo_bar',
            group: 'shop',
            to: 'sw.bar.index',
            icon: 'regular-storefront',
            id: 'sw-settings-bar',
            name: 'settings-bar',
            label: 'b',
        };

        Shopware.Store.get('settingsItems').addItem(settingsItemToAdd);

        const wrapper = await createWrapper();

        const settingsGroups = wrapper.vm.settingsGroups.shop;
        const barSetting = settingsGroups.find((setting) => setting.id === 'sw-settings-bar');

        expect(barSetting).toBeUndefined();
    });

    it('should correctly resolve dynamic group functions and add the item', async () => {
        const settingsItemToAdd = {
            group: () => 'dynamicGroup',
            to: 'sw.dynamic.index',
            icon: 'regular-storefront',
            id: 'sw-dynamic-setting',
            name: 'settings-dynamic',
            label: 'Dynamic Setting',
        };

        Shopware.Store.get('settingsItems').addItem(settingsItemToAdd);

        const wrapper = await createWrapper();
        await flushPromises();

        const dynamicGroup = wrapper.vm.settingsGroups.dynamicGroup;
        expect(dynamicGroup).toBeDefined();
        expect(dynamicGroup).toHaveLength(1);
        expect(dynamicGroup[0]).toEqual(settingsItemToAdd);
    });

    it('should display settings items based on user privileges', async () => {
        const settingsItemToAdd = {
            privilege: 'system.foo_bar',
            group: 'shop',
            to: 'sw.bar.index',
            icon: 'regular-storefront',
            id: 'sw-settings-bar',
            name: 'settings-bar',
            label: 'Bar Setting',
        };

        Shopware.Store.get('settingsItems').addItem(settingsItemToAdd);

        const wrapper = await createWrapper(['system.foo_bar']);
        const shopGroup = wrapper.vm.settingsGroups.shop;

        const barSetting = shopGroup.find((setting) => setting.id === 'sw-settings-bar');
        expect(barSetting).toBeDefined();
    });

    /**
     * @deprecated tag:v6.8.0 - Will be removed
     */
    it('should load user config for banner on created', async () => {
        const wrapper = await createWrapper();
        const userConfigService = wrapper.vm.userConfigService;
        expect(userConfigService.search).toHaveBeenCalledWith(['settings.hideRenameBanner']);
    });

    /**
     * @deprecated tag:v6.8.0 - Will be removed
     */
    it('should show banner by default when no config is set', async () => {
        const wrapper = await createWrapper();
        const userConfigService = wrapper.vm.userConfigService;
        userConfigService.search.mockResolvedValueOnce({ data: {} });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.hideSettingRenameBanner).toBe(false);
    });

    /**
     * @deprecated tag:v6.8.0 - Will be removed
     */
    it('should hide banner when config is set to true', async () => {
        const wrapper = await createWrapper();
        const userConfigService = wrapper.vm.userConfigService;
        userConfigService.search.mockResolvedValueOnce({
            data: {
                'settings.hideRenameBanner': {
                    value: true,
                },
            },
        });

        await wrapper.vm.getUserConfig();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.hideSettingRenameBanner).toBe(true);
    });

    /**
     * @deprecated tag:v6.8.0 - Will be removed
     */
    it('should show banner when config is set to false', async () => {
        const wrapper = await createWrapper();
        const userConfigService = wrapper.vm.userConfigService;
        userConfigService.search.mockResolvedValueOnce({
            data: {
                'settings.hideRenameBanner': {
                    data: false,
                },
            },
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.hideSettingRenameBanner).toBe(false);
    });

    /**
     * @deprecated tag:v6.8.0 - Will be removed
     */
    it('should toggle banner visibility and save config', async () => {
        const wrapper = await createWrapper();
        const userConfigService = wrapper.vm.userConfigService;
        userConfigService.search.mockResolvedValueOnce({
            data: {
                'settings.hideRenameBanner': {
                    value: true,
                },
            },
        });

        await wrapper.vm.getUserConfig();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.hideSettingRenameBanner).toBe(true);

        await wrapper.vm.onCloseSettingRenameBanner();

        expect(wrapper.vm.hideSettingRenameBanner).toBe(true);
        expect(userConfigService.upsert).toHaveBeenCalledWith({
            'settings.hideRenameBanner': {
                value: true,
            },
        });
    });
});
