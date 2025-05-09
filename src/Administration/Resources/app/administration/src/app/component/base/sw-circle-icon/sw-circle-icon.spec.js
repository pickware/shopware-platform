/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

describe('components/base/sw-circle-icon', () => {
    let wrapper;
    let stubs;

    beforeAll(async () => {
        stubs = {
            'sw-label': await wrapTestComponent('sw-label'),
            'sw-color-badge': true,
        };
    });

    async function createWrapper(props) {
        return mount(await wrapTestComponent('sw-circle-icon', { sync: true }), {
            props,
            global: {
                stubs,
            },
        });
    }

    it('passes default values', async () => {
        wrapper = await createWrapper({
            iconName: 'regular-checkmark',
        });
        await flushPromises();

        const swLabel = wrapper.getComponent({ name: 'sw-label__wrapped' });
        expect(swLabel.props('variant')).toBe('');
        expect(swLabel.props('appearance')).toBe('circle');
        expect(swLabel.props('dismissable')).toBe(false);

        const { width, height } = wrapper.element.style;

        expect(width).toBe('50px');
        expect(height).toBe('50px');

        const icon = wrapper.getComponent('.mt-icon');

        expect(icon.props('name')).toBe('regular-checkmark');
        expect(icon.props('size')).toBe('25px');
    });

    it('passes variant correctly', async () => {
        wrapper = await createWrapper({
            iconName: 'regular-checkmark',
            variant: 'danger',
        });

        const swLabel = wrapper.findComponent({ name: 'sw-label__wrapped' });

        expect(swLabel.props('variant')).toBe('danger');
    });

    it('passes size correctly', async () => {
        const size = 72;

        wrapper = await createWrapper({
            iconName: 'regular-checkmark',
            size,
        });

        const { width, height } = wrapper.element.style;

        expect(width).toBe(`${size}px`);
        expect(height).toBe(`${size}px`);

        const icon = wrapper.getComponent('.mt-icon');
        expect(icon.props('size')).toBe('36px');
    });
});
