/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import 'src/app/component/utils/sw-external-link';

const createWrapper = async (props = {}) => {
    return mount(await wrapTestComponent('sw-external-link', { sync: true }), {
        props,
        global: {
            slots: {
                default: 'test external link',
            },
        },
    });
};

describe('components/utils/sw-external-link', () => {
    it('should display the correct link', async () => {
        const wrapper = await createWrapper({ href: 'https://google.com' });
        const anchor = wrapper.find('a');

        expect(anchor.attributes('href')).toBe('https://google.com');
    });

    it('should emit click event if no href is provided', async () => {
        const wrapper = await createWrapper();

        await wrapper.trigger('click');
        await flushPromises();

        expect(wrapper.emitted().click).toBeTruthy();
    });

    it('should render small', async () => {
        const wrapper = await createWrapper({
            href: 'https://google.com',
            small: true,
        });

        expect(wrapper.findComponent('.mt-icon').vm.size).toBe('8px');
        expect(wrapper.classes()).toContain('sw-external-link--small');
    });
});
