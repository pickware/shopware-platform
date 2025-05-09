import { mount } from '@vue/test-utils';

/**
 * @sw-package framework
 */
async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-settings-usage-data', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-page': {
                        template: `
                        <div class="sw-page">
                            <slot name="smart-bar-header"></slot>
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content"></slot>
                        </div>`,
                    },
                    'sw-card-view': await wrapTestComponent('sw-card-view'),
                    'sw-tabs': await wrapTestComponent('sw-tabs'),
                    'sw-tabs-deprecated': await wrapTestComponent('sw-tabs-deprecated', { sync: true }),
                    'router-view': true,
                    'sw-search-bar': true,
                    'sw-tabs-item': true,
                    'sw-error-summary': true,
                    'sw-extension-component-section': true,
                },
            },
        },
    );
}

describe('src/module/sw-settings-usage-data/page/sw-settings-usage-data', () => {
    let wrapper;

    it('should show tabs', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const tabs = wrapper.findComponent({
            name: 'sw-tabs-deprecated__wrapped',
        });
        expect(tabs.isVisible()).toBe(true);
        expect(tabs.vm.positionIdentifier).toBe('sw-settings-usage-data');
    });
});
