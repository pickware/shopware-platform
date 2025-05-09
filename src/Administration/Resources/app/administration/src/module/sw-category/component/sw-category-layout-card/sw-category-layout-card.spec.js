/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';

const categoryId = 'some-category-id';
const cmsPageId = 'some-cms-page-id';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-category-layout-card', { sync: true }), {
        global: {
            stubs: {
                'router-link': true,
                'sw-loader': true,
                'sw-cms-list-item': {
                    template: '<div class="sw-cms-list-item"></div>',
                    props: ['disabled'],
                },
                'mt-card': {
                    template: '<div class="mt-card"><slot></slot></div>',
                },
                'sw-cms-layout-modal': true,
            },
            mocks: {
                $route: {
                    params: {},
                },
            },
            provide: {
                cmsPageTypeService: {
                    getType(type) {
                        return {
                            title: type,
                        };
                    },
                },
            },
        },
        props: {
            category: {
                id: categoryId,
                cmsPageId,
            },
        },
    });
}

describe('src/module/sw-category/component/sw-category-layout-card', () => {
    beforeEach(() => {
        global.activeAclRoles = [];
    });

    it('should have an enabled cms list item', async () => {
        global.activeAclRoles = ['category.editor'];

        const wrapper = await createWrapper();

        const cmsListItem = wrapper.getComponent('.sw-cms-list-item');

        expect(cmsListItem.props('disabled')).toBe(false);
    });

    it('should have an disabled cms list item', async () => {
        const wrapper = await createWrapper();

        const cmsListItem = wrapper.getComponent('.sw-cms-list-item');

        expect(cmsListItem.props('disabled')).toBe(true);
    });

    it('should have an enabled button for changing the layout', async () => {
        global.activeAclRoles = ['category.editor'];

        const wrapper = await createWrapper();

        const changeLayoutButton = wrapper.find('.sw-category-detail-layout__change-layout-action');

        expect(changeLayoutButton.attributes('disabled')).toBeUndefined();
    });

    it('should have an disabled button for changing the layout', async () => {
        const wrapper = await createWrapper();

        const changeLayoutButton = wrapper.find('.sw-category-detail-layout__change-layout-action');

        expect(changeLayoutButton.attributes('disabled') === undefined).toBe(false);
    });

    it('should have an enabled button for open the page builder', async () => {
        global.activeAclRoles = ['category.editor'];

        const wrapper = await createWrapper();

        const pageBuilderButton = wrapper.find('.sw-category-detail-layout__open-in-pagebuilder');

        expect(pageBuilderButton.attributes('disabled')).toBeUndefined();
    });

    it('should have an disabled button for open the page builder', async () => {
        const wrapper = await createWrapper();

        const pageBuilderButton = wrapper.find('.sw-category-detail-layout__open-in-pagebuilder');

        expect(pageBuilderButton.attributes('disabled') !== undefined).toBe(true);
    });

    it('should have an enabled button for resetting the layout', async () => {
        global.activeAclRoles = ['category.editor'];

        const wrapper = await createWrapper();

        await wrapper.setProps({
            cmsPage: {
                type: 'landingpage',
            },
        });
        await flushPromises();

        const resetLayoutButton = wrapper.find('.sw-category-detail-layout__layout-reset');

        expect(resetLayoutButton.attributes('disabled')).toBeUndefined();
    });

    it('should have an disabled button for resetting the layout', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            cmsPage: {
                type: 'landingpage',
            },
        });
        await flushPromises();

        const resetLayoutButton = wrapper.find('.sw-category-detail-layout__layout-reset');

        expect(resetLayoutButton.attributes('disabled') !== undefined).toBe(true);
    });

    it('should pass the category id and type to the sw.cms.create route', async () => {
        global.activeAclRoles = ['category.editor'];
        const wrapper = await createWrapper();

        await wrapper.find('button.sw-category-detail-layout__open-in-pagebuilder').trigger('click');

        const routerPush = wrapper.vm.$router.push;

        expect(routerPush).toHaveBeenCalledTimes(1);
        expect(routerPush).toHaveBeenLastCalledWith({
            name: 'sw.cms.create',
            params: {
                id: categoryId,
                type: 'category',
            },
        });
    });

    it('should pass the category id to the sw.cms.create route', async () => {
        global.activeAclRoles = ['category.editor'];
        const wrapper = await createWrapper();

        await wrapper.setProps({
            cmsPage: {
                id: cmsPageId,
                type: 'landingpage',
            },
        });

        await wrapper.find('button.sw-category-detail-layout__open-in-pagebuilder').trigger('click');

        const routerPush = wrapper.vm.$router.push;

        expect(routerPush).toHaveBeenCalledTimes(1);
        expect(routerPush).toHaveBeenLastCalledWith({
            name: 'sw.cms.detail',
            params: { id: cmsPageId },
        });
    });
});
