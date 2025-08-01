/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper(additionalOptions = {}) {
    return mount(await wrapTestComponent('sw-datepicker', { sync: true }), {
        global: {
            stubs: {
                'mt-datepicker': true,
                'sw-datepicker-deprecated': true,
            },
        },
        props: {},
        ...additionalOptions,
    });
}

describe('src/app/component/base/sw-datepicker', () => {
    it('should render the mt-datepicker', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.html()).toContain('mt-datepicker');
    });
});
