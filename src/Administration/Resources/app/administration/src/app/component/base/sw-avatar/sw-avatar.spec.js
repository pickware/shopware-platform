/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

describe('components/base/sw-avatar', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = mount(await wrapTestComponent('sw-avatar', { sync: true }));
    });

    it('should change the variant to a square', async () => {
        await wrapper.setProps({
            variant: 'square',
        });

        expect(wrapper.get('span').classes()).toContain('sw-avatar__square');
    });
});
