/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper(propsData = {}) {
    return mount(await wrapTestComponent('sw-label', { sync: true }), {
        global: {
            stubs: {
                'sw-color-badge': true,
            },
        },
        props: propsData,
    });
}

describe('src/app/component/base/sw-label', () => {
    it('should be dismissable', async () => {
        const wrapper = await createWrapper({ dismissable: true });

        expect(wrapper.find('sw-label__dismiss')).toBeTruthy();
    });

    it('should not be dismissable', async () => {
        const wrapper = await createWrapper({ dismissable: false });

        expect(wrapper.find('sw-label__dismiss').exists()).toBeFalsy();
    });
});
