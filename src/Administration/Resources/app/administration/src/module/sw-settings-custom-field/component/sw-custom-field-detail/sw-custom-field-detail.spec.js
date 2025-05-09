/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import selectMtSelectOptionByText from 'test/_helper_/select-mt-select-by-text';

function getFieldTypes() {
    return {
        select: {
            configRenderComponent: 'sw-custom-field-type-select',
            config: {
                componentName: 'sw-single-select',
            },
        },
        checkbox: {
            configRenderComponent: 'sw-custom-field-type-checkbox',
            type: 'bool',
            config: { componentName: 'sw-field', type: 'checkbox' },
        },
        switch: {
            configRenderComponent: 'sw-custom-field-type-checkbox',
            type: 'bool',
            config: { componentName: 'sw-field', type: 'switch' },
        },
    };
}

async function createWrapper(privileges = []) {
    return mount(
        await wrapTestComponent('sw-custom-field-detail', {
            sync: true,
        }),
        {
            props: {
                currentCustomField: {
                    id: 'id1',
                    name: 'custom_additional_field_1',
                    config: {
                        label: { 'en-GB': 'Special field 1' },
                        customFieldType: 'checkbox',
                        customFieldPosition: 1,
                    },
                    _isNew: true,
                },
                set: {},
            },
            global: {
                renderStubDefaultSlot: true,
                mocks: {
                    $i18n: {
                        fallbackLocale: 'en-GB',
                    },
                },
                provide: {
                    acl: {
                        can: (identifier) => {
                            if (!identifier) {
                                return true;
                            }

                            return privileges.includes(identifier);
                        },
                    },
                    customFieldDataProviderService: {
                        getTypes: () => getFieldTypes(),
                    },
                    SwCustomFieldListIsCustomFieldNameUnique: () => Promise.resolve(null),
                    validationService: {},
                    shortcutService: {
                        stopEventListener: () => {},
                        startEventListener: () => {},
                    },
                },
                stubs: {
                    'sw-modal': await wrapTestComponent('sw-modal'),
                    'sw-container': true,
                    'sw-custom-field-type-checkbox': true,
                    'mt-number-field': true,
                    'sw-text-field': true,
                    'sw-select-field': await wrapTestComponent('sw-select-field', { sync: true }),
                    'sw-select-field-deprecated': await wrapTestComponent('sw-select-field-deprecated', { sync: true }),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-field-error': true,
                    'sw-help-text': true,
                    'sw-loader': true,
                    'router-link': true,
                    'sw-inheritance-switch': true,
                    'sw-ai-copilot-badge': true,
                },
            },
        },
    );
}

describe('src/module/sw-settings-custom-field/component/sw-custom-field-detail', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('can edit fields', async () => {
        const wrapper = await createWrapper([
            'custom_field.editor',
        ]);
        await flushPromises();

        const modalTypeField = wrapper.find('.sw-custom-field-detail__modal-type input');
        const technicalNameField = wrapper.findComponent('.sw-custom-field-detail__technical-name');
        const modalPositionField = wrapper.find('.sw-custom-field-detail__modal-position');
        const modalSaveButton = wrapper.find('.sw-custom-field-detail__footer-save');

        expect(modalTypeField.attributes('disabled')).toBeFalsy();
        expect(technicalNameField.props('disabled')).toBeFalsy();
        expect(modalPositionField.attributes('disabled')).toBeFalsy();
        expect(modalSaveButton.attributes('disabled')).toBeFalsy();
    });

    it('cannot edit fields', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const modalTypeField = wrapper.find('.sw-custom-field-detail__modal-type input');
        const technicalNameField = wrapper.findComponent('.sw-custom-field-detail__technical-name');
        const modalPositionField = wrapper.find('.sw-custom-field-detail__modal-position');
        const modalSaveButton = wrapper.find('.sw-custom-field-detail__footer-save');

        expect(modalTypeField.attributes('disabled')).toBeDefined();
        expect(technicalNameField.props('disabled')).toBeTruthy();
        expect(modalPositionField.attributes('disabled')).toBeDefined();
        expect(modalSaveButton.attributes('disabled')).toBeDefined();
    });

    it('should update config correctly', async () => {
        const wrapper = await createWrapper(['custom_field.editor']);
        await flushPromises();

        await selectMtSelectOptionByText(wrapper, 'sw-settings-custom-field.types.select');

        await flushPromises();

        expect(wrapper.vm.currentCustomField.config).toEqual(
            expect.objectContaining({
                customFieldType: 'select',
            }),
        );

        await selectMtSelectOptionByText(wrapper, 'sw-settings-custom-field.types.switch');

        expect(wrapper.vm.currentCustomField.config).toEqual(
            expect.objectContaining({
                customFieldType: 'switch',
            }),
        );

        const saveButton = wrapper.find('.sw-custom-field-detail__footer-save');
        await saveButton.trigger('click');

        expect(wrapper.vm.currentCustomField.config).toEqual(
            expect.objectContaining({
                customFieldType: 'switch',
                componentName: 'sw-field',
            }),
        );
    });
});
