import template from './sw-bulk-edit-product.html.twig';
import './sw-bulk-edit-product.scss';
import '../../../sw-product/page/sw-product-detail/store';

const { Context } = Shopware;
const { Criteria } = Shopware.Data;
const { types } = Shopware.Utils;
const { chunk } = Shopware.Utils.array;
const { cloneDeep } = Shopware.Utils.object;

/**
 * @sw-package inventory
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'feature',
        'bulkEditApiFactory',
        'repositoryFactory',
    ],

    data() {
        return {
            isLoading: false,
            isLoadedData: false,
            isSaveSuccessful: false,
            displayAdvancePricesModal: false,
            isDisabledListPrice: true,
            isDisabledRegulationPrice: true,
            bulkEditProduct: {},
            bulkEditSelected: [],
            taxRate: {},
            currency: {},
            customFieldSets: [],
            processStatus: '',
            rules: [],
            parentProductFrozen: null,
            isComponentMounted: true,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        product() {
            return Shopware.Store.get('swProductDetail').product;
        },

        parentProduct() {
            return Shopware.Store.get('swProductDetail').parentProduct;
        },

        taxes() {
            return Shopware.Store.get('swProductDetail').taxes;
        },

        defaultCurrency() {
            return Shopware.Store.get('swProductDetail').defaultCurrency;
        },

        defaultPrice() {
            return Shopware.Store.get('swProductDetail').defaultPrice;
        },

        selectedIds() {
            return Shopware.Store.get('swBulkEdit').selectedIds;
        },

        customFieldSetRepository() {
            return this.repositoryFactory.create('custom_field_set');
        },

        currencyRepository() {
            return this.repositoryFactory.create('currency');
        },

        taxRepository() {
            return this.repositoryFactory.create('tax');
        },

        productRepository() {
            return this.repositoryFactory.create('product');
        },

        hasSelectedChanges() {
            return Object.values(this.bulkEditProduct).some((field) => field.isChanged) || this.bulkEditSelected.length > 0;
        },

        customFieldSetCriteria() {
            const criteria = new Criteria(1, null);

            criteria.addFilter(Criteria.equals('relations.entityName', 'product'));

            return criteria;
        },

        taxCriteria() {
            const criteria = new Criteria(1, 500);
            criteria.addSorting(Criteria.sort('position'));

            return criteria;
        },

        productCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.getAssociation('media').addSorting(Criteria.sort('position', 'ASC'));

            criteria.getAssociation('properties').addSorting(Criteria.sort('name', 'ASC', true));

            criteria.getAssociation('prices').addSorting(Criteria.sort('quantityStart', 'ASC', true));

            criteria.getAssociation('tags').addSorting(Criteria.sort('name', 'ASC'));

            criteria.getAssociation('seoUrls').addFilter(Criteria.equals('isCanonical', true));

            criteria
                .getAssociation('crossSellings')
                .addSorting(Criteria.sort('position', 'ASC'))
                .getAssociation('assignedProducts')
                .addSorting(Criteria.sort('position', 'ASC'))
                .addAssociation('product')
                .getAssociation('product')
                .addAssociation('options.group');

            criteria
                .addAssociation('cover')
                .addAssociation('categories')
                .addAssociation('visibilities.salesChannel')
                .addAssociation('options')
                .addAssociation('configuratorSettings.option')
                .addAssociation('unit')
                .addAssociation('productReviews')
                .addAssociation('seoUrls')
                .addAssociation('mainCategories')
                .addAssociation('options.group')
                .addAssociation('customFieldSets')
                .addAssociation('featureSet')
                .addAssociation('cmsPage')
                .addAssociation('featureSet');

            criteria.getAssociation('manufacturer').addAssociation('media');

            return criteria;
        },

        isChild() {
            return this.$route.params.parentId !== 'null';
        },

        restrictedFields() {
            let restrictedFields = [];
            const includesDigital = this.$route.params.includesDigital;

            if (includesDigital === '1' || includesDigital === '2') {
                restrictedFields = [
                    'isCloseout',
                    'restockTime',
                    'maxPurchase',
                    'purchaseSteps',
                    'minPurchase',
                    'shippingFree',
                ];
            }
            if (includesDigital === '1') {
                restrictedFields.push('stock');
            }

            return restrictedFields;
        },

        generalFormFields() {
            return [
                {
                    name: 'description',
                    type: 'html',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'sw-bulk-edit-product-description',
                        changeLabel: this.$tc('sw-bulk-edit.product.generalInformation.description.changeLabel'),
                        disabled: this.bulkEditProduct?.description?.isInherited,
                    },
                },
                {
                    name: 'manufacturerId',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'sw-entity-single-select',
                        entity: 'product_manufacturer',
                        changeLabel: this.$tc('sw-bulk-edit.product.generalInformation.manufacturer.changeLabel'),
                        placeholder: this.$tc(
                            'sw-bulk-edit.product.generalInformation.manufacturer.placeholderManufacturer',
                        ),
                        disabled: this.bulkEditProduct?.manufacturerId?.isInherited,
                    },
                },
                {
                    name: 'active',
                    type: 'bool',
                    canInherit: this.isChild,
                    config: {
                        type: 'switch',
                        label: this.$tc('sw-bulk-edit.product.generalInformation.active.switchLabel'),
                        changeLabel: this.$tc('sw-bulk-edit.product.generalInformation.active.changeLabel'),
                        disabled: this.bulkEditProduct?.active?.isInherited,
                    },
                },
                {
                    name: 'markAsTopseller',
                    type: 'bool',
                    canInherit: this.isChild,
                    config: {
                        type: 'switch',
                        label: this.$tc('sw-bulk-edit.product.generalInformation.productPromotion.switchLabel'),
                        changeLabel: this.$tc('sw-bulk-edit.product.generalInformation.productPromotion.changeLabel'),
                        disabled: this.bulkEditProduct?.markAsTopseller?.isInherited,
                    },
                },
            ];
        },

        pricesFormFields() {
            const fields = [
                {
                    name: 'taxId',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'sw-entity-single-select',
                        entity: 'tax',
                        changeLabel: this.$tc('sw-bulk-edit.product.prices.taxRate.changeLabel'),
                        placeholder: this.$tc('sw-bulk-edit.product.prices.taxRate.placeholderTax'),
                        disabled: this.bulkEditProduct?.taxId?.isInherited,
                    },
                },
                {
                    name: 'price',
                    config: {
                        componentName: 'sw-price-field',
                        price: this.product?.price,
                        taxRate: this.taxRate,
                        currency: this.currency,
                        changeLabel: this.isChild
                            ? this.$tc('sw-bulk-edit.product.prices.price.label')
                            : this.$tc('sw-bulk-edit.product.prices.price.changeLabel'),
                        placeholder: this.$tc('sw-bulk-edit.product.prices.price.placeholderPrice'),
                        disabled: this.isChild ? this.bulkEditProduct?.isPriceInherited?.isInherited : false,
                    },
                },
                {
                    name: 'purchasePrices',
                    config: {
                        componentName: 'sw-price-field',
                        price: this.product?.purchasePrices,
                        taxRate: this.taxRate,
                        currency: this.currency,
                        changeLabel: this.isChild
                            ? this.$tc('sw-bulk-edit.product.prices.purchasePrices.label')
                            : this.$tc('sw-bulk-edit.product.prices.purchasePrices.changeLabel'),
                        placeholder: this.$tc('sw-bulk-edit.product.prices.purchasePrices.placeholderPurchasePrices'),
                        disabled: this.isChild ? this.bulkEditProduct?.isPriceInherited?.isInherited : false,
                    },
                },
                {
                    name: 'listPrice',
                    config: {
                        componentName: 'sw-price-field',
                        price: this.product?.listPrice,
                        taxRate: this.taxRate,
                        currency: this.currency,
                        changeLabel: this.isChild
                            ? this.$tc('sw-bulk-edit.product.prices.listPrice.label')
                            : this.$tc('sw-bulk-edit.product.prices.listPrice.changeLabel'),
                        placeholder: this.$tc('sw-bulk-edit.product.prices.listPrice.placeholderListPrice'),
                        disabled: this.isChild
                            ? this.bulkEditProduct?.isPriceInherited?.isInherited
                            : this.isDisabledListPrice,
                    },
                },
                {
                    name: 'regulationPrice',
                    config: {
                        componentName: 'sw-price-field',
                        price: this.product?.regulationPrice,
                        taxRate: this.taxRate,
                        currency: this.currency,
                        changeLabel: this.isChild
                            ? this.$tc('sw-bulk-edit.product.prices.regulationPrice.label')
                            : this.$tc('sw-bulk-edit.product.prices.regulationPrice.changeLabel'),
                        placeholder: this.$tc('sw-bulk-edit.product.prices.regulationPrice.placeholderRegulationPrice'),
                        disabled: this.isChild
                            ? this.bulkEditProduct?.isPriceInherited?.isInherited
                            : this.isDisabledRegulationPrice,
                    },
                },
            ];

            if (this.isChild) {
                const isPriceInherited = {
                    name: 'isPriceInherited',
                    canInherit: this.isChild,
                    config: {
                        componentName: '',
                        changeLabel: this.$tc('sw-bulk-edit.product.prices.isPriceInherited.changeLabel'),
                    },
                };
                fields.splice(1, 0, isPriceInherited);
            }

            return fields;
        },

        advancedPricesFormFields() {
            return [
                {
                    name: 'prices',
                    canInherit: this.isChild,
                    config: {
                        allowOverwrite: true,
                        allowClear: true,
                        allowAdd: true,
                        allowRemove: true,
                        changeLabel: this.$tc('sw-bulk-edit.product.advancedPrices.changeLabel'),
                    },
                },
            ];
        },

        propertyFormFields() {
            return [
                {
                    name: 'properties',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'sw-product-properties',
                        allowOverwrite: true,
                        allowClear: true,
                        allowAdd: true,
                        allowRemove: true,
                        changeLabel: this.$tc('sw-bulk-edit.product.property.changeLabel'),
                        disabled: this.bulkEditProduct?.properties?.isInherited,
                        isAssociation: false,
                        showInheritanceSwitcher: false,
                    },
                },
            ];
        },

        deliverabilityFormFields() {
            const fields = [
                {
                    name: 'stock',
                    type: 'int',
                    canInherit: false,
                    config: {
                        componentName: 'mt-number-field',
                        changeLabel: this.$tc('sw-bulk-edit.product.deliverability.stock.changeLabel'),
                        placeholder: this.$tc('sw-bulk-edit.product.deliverability.stock.placeholderStock'),
                        numberType: 'int',
                        allowEmpty: true,
                        allowOverwrite: true,
                        allowClear: true,
                        min: 0,
                    },
                },
                {
                    name: 'isCloseout',
                    type: 'bool',
                    canInherit: this.isChild,
                    config: {
                        type: 'switch',
                        label: this.$tc('sw-bulk-edit.product.deliverability.isCloseout.switchLabel'),
                        changeLabel: this.$tc('sw-bulk-edit.product.deliverability.isCloseout.changeLabel'),
                        disabled: this.bulkEditProduct?.isCloseout?.isInherited,
                    },
                },
                {
                    name: 'deliveryTimeId',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'sw-entity-single-select',
                        entity: 'delivery_time',
                        changeLabel: this.$tc('sw-bulk-edit.product.deliverability.deliveryTime.changeLabel'),
                        placeholder: this.$tc('sw-bulk-edit.product.deliverability.deliveryTime.placeholderDeliveryTime'),
                        disabled: this.bulkEditProduct?.deliveryTimeId?.isInherited,
                    },
                },
                {
                    name: 'restockTime',
                    type: 'int',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'mt-number-field',
                        changeLabel: this.$tc('sw-bulk-edit.product.deliverability.restockTime.changeLabel'),
                        placeholder: this.$tc('sw-bulk-edit.product.deliverability.restockTime.placeholderRestockTime'),
                        numberType: 'int',
                        allowEmpty: true,
                        allowOverwrite: true,
                        allowClear: true,
                        min: 0,
                        disabled: this.bulkEditProduct?.restockTime?.isInherited,
                    },
                },
                {
                    name: 'shippingFree',
                    type: 'bool',
                    canInherit: this.isChild,
                    config: {
                        type: 'switch',
                        label: this.$tc('sw-bulk-edit.product.deliverability.freeShipping.switchLabel'),
                        changeLabel: this.$tc('sw-bulk-edit.product.deliverability.freeShipping.changeLabel'),
                        disabled: this.bulkEditProduct?.shippingFree?.isInherited,
                    },
                },
                {
                    name: 'minPurchase',
                    type: 'int',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'mt-number-field',
                        changeLabel: this.$tc('sw-bulk-edit.product.deliverability.minOrderQuantity.changeLabel'),
                        placeholder: this.$tc(
                            'sw-bulk-edit.product.deliverability.minOrderQuantity.placeholderMinOrderQuantity',
                        ),
                        numberType: 'int',
                        allowOverwrite: true,
                        allowClear: true,
                        allowEmpty: true,
                        min: 1,
                        disabled: this.bulkEditProduct?.minPurchase?.isInherited,
                    },
                },
                {
                    name: 'purchaseSteps',
                    type: 'int',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'mt-number-field',
                        changeLabel: this.$tc('sw-bulk-edit.product.deliverability.purchaseSteps.changeLabel'),
                        placeholder: this.$tc('sw-bulk-edit.product.deliverability.purchaseSteps.placeholderPurchaseSteps'),
                        numberType: 'int',
                        allowOverwrite: true,
                        allowClear: true,
                        allowEmpty: true,
                        min: 1,
                        disabled: this.bulkEditProduct?.purchaseSteps?.isInherited,
                    },
                },
                {
                    name: 'maxPurchase',
                    type: 'int',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'mt-number-field',
                        changeLabel: this.$tc('sw-bulk-edit.product.deliverability.maxOrderQuantity.changeLabel'),
                        placeholder: this.$tc(
                            'sw-bulk-edit.product.deliverability.maxOrderQuantity.placeholderMaxOrderQuantity',
                        ),
                        numberType: 'int',
                        allowOverwrite: true,
                        allowClear: true,
                        allowEmpty: true,
                        min: 0,
                        disabled: this.bulkEditProduct?.maxPurchase?.isInherited,
                    },
                },
            ];

            return fields.filter((field) => {
                return !this.restrictedFields.includes(field.name);
            });
        },

        assignmentFormFields() {
            return [
                {
                    name: 'visibilities',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'sw-bulk-edit-product-visibility',
                        bulkEditProduct: this.bulkEditProduct,
                        allowOverwrite: true,
                        allowClear: true,
                        allowAdd: true,
                        allowRemove: true,
                        changeLabel: this.$tc('sw-bulk-edit.product.assignment.visibilities.changeLabel'),
                        placeholder: this.$tc('sw-bulk-edit.product.assignment.visibilities.placeholder'),
                        disabled: this.bulkEditProduct?.visibilities?.isInherited,
                    },
                },
                {
                    name: 'categories',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'sw-category-tree-field',
                        categoriesCollection: this.product?.categories,
                        allowOverwrite: true,
                        allowClear: true,
                        allowAdd: true,
                        allowRemove: true,
                        changeLabel: this.$tc('sw-bulk-edit.product.assignment.categories.changeLabel'),
                        placeholder: this.$tc('sw-bulk-edit.product.assignment.categories.placeholder'),
                        disabled: this.bulkEditProduct?.categories?.isInherited,
                    },
                },
                {
                    name: 'tags',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'sw-entity-tag-select',
                        entityCollection: this.product?.tags,
                        entityName: 'tag',
                        allowOverwrite: true,
                        allowClear: true,
                        allowAdd: true,
                        allowRemove: true,
                        changeLabel: this.$tc('sw-bulk-edit.product.assignment.tags.changeLabel'),
                        placeholder: this.$tc('sw-bulk-edit.product.assignment.tags.placeholder'),
                        disabled: this.bulkEditProduct?.tags?.isInherited,
                    },
                },
                {
                    name: 'searchKeywords',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'sw-multi-tag-select',
                        value: this.product?.searchKeywords,
                        allowOverwrite: true,
                        allowClear: true,
                        allowAdd: false,
                        allowRemove: false,
                        changeLabel: this.$tc('sw-bulk-edit.product.assignment.searchKeywords.changeLabel'),
                        placeholder: this.$tc('sw-bulk-edit.product.assignment.searchKeywords.placeholder'),
                        disabled: this.bulkEditProduct?.searchKeywords?.isInherited,
                    },
                },
            ];
        },

        mediaFormFields() {
            return [
                {
                    name: 'media',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'sw-bulk-edit-product-media',
                        allowOverwrite: true,
                        allowClear: true,
                        allowAdd: true,
                        changeLabel: this.$tc('sw-bulk-edit.product.media.changeLabel'),
                        disabled: this.bulkEditProduct?.media?.isInherited,
                    },
                },
            ];
        },

        labellingFormFields() {
            return [
                {
                    name: 'releaseDate',
                    type: 'datetime',
                    canInherit: this.isChild,
                    config: {
                        type: 'date',
                        dateType: 'datetime-local',
                        changeLabel: this.$tc('sw-bulk-edit.product.labelling.releaseDate.changeLabel'),
                        disabled: this.bulkEditProduct?.releaseDate?.isInherited,
                    },
                },
            ];
        },

        seoFormFields() {
            return [
                {
                    name: 'metaTitle',
                    type: 'text',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'sw-field',
                        type: 'text',
                        changeLabel: this.$tc('sw-bulk-edit.product.seo.metaTitle.changeLabel'),
                        placeholder: this.$tc('sw-bulk-edit.product.seo.metaTitle.placeholderMetaTitle'),
                        disabled: this.bulkEditProduct?.metaTitle?.isInherited,
                    },
                },
                {
                    name: 'metaDescription',
                    type: 'text',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'sw-field',
                        type: 'textarea',
                        changeLabel: this.$tc('sw-bulk-edit.product.seo.metaDescription.changeLabel'),
                        placeholder: this.$tc('sw-bulk-edit.product.seo.metaDescription.placeholderMetaDescription'),
                        disabled: this.bulkEditProduct?.metaDescription?.isInherited,
                    },
                },
                {
                    name: 'keywords',
                    type: 'text',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'sw-field',
                        type: 'text',
                        changeLabel: this.$tc('sw-bulk-edit.product.seo.seoKeywords.changeLabel'),
                        placeholder: this.$tc('sw-bulk-edit.product.seo.seoKeywords.placeholderSeoKeywords'),
                        disabled: this.bulkEditProduct?.keywords?.isInherited,
                    },
                },
            ];
        },

        measuresPackagingFields() {
            return [
                {
                    name: 'width',
                    type: 'float',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'mt-number-field',
                        changeLabel: this.$tc('sw-bulk-edit.product.measuresAndPackaging.widthTitle.changeLabel'),
                        placeholder: this.$tc('sw-bulk-edit.product.measuresAndPackaging.widthTitle.placeholder'),
                        numberType: 'float',
                        suffixLabel: 'mm',
                        min: 0,
                        disabled: this.bulkEditProduct?.width?.isInherited,
                    },
                },
                {
                    name: 'height',
                    type: 'float',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'mt-number-field',
                        changeLabel: this.$tc('sw-bulk-edit.product.measuresAndPackaging.heightTitle.changeLabel'),
                        placeholder: this.$tc('sw-bulk-edit.product.measuresAndPackaging.heightTitle.placeholder'),
                        numberType: 'float',
                        suffixLabel: 'mm',
                        min: 0,
                        disabled: this.bulkEditProduct?.height?.isInherited,
                    },
                },
                {
                    name: 'length',
                    type: 'float',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'sw-number-field',
                        changeLabel: this.$tc('sw-bulk-edit.product.measuresAndPackaging.lengthTitle.changeLabel'),
                        placeholder: this.$tc('sw-bulk-edit.product.measuresAndPackaging.lengthTitle.placeholder'),
                        numberType: 'float',
                        suffixLabel: 'mm',
                        min: 0,
                        disabled: this.bulkEditProduct?.length?.isInherited,
                    },
                },
                {
                    name: 'weight',
                    type: 'float',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'sw-number-field',
                        changeLabel: this.$tc('sw-bulk-edit.product.measuresAndPackaging.weightTitle.changeLabel'),
                        placeholder: this.$tc('sw-bulk-edit.product.measuresAndPackaging.weightTitle.placeholder'),
                        numberType: 'float',
                        suffixLabel: 'kg',
                        min: 0,
                        disabled: this.bulkEditProduct?.weight?.isInherited,
                    },
                },
                {
                    name: 'purchaseUnit',
                    type: 'float',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'sw-number-field',
                        numberType: 'float',
                        min: 0,
                        changeLabel: this.$tc('sw-bulk-edit.product.measuresAndPackaging.sellingUnitTitle.changeLabel'),
                        placeholder: this.$tc('sw-bulk-edit.product.measuresAndPackaging.sellingUnitTitle.placeholder'),
                        disabled: this.bulkEditProduct?.purchaseUnit?.isInherited,
                    },
                },
                {
                    name: 'unitId',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'sw-entity-single-select',
                        entity: 'unit',
                        changeLabel: this.$tc('sw-bulk-edit.product.measuresAndPackaging.scaleUnitTitle.changeLabel'),
                        placeholder: this.$tc('sw-bulk-edit.product.measuresAndPackaging.scaleUnitTitle.placeholder'),
                        disabled: this.bulkEditProduct?.unitId?.isInherited,
                    },
                },
                {
                    name: 'packUnit',
                    type: 'text',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'sw-field',
                        type: 'text',
                        changeLabel: this.$tc('sw-bulk-edit.product.measuresAndPackaging.packUnitTitle.changeLabel'),
                        placeholder: this.$tc('sw-bulk-edit.product.measuresAndPackaging.packUnitTitle.placeholder'),
                        disabled: this.bulkEditProduct?.packUnit?.isInherited,
                    },
                },
                {
                    name: 'packUnitPlural',
                    type: 'text',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'sw-field',
                        type: 'text',
                        changeLabel: this.$tc('sw-bulk-edit.product.measuresAndPackaging.packUnitPluralTitle.changeLabel'),
                        placeholder: this.$tc('sw-bulk-edit.product.measuresAndPackaging.packUnitPluralTitle.placeholder'),
                        disabled: this.bulkEditProduct?.packUnitPlural?.isInherited,
                    },
                },
                {
                    name: 'referenceUnit',
                    type: 'float',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'sw-number-field',
                        numberType: 'float',
                        min: 0,
                        changeLabel: this.$tc('sw-bulk-edit.product.measuresAndPackaging.basicUnitTitle.changeLabel'),
                        placeholder: this.$tc('sw-bulk-edit.product.measuresAndPackaging.basicUnitTitle.placeholder'),
                        disabled: this.bulkEditProduct?.referenceUnit?.isInherited,
                    },
                },
            ];
        },

        essentialCharacteristicsFormFields() {
            return [
                {
                    name: 'featureSetId',
                    canInherit: this.isChild,
                    config: {
                        componentName: 'sw-entity-single-select',
                        entity: 'product_feature_set',
                        changeLabel: this.$tc('sw-bulk-edit.product.featureSets.changeLabel'),
                        placeholder: this.$tc('sw-bulk-edit.product.featureSets.placeholder'),
                        disabled: this.bulkEditProduct?.featureSetId?.isInherited,
                    },
                },
            ];
        },

        ruleRepository() {
            return this.repositoryFactory.create('rule');
        },

        priceRepository() {
            if (!this.product?.prices) {
                return null;
            }

            return this.repositoryFactory.create(this.product?.prices.entity, this.product?.prices.source);
        },

        ruleCriteria() {
            const criteria = new Criteria(1, 500);
            criteria.addFilter(
                Criteria.multi('OR', [
                    Criteria.contains('rule.moduleTypes.types', 'price'),
                    Criteria.equals('rule.moduleTypes', null),
                ]),
            );

            return criteria;
        },

        priceRuleGroups() {
            if (!this.product?.prices) {
                return {};
            }

            return this.product?.prices.reduce((r, a) => {
                r[a.ruleId] = [
                    ...(r[a.ruleId] || []),
                    a,
                ];
                return r;
            }, {});
        },
    },

    watch: {
        'bulkEditProduct.prices.type'(type) {
            if (!this.product?.prices?.length || type !== 'remove') {
                return;
            }

            const ids = this.product?.prices?.getIds();
            ids.forEach((id) => this.product?.prices.remove(id));
        },
        'product.visibilities': {
            handler(productVisibilities) {
                if (!this.isChild) {
                    return;
                }

                this.bulkEditProduct.visibilities.value = productVisibilities;
            },
        },
        'bulkEditProduct.isPriceInherited.isChanged': {
            handler(isChanged) {
                if (!this.isChild) {
                    return;
                }

                this.bulkEditProduct.price.isChanged = isChanged;
                this.bulkEditProduct.purchasePrices.isChanged = isChanged;
                this.bulkEditProduct.listPrice.isChanged = isChanged;
                this.bulkEditProduct.regulationPrice.isChanged = isChanged;
            },
        },
        'bulkEditProduct.isPriceInherited.isInherited': {
            handler(isInherited) {
                if (!this.isChild) {
                    return;
                }

                this.bulkEditProduct.price.isInherited = isInherited;
                this.bulkEditProduct.purchasePrices.isInherited = isInherited;
                this.bulkEditProduct.listPrice.isInherited = isInherited;
                this.bulkEditProduct.regulationPrice.isInherited = isInherited;
            },
        },
        'product.listPrice': {
            deep: true,
            handler(listPrice) {
                if (!this.isChild) {
                    return;
                }
                if (!this.bulkEditProduct?.price?.value?.length) {
                    return;
                }
                if (this.bulkEditProduct?.price?.value[0]?.listPrice) {
                    return;
                }

                this.bulkEditProduct.price.value[0].listPrice = listPrice[0];
            },
        },
        'product.regulationPrice': {
            deep: true,
            handler(regulationPrice) {
                if (!this.isChild) {
                    return;
                }
                if (!this.bulkEditProduct?.price?.value?.length) {
                    return;
                }
                if (this.bulkEditProduct?.price?.value[0]?.regulationPrice) {
                    return;
                }

                this.bulkEditProduct.price.value[0].regulationPrice = regulationPrice[0];
            },
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            this.setRouteMetaModule();
            this.isLoading = true;

            if (this.isChild) {
                await this.getParentProduct();
            }

            const promises = [
                this.loadCurrencies(),
                this.loadTaxes(),
                this.loadCustomFieldSets(),
                this.loadDefaultCurrency(),
                this.loadRules(),
            ];

            Promise.all(promises).then(() => {
                this.loadBulkEditData();

                const product = this.isChild ? this.parentProduct : this.productRepository.create();
                Shopware.Store.get('swProductDetail').product = product;
                this.definePricesBulkEdit();

                if (this.isChild) {
                    this.setBulkEditProductValue();
                }

                this.isLoading = false;
                this.isLoadedData = true;
            });
        },

        setRouteMetaModule() {
            this.$route.meta.$module.color = '#57D9A3';
            this.$route.meta.$module.icon = 'regular-products';
        },

        setBulkEditProductValue() {
            Object.keys(this.bulkEditProduct).forEach((key) => {
                this.bulkEditProduct[key].value = cloneDeep(this.parentProduct?.[key]);

                if (key === 'searchKeywords') {
                    this.setProductSearchKeywords();
                }
            });
        },

        getParentProduct() {
            return this.productRepository
                .get(this.$route.params.parentId, Shopware.Context.api, this.productCriteria)
                .then((parentProduct) => {
                    parentProduct.stock = null;
                    Shopware.Store.get('swProductDetail').parentProduct = parentProduct;
                    this.parentProductFrozen = JSON.stringify(parentProduct);
                })
                .catch(() => {
                    Shopware.Store.get('swProductDetail').parentProduct = {};
                });
        },

        loadDefaultCurrency() {
            return this.currencyRepository.get(Context.app.systemCurrencyId).then((currency) => {
                this.currency = currency;
            });
        },

        defineBulkEditData(name, value = null, type = 'overwrite', isChanged = false) {
            if (this.bulkEditProduct[name]) {
                return;
            }

            if (name === 'stock') {
                this.bulkEditProduct[name] = {
                    value,
                    type,
                    isChanged,
                    isInherited: false,
                };
                return;
            }

            this.bulkEditProduct[name] = {
                isChanged: isChanged,
                type: type,
                value: value,
                isInherited: this.isChild,
            };
        },

        loadBulkEditData() {
            const bulkEditFormGroups = [
                this.generalFormFields,
                this.deliverabilityFormFields,
                this.pricesFormFields,
                this.advancedPricesFormFields,
                this.propertyFormFields,
                this.assignmentFormFields,
                this.mediaFormFields,
                this.labellingFormFields,
                this.seoFormFields,
                this.measuresPackagingFields,
                this.essentialCharacteristicsFormFields,
            ];

            bulkEditFormGroups.forEach((bulkEditForms) => {
                bulkEditForms.forEach((bulkEditForm) => {
                    this.defineBulkEditData(bulkEditForm.name);
                });
            });
        },

        loadCustomFieldSets() {
            return this.customFieldSetRepository.search(this.customFieldSetCriteria).then((res) => {
                this.customFieldSets = res;
            });
        },

        loadTaxes() {
            return this.taxRepository.search(this.taxCriteria).then((taxes) => {
                this.taxRate = this.isChild ? this.parentProduct?.tax : taxes[0];
                Shopware.Store.get('swProductDetail').setTaxes(taxes);
            });
        },

        productTaxRate() {
            if (!this.taxes) {
                return {};
            }

            return this.taxes.find((tax) => {
                return tax.id === this.product?.taxId;
            });
        },

        loadCurrencies() {
            return this.currencyRepository.search(new Criteria(1, 500)).then((res) => {
                Shopware.Store.get('swProductDetail').currencies = res;
            });
        },

        definePricesBulkEdit() {
            if (!this.isComponentMounted) {
                return;
            }

            if (this.isChild && !types.isEmpty(this.parentProduct)) {
                this.product.price = this.parentProduct.price;
                this.product.purchasePrices = this.parentProduct.purchasePrices;
                this.setProductPrice('listPrice');
                this.setProductPrice('regulationPrice');

                return;
            }

            this.product.price ??= [
                {
                    currencyId: this.currency.id,
                    net: null,
                    linked: true,
                    gross: null,
                },
            ];

            this.product.purchasePrices ??= [
                {
                    currencyId: this.currency.id,
                    net: null,
                    linked: true,
                    gross: null,
                },
            ];

            this.product.listPrice = [
                {
                    currencyId: this.currency.id,
                    net: null,
                    linked: true,
                    gross: null,
                },
            ];

            this.product.regulationPrice = [
                {
                    currencyId: this.currency.id,
                    net: null,
                    linked: true,
                    gross: null,
                },
            ];
        },

        setProductPrice(price) {
            const emptyPrice = [
                {
                    currencyId: this.currency.id,
                    net: null,
                    linked: true,
                    gross: null,
                },
            ];

            if (!types.isEmpty(this.parentProduct.price?.[0][price])) {
                this.product[price] = [this.parentProduct.price[0][price]];
            } else {
                this.product[price] = emptyPrice;
            }
        },

        onChangePrices(item, value = null) {
            if (item === 'taxId') {
                this.taxRate = this.productTaxRate();
                return;
            }

            if (item === 'price') {
                this.isDisabledListPrice = !this.bulkEditProduct.price.isChanged;
                this.isDisabledRegulationPrice = !this.bulkEditProduct.price.isChanged;
            }

            if (value && typeof value !== 'boolean') {
                this.product[item] = [value];
            }
        },

        onCustomFieldsChange(value) {
            if (Object.keys(value).length <= 0) {
                this.bulkEditSelected = this.bulkEditSelected.filter((change) => change.field !== 'customFields');
                return;
            }

            const change = {
                field: 'customFields',
                type: 'overwrite',
                value: value,
            };

            this.bulkEditSelected.push(change);
        },

        onProcessData() {
            let hasListPrice = false;
            let hasRegulationPrice = false;

            Object.keys(this.bulkEditProduct).forEach((key) => {
                const bulkEditField = cloneDeep(this.bulkEditProduct[key]);
                if (!bulkEditField.isChanged) {
                    return;
                }

                if (key === 'listPrice') {
                    hasListPrice = true;

                    return;
                }

                if (key === 'regulationPrice') {
                    hasRegulationPrice = true;

                    return;
                }

                let bulkEditValue = this.product[key];

                if (
                    [
                        'minPurchase',
                        'maxPurchase',
                        'purchaseSteps',
                        'restockTime',
                    ].includes(key) &&
                    bulkEditField.type === 'clear'
                ) {
                    bulkEditValue = null;
                    bulkEditField.type = 'overwrite';
                } else if (key === 'searchKeywords') {
                    key = 'customSearchKeywords';
                }

                if (bulkEditField.isInherited) {
                    bulkEditValue = null;
                }

                const change = {
                    field: key,
                    type: bulkEditField.type,
                    value: bulkEditValue,
                };

                if (key === 'visibilities') {
                    change.mappingReferenceField = 'salesChannelId';
                } else if (key === 'media') {
                    change.mappingReferenceField = 'mediaId';
                } else if (key === 'prices') {
                    change.mappingReferenceField = 'ruleId';
                }

                if (this.isChild && change.value !== null && types.isArray(change.value)) {
                    change.value.forEach((association) => {
                        delete association.id;
                    });
                }

                this.bulkEditSelected.push(change);
            });

            if (hasListPrice) {
                this.processListPrice();
            }

            if (hasRegulationPrice) {
                this.processRegulationPrice();
            }
        },

        processListPrice() {
            const priceField = this.bulkEditSelected.find((dataField) => {
                return dataField.field === 'price' && !types.isEmpty(dataField.value);
            });

            if (priceField) {
                priceField.value[0].listPrice = this.product?.listPrice[0];
            }
        },

        processRegulationPrice() {
            const priceField = this.bulkEditSelected.find((dataField) => {
                return dataField.field === 'price' && !types.isEmpty(dataField.value);
            });

            if (priceField) {
                priceField.value[0].regulationPrice = this.product?.regulationPrice[0];
            }
        },

        openModal() {
            this.isComponentMounted = false;
            this.$router.push({
                name: 'sw.bulk.edit.product.save',
                params: {
                    parentId: this.$route.params.parentId,
                },
            });
        },

        async onSave() {
            this.isLoading = true;
            this.onProcessData();

            const payloadChunks = chunk(this.selectedIds, 50);

            const requests = payloadChunks.map((payload) => {
                return this.bulkEditApiFactory.getHandler('product').bulkEdit(payload, this.bulkEditSelected);
            });

            this.bulkEditSelected = [];

            return Promise.all(requests)
                .then((response) => {
                    const isSuccessful = response.every((item) => item.data);
                    this.processStatus = isSuccessful ? 'success' : 'fail';
                })
                .catch(() => {
                    this.processStatus = 'fail';
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        closeModal() {
            this.$router.push({ name: 'sw.bulk.edit.product' });
        },

        onChangeLanguage(languageId) {
            Shopware.Store.get('context').setApiLanguageId(languageId);
        },

        loadRules() {
            return this.ruleRepository.search(this.ruleCriteria).then((res) => {
                this.rules = res;
            });
        },

        onRuleChange(rules) {
            if (rules.length > this.product?.prices.length) {
                const newPriceRule = this.priceRepository.create();

                newPriceRule.productId = this.product?.id;
                newPriceRule.quantityStart = 1;
                newPriceRule.quantityEnd = null;
                newPriceRule.currencyId = this.defaultCurrency.id;
                newPriceRule.price = [
                    {
                        currencyId: this.defaultCurrency.id,
                        gross: 0,
                        linked: this.defaultPrice.linked,
                        net: 0,
                        listPrice: null,
                        regulationPrice: null,
                    },
                ];

                if (this.defaultPrice.listPrice) {
                    newPriceRule.price[0].listPrice = {
                        currencyId: this.defaultCurrency.id,
                        gross: this.defaultPrice.listPrice.gross,
                        linked: this.defaultPrice.listPrice.linked,
                        net: this.defaultPrice.listPrice.net,
                    };
                }

                if (this.defaultPrice.regulationPrice) {
                    newPriceRule.price[0].regulationPrice = {
                        currencyId: this.defaultCurrency.id,
                        gross: this.defaultPrice.regulationPrice.gross,
                        linked: this.defaultPrice.regulationPrice.linked,
                        net: this.defaultPrice.regulationPrice.net,
                    };
                }

                rules.forEach((rule) => {
                    if (this.product?.prices.some((item) => item.ruleId === rule.ruleId)) {
                        return;
                    }

                    newPriceRule.ruleId = rule.id;
                    newPriceRule.ruleName = rule.name;

                    this.product?.prices.add(newPriceRule);
                });

                return;
            }

            this.product?.prices.forEach((price) => {
                if (rules.some((rule) => price.ruleId === rule.ruleId)) {
                    return;
                }

                this.product?.prices.remove(price.id);
            });
        },

        onInheritanceRestore(item) {
            const parentProductFrozen = JSON.parse(this.parentProductFrozen);

            this.bulkEditProduct[item.name].isInherited = true;
            this.bulkEditProduct[item.name].value = parentProductFrozen[item.name];

            if (item.name === 'taxId') {
                this.taxRate = parentProductFrozen.tax;

                this.product.taxId = parentProductFrozen.taxId;
                return;
            }
            if (item.name === 'isPriceInherited') {
                this.product.price[0] = parentProductFrozen.price[0];
                this.product.purchasePrices[0] = parentProductFrozen.purchasePrices[0];

                const listPrice = !types.isEmpty(parentProductFrozen.price[0].listPrice)
                    ? parentProductFrozen.price[0].listPrice
                    : {
                          currencyId: this.currency.id,
                          net: null,
                          linked: true,
                          gross: null,
                      };
                this.product.listPrice = [listPrice];
                this.product.price[0].listPrice = listPrice;

                const regulationPrice = !types.isEmpty(parentProductFrozen.price[0].regulationPrice)
                    ? parentProductFrozen.price[0].regulationPrice
                    : {
                          currencyId: this.currency.id,
                          net: null,
                          linked: true,
                          gross: null,
                      };
                this.product.regulationPrice = [regulationPrice];
                this.product.price[0].regulationPrice = regulationPrice;

                return;
            }
            if (item.name === 'categories') {
                this.setProductAssociation(item.name, parentProductFrozen);
                return;
            }
            if (item.name === 'media') {
                this.product.media = parentProductFrozen.media;
                return;
            }
            if (item.name === 'prices') {
                this.setProductAssociation(item.name, parentProductFrozen);
                return;
            }
            if (item.name === 'properties') {
                this.setProductAssociation(item.name, parentProductFrozen);
                return;
            }
            if (item.name === 'searchKeywords') {
                this.setProductSearchKeywords();
                return;
            }

            this.product[item.name] = parentProductFrozen[item.name];
        },

        onInheritanceRemove(item) {
            if (
                [
                    'properties',
                    'prices',
                ].includes(item.name)
            ) {
                this.setProductAssociation(item.name);
            }

            this.bulkEditProduct[item.name].isInherited = false;
        },

        setProductSearchKeywords() {
            if (types.isEmpty(this.parentProduct?.customSearchKeywords)) {
                this.bulkEditProduct.searchKeywords.value = [];
                this.product.searchKeywords = [];

                return;
            }

            this.bulkEditProduct.searchKeywords.value = this.parentProduct.customSearchKeywords;
            this.product.searchKeywords = this.parentProduct.customSearchKeywords;
        },

        setProductAssociation(entityName, parentProduct = null) {
            const ids = this.product[entityName].getIds();
            ids.forEach((id) => this.product[entityName].remove(id));

            if (!parentProduct) {
                this.bulkEditProduct[entityName].value = [];
                return;
            }

            parentProduct[entityName].forEach((item) => this.product[entityName].add(item));
        },
    },
};
