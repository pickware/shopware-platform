/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-cms-el-preview-cross-selling', {
            sync: true,
        }),
    );
}

describe('src/module/sw-cms/elements/cross-selling/preview', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });
});
