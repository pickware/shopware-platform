/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-cms-el-preview-sidebar-filter', {
            sync: true,
        }),
    );
}

describe('src/module/sw-cms/elements/sidebar-filter/preview', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });
});
