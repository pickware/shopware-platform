import { mount } from '@vue/test-utils';

/**
 * @sw-package checkout
 */
async function createWrapper(privileges = [], isShopwareDefaultTax = true) {
    return mount(
        await wrapTestComponent('sw-settings-tax-detail', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                mocks: {
                    $te: () => isShopwareDefaultTax,
                },
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            get: () => {
                                return Promise.resolve({
                                    isNew: () => false,
                                });
                            },

                            create: () => {
                                return Promise.resolve({
                                    isNew: () => true,
                                });
                            },

                            save: () => {
                                return Promise.resolve();
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
                    customFieldDataProviderService: {
                        getCustomFieldSets: () => Promise.resolve([]),
                    },
                    systemConfigApiService: {
                        getConfig: () =>
                            Promise.resolve({
                                'core.tax.defaultTaxRate': '',
                            }),
                    },
                },
                stubs: {
                    'sw-page': {
                        template: `
                    <div class="sw-page">
                        <slot name="search-bar"></slot>
                        <slot name="smart-bar-back"></slot>
                        <slot name="smart-bar-header"></slot>
                        <slot name="language-switch"></slot>
                        <slot name="smart-bar-actions"></slot>
                        <slot name="side-content"></slot>
                        <slot name="content"></slot>
                        <slot name="sidebar"></slot>
                        <slot></slot>
                    </div>
                `,
                    },

                    'sw-card-view': true,
                    'sw-language-switch': true,
                    'sw-container': true,
                    'sw-button-process': true,

                    'sw-text-field': true,
                    'mt-number-field': true,
                    'sw-skeleton': true,
                    'sw-tax-rule-card': true,
                    'sw-custom-field-set-renderer': true,
                },
            },
        },
    );
}

describe('module/sw-settings-tax/page/sw-settings-tax-detail', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should be able to save the tax', async () => {
        const wrapper = await createWrapper([
            'tax.editor',
        ]);
        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find('.sw-settings-tax-detail__save-action');
        const taxNameField = wrapper.find('input[aria-label="sw-settings-tax.detail.labelName"]');
        const taxRateField = wrapper.find('mt-number-field-stub[label="sw-settings-tax.detail.labelDefaultTaxRate"]');

        expect(saveButton.attributes().disabled).toBeFalsy();
        expect(taxNameField.attributes().disabled).toBeDefined();
        expect(taxRateField.attributes().disabled).toBeUndefined();
    });

    it('the name should be editable for non default rates', async () => {
        const wrapper = await createWrapper(
            [
                'tax.editor',
            ],
            false,
        );
        await wrapper.vm.$nextTick();

        const taxNameField = wrapper.find('input[aria-label="sw-settings-tax.detail.labelName"]');
        expect(taxNameField.attributes().disabled).toBeUndefined();
    });

    it('should not be able to save the tax', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find('.sw-settings-tax-detail__save-action');
        const taxNameField = wrapper.find('input[aria-label="sw-settings-tax.detail.labelName"]');
        const taxRateField = wrapper.find('mt-number-field-stub[label="sw-settings-tax.detail.labelDefaultTaxRate"]');

        expect(saveButton.attributes().disabled).toBeTruthy();
        expect(taxNameField.attributes().disabled).toBeDefined();
        expect(taxRateField.attributes().disabled).toBeTruthy();
    });

    it('should have a tax rate field with a correct "digits" property', async () => {
        const wrapper = await createWrapper();

        const taxRateField = wrapper.find('mt-number-field-stub[label="sw-settings-tax.detail.labelDefaultTaxRate"]');

        expect(taxRateField.attributes('digits')).toBe('3');
    });

    it('should change the default tax rate', async () => {
        const wrapper = await createWrapper([
            'tax.editor',
        ]);
        await wrapper.setProps({
            taxId: '12345',
        });
        await flushPromises();

        // Look for defaultTaxRate switch
        const defaultTaxRateSwitch = wrapper.find('.sw-settings-tax-detail__default-tax-rate');
        await defaultTaxRateSwitch.find('input').setValue(true);

        // Check if config value is set to the default tax id
        expect(wrapper.vm.config['core.tax.defaultTaxRate']).toBe('12345');
    });
});
