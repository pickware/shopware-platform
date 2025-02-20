/**
 * @sw-package inventory
 */
import { mount } from '@vue/test-utils';
import 'src/app/component/utils/sw-loader';
import 'src/app/component/base/sw-button';
import 'src/app/component/base/sw-empty-state';
import 'src/module/sw-product/component/sw-product-variants/sw-product-variants-overview';
import ShopwareDiscountCampaignService from 'src/app/service/discount-campaign.service';
import Criteria from 'src/core/data/criteria.data';

async function createWrapper(privileges = []) {
    return mount(await wrapTestComponent('sw-product-detail-variants', { sync: true }), {
        global: {
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: jest.fn(() =>
                            Promise.resolve([
                                {
                                    id: '1',
                                    name: 'group-1',
                                },
                            ]),
                        ),
                        delete: () => {
                            return Promise.resolve();
                        },
                        get: () => {
                            return Promise.resolve({
                                configuratorSettings: [
                                    {
                                        option: {
                                            groupId: 1,
                                        },
                                    },
                                ],
                            });
                        },
                    }),
                },
                acl: {
                    can: (identifier) => {
                        if (!identifier) {
                            return true;
                        }

                        return privileges.includes(identifier);
                    },
                },
            },
            mocks: {
                $tc: (key) => key,
            },
            stubs: {
                'mt-card': {
                    template: `
                    <div class="mt-card">
                        <slot name="grid"></slot>
                        <slot></slot>
                    </div>
                `,
                },
                'sw-data-grid': {
                    props: ['dataSource'],
                    template: `
                  <div class="sw-data-grid">
                  <template v-for="item in dataSource">
                    <slot name="actions" v-bind="{ item }"></slot>
                  </template>
                  </div>
                `,
                },
                'sw-empty-state': await Shopware.Component.build('sw-empty-state'),
                'sw-context-menu-item': true,
                'sw-loader': await Shopware.Component.build('sw-loader'),
                'sw-modal': true,
                'sw-skeleton': true,
                'sw-product-variants-overview': true,
                'sw-tabs': true,
                'sw-tabs-item': true,
                'sw-product-modal-variant-generation': true,
                'sw-product-modal-delivery': true,
                'sw-product-add-properties-modal': true,
                'sw-icon': true,
            },
        },
    });
}

describe('src/module/sw-product/view/sw-product-detail-variants', () => {
    beforeAll(() => {
        Shopware.Service().register('shopwareDiscountCampaignService', () => {
            return new ShopwareDiscountCampaignService();
        });

        const store = Shopware.Store.get('swProductDetail');
        store.$reset();
        store.parentProduct = {
            media: [],
            reviews: [
                {
                    id: '1a2b3c',
                    entity: 'review',
                    customerId: 'd4c3b2a1',
                    productId: 'd4c3b2a1',
                    salesChannelId: 'd4c3b2a1',
                },
            ],
        };
        store.product = {
            isNew: () => false,
            getEntityName: () => 'product',
            media: [],
            reviews: [
                {
                    id: '1a2b3c',
                    entity: 'review',
                    customerId: 'd4c3b2a1',
                    productId: 'd4c3b2a1',
                    salesChannelId: 'd4c3b2a1',
                },
            ],
            purchasePrices: [
                {
                    currencyId: '1',
                    linked: true,
                    gross: 0,
                    net: 0,
                },
            ],
            price: [
                {
                    currencyId: '1',
                    linked: true,
                    gross: 100,
                    net: 84.034,
                },
            ],
            configuratorSettings: [],
            children: [],
        };
        store.modeSettings = [
            'general_information',
            'prices',
            'deliverability',
            'visibility_structure',
            'media',
            'labelling',
        ];
        store.advancedModeSetting = {
            value: {
                settings: [
                    {
                        key: 'general_information',
                        label: 'sw-product.detailBase.cardTitleProductInfo',
                        enabled: true,
                        name: 'general',
                    },
                    {
                        key: 'prices',
                        label: 'sw-product.detailBase.cardTitlePrices',
                        enabled: true,
                        name: 'general',
                    },
                    {
                        key: 'deliverability',
                        label: 'sw-product.detailBase.cardTitleDeliverabilityInfo',
                        enabled: true,
                        name: 'general',
                    },
                    {
                        key: 'visibility_structure',
                        label: 'sw-product.detailBase.cardTitleVisibilityStructure',
                        enabled: true,
                        name: 'general',
                    },
                    {
                        key: 'labelling',
                        label: 'sw-product.detailBase.cardTitleSettings',
                        enabled: true,
                        name: 'general',
                    },
                ],
                advancedMode: {
                    enabled: true,
                    label: 'sw-product.general.textAdvancedMode',
                },
            },
        };
        store.creationStates = 'is-physical';
    });

    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();

        await wrapper.vm.$nextTick();
        await wrapper.setData({
            isLoading: false,
        });

        expect(wrapper.vm).toBeTruthy();
    });

    it('should display a customized empty state if there are neither variants nor properties', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            groups: [{}],
            propertiesAvailable: false,
            isLoading: false,
        });

        await flushPromises();

        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.find('.sw-empty-state__title').text()).toBe('sw-product.variations.emptyStatePropertyTitle');
        expect(wrapper.find('.sw-empty-state__description-content').text()).toBe(
            'sw-product.variations.emptyStatePropertyDescription',
        );
    });

    it('should split the product states string into an array', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            activeTab: 'is-foo,is-bar',
        });
        await flushPromises();

        expect(wrapper.vm.currentProductStates).toEqual([
            'is-foo',
            'is-bar',
        ]);
    });

    it('should be able to load configuration setting with group ids', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            groups: [
                {
                    id: 'group-1',
                },
            ],
            productEntity: {
                configuratorSettings: [
                    { option: { groupId: 'id-1' } },
                    { option: { groupId: 'id-2' } },
                ],
            },
        });
        await flushPromises();
        const criteria = new Criteria(1, null);
        criteria.addFilter(
            Criteria.equalsAny('id', [
                'id-1',
                'id-2',
            ]),
        );

        expect(wrapper.vm.groupRepository.search).toHaveBeenCalledWith(criteria);
        expect(wrapper.vm.configSettingGroups).toEqual([
            {
                id: '1',
                name: 'group-1',
            },
        ]);
    });
});
