import { mount } from '@vue/test-utils';

/**
 * @sw-package checkout
 */

async function createWrapper() {
    return mount(await wrapTestComponent('sw-order-delivery-metadata', { sync: true }), {
        props: {
            delivery: {
                shippingMethod: {
                    translated: {
                        name: '',
                    },
                },
                shippingOrderAddress: {
                    country: {
                        addressFormat: [
                            [{ type: 'snippet', value: 'address/company' }],
                        ],
                    },
                },
            },
            order: {
                currency: {
                    isoCode: 'EUR',
                    symbol: '€',
                },
            },
        },
        global: {
            stubs: {
                'sw-container': await wrapTestComponent('sw-container', {
                    sync: true,
                }),
                'sw-address': await wrapTestComponent('sw-address', {
                    sync: true,
                }),
                'sw-description-list': await wrapTestComponent('sw-description-list', { sync: true }),
                'sw-extension-component-section': true,
                'sw-ai-copilot-badge': true,
                'sw-context-button': true,
                'sw-loader': true,
                'router-link': true,
            },
            provide: {
                customSnippetApiService: {
                    render() {
                        return Promise.resolve({
                            rendered:
                                'Christa Stracke<br/> \\n \\n Philip Inlet<br/> \\n \\n \\n \\n 22005-3637 New Marilyneside<br/> \\n \\n Moldova (Republic of)<br/><br/>',
                        });
                    },
                },
            },
        },
    });
}

describe('module/sw-order/component/sw-order-delivery-metadata', () => {
    let wrapper;

    it('should be a Vue.JS component', async () => {
        wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render formatting address for delivery address', async () => {
        wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-address .sw-address__formatting').text()).toBe(
            'Christa Stracke \\n \\n Philip Inlet \\n \\n \\n \\n 22005-3637 New Marilyneside \\n \\n Moldova (Republic of)',
        );
    });
});
