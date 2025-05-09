/**
 * @sw-package framework
 */
const { Module } = Shopware;
const ModuleFactory = Module;
const register = ModuleFactory.register;
const { hasOwnProperty } = Shopware.Utils.object;

describe('src/module/sw-settings', () => {
    let settingsIndex;

    beforeEach(async () => {
        const modules = ModuleFactory.getModuleRegistry();
        modules.clear();

        Shopware.Store.get('settingsItems').settingsGroups = {};

        settingsIndex = {
            type: 'core',
            name: 'settings',

            routes: {
                index: {
                    component: 'sw-settings-index',
                    path: 'index',
                    icon: 'default-action-settings',
                },
            },
        };
    });

    it('should not contain any registered settings items', async () => {
        register('sw-settings-foo', settingsIndex);

        const settingsGroups = Shopware.Store.get('settingsItems').settingsGroups;

        expect(settingsGroups).toEqual({});
    });

    it('should contain registered settings items group', async () => {
        settingsIndex.settingsItem = [
            {
                group: 'shop',
                to: 'sw.settings.address.index',
                icon: 'default-object-address',
            },
        ];
        register('sw-settings-foo', settingsIndex);

        const settingsGroups = Shopware.Store.get('settingsItems').settingsGroups;

        expect(hasOwnProperty(settingsGroups, 'shop')).toBe(true);
    });

    it('should register a specific key for the defined group property in the settings items', async () => {
        settingsIndex.settingsItem = [
            {
                group: 'shop',
                to: 'sw.settings.address.index',
                icon: 'default-object-address',
                name: 'sw-settings-address-foo',
            },
            {
                group: 'shop',
                to: 'sw.settings.tax.index',
                icon: 'default-chart-pie',
                name: 'sw-settings-tax-foo',
            },
            {
                group: 'system',
                to: 'sw.settings.store.index',
                icon: 'default-device-laptop',
                name: 'sw-settings-store-foo',
            },
            {
                group: 'plugins',
                to: 'swag.paypal.index',
                icon: 'paypal-default',
                name: 'SwagPayPal',
            },
        ];
        register('sw-settings-foo', settingsIndex);

        const settingsGroups = Shopware.Store.get('settingsItems').settingsGroups;

        expect(settingsGroups.shop).toHaveLength(2);
        expect(settingsGroups.system).toHaveLength(1);
        expect(settingsGroups.plugins).toHaveLength(1);
    });

    it('should only allow unique settings items name per group', async () => {
        settingsIndex.settingsItem = [
            {
                group: 'shop',
                to: 'sw.settings.address.index',
                icon: 'default-object-address',
                name: 'foo',
            },
            {
                group: 'shop',
                to: 'sw.settings.address.index',
                icon: 'default-object-address',
                name: 'foo',
            },
        ];
        register('sw-settings-foo', settingsIndex);

        const settingsGroups = Shopware.Store.get('settingsItems').settingsGroups;

        expect(settingsGroups.shop).toHaveLength(1);
    });

    it('should allow to add settings items with duplicate name in different groups', async () => {
        settingsIndex.settingsItem = [
            {
                group: 'shop',
                to: 'sw.settings.address.index',
                icon: 'default-object-address',
                name: 'foo',
            },
            {
                group: 'system',
                to: 'sw.settings.address.index',
                icon: 'default-object-address',
                name: 'foo',
            },
        ];
        register('sw-settings-foo', settingsIndex);

        const settingsGroups = Shopware.Store.get('settingsItems').settingsGroups;

        expect(settingsGroups.shop).toHaveLength(1);
        expect(settingsGroups.shop[0].name).toBe('foo');
        expect(settingsGroups.system).toHaveLength(1);
        expect(settingsGroups.system[0].name).toBe('foo');
    });
});
