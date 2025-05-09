/**
 * @sw-package after-sales
 */

import { mount } from '@vue/test-utils';

const createWrapper = async () => {
    return mount(
        await wrapTestComponent('sw-mail-template-index', {
            sync: true,
        }),
        {
            global: {
                provide: {
                    searchRankingService: {},
                },
                mocks: {
                    $route: {
                        query: {
                            page: 1,
                            limit: 25,
                        },
                    },
                },
                stubs: {
                    'sw-page': {
                        template: `
                    <div class="sw-page">
                        <slot name="smart-bar-actions"></slot>
                        <slot></slot>
                    </div>`,
                    },
                    'sw-card-view': {
                        template: '<div><slot></slot></div>',
                    },
                    'sw-container': {
                        template: '<div><slot></slot></div>',
                    },
                    'sw-context-button': {
                        template: `
                    <div class="sw-context-button">
                        <slot name="button"></slot>
                        <slot></slot>
                     </div>`,
                    },
                    'sw-context-menu-item': true,
                    'sw-search-bar': true,
                    'sw-language-switch': true,
                    'sw-mail-template-list': true,
                    'sw-mail-header-footer-list': true,
                },
            },
        },
    );
};

describe('modules/sw-mail-template/page/sw-mail-template-index', () => {
    it('should not allow to create', async () => {
        const wrapper = await createWrapper();

        const createButton = wrapper.findByText('button', 'global.default.add');

        expect(createButton.attributes('disabled') !== undefined).toBe(true);
    });

    it('should allow to create', async () => {
        global.activeAclRoles = ['mail_templates.creator'];

        const wrapper = await createWrapper();

        const createButton = wrapper.findByText('button', 'global.default.add');

        expect(createButton.attributes('disabled')).toBeUndefined();
    });
});
