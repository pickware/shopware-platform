/*
 * @sw-package inventory
 */

import template from './sw-product-price-form.html.twig';
import './sw-product-price-form.scss';

const { Mixin } = Shopware;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    mixins: [
        Mixin.getByName('placeholder'),
    ],

    props: {
        allowEdit: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },
    },

    data() {
        return {
            displayMaintainCurrencies: false,
        };
    },

    computed: {
        isLoading() {
            return Shopware.Store.get('swProductDetail').isLoading;
        },

        defaultPrice() {
            return Shopware.Store.get('swProductDetail').defaultPrice;
        },

        defaultCurrency() {
            return Shopware.Store.get('swProductDetail').defaultCurrency;
        },

        productTaxRate() {
            return Shopware.Store.get('swProductDetail').productTaxRate;
        },

        showModeSetting() {
            return Shopware.Store.get('swProductDetail').showModeSetting;
        },

        product() {
            return Shopware.Store.get('swProductDetail').product;
        },

        parentProduct() {
            return Shopware.Store.get('swProductDetail').parentProduct;
        },

        taxes() {
            return Shopware.Store.get('swProductDetail').taxes;
        },

        currencies() {
            return Shopware.Store.get('swProductDetail').currencies;
        },

        ...mapPropertyErrors('product', [
            'taxId',
            'price',
            'purchasePrices',
        ]),

        taxRateHelpText() {
            const link = {
                name: 'sw.settings.tax.index',
            };

            return this.$tc(
                'sw-product.priceForm.taxRateHelpText.label',
                {
                    link: `<sw-internal-link
                           :router-link=${JSON.stringify(link)}
                           :inline="true">
                           ${this.$tc('sw-product.priceForm.taxRateHelpText.linkText')}
                      </sw-internal-link>`,
                },
                0,
            );
        },

        prices: {
            get() {
                const prices = {
                    price: [],
                    purchasePrices: [],
                };

                if (this.product && Array.isArray(this.product.price)) {
                    prices.price = [...this.product.price];
                }

                if (this.product && Array.isArray(this.product.purchasePrices)) {
                    prices.purchasePrices = [...this.product.purchasePrices];
                }

                return prices;
            },

            set(newValue) {
                this.product.price = newValue?.price || null;
                this.product.purchasePrices = newValue?.purchasePrices || null;
            },
        },

        parentPrices() {
            return {
                price: this.product.price || this.parentProduct.price,
                purchasePrices: this.product.purchasePrices || this.parentProduct.purchasePrices,
            };
        },

        taxRateOptions() {
            return this.taxes.map((tax) => {
                return {
                    id: tax.id,
                    value: tax.id,
                    label: this.getTaxLabel(tax),
                };
            });
        },
    },

    methods: {
        removePriceInheritation(refPrice) {
            const defaultRefPrice = refPrice.price?.find((price) => price.currencyId === this.defaultCurrency.id);
            const defaultRefPurchasePrice = refPrice.purchasePrices?.find(
                (price) => price.currencyId === this.defaultCurrency.id,
            );

            const prices = {
                price: [],
                purchasePrices: [],
            };

            if (defaultRefPrice) {
                prices.price.push({
                    currencyId: defaultRefPrice.currencyId,
                    gross: defaultRefPrice.gross,
                    net: defaultRefPrice.net,
                    linked: defaultRefPrice.linked,
                    listPrice: defaultRefPrice.listPrice ? defaultRefPrice.listPrice : null,
                    regulationPrice: defaultRefPrice.regulationPrice ? defaultRefPrice.regulationPrice : null,
                });
            }

            if (defaultRefPurchasePrice) {
                prices.purchasePrices.push({
                    currencyId: defaultRefPurchasePrice.currencyId,
                    gross: defaultRefPurchasePrice.gross,
                    net: defaultRefPurchasePrice.net,
                    linked: defaultRefPurchasePrice.linked,
                });
            }

            return prices;
        },

        inheritationCheckFunction() {
            return !this.prices.price.length && !this.prices.purchasePrices.length;
        },

        onMaintainCurrenciesClose(prices) {
            this.product.price = prices;

            this.displayMaintainCurrencies = false;
        },

        getTaxLabel(tax) {
            if (this.$te(`global.tax-rates.${tax.name}`)) {
                return this.$tc(`global.tax-rates.${tax.name}`);
            }

            return tax.name;
        },

        updatePrices(index) {
            this.product.price.splice(index, 1);
        },
    },
};
