/**
 * @sw-package discovery
 */

import template from './sw-sales-channel-products-assignment-modal.html.twig';
import './sw-sales-channel-products-assignment-modal.scss';

const { uniqBy } = Shopware.Utils.array;

const updateElementVisibility = (element, binding) => {
    element.style.visibility = binding.value ? 'visible' : 'hidden';
    element.style.position = binding.value ? 'static' : 'absolute';
    element.style.top = binding.value ? 'auto' : '0';
    element.style.left = binding.value ? 'auto' : '0';
    element.style.bottom = binding.value ? 'auto' : '0';
    element.style.right = binding.value ? 'auto' : '0';
    element.style.transform = binding.value ? 'translateX(0)' : 'translateX(100%)';
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    emits: [
        'modal-close',
        'products-add',
    ],

    directives: {
        hide: {
            beforeMount: updateElementVisibility,
            updated: updateElementVisibility,
        },
    },

    props: {
        salesChannel: {
            type: Object,
            required: true,
        },

        isAssignProductLoading: {
            type: Boolean,
            required: true,
        },
    },

    data() {
        return {
            singleProducts: [],
            categoryProducts: [],
            groupProducts: [],
            isProductLoading: false,
            tabContentHeight: '600px',
            productContainerStyle: {
                display: 'grid',
                placeItems: 'stretch',
            },
            categoryContainerStyle: {
                display: 'grid',
                placeItems: 'stretch',
            },
            productGroupContainerStyle: {
                display: 'grid',
                placeItems: 'stretch',
            },
        };
    },

    computed: {
        productCount() {
            return this.products.length;
        },

        products() {
            return uniqBy(
                [
                    ...this.singleProducts,
                    ...this.categoryProducts,
                    ...this.groupProducts,
                ],
                'id',
            );
        },
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        mountedComponent() {
            this.getProductContainerStyle();
            this.getCategoryContainerStyle();
            this.getProductGroupContainerStyle();
        },

        getProductContainerStyle() {
            // eslint-disable-next-line max-len
            const cardSectionSecondaryHeight = `${this.$refs?.product?.$refs?.cardSectionSecondary?.$el?.offsetHeight ?? 0}px`;

            this.productContainerStyle['grid-template-rows'] =
                `auto calc(${this.tabContentHeight} - ${cardSectionSecondaryHeight})`;
        },

        getCategoryContainerStyle() {
            const tabContentGutter = '20px';
            const alertHeight = `${this.$refs?.category?.$refs?.alert?.$el?.offsetHeight ?? 0}px`;
            // eslint-disable-next-line max-len
            const cardSectionSecondaryHeight = `${this.$refs?.category?.$refs?.cardSectionSecondary?.$el?.offsetHeight ?? 0}px`;

            this.categoryContainerStyle['grid-template-rows'] =
                `auto calc(${this.tabContentHeight} - (${tabContentGutter} + ${alertHeight} + ${
                    cardSectionSecondaryHeight
                }))`;
        },

        getProductGroupContainerStyle() {
            const tabContentGutter = '20px';
            const alertHeight = `${this.$refs?.productGroup?.$refs?.alert?.$el?.offsetHeight ?? 0}px`;
            // eslint-disable-next-line max-len
            const cardSectionSecondaryHeight = `${this.$refs?.productGroup?.$refs?.cardSectionSecondary?.$el?.offsetHeight ?? 0}px`;

            this.productGroupContainerStyle['grid-template-rows'] =
                `auto calc(${this.tabContentHeight} - (${tabContentGutter} + ${alertHeight} + ${
                    cardSectionSecondaryHeight
                }))`;
        },

        onChangeSelection(products, type) {
            this[type] = products;
        },

        onCloseModal() {
            this.$emit('modal-close');
        },

        onAddProducts() {
            this.$emit('products-add', this.products);
        },

        setProductLoading(isProductLoading) {
            this.isProductLoading = isProductLoading;
        },
    },
};
