/**
 * @sw-package checkout
 */

import { mount } from '@vue/test-utils';

async function createWrapper(privileges = []) {
    return mount(
        await wrapTestComponent('sw-settings-salutation-detail', {
            sync: true,
        }),
        {
            props: {
                salutationId: '1',
            },

            global: {
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            create: () => {
                                return {
                                    apiAlias: null,
                                    createdAt: '2020-08-25T10:23:24.051+00:00',
                                    displayName: 'Mr.',
                                    letterName: 'Dear Mr.',
                                    salutationKey: 'mr-edit',
                                    translated: {
                                        displayName: 'Mr.',
                                        letterName: 'Dear Mr.',
                                    },
                                    updatedAt: '2020-08-27T04:59:39.428+00:00',
                                    isNew: () => true,
                                };
                            },
                            get: (id) => {
                                const salutations = [
                                    {
                                        id: '1',
                                        type: 'salutation',
                                        attributes: {
                                            apiAlias: null,
                                            createdAt: '2020-08-25T10:23:24.051+00:00',
                                            displayName: 'Mr.',
                                            letterName: 'Dear Mr.',
                                            salutationKey: 'mr-edit',
                                        },
                                        isNew: () => false,
                                    },
                                    {
                                        id: '2',
                                        type: 'salutation',
                                        attributes: {
                                            apiAlias: null,
                                            createdAt: '2020-08-25T10:23:24.051+00:00',
                                            displayName: 'Mr.',
                                            letterName: 'Dear Mr.',
                                            salutationKey: 'mr-edit',
                                        },
                                        isNew: () => false,
                                    },
                                ];

                                return Promise.resolve(
                                    salutations.find((salutation) => {
                                        return salutation.id === id;
                                    }),
                                );
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
                    'sw-card-view': {
                        template: `
                    <div class="sw-card-view">
                        <slot></slot>
                    </div>
                `,
                    },
                    'mt-card': {
                        template: `
                    <div class="mt-card">
                        <slot></slot>
                    </div>
                `,
                    },
                    'sw-search-bar': true,
                    'sw-language-switch': true,
                    'sw-button-process': true,
                    'sw-context-menu-item': true,
                    'sw-language-info': true,
                    'sw-text-field': true,
                    'sw-skeleton': true,
                    'sw-custom-field-set-renderer': true,
                },
            },
        },
    );
}

describe('module/sw-settings-salutation/page/sw-settings-salutation-detail', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should be able to save a salutation if have a editor privilege', async () => {
        const wrapper = await createWrapper([
            'salutation.editor',
        ]);
        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find('.sw-settings-salutation-detail__save');

        const labelPath = 'sw-settings-salutation.detail';
        const fieldSalutationKeyLabel = wrapper.find(`input[aria-label="${labelPath}.fieldSalutationKeyLabel"]`);
        const fieldDisplayNameLabel = wrapper.find(`input[aria-label="${labelPath}.fieldDisplayNameLabel"]`);
        const fieldLetterNameLabel = wrapper.find(`input[aria-label="${labelPath}.fieldLetterNameLabel"]`);

        expect(fieldSalutationKeyLabel.attributes().disabled).toBeFalsy();
        expect(fieldDisplayNameLabel.attributes().disabled).toBeFalsy();
        expect(fieldLetterNameLabel.attributes().disabled).toBeFalsy();

        expect(saveButton.attributes().disabled).toBeFalsy();
        expect(wrapper.vm.tooltipSave).toStrictEqual({
            message: 'CTRL + S',
            appearance: 'light',
        });
    });

    it('should not be able to save a salutation if not have editor privilege', async () => {
        const wrapper = await createWrapper([]);
        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find('.sw-settings-salutation-detail__save');

        const labelPath = 'sw-settings-salutation.detail';
        const fieldSalutationKeyLabel = wrapper.find(`input[aria-label="${labelPath}.fieldSalutationKeyLabel"]`);
        const fieldDisplayNameLabel = wrapper.find(`input[aria-label="${labelPath}.fieldDisplayNameLabel"]`);
        const fieldLetterNameLabel = wrapper.find(`input[aria-label="${labelPath}.fieldLetterNameLabel"]`);

        expect(fieldSalutationKeyLabel.attributes().disabled).toBeDefined();
        expect(fieldDisplayNameLabel.attributes().disabled).toBeDefined();
        expect(fieldLetterNameLabel.attributes().disabled).toBeDefined();

        expect(saveButton.attributes().disabled).toBeTruthy();
        expect(wrapper.vm.tooltipSave).toStrictEqual({
            disabled: false,
            message: 'sw-privileges.tooltip.warning',
            showOnDisabledElements: true,
        });
    });

    it('should not be able to save a salutation if have privileges which do not contain editor privilege', async () => {
        const wrapper = await createWrapper([
            'salutation.creator',
            'salutation.deleter',
        ]);
        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find('.sw-settings-salutation-detail__save');

        const labelPath = 'sw-settings-salutation.detail';
        const fieldSalutationKeyLabel = wrapper.find(`input[aria-label="${labelPath}.fieldSalutationKeyLabel"]`);
        const fieldDisplayNameLabel = wrapper.find(`input[aria-label="${labelPath}.fieldDisplayNameLabel"]`);
        const fieldLetterNameLabel = wrapper.find(`input[aria-label="${labelPath}.fieldLetterNameLabel"]`);

        expect(fieldSalutationKeyLabel.attributes().disabled).toBeDefined();
        expect(fieldDisplayNameLabel.attributes().disabled).toBeDefined();
        expect(fieldLetterNameLabel.attributes().disabled).toBeDefined();

        expect(saveButton.attributes().disabled).toBeTruthy();
        expect(wrapper.vm.tooltipSave).toStrictEqual({
            disabled: false,
            message: 'sw-privileges.tooltip.warning',
            showOnDisabledElements: true,
        });
    });
});
