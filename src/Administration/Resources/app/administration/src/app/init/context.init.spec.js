/**
 * @sw-package framework
 */
import initContext from 'src/app/init/context.init';
import {
    getCurrency,
    getEnvironment,
    getLocale,
    getShopwareVersion,
    getModuleInformation,
    getAppInformation,
    getUserInformation,
    getUserTimezone,
    getShopId,
} from '@shopware-ag/meteor-admin-sdk/es/context';
import { getId } from '@shopware-ag/meteor-admin-sdk/es/window';

describe('src/app/init/context.init.ts', () => {
    beforeAll(() => {
        initContext();
    });

    beforeEach(() => {
        Shopware.Store.get('extensions').extensionsState = {};
        Shopware.Store.get('context').app.windowId = null;
    });

    it('should handle currency', async () => {
        await getCurrency().then((currency) => {
            expect(currency).toEqual(
                expect.objectContaining({
                    systemCurrencyId: expect.any(String),
                    systemCurrencyISOCode: expect.any(String),
                }),
            );
        });
    });

    it('should handle environment', async () => {
        await getEnvironment().then((environment) => {
            expect(environment).toEqual(expect.any(String));
        });
    });

    it('should handle locale', async () => {
        await getLocale().then((locale) => {
            expect(locale).toEqual(
                expect.objectContaining({
                    fallbackLocale: expect.any(String),
                    locale: expect.any(String),
                }),
            );
        });
    });

    it('should handle shopware version', async () => {
        await getShopwareVersion().then((version) => {
            expect(version).toEqual(expect.any(String));
        });
    });

    it('should handle module information', async () => {
        await getModuleInformation().then((moduleInformation) => {
            expect(moduleInformation).toEqual(
                expect.objectContaining({
                    modules: expect.any(Array),
                }),
            );
        });
    });

    it('should return placeholder app information', async () => {
        await getAppInformation().then((appInformation) => {
            expect(appInformation).toEqual(
                expect.objectContaining({
                    name: 'unknown',
                    version: '0.0.0',
                    type: 'app',
                }),
            );
        });
    });

    it('should return user timezone', async () => {
        Shopware.Store.get('session').setCurrentUser({
            timeZone: 'Europe/Berlin',
        });
        await getUserTimezone().then((timezone) => {
            expect(timezone).toBe('Europe/Berlin');
        });

        Shopware.Store.get('session').setCurrentUser({
            timeZone: undefined,
        });
        await getUserTimezone().then((timezone) => {
            expect(timezone).toBe('UTC');
        });
    });

    it('should return app information', async () => {
        Shopware.Store.get('extensions').addExtension({
            name: 'jestapp',
            baseUrl: '',
            permissions: [],
            version: '1.0.0',
            type: 'app',
            integrationId: '123',
            active: true,
        });

        await getAppInformation().then((appInformation) => {
            expect(appInformation).toEqual(
                expect.objectContaining({
                    name: 'jestapp',
                    version: '1.0.0',
                    type: 'app',
                }),
            );
        });
    });

    it('should return user information', async () => {
        Shopware.Store.get('extensions').addExtension({
            name: 'jestapp',
            baseUrl: '',
            permissions: {
                read: [
                    'user',
                ],
            },
            version: '1.0.0',
            type: 'app',
            integrationId: '123',
            active: true,
        });

        Shopware.Store.get('session').setCurrentUser({
            aclRoles: [],
            active: true,
            admin: true,
            email: 'john.doe@test.com',
            firstName: 'John',
            id: '123',
            lastName: 'Doe',
            localeId: 'lOcAlEiD',
            title: 'Dr.',
            type: 'user',
            username: 'john.doe',
        });

        await getUserInformation().then((userInformation) => {
            expect(userInformation).toEqual(
                expect.objectContaining({
                    aclRoles: expect.any(Array),
                    active: true,
                    admin: true,
                    email: 'john.doe@test.com',
                    firstName: 'John',
                    id: '123',
                    lastName: 'Doe',
                    localeId: 'lOcAlEiD',
                    title: 'Dr.',
                    type: 'user',
                    username: 'john.doe',
                }),
            );
        });
    });

    it('should not return user information when permissions arent existing', async () => {
        Shopware.Store.get('extensions').addExtension({
            name: 'jestapp',
            baseUrl: '',
            permissions: [],
            version: '1.0.0',
            type: 'app',
            integrationId: '123',
            active: true,
        });

        Shopware.Store.get('session').setCurrentUser({
            aclRoles: [],
            active: true,
            admin: true,
            email: 'john.doe@test.com',
            firstName: 'John',
            id: '123',
            lastName: 'Doe',
            localeId: 'lOcAlEiD',
            title: 'Dr.',
            type: 'user',
            username: 'john.doe',
        });

        await expect(getUserInformation()).rejects.toThrow('Extension "jestapp" does not have the permission to read users');
    });

    it('should not return user information when extension is not existing', async () => {
        Shopware.Store.get('session').setCurrentUser({
            aclRoles: [],
            active: true,
            admin: true,
            email: 'john.doe@test.com',
            firstName: 'John',
            id: '123',
            lastName: 'Doe',
            localeId: 'lOcAlEiD',
            title: 'Dr.',
            type: 'user',
            username: 'john.doe',
        });

        await expect(getUserInformation()).rejects.toThrow('Could not find a extension with the given event origin ""');
    });

    it('returns windowId from store', async () => {
        Shopware.Store.get('context').app.windowId = '123';

        const windowId = await getId();

        expect(windowId).toBe('123');
    });

    it('should initialize windowId if not set', async () => {
        expect(Shopware.Store.get('context').app.windowId).toBeNull();

        const windowId = await getId();

        expect(Shopware.Store.get('context').windowId).not.toBeNull();
        expect(windowId).toBe(Shopware.Store.get('context').app.windowId);
    });

    it('should return correct shopId', async () => {
        expect(Shopware.Store.get('context').app.config.shopId).toBeNull();

        expect(await getShopId()).toBeNull();

        Shopware.Store.get('context').app.config.shopId = 'shop-id';

        expect(await getShopId()).toBe('shop-id');
    });
});
