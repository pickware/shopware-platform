/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-cms-section-actions', {
            sync: true,
        }),
        {
            props: {
                section: {},
            },
        },
    );
}

describe('module/sw-cms/component/sw-cms-section-actions', () => {
    beforeAll(() => {
        Shopware.Store.register({
            id: 'cmsPage',
            state: () => ({
                selectedSection: {},
            }),
            actions: {
                setSection: () => {},
            },
        });
    });

    it('should contain disabled styling', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            disabled: true,
        });

        expect(wrapper.classes()).toContain('is--disabled');
    });

    it('should not contain disabled styling', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.classes()).not.toContain('is--disabled');
    });
});
