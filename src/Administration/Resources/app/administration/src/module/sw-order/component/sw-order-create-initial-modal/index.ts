import template from './sw-order-create-initial-modal.html.twig';
import './sw-order-create-initial-modal.scss';

import type { Cart, LineItem, SalesChannelContext, ContextSwitchParameters, CartDelivery } from '../../order.types';

import { LineItemType } from '../../order.types';

const { Component, Store, Mixin, Service } = Shopware;

interface PromotionCodeItem {
    type: string;
    referencedId: string;
}

/**
 * @sw-package checkout
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Component.wrapComponentConfig({
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('cart-notification'),
    ],

    data(): {
        isLoading: boolean;
        isProductGridLoading: boolean;
        disabledAutoPromotion: boolean;
        promotionCodes: string[];
        productItems: LineItem[];
        context: ContextSwitchParameters;
        shippingCosts: number | null;
    } {
        return {
            productItems: [],
            promotionCodes: [],
            isLoading: false,
            isProductGridLoading: false,
            disabledAutoPromotion: false,
            shippingCosts: null,
            context: {
                currencyId: '',
                paymentMethodId: '',
                shippingMethodId: '',
                languageId: '',
                billingAddressId: '',
                shippingAddressId: '',
            },
        };
    },

    computed: {
        salesChannelId(): string {
            return this.customer?.salesChannelId ?? '';
        },

        salesChannelContext(): SalesChannelContext {
            return Store.get('swOrder').context;
        },

        currency(): Entity<'currency'> {
            return this.salesChannelContext.currency;
        },

        cart(): Cart {
            return Store.get('swOrder').cart;
        },

        customer(): Entity<'customer'> | null {
            return Store.get('swOrder').customer;
        },

        isCustomerActive(): boolean {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-return,@typescript-eslint/no-unsafe-member-access
            return Store.get('swOrder').isCustomerActive;
        },

        promotionCodeItems(): PromotionCodeItem[] {
            return this.promotionCodes.map((code) => {
                return {
                    type: LineItemType.PROMOTION,
                    referencedId: code,
                };
            });
        },

        cartDelivery(): CartDelivery | null {
            return this.cart?.deliveries[0] as CartDelivery | null;
        },
    },

    watch: {
        salesChannelContext(value: SalesChannelContext): void {
            // Update context after switching customer successfully
            this.context = {
                ...this.context,
                currencyId: value.context.currencyId,
                languageId: value.context.languageIdChain[0],
                shippingMethodId: value.shippingMethod.id,
                paymentMethodId: value.paymentMethod.id,
                // eslint-disable-next-line max-len
                // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-member-access
                billingAddressId: value.customer?.activeBillingAddress?.id ?? '',
                // eslint-disable-next-line max-len
                // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-member-access
                shippingAddressId: value.customer?.activeShippingAddress?.id ?? '',
            };
        },
    },

    methods: {
        onCloseModal(): void {
            if (!this.customer || !this.cart.token) {
                this.$emit('modal-close');
                return;
            }

            void this.cancelCart().then(() => {
                this.$emit('modal-close');
            });
        },

        async onPreviewOrder(): Promise<void> {
            const promises = [];

            this.isLoading = true;

            promises.push(this.updateOrderContext());

            if (this.disabledAutoPromotion) {
                promises.push(this.disableAutoAppliedPromotions());
            }

            if (this.promotionCodes.length) {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-call
                promises.push(this.addPromotionCodes());
            }

            if (this.shippingCosts !== null && this.shippingCosts !== this.cartDelivery?.shippingCosts?.totalPrice) {
                promises.push(this.modifyShippingCost(this.shippingCosts));
            }

            try {
                const responses = await Promise.all(promises);
                if (responses) {
                    this.$emit('order-preview');
                }
            } finally {
                this.isLoading = false;
            }
        },

        async onSaveItem(item: LineItem): Promise<void> {
            this.isProductGridLoading = true;

            try {
                await Store.get('swOrder').saveLineItem({
                    salesChannelId: this.salesChannelId,
                    contextToken: this.cart.token,
                    item,
                });
            } finally {
                this.isProductGridLoading = false;
            }
        },

        async addPromotionCodes(): Promise<void> {
            if (!this.customer) return;

            await Store.get('swOrder').saveMultipleLineItems({
                salesChannelId: this.customer?.salesChannelId,
                contextToken: this.cart.token,
                items: this.promotionCodeItems as unknown as LineItem[],
            });
        },

        updatePromotion(promotions: string[]): void {
            this.promotionCodes = promotions;
        },

        async onRemoveItems(lineItemKeys: string[]): Promise<void> {
            this.isProductGridLoading = true;

            try {
                await Store.get('swOrder').removeLineItems({
                    salesChannelId: this.salesChannelId,
                    contextToken: this.cart.token,
                    lineItemKeys: lineItemKeys,
                });
            } finally {
                this.isProductGridLoading = false;
            }
        },

        updateAutoPromotionToggle(value: boolean): void {
            this.disabledAutoPromotion = value;
        },

        updateShippingCost(value: number): void {
            this.shippingCosts = value;
        },

        async updateOrderContext(): Promise<void> {
            await Store.get('swOrder').updateOrderContext({
                context: this.context,
                salesChannelId: this.salesChannelId,
                contextToken: this.cart.token,
            });
        },

        disableAutoAppliedPromotions(): Promise<void> {
            const additionalParams = { salesChannelId: this.salesChannelId };

            return Service('cartStoreService')
                .disableAutomaticPromotions(this.cart.token, additionalParams)
                .then(() => {
                    Store.get('swOrder').setDisabledAutoPromotion(true);
                });
        },

        async modifyShippingCost(amount: number): Promise<void> {
            if (!this.cartDelivery) {
                return;
            }

            const positiveAmount = Math.abs(amount);
            this.cartDelivery.shippingCosts.unitPrice = positiveAmount;
            this.cartDelivery.shippingCosts.totalPrice = positiveAmount;

            if (!this.customer) return;

            await Store.get('swOrder').modifyShippingCosts({
                salesChannelId: this.customer?.salesChannelId,
                contextToken: this.cart.token,
                shippingCosts: this.cartDelivery?.shippingCosts,
            });
        },

        cancelCart(): Promise<void> {
            return Store.get('swOrder').cancelCart({
                salesChannelId: this.salesChannelId,
                contextToken: this.cart.token,
            });
        },
    },
});
