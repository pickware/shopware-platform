/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

describe('components/base/sw-alert-deprecated', () => {
    let wrapper;

    it('should render correctly', async () => {
        const title = 'Alert title';
        const message = '<p>Alert message</p>';

        wrapper = mount(await wrapTestComponent('sw-alert-deprecated', { sync: true }), {
            props: {
                title,
            },
            slots: {
                default: message,
            },
        });

        expect(wrapper.get('.sw-alert__title').text()).toBe(title);
        expect(wrapper.get('.sw-alert__message').html()).toContain(message);
    });

    it.each([
        [
            'info',
            'default',
            true,
        ],
        [
            'warning',
            'default',
            true,
        ],
        [
            'error',
            'default',
            true,
        ],
        [
            'success',
            'default',
            true,
        ],
        [
            'info',
            'notification',
            true,
        ],
        [
            'warning',
            'notification',
            true,
        ],
        [
            'error',
            'notification',
            true,
        ],
        [
            'success',
            'notification',
            true,
        ],
        [
            'info',
            'system',
            false,
        ],
        [
            'warning',
            'system',
            false,
        ],
        [
            'error',
            'system',
            false,
        ],
        [
            'success',
            'system',
            false,
        ],
        [
            'neutral',
            'default',
            true,
        ],
        [
            'neutral',
            'notification',
            true,
        ],
        [
            'neutral',
            'system',
            false,
        ],
    ])('applies variant class %s to %s is %s', async (variant, appearance, applied) => {
        wrapper = mount(await wrapTestComponent('sw-alert-deprecated', { sync: true }), {
            props: {
                appearance: appearance,
                variant: variant,
            },
        });

        expect(wrapper.get('.sw-alert').classes(`sw-alert--${variant}`)).toBe(applied);
    });
});
