import { mount } from '@vue/test-utils';

/**
 * @sw-package checkout
 */

let repositoryFactoryMock;

async function createWrapper(privileges = []) {
    repositoryFactoryMock = {
        saveAll: () => {
            return Promise.resolve();
        },
    };

    return mount(
        await wrapTestComponent('sw-settings-payment-sorting-modal', {
            sync: true,
        }),
        {
            props: {
                paymentMethods: [
                    {
                        id: '1a',
                        position: 1,
                    },
                    {
                        id: '2b',
                        position: 2,
                    },
                ],
            },
            global: {
                renderStubDefaultSlot: true,
                provide: {
                    acl: {
                        can: (identifier) => {
                            if (!identifier) {
                                return true;
                            }

                            return privileges.includes(identifier);
                        },
                    },
                    repositoryFactory: {
                        create: () => {
                            return repositoryFactoryMock;
                        },
                    },
                },
                stubs: {
                    'sw-modal': true,
                    'sw-sortable-list': true,
                    'sw-button-process': true,
                    'sw-media-preview-v2': true,
                },
            },
        },
    );
}

describe('module/sw-settings-payment/component/sw-settings-payment-sorting-modal', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should save reordered methods', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.vm.sortedPaymentMethods = [
            wrapper.vm.sortedPaymentMethods[1],
            wrapper.vm.sortedPaymentMethods[0],
        ];

        wrapper.vm.paymentMethodRepository.saveAll = jest.fn(() => Promise.resolve());

        await wrapper.vm.applyChanges();

        expect(wrapper.vm.paymentMethodRepository.saveAll).toHaveBeenCalledWith(
            [
                {
                    id: '2b',
                    position: 1,
                },
                {
                    id: '1a',
                    position: 2,
                },
            ],
            Shopware.Context.api,
        );

        wrapper.vm.paymentMethodRepository.saveAll.mockRestore();
    });
});
