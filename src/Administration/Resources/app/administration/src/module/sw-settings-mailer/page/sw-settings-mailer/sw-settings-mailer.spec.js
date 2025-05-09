/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

describe('src/module/sw-settings-mailer/page/sw-settings-mailer', () => {
    const CreateSettingsMailer = async function CreateSettingsMailer(emailAgent = null) {
        return mount(
            await wrapTestComponent('sw-settings-mailer', {
                sync: true,
            }),
            {
                global: {
                    renderStubDefaultSlot: true,
                    stubs: {
                        'sw-page': {
                            template: '<div />',
                        },
                        'sw-button-process': true,
                        'sw-skeleton': true,
                        'sw-select-field': true,
                        'sw-radio-field': true,
                        'sw-settings-mailer-smtp': true,
                        'sw-card-view': true,
                    },
                    provide: {
                        systemConfigApiService: {
                            getValues: () =>
                                Promise.resolve({
                                    'core.mailerSettings.emailAgent': emailAgent,
                                    'core.mailerSettings.host': null,
                                    'core.mailerSettings.port': null,
                                    'core.mailerSettings.username': null,
                                    'core.mailerSettings.password': null,
                                    'core.mailerSettings.encryption': 'null',
                                    'core.mailerSettings.senderAddress': null,
                                    'core.mailerSettings.deliveryAddress': null,
                                    'core.mailerSettings.disableDelivery': false,
                                }),
                            saveValues: () => Promise.resolve(),
                        },
                    },
                },
            },
        );
    };

    it('should be a vue js component', async () => {
        const settingsMailer = await new CreateSettingsMailer();

        expect(settingsMailer.vm).toBeTruthy();
    });

    it('should load the mailerSettings on creation', async () => {
        const settingsMailer = await new CreateSettingsMailer();
        const spyLoadMailer = jest.spyOn(settingsMailer.vm, 'loadMailerSettings');

        await settingsMailer.vm.createdComponent();

        expect(spyLoadMailer).toHaveBeenCalled();
    });

    it('should assign the loaded mailerSettings', async () => {
        const settingsMailer = await new CreateSettingsMailer();
        await flushPromises();

        const expectedMailerSettings = {
            'core.mailerSettings.emailAgent': 'local',
            'core.mailerSettings.host': 'shopware.com',
            'core.mailerSettings.port': 321,
            'core.mailerSettings.username': 'Mad max',
            'core.mailerSettings.password': 'verySafe123',
            'core.mailerSettings.encryption': 'md5',
            'core.mailerSettings.senderAddress': 'sender@address.com',
            'core.mailerSettings.deliveryAddress': 'delivery@address.com',
            'core.mailerSettings.disableDelivery': true,
        };

        settingsMailer.vm.systemConfigApiService.getValues = () => Promise.resolve(expectedMailerSettings);

        await settingsMailer.vm.createdComponent();
        expect(settingsMailer.vm.mailerSettings).toEqual(expectedMailerSettings);
    });

    it('should call the saveValues function', async () => {
        const settingsMailer = await new CreateSettingsMailer();
        const spySaveValues = jest.spyOn(settingsMailer.vm.systemConfigApiService, 'saveValues');

        const expectedMailerSettings = {
            'core.mailerSettings.emailAgent': 'smtp',
            'core.mailerSettings.host': 'shopware.com',
            'core.mailerSettings.port': 321,
            'core.mailerSettings.username': 'Mad max',
            'core.mailerSettings.password': 'verySafe123',
            'core.mailerSettings.encryption': 'md5',
            'core.mailerSettings.senderAddress': 'sender@address.com',
            'core.mailerSettings.deliveryAddress': 'delivery@address.com',
            'core.mailerSettings.disableDelivery': true,
        };

        settingsMailer.vm.systemConfigApiService.getValues = () => Promise.resolve(expectedMailerSettings);
        await settingsMailer.vm.createdComponent();

        expect(spySaveValues).not.toHaveBeenCalledWith(expectedMailerSettings);
        await settingsMailer.vm.saveMailerSettings();
        expect(spySaveValues).toHaveBeenCalledWith(expectedMailerSettings);
    });

    it('should throw smtp configuration errors', async () => {
        const wrapper = await new CreateSettingsMailer('smtp');
        await flushPromises();

        expect(wrapper.vm.smtpHostError).toBeNull();
        expect(wrapper.vm.smtpPortError).toBeNull();

        wrapper.vm.createNotificationError = jest.fn();

        wrapper.vm.saveMailerSettings();
        await flushPromises();

        expect(wrapper.vm.smtpHostError).toBeTruthy();
        expect(wrapper.vm.smtpPortError).toBeTruthy();
        expect(wrapper.vm.createNotificationError).toHaveBeenCalledTimes(1);
    });

    it('should reset smtp host error', async () => {
        const wrapper = await new CreateSettingsMailer();
        wrapper.vm.smtpHostError = { detail: 'FooBar' };
        expect(wrapper.vm.smtpHostError).toStrictEqual({ detail: 'FooBar' });

        wrapper.vm.resetSmtpHostError();

        expect(wrapper.vm.smtpHostError).toBeNull();
    });

    it('should reset smtp port error', async () => {
        const wrapper = await new CreateSettingsMailer();
        wrapper.vm.smtpPortError = { detail: 'FooBar' };
        expect(wrapper.vm.smtpPortError).toStrictEqual({ detail: 'FooBar' });

        wrapper.vm.resetSmtpPortError();

        expect(wrapper.vm.smtpPortError).toBeNull();
    });

    it('should reset mailer settings when submitting as emailAgent local', async () => {
        const wrapper = await new CreateSettingsMailer();

        await wrapper.setData({
            mailerSettings: {
                'core.mailerSettings.emailAgent': 'local',
                'core.mailerSettings.host': 'smtp.shopware.com',
                'core.mailerSettings.port': 465,
                'core.mailerSettings.username': 'smtp',
                'core.mailerSettings.password': 'smtp',
                'core.mailerSettings.encryption': 'ssl',
                'core.mailerSettings.senderAddress': 'test@example.com',
                'core.mailerSettings.deliveryAddress': 'info@test.de',
                'core.mailerSettings.sendMailOptions': '-t -i',
            },
        });

        const spySaveValues = jest.spyOn(wrapper.vm.systemConfigApiService, 'saveValues');

        wrapper.vm.saveMailerSettings();

        expect(spySaveValues).toHaveBeenCalledWith({
            'core.mailerSettings.emailAgent': 'local',
            'core.mailerSettings.host': null,
            'core.mailerSettings.port': null,
            'core.mailerSettings.username': null,
            'core.mailerSettings.password': null,
            'core.mailerSettings.encryption': 'null',
            'core.mailerSettings.senderAddress': null,
            'core.mailerSettings.deliveryAddress': null,
            'core.mailerSettings.disableDelivery': false,
            'core.mailerSettings.sendMailOptions': '-t -i',
        });
    });

    it('should be possible to set disableDelivery to true', async () => {
        const wrapper = await new CreateSettingsMailer();

        await wrapper.setData({
            mailerSettings: {
                'core.mailerSettings.emailAgent': 'local',
                'core.mailerSettings.sendMailOptions': '-bs',
                'core.mailerSettings.disableDelivery': true,
            },
        });

        const spySaveValues = jest.spyOn(wrapper.vm.systemConfigApiService, 'saveValues');

        wrapper.vm.saveMailerSettings();

        expect(spySaveValues).toHaveBeenCalledWith({
            'core.mailerSettings.emailAgent': 'local',
            'core.mailerSettings.host': null,
            'core.mailerSettings.port': null,
            'core.mailerSettings.username': null,
            'core.mailerSettings.password': null,
            'core.mailerSettings.encryption': 'null',
            'core.mailerSettings.senderAddress': null,
            'core.mailerSettings.deliveryAddress': null,
            'core.mailerSettings.disableDelivery': true,
            'core.mailerSettings.sendMailOptions': '-bs',
        });
    });

    it('should display and allow selection of email sendmail options', async () => {
        const wrapper = await new CreateSettingsMailer();

        // Verify options are correct
        expect(wrapper.vm.emailSendmailOptions).toEqual([
            {
                value: '-bs',
                name: 'sw-settings-mailer.sendmail.sync',
            },
            {
                value: '-t -i',
                name: 'sw-settings-mailer.sendmail.async',
            },
        ]);

        // Set the mailer settings directly
        await wrapper.setData({
            mailerSettings: {
                'core.mailerSettings.emailAgent': 'local',
                'core.mailerSettings.sendMailOptions': '-bs',
            },
        });

        // Verify the value was set correctly
        expect(wrapper.vm.mailerSettings['core.mailerSettings.sendMailOptions']).toBe('-bs');
    });
});
