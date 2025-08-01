/**
 * @sw-package fundamentals@discovery
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-settings-snippet-filter-switch', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field'),
                    'sw-checkbox-field-deprecated': await wrapTestComponent('sw-checkbox-field-deprecated', { sync: true }),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-field-error': await wrapTestComponent('sw-field-error'),
                    'sw-inheritance-switch': true,
                    'sw-ai-copilot-badge': true,
                    'sw-help-text': true,
                },
            },
            props: {
                name: 'Shopware',
            },
        },
    );
}

describe('sw-settings-snippet-filter-switch', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
    });

    it('should contain a prop property, called: value', async () => {
        expect(wrapper.vm.value).toBe(false);
        await wrapper.setProps({
            value: true,
        });
        expect(wrapper.vm.value).toBe(true);

        const fieldSwitchInput = wrapper.find('.mt-switch input');
        expect(fieldSwitchInput.attributes('name')).toBe('Shopware');
    });
});
