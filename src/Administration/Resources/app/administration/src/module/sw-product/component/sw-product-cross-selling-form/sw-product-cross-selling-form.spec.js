/**
 * @sw-package inventory
 */
import { mount } from '@vue/test-utils';

const { Store } = Shopware;

const productMock = {
    id: 'productId',
    properties: [],
};

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-product-cross-selling-form', {
            sync: true,
        }),
        {
            props: {
                crossSelling: {},
                allowEdit: false,
            },
            global: {
                stubs: {
                    'sw-container': true,
                    'sw-context-button': true,
                    'sw-text-field': true,
                    'sw-context-menu-item': true,
                    'sw-select-field': true,
                    'sw-entity-single-select': true,
                    'sw-product-cross-selling-assignment': true,
                    'sw-product-stream-modal-preview': true,
                    'sw-modal': true,
                    'sw-condition-tree': true,
                },
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                get: () => {
                                    return Promise.resolve([]);
                                },
                                search: () => {
                                    return Promise.resolve([]);
                                },
                            };
                        },
                    },
                    productStreamConditionService: {
                        search: () => {},
                    },
                },
            },
        },
    );
}

describe('module/sw-product/component/sw-product-cross-selling-form', () => {
    let wrapper;

    beforeAll(() => {
        Store.get('swProductDetail').$reset();
        Store.get('swProductDetail').product = productMock;
    });

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should get correct sorting types', async () => {
        wrapper = await createWrapper();
        await wrapper.setData({
            productStream: {
                filters: {
                    entity: 'product',
                    source: 'source',
                },
            },
        });

        expect(wrapper.vm.sortingTypes).toEqual([
            {
                label: 'sw-product.crossselling.priceDescendingSortingType',
                value: 'cheapestPrice:DESC',
            },
            {
                label: 'sw-product.crossselling.priceAscendingSortingType',
                value: 'cheapestPrice:ASC',
            },
            {
                label: 'sw-product.crossselling.nameSortingType',
                value: 'name:ASC',
            },
            {
                label: 'sw-product.crossselling.releaseDateDescendingSortingType',
                value: 'releaseDate:DESC',
            },
            {
                label: 'sw-product.crossselling.releaseDateAscendingSortingType',
                value: 'releaseDate:ASC',
            },
        ]);
    });
});
