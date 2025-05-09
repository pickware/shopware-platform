import { mount } from '@vue/test-utils';
import { createPinia } from 'pinia';

/**
 * @sw-package after-sales
 */

async function createWrapper(privileges = [], query = {}) {
    return mount(
        await wrapTestComponent('sw-flow-detail-general', {
            sync: true,
        }),
        {
            global: {
                plugins: [createPinia()],
                provide: {
                    acl: {
                        can: (identifier) => {
                            if (!identifier) {
                                return true;
                            }

                            return privileges.includes(identifier);
                        },
                    },
                    repositoryFactory: {
                        create: () => ({
                            create: () => {
                                return Promise.resolve({});
                            },
                        }),
                    },

                    mocks: {
                        $route: { params: {}, query: query },
                    },
                },
                stubs: {
                    'mt-card': {
                        template: '<div><slot></slot></div>',
                    },
                    'mt-text-field': true,
                    'mt-textarea': true,
                    'mt-number-field': true,
                    'sw-container': {
                        template: '<div><slot></slot></div>',
                    },
                    'mt-switch': true,
                },
            },
        },
    );
}

describe('module/sw-flow/view/detail/sw-flow-detail-general', () => {
    it('should enabled element when have privilege', async () => {
        const wrapper = await createWrapper([
            'flow.editor',
        ]);
        await flushPromises();

        const elementClasses = [
            '.sw-flow-detail-general__general-name',
            '.sw-flow-detail-general__general-description',
            '.sw-flow-detail-general__general-priority',
            '.sw-flow-detail-general__general-active',
        ];

        elementClasses.forEach((element) => {
            const inputElement = wrapper.find(`${element}`);
            expect(inputElement.attributes().disabled).toBeFalsy();
        });
    });

    it('should disabled element when have not privilege', async () => {
        const wrapper = await createWrapper([
            'flow.viewer',
        ]);
        await flushPromises();

        const elementClasses = [
            '.sw-flow-detail-general__general-name',
            '.sw-flow-detail-general__general-description',
            '.sw-flow-detail-general__general-priority',
            '.sw-flow-detail-general__general-active',
        ];

        elementClasses.forEach((element) => {
            const inputElement = wrapper.find(element);
            expect(inputElement.attributes('disabled')).toBeDefined();
        });
    });

    it('should not able to edit flow template', async () => {
        const wrapper = await createWrapper([
            'flow.viewer',
        ]);
        await flushPromises();

        await wrapper.setProps({
            isTemplate: true,
        });
        await flushPromises();

        expect(wrapper.exists('.sw-flow-detail-general__template')).toBe(true);
    });
});
