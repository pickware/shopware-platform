/**
 * @sw-package fundamentals@discovery
 */
import { mount } from '@vue/test-utils';

async function createWrapper(privileges = [], customPropsData = {}) {
    return mount(
        await wrapTestComponent('sw-settings-country-general', {
            sync: true,
        }),
        {
            props: {
                country: {
                    isNew: () => false,
                    customerTax: {
                        enabled: customPropsData.enabled,
                    },
                    companyTax: {
                        enabled: customPropsData.enabled,
                    },
                    ...customPropsData,
                },
                userConfig: {},
                userConfigValues: {},
                isLoading: false,
            },

            global: {
                mocks: {
                    $tc: (key) => key,
                    $route: {
                        params: {
                            id: 'id',
                        },
                    },
                    $device: {
                        getSystemKey: () => {},
                        onResize: () => {},
                    },
                },

                provide: {
                    repositoryFactory: {
                        create: () => ({
                            get: () => {
                                return Promise.resolve({});
                            },
                            search: () => {
                                return Promise.resolve({
                                    userConfigs: {
                                        first: () => ({}),
                                    },
                                });
                            },
                        }),
                    },
                    acl: {
                        can: (identifier) => {
                            if (!identifier) {
                                return true;
                            }

                            return privileges.includes(identifier);
                        },
                    },
                    feature: {
                        isActive: () => true,
                    },
                },

                stubs: {
                    'sw-ignore-class': true,
                    'sw-container': await wrapTestComponent('sw-container'),
                    'sw-text-field': true,
                    'mt-number-field': true,
                    'sw-settings-country-currency-dependent-modal': true,
                    'sw-entity-single-select': true,
                    'sw-extension-component-section': true,
                    'sw-ai-copilot-badge': true,
                    'sw-context-button': true,
                    'sw-loader': true,
                },
            },
        },
    );
}

describe('module/sw-settings-country/component/sw-settings-country-general', () => {
    beforeAll(() => {
        Shopware.Store.get('session').setCurrentUser({});
    });

    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should be able to show the tax free from', async () => {
        const wrapper = await createWrapper(
            [
                'country.editor',
            ],
            {
                enabled: true,
            },
        );

        await flushPromises();

        const countryNameField = wrapper.find('.mt-text-field input[aria-label="sw-settings-country.detail.labelName"]');
        const countryPositionField = wrapper.find('mt-number-field-stub[label="sw-settings-country.detail.labelPosition"]');
        const countryIsoField = wrapper.find('.mt-text-field input[aria-label="sw-settings-country.detail.labelIso"]');
        const countryIso3Field = wrapper.find('.mt-text-field input[aria-label="sw-settings-country.detail.labelIso3"]');
        const countryActiveField = wrapper.find('.mt-switch input[aria-label="sw-settings-country.detail.labelActive"]');
        const countryShippingAvailableField = wrapper.find(
            '.mt-switch input[aria-label="sw-settings-country.detail.labelShippingAvailable"]',
        );
        const countryTaxFreeField = wrapper.find('.mt-switch input[aria-label="sw-settings-country.detail.labelTaxFree"]');
        const countryCompaniesTaxFreeField = wrapper.find(
            '.mt-switch input[aria-label="sw-settings-country.detail.labelCompanyTaxFree"]',
        );
        const countryCheckVatIdFormatField = wrapper.find(
            '.mt-switch input[aria-label="sw-settings-country.detail.labelCheckVatIdFormat"]',
        );
        const countryTaxFreeFromField = wrapper.find('mt-number-field-stub[label="sw-settings-country.detail.taxFreeFrom"]');
        const countryVatIdRequiredField = wrapper.find(
            '.mt-switch input[aria-label="sw-settings-country.detail.labelVatIdRequired"]',
        );

        const countryIsEuField = wrapper.find('.mt-switch input[aria-label="sw-settings-country.detail.labelIsEu"]');

        expect(countryNameField.attributes().disabled).toBeUndefined();
        expect(countryPositionField.attributes().disabled).toBeUndefined();
        expect(countryIsoField.attributes().disabled).toBeUndefined();
        expect(countryIso3Field.attributes().disabled).toBeUndefined();
        expect(countryActiveField.attributes().disabled).toBeUndefined();
        expect(countryShippingAvailableField.attributes().disabled).toBeUndefined();
        expect(countryTaxFreeField.attributes().disabled).toBeUndefined();
        expect(countryCompaniesTaxFreeField.attributes().disabled).toBeUndefined();
        expect(countryCheckVatIdFormatField.attributes().disabled).toBeUndefined();
        expect(countryTaxFreeFromField.attributes()).toBeDefined();
        expect(countryVatIdRequiredField.attributes().disabled).toBeUndefined();
        expect(countryIsEuField.attributes().disabled).toBeUndefined();
    });

    it('should not able to show the tax free from', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const countryNameField = wrapper.find('.mt-text-field input[aria-label="sw-settings-country.detail.labelName"]');
        const countryPositionField = wrapper.find('mt-number-field-stub[label="sw-settings-country.detail.labelPosition"]');
        const countryIsoField = wrapper.find('.mt-text-field input[aria-label="sw-settings-country.detail.labelIso"]');
        const countryIso3Field = wrapper.find('.mt-text-field input[aria-label="sw-settings-country.detail.labelIso3"]');
        const countryActiveField = wrapper.find('.mt-switch input[aria-label="sw-settings-country.detail.labelActive"]');
        const countryShippingAvailableField = wrapper.find(
            '.mt-switch input[aria-label="sw-settings-country.detail.labelShippingAvailable"]',
        );
        const countryTaxFreeField = wrapper.find('.mt-switch input[aria-label="sw-settings-country.detail.labelTaxFree"]');
        const countryCompaniesTaxFreeField = wrapper.find(
            '.mt-switch input[aria-label="sw-settings-country.detail.labelCompanyTaxFree"]',
        );
        const countryCheckVatIdFormatField = wrapper.find(
            '.mt-switch input[aria-label="sw-settings-country.detail.labelCheckVatIdFormat"]',
        );
        const countryTaxFreeFromField = wrapper.find('mt-number-field-stub[label="sw-settings-country.detail.taxFreeFrom"]');
        const currencyDropdownList = wrapper.find('sw-entity-single-select-stub');
        const countryVatIdRequiredField = wrapper.find(
            '.mt-switch input[aria-label="sw-settings-country.detail.labelVatIdRequired"]',
        );

        const countryIsEuField = wrapper.find('.mt-switch input[aria-label="sw-settings-country.detail.labelIsEu"]');

        expect(countryNameField.attributes().disabled).toBeDefined();
        expect(countryPositionField.attributes().disabled).toBeTruthy();
        expect(countryIsoField.attributes().disabled).toBeDefined();
        expect(countryIso3Field.attributes().disabled).toBeDefined();
        expect(countryActiveField.attributes().disabled).toBeDefined();
        expect(countryShippingAvailableField.attributes().disabled).toBeDefined();
        expect(countryTaxFreeField.attributes().disabled).toBeDefined();
        expect(countryCompaniesTaxFreeField.attributes().disabled).toBeDefined();
        expect(countryCheckVatIdFormatField.attributes().disabled).toBeDefined();
        expect(countryTaxFreeFromField.exists()).toBe(false);
        expect(currencyDropdownList.exists()).toBe(false);
        expect(countryVatIdRequiredField.attributes().disabled).toBeDefined();
        expect(countryIsEuField.attributes().disabled).toBeDefined();
    });
});
