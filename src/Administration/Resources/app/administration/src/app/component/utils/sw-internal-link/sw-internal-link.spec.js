/**
 * @sw-package framework
 */

import { mount, RouterLinkStub } from '@vue/test-utils';

// initial component setup
const setup = async (propOverride) => {
    const props = {
        routerLink: { name: 'sw.product.index' },
        ...propOverride,
    };

    return mount(await wrapTestComponent('sw-internal-link', { sync: true }), {
        global: {
            stubs: {
                RouterLink: RouterLinkStub,
            },
        },
        slots: {
            default: 'test internal link',
        },
        props,
    });
};

describe('components/utils/sw-internal-link', () => {
    it('should render correctly', async () => {
        const wrapper = await setup();
        expect(wrapper.find('.sw-internal-link').classes()).not.toContain('sw-internal-link--disabled');
    });

    it('should render correctly when disabled', async () => {
        const wrapper = await setup({ disabled: true });
        expect(wrapper.find('.sw-internal-link').classes()).toContain('sw-internal-link--disabled');
    });

    it('should add custom target to link', async () => {
        const wrapper = await setup({ target: '_blank' });

        expect(wrapper.findComponent({ name: 'RouterLinkStub' }).props().to).toEqual({ name: 'sw.product.index' });
    });

    it('should add inline class if it is an inline link', async () => {
        const wrapper = await setup({ inline: true });

        expect(wrapper.findComponent({ name: 'RouterLinkStub' }).classes()).toContain('sw-internal-link--inline');
    });

    it('should allow links without router-links', async () => {
        const wrapper = await setup({
            routerLink: undefined,
        });

        expect(wrapper.find('a').exists()).toBe(true);
    });

    it('should emit click event on non-router links', async () => {
        const wrapper = await setup({
            routerLink: undefined,
        });

        expect(wrapper.emitted('click')).toBeFalsy();

        await wrapper.find('a').trigger('click');

        expect(wrapper.emitted('click')[0]).toEqual([]);
    });
});
