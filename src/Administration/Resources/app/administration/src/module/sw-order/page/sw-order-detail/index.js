import template from './sw-order-detail.html.twig';
import './sw-order-detail.scss';
import '../../store/order-detail.store';

/**
 * @sw-package checkout
 */

const { Store, Mixin, Utils } = Shopware;
const { Criteria } = Shopware.Data;
const { array } = Utils;
const ApiService = Shopware.Classes.ApiService;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
        'orderService',
        'feature',
    ],

    provide() {
        return {
            swOrderDetailOnCreatedByIdChange: this.updateCreatedById,
            swOrderDetailOnLoadingChange: this.onUpdateLoading,
            swOrderDetailOnEditingChange: this.onUpdateEditing,
            swOrderDetailOnSaveAndRecalculate: this.onSaveAndRecalculate,
            swOrderDetailOnRecalculateAndReload: this.onRecalculateAndReload,
            swOrderDetailOnReloadEntityData: this.reloadEntityData,
            swOrderDetailOnSaveAndReload: this.onSaveAndReload,
            swOrderDetailOnSaveEdits: this.onSaveEdits,
            swOrderDetailAskAndSaveEdits: this.askAndSaveEdits,
            swOrderDetailOnError: this.onError,
            swOrderDetailHandleCartErrors: this.handleCartErrors,
        };
    },

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        orderId: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            isEditing: false,
            isLoading: true,
            isSaveSuccessful: false,
            createdById: '',
            isDisplayingLeavePageWarning: false,
            nextRoute: null,
            hasNewVersionId: false,
            hasOrderDeepEdit: false,
            missingProductLineItems: [],
            promotionsToDelete: [],
            deliveryDiscountsToDelete: [],
            askForSaveBeforehand: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.orderIdentifier),
        };
    },

    computed: {
        order: () => Store.get('swOrderDetail').order,

        versionContext: () => Store.get('swOrderDetail').versionContext,

        orderAddressIds: () => Store.get('swOrderDetail').orderAddressIds,

        editing: () => Store.get('swOrderDetail').editing,

        loading: () => Store.get('swOrderDetail').loading,

        orderIdentifier() {
            return this.order?.orderNumber ?? '';
        },

        orderChanges() {
            if (!this.order) {
                return false;
            }

            return this.orderRepository.hasChanges(this.order);
        },

        showTabs() {
            return this.$route.meta.$module.routes.detail.children.length > 1;
        },

        showWarningTabStyle() {
            return this.isOrderEditing && this.$route.name === 'sw.order.detail.documents';
        },

        isOrderEditing() {
            return this.orderChanges || this.hasOrderDeepEdit || this.orderAddressIds?.length > 0;
        },

        orderRepository() {
            return this.repositoryFactory.create('order');
        },

        automaticPromotions() {
            return this.order.lineItems.filter((item) => item.type === 'promotion' && item.referencedId === null);
        },

        deliveryDiscounts() {
            return array.slice(this.order.deliveries, 1) || [];
        },

        orderCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addAssociation('currency').addAssociation('orderCustomer.salutation').addAssociation('language');

            criteria
                .getAssociation('lineItems')
                .addFilter(Criteria.equals('parentId', null))
                .addSorting(Criteria.sort('position', 'ASC'));

            criteria.getAssociation('lineItems.children').addSorting(Criteria.sort('position', 'ASC'));

            criteria.addAssociation('salesChannel.domains');

            criteria
                .addAssociation('addresses.country')
                .addAssociation('addresses.countryState')
                .addAssociation('deliveries.shippingMethod')
                .addAssociation('deliveries.shippingOrderAddress')
                .addAssociation('transactions.paymentMethod')
                .addAssociation('documents.documentType')
                .addAssociation('tags');

            criteria.addAssociation('stateMachineState');

            criteria
                .getAssociation('deliveries')
                .addAssociation('stateMachineState')
                .addSorting(Criteria.sort('shippingCosts.unitPrice', 'DESC'));

            criteria
                .getAssociation('transactions')
                .addAssociation('stateMachineState')
                .addSorting(Criteria.sort('createdAt'));

            criteria.addAssociation('billingAddress');

            return criteria;
        },

        convertedProductLineItems() {
            return (
                this.order?.lineItems?.filter((lineItem) => {
                    return (
                        lineItem.payload?.isConvertedProductLineItem &&
                        lineItem.type === 'custom' &&
                        !this.missingProductLineItems.includes(lineItem)
                    );
                }) || []
            );
        },
    },

    watch: {
        orderId() {
            this.createdComponent();
        },

        isOrderEditing(value) {
            this.updateEditing(value);
        },
    },

    beforeUnmount() {
        this.beforeDestroyComponent();
    },

    beforeRouteLeave(to, from, next) {
        if (this.isOrderEditing) {
            this.nextRoute = next;
            this.isDisplayingLeavePageWarning = true;
        } else {
            Shopware.Store.get('shopwareApps').selectedIds = [];
            next();
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            Shopware.ExtensionAPI.publishData({
                id: 'sw-order-detail-base__order',
                path: 'order',
                scope: this,
            });

            window.addEventListener('beforeunload', this.beforeDestroyComponent);

            Shopware.Store.get('shopwareApps').selectedIds = this.orderId ? [this.orderId] : [];

            this.createNewVersionId();
        },

        async beforeDestroyComponent() {
            if (this.hasNewVersionId) {
                const oldVersionContext = this.versionContext;
                Store.get('swOrderDetail').versionContext = Shopware.Context.api;
                this.hasNewVersionId = false;

                // clean up recently created version
                await this.orderRepository.deleteVersion(this.orderId, oldVersionContext.versionId, oldVersionContext);
            }
        },

        updateCreatedById(createdById) {
            this.createdById = createdById;
        },

        onChangeLanguage() {
            this.$root.$emit('language-change');
        },

        saveEditsFinish() {
            this.isSaveSuccessful = false;
            this.isEditing = false;
        },

        onStartEditing() {
            this.$root.$emit('order-edit-start');
        },

        async onSaveEdits() {
            this.isLoading = true;

            await this.handleOrderAddressUpdate(this.orderAddressIds);

            if (this.promotionsToDelete.length > 0) {
                this.order.lineItems = this.order.lineItems.filter(
                    (lineItem) => !this.promotionsToDelete.includes(lineItem.id),
                );
            }

            if (this.order.lineItems.length === 0) {
                this.createNotificationError({
                    message: this.$tc('sw-order.detail.messageEmptyLineItems'),
                });

                this.createNewVersionId().then(() => {
                    Store.get('swOrderDetail').setLoading([
                        'order',
                        false,
                    ]);
                });

                return;
            }

            if (this.deliveryDiscountsToDelete.length > 0) {
                this.order.deliveries = this.order.deliveries.filter(
                    (delivery) => !this.deliveryDiscountsToDelete.includes(delivery.id),
                );
            }

            await this.orderRepository
                .save(this.order, this.versionContext)
                .then(() => {
                    this.hasOrderDeepEdit = false;
                    this.promotionsToDelete = [];
                    this.deliveryDiscountsToDelete = [];
                    return this.orderRepository.mergeVersion(this.versionContext.versionId, this.versionContext);
                })
                .then(() => this.createNewVersionId())
                .then(() => {
                    Store.get('swOrderDetail').savedSuccessful = true;
                })
                .catch((error) => {
                    this.onError('error', error);
                    this.isLoading = false;
                });

            this.$root.$emit('order-edit-save');
        },

        async handleOrderAddressUpdate(addressMappings) {
            const mappings = [];

            addressMappings.forEach((addressMapping) => {
                // If they are the same means that the address has not changed, so skip it
                if (addressMapping.customerAddressId === addressMapping.orderAddressId) {
                    return;
                }

                const mapping = {
                    customerAddressId: addressMapping.customerAddressId,
                    type: addressMapping.type,
                };

                if (addressMapping.type === 'shipping') {
                    mapping.deliveryId = this.order.deliveries[0].id;
                }

                mappings.push(mapping);
            });

            if (mappings.length === 0) {
                Store.get('swOrderDetail').setOrderAddressIds(false);

                return;
            }

            await this.updateOrderAddresses(mappings)
                .then(() => {
                    Store.get('swOrderDetail').setOrderAddressIds(false);
                })
                .catch((error) => {
                    this.createNotificationError({
                        message: error,
                    });
                });
        },

        onCancelEditing() {
            this.isLoading = true;
            Store.get('swOrderDetail').setLoading([
                'order',
                true,
            ]);

            const oldVersionContext = this.versionContext;
            Store.get('swOrderDetail').versionContext = Shopware.Context.api;
            this.hasNewVersionId = false;

            return this.orderRepository
                .deleteVersion(this.orderId, oldVersionContext.versionId, oldVersionContext)
                .then(() => {
                    this.hasOrderDeepEdit = false;
                    Store.get('swOrderDetail').setOrderAddressIds(false);
                })
                .catch((error) => {
                    this.onError('error', error);
                })
                .finally(() => {
                    this.missingProductLineItems = [];

                    return this.createNewVersionId().then(() => {
                        Store.get('swOrderDetail').setLoading([
                            'order',
                            false,
                        ]);
                    });
                });
        },

        async onSaveAndRecalculate() {
            Store.get('swOrderDetail').setLoading([
                'order',
                true,
            ]);
            this.isLoading = true;

            try {
                await this.orderRepository.save(this.order, this.versionContext);
                await this.orderService
                    .recalculateOrder(this.orderId, this.versionContext.versionId, {}, {})
                    .then(this.handleCartErrors.bind(this));
                await this.reloadEntityData();
            } catch (error) {
                this.onError('error', error);
            } finally {
                this.isLoading = false;
                Store.get('swOrderDetail').setLoading([
                    'order',
                    false,
                ]);
            }
        },

        async onRecalculateAndReload() {
            Store.get('swOrderDetail').setLoading([
                'order',
                true,
            ]);

            try {
                await this.orderService
                    .recalculateOrder(this.orderId, this.versionContext.versionId, {}, {})
                    .then(this.handleCartErrors.bind(this));
                await this.reloadEntityData();
            } catch (error) {
                this.onError('error', error);
            } finally {
                Store.get('swOrderDetail').setLoading([
                    'order',
                    false,
                ]);
            }
        },

        onSaveAndReload() {
            Store.get('swOrderDetail').setLoading([
                'order',
                true,
            ]);

            return this.orderRepository
                .save(this.order, this.versionContext)
                .then(() => this.reloadEntityData())
                .catch((error) => {
                    this.onError('error', error);
                })
                .finally(() => {
                    Store.get('swOrderDetail').setLoading([
                        'order',
                        false,
                    ]);
                });
        },

        onUpdateLoading(loadingValue) {
            this.isLoading = loadingValue;
        },

        onUpdateEditing(editingValue) {
            this.isEditing = editingValue;
        },

        onError(error) {
            let errorDetails = null;

            try {
                errorDetails = error.response.data.errors[0].detail;
            } catch (e) {
                errorDetails = '';
            }

            this.createNotificationError({
                message: this.$tc('sw-order.detail.messageRecalculationError') + errorDetails,
            });
        },

        onLeaveModalClose() {
            this.nextRoute(false);
            this.nextRoute = null;
            this.isDisplayingLeavePageWarning = false;
        },

        onLeaveModalConfirm() {
            this.isDisplayingLeavePageWarning = false;

            this.$nextTick(() => {
                this.nextRoute();
            });
        },

        reloadEntityData(isSaved = true) {
            Store.get('swOrderDetail').setLoading([
                'order',
                true,
            ]);

            return this.orderRepository
                .get(this.orderId, this.versionContext, this.orderCriteria)
                .then((response) => {
                    if (this.$route.name !== 'sw.order.detail.documents' && isSaved) {
                        this.hasOrderDeepEdit = true;
                    }

                    Store.get('swOrderDetail').order = response;
                })
                .finally(() => {
                    Store.get('swOrderDetail').setLoading([
                        'order',
                        false,
                    ]);
                    this.isLoading = false;
                });
        },

        createNewVersionId() {
            // Reset the current version context
            Store.get('swOrderDetail').versionContext = Shopware.Context.api;
            this.hasNewVersionId = false;

            return this.orderRepository
                .createVersion(this.orderId, this.versionContext)
                .then((newContext) => {
                    this.hasNewVersionId = true;

                    Store.get('swOrderDetail').versionContext = newContext;

                    return this.reloadEntityData(false);
                })
                .then(() => this.convertMissingProductLineItems());
        },

        updateOrderAddresses(mappings) {
            return this.orderService.updateOrderAddresses(
                this.orderId,
                mappings,
                {},
                ApiService.getVersionHeader(this.order.versionId),
            );
        },

        updateEditing(value) {
            Store.get('swOrderDetail').editing = value;
        },

        convertMissingProductLineItems() {
            this.missingProductLineItems =
                this.order?.lineItems?.filter((lineItem) => {
                    return lineItem.productId === null && lineItem.type === 'product';
                }) || [];

            if (this.missingProductLineItems.length === 0) {
                return Promise.resolve();
            }

            this.missingProductLineItems.forEach((lineItem) => {
                lineItem.type = 'custom';
                lineItem.productId = null;
                lineItem.referencedId = null;
                lineItem.payload.isConvertedProductLineItem = true;
            });

            return this.orderRepository.save(this.order, this.versionContext);
        },

        handleCartErrors(response) {
            if (!response?.data?.errors) {
                return;
            }

            Object.values(response.data.errors).forEach(({ level, message }) => {
                switch (level) {
                    case 0: {
                        this.createNotificationInfo({ message });
                        break;
                    }

                    case 10: {
                        this.createNotificationWarning({ message });
                        break;
                    }

                    default: {
                        this.createNotificationError({ message });
                        break;
                    }
                }
            });
        },

        /**
         * Asks the user to save pending edits before e.g. doing a status change.
         * This will trigger `onSaveEdits` and therefore merge the versioned order.
         *
         * @returns Promise<bool> - `true` if it's safe to proceed (e.g. edits were saved)
         *  or `false` if the user wants to cancel the action.
         */
        askAndSaveEdits(reason = 'status') {
            if (!this.isOrderEditing) {
                return Promise.resolve(true);
            }

            return new Promise((resolve, reject) => {
                this.askForSaveBeforehand = {
                    reason: this.$tc(`sw-order.saveChangesBeforehandModal.${reason}Description`),
                    resolve,
                    reject,
                };
            });
        },

        async onAskAndSaveEditsConfirm() {
            await this.onSaveEdits();
            this.askForSaveBeforehand.resolve(Store.get('swOrderDetail').savedSuccessful);
            this.askForSaveBeforehand = null;
        },

        onAskAndSaveEditsCancel() {
            this.askForSaveBeforehand.resolve(false);
            this.askForSaveBeforehand = null;
        },
    },
};
