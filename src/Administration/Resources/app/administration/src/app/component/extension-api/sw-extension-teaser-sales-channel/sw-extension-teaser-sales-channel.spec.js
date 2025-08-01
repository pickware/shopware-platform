/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-extension-teaser-sales-channel', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-extension-teaser-popover': true,
                },
            },
        },
    );
}

describe('src/app/component/extension-api/sw-extension-teaser-sales-channel', () => {
    let wrapper = null;
    let store = null;

    beforeEach(async () => {
        store = Shopware.Store.get('teaserPopover');
        store.salesChannels = [];
    });

    it('should render correctly', async () => {
        store.addSalesChannel({
            positionId: 'positionId',
            salesChannel: {
                title: 'Facebook',
                description: 'Sell products on Facebook',
                iconName: 'regular-facebook',
            },
            popoverComponent: {
                src: 'http://localhost:8080',
                component: 'button',
                props: {
                    locationId: 'locationId',
                    label: 'Ask AI Copilot',
                },
            },
        });

        wrapper = await createWrapper();
        const salesChannels = wrapper.findAll('.sw-extension-teaser-sales-channel');

        expect(salesChannels).toHaveLength(1);

        const salesChannel = salesChannels[0];
        expect(salesChannel.findComponent('.mt-icon').vm.name).toBe('regular-facebook');
        expect(salesChannel.find('.sw-extension-teaser-sales-channel__item-name').text()).toBe('Facebook');
        expect(salesChannel.find('.sw-extension-teaser-sales-channel__item-description').text()).toBe(
            'Sell products on Facebook',
        );
    });
});
