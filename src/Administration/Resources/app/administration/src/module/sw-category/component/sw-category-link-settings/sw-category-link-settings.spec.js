/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';

async function createWrapper(category = {}) {
    const responses = global.repositoryFactoryMock.responses;

    responses.addResponse({
        method: 'Post',
        url: '/search/category',
        status: 200,
        response: {
            data: [
                {
                    id: Shopware.Utils.createId(),
                    attributes: {
                        id: Shopware.Utils.createId(),
                    },
                    relationships: [],
                },
            ],
        },
    });

    return mount(await wrapTestComponent('sw-category-link-settings', { sync: true }), {
        global: {
            stubs: {
                'mt-card': {
                    template: '<div class="mt-card"><slot></slot></div>',
                },
                'sw-single-select': true,
                'sw-entity-single-select': true,

                'sw-category-tree-field': true,
            },
        },
        props: {
            category,
        },
    });
}

describe('src/module/sw-category/component/sw-category-link-settings', () => {
    beforeEach(() => {
        global.activeAclRoles = [];
    });

    it('should have an enabled text field for old configuration', async () => {
        global.activeAclRoles = ['category.editor'];
        const wrapper = await createWrapper({
            linkType: null,
            externalLink: 'https://',
        });

        const linkTypeField = wrapper.find('sw-single-select-stub');
        expect(linkTypeField.attributes().disabled).toBeFalsy();
        expect(linkTypeField.attributes().options).toBeTruthy();
        expect(wrapper.vm.linkTypeValues).toHaveLength(2);

        const textField = wrapper.findComponent('.mt-text-field');
        expect(textField.props().disabled).toBeFalsy();

        const newTabField = wrapper.find('.mt-switch');
        expect(newTabField.attributes().disabled).toBeFalsy();
    });

    it('should have an enabled text field for external link', async () => {
        global.activeAclRoles = ['category.editor'];

        const wrapper = await createWrapper({
            linkType: 'external',
        });

        const linkTypeField = wrapper.find('sw-single-select-stub');
        expect(linkTypeField.attributes().disabled).toBeFalsy();
        expect(linkTypeField.attributes().options).toBeTruthy();
        expect(wrapper.vm.linkTypeValues).toHaveLength(2);

        const textField = wrapper.findComponent('.mt-text-field');
        expect(textField.props().disabled).toBeFalsy();

        const newTabField = wrapper.find('.mt-switch');
        expect(newTabField.attributes().disabled).toBeFalsy();
    });

    it('should have enabled select fields for internal link', async () => {
        global.activeAclRoles = ['category.editor'];

        const wrapper = await createWrapper({
            linkType: 'product',
        });

        const selects = wrapper.findAll('sw-single-select-stub');
        expect(selects).toHaveLength(2);

        const linkTypeField = selects.at(0);
        expect(linkTypeField.attributes().disabled).toBeFalsy();
        expect(linkTypeField.attributes().options).toBeTruthy();
        expect(wrapper.vm.linkTypeValues).toHaveLength(2);

        const internalTypeField = selects.at(1);
        expect(internalTypeField.attributes().disabled).toBeFalsy();
        expect(internalTypeField.attributes().options).toBeTruthy();
        expect(wrapper.vm.entityValues).toHaveLength(3);

        const productSelectField = wrapper.find('sw-entity-single-select-stub');
        expect(productSelectField.attributes().disabled).toBeFalsy();
        expect(productSelectField.attributes().entity).toBe('product');

        const newTabField = wrapper.find('.mt-switch');
        expect(newTabField.attributes().disabled).toBeFalsy();
    });

    it('should have correct select fields on entity switch', async () => {
        global.activeAclRoles = ['category.editor'];

        const wrapper = await createWrapper({
            linkType: 'product',
            internalLink: 'someUuid',
        });

        const productSelectField = wrapper.find('sw-entity-single-select-stub');
        expect(productSelectField.attributes().entity).toBe('product');
        expect(wrapper.vm.category.internalLink).toBe('someUuid');
    });

    it('should clean up on switch to internal', async () => {
        global.activeAclRoles = ['category.editor'];

        const wrapper = await createWrapper({
            linkType: 'external',
            externalLink: 'https://',
        });

        await wrapper.getComponent('.sw-category-link-settings__type').vm.$emit('update:value', 'internal');

        expect(wrapper.vm.category.externalLink).toBeNull();
    });

    it('should clean up on switch to external', async () => {
        global.activeAclRoles = ['category.editor'];

        const wrapper = await createWrapper({
            linkType: 'internal',
            internalLink: 'someUuid',
        });

        await wrapper.getComponent('.sw-category-link-settings__type').vm.$emit('update:value', 'external');

        expect(wrapper.vm.category.internalLink).toBeNull();
    });

    it('should have disabled fields with no rights', async () => {
        const wrapper = await createWrapper({
            linkType: 'external',
        });

        const linkTypeField = wrapper.find('sw-single-select-stub');
        expect(linkTypeField.attributes().disabled).toBeTruthy();

        const externalLinkField = wrapper.findComponent('.mt-text-field');
        expect(externalLinkField.props().disabled).toBeTruthy();

        const newTabField = wrapper.findComponent('.mt-switch');
        expect(newTabField.props().disabled).toBe(true);
    });

    it('should have correct internal link', async () => {
        global.activeAclRoles = ['category.editor'];

        const wrapper = await createWrapper({
            linkType: 'category',
            internalLink: 'someUuid',
        });

        wrapper.find('sw-category-tree-field-stub');
        expect(wrapper.vm.category.internalLink).toBe('someUuid');
    });
});
