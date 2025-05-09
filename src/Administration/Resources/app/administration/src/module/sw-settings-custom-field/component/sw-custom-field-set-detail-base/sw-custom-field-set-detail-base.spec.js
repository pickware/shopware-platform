/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

function getFieldTypes() {
    return {
        checkbox: {
            config: {
                componentName: 'sw-field',
                type: 'checkbox',
            },
            configRenderComponent: 'sw-custom-field-type-checkbox',
        },
    };
}

async function createWrapper(privileges = []) {
    return mount(
        await wrapTestComponent('sw-custom-field-set-detail-base', {
            sync: true,
        }),
        {
            props: {
                set: {
                    _isNew: true,
                },
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
                },
                stubs: {
                    'sw-container': true,
                    'sw-custom-field-type-checkbox': true,
                    'sw-text-field': true,
                    'sw-multi-select': true,
                    'sw-loader': true,

                    'sw-custom-field-translated-labels': true,
                },
            },
        },
    );
}

describe('src/module/sw-settings-custom-field/component/sw-custom-field-set-detail-base', () => {
    it('can edit fields', async () => {
        const wrapper = await createWrapper([
            'custom_field.editor',
        ]);
        await flushPromises();

        const technicalNameField = wrapper.findComponent('.sw-settings-custom-field-set-detail-base__technical-name');
        const positionField = wrapper.find('.sw-settings-custom-field-set-detail-base__base-postion');
        const entitiesField = wrapper.find('.sw-settings-custom-field-set-detail-base__label-entities');

        expect(technicalNameField.props('disabled')).toBeFalsy();
        expect(positionField.attributes('disabled')).toBeFalsy();
        expect(entitiesField.attributes('disabled')).toBeFalsy();
    });

    it('cannot edit fields', async () => {
        const wrapper = await createWrapper();

        const technicalNameField = wrapper.findComponent('.sw-settings-custom-field-set-detail-base__technical-name');
        const positionField = wrapper.findByLabel('sw-settings-custom-field.set.detail.labelPosition');
        const entitiesField = wrapper.find('.sw-settings-custom-field-set-detail-base__label-entities');

        expect(technicalNameField.props('disabled')).toBeTruthy();
        expect(positionField.attributes('disabled')).toBeDefined();
        expect(entitiesField.attributes('disabled')).toBeDefined();
    });
});
