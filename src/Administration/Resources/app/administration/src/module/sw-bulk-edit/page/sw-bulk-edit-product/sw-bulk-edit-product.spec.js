/**
 * @sw-package inventory
 */
import { config, mount } from '@vue/test-utils';
import { createRouter, createWebHashHistory } from 'vue-router';
import findByLabel from '../../../../../test/_helper_/find-by-label';

let bulkEditResponse = {
    data: {},
};

describe('src/module/sw-bulk-edit/page/sw-bulk-edit-product', () => {
    let routes;
    let router;

    async function createWrapper(
        productEntityOverride,
        initialRoute = {
            name: 'sw.bulk.edit.product.save',
            params: { parentId: 'null', includesDigital: '0' },
        },
        customMocks = {
            productRepositoryMock: undefined,
        },
    ) {
        const productEntity = productEntityOverride === undefined ? { metaTitle: 'test' } : productEntityOverride;

        const taxes = [
            {
                id: 'rate1',
                name: 'Rate 1',
                position: 1,
                taxRate: 19,
            },
            {
                id: 'rate2',
                name: 'Rate 2',
                position: 2,
                taxRate: 27,
            },
        ];

        const rules = [
            {
                id: '1',
                name: 'Cart >= 0',
            },
            {
                id: '2',
                name: 'Customer from USA',
            },
        ];

        // delete global $router and $routes mocks
        delete config.global.mocks.$router;
        delete config.global.mocks.$route;

        router = createRouter({
            history: createWebHashHistory(),
            routes,
        });
        router.push(initialRoute);
        await router.isReady();

        Shopware.Store.get('swProductDetail').$reset();
        Shopware.Store.get('swProductDetail').product = productEntity;
        Shopware.Store.get('swProductDetail').setTaxes(taxes);

        return mount(await wrapTestComponent('sw-bulk-edit-product', { sync: true }), {
            global: {
                plugins: [
                    router,
                ],
                stubs: {
                    'sw-page': await wrapTestComponent('sw-page'),
                    'sw-loader': await wrapTestComponent('sw-loader'),
                    'sw-bulk-edit-custom-fields': await wrapTestComponent('sw-bulk-edit-custom-fields'),
                    'sw-bulk-edit-change-type-field-renderer': await wrapTestComponent(
                        'sw-bulk-edit-change-type-field-renderer',
                    ),
                    'sw-bulk-edit-form-field-renderer': await wrapTestComponent('sw-bulk-edit-form-field-renderer'),
                    'sw-bulk-edit-change-type': await wrapTestComponent('sw-bulk-edit-change-type'),
                    'sw-form-field-renderer': await wrapTestComponent('sw-form-field-renderer'),
                    'sw-empty-state': await wrapTestComponent('sw-empty-state'),
                    'sw-button-process': await wrapTestComponent('sw-button-process'),
                    'sw-ignore-class': true,
                    'sw-context-menu-item': true,
                    'sw-select-base': await wrapTestComponent('sw-select-base'),
                    'sw-single-select': await wrapTestComponent('sw-single-select'),
                    'sw-text-field': await wrapTestComponent('sw-text-field'),
                    'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                    'sw-textarea-field': await wrapTestComponent('sw-textarea-field'),
                    'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field'),
                    'sw-checkbox-field-deprecated': await wrapTestComponent('sw-checkbox-field-deprecated', { sync: true }),
                    'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-container': await wrapTestComponent('sw-container'),
                    'sw-field-error': await wrapTestComponent('sw-field-error'),
                    'sw-entity-single-select': await wrapTestComponent('sw-entity-single-select'),
                    'sw-entity-multi-select': await wrapTestComponent('sw-entity-multi-select'),
                    'sw-card-view': await wrapTestComponent('sw-card-view'),
                    'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                    'sw-select-result': await wrapTestComponent('sw-select-result'),
                    'sw-popover': await wrapTestComponent('sw-popover'),
                    'sw-popover-deprecated': await wrapTestComponent('sw-popover-deprecated', { sync: true }),
                    'sw-highlight-text': await wrapTestComponent('sw-highlight-text'),
                    'sw-price-field': await wrapTestComponent('sw-price-field'),
                    'sw-inherit-wrapper': await wrapTestComponent('sw-inherit-wrapper'),
                    'sw-select-selection-list': await wrapTestComponent('sw-select-selection-list'),
                    'sw-bulk-edit-save-modal': await wrapTestComponent('sw-bulk-edit-save-modal'),
                    'sw-switch-field-deprecated': await wrapTestComponent('sw-switch-field-deprecated'),
                    'sw-bulk-edit-product-visibility': true,
                    'sw-product-visibility-select': true,
                    'sw-custom-field-set-renderer': true,
                    'sw-text-editor-toolbar': true,
                    'sw-app-actions': true,
                    'sw-search-bar': true,
                    'sw-datepicker': true,
                    'sw-text-editor': true,
                    'sw-language-switch': true,
                    'sw-notification-center': true,
                    'sw-help-center': true,
                    'sw-multi-tag-select': true,
                    'sw-entity-tag-select': true,
                    'sw-product-properties': true,
                    'sw-product-detail-context-prices': true,
                    'sw-category-tree-field': true,
                    'sw-bulk-edit-product-media': true,
                    'sw-tabs': await wrapTestComponent('sw-tabs'),
                    'sw-tabs-deprecated': await wrapTestComponent('sw-tabs-deprecated', { sync: true }),
                    'sw-tabs-item': await wrapTestComponent('sw-tabs-item'),
                    'sw-label': true,
                    'sw-extension-component-section': true,
                    'sw-inheritance-switch': true,
                    'sw-bulk-edit-product-description': true,
                    'mt-loader': true,
                    'sw-loader-deprecated': true,
                    'sw-app-topbar-button': true,
                    'sw-error-summary': true,
                    'sw-ai-copilot-badge': true,
                    'sw-context-button': true,
                    'sw-help-center-v2': true,
                    'sw-help-text': true,
                    'sw-field-copyable': true,
                    'sw-maintain-currencies-modal': true,
                    'sw-product-variant-info': true,
                    'mt-textarea': true,
                    'sw-textarea-field-deprecated': true,
                    'mt-text-field': true,
                    'mt-tabs': true,
                    'sw-media-collapse': true,
                    'mt-floating-ui': true,
                },
                provide: {
                    validationService: {},
                    bulkEditApiFactory: {
                        getHandler: () => {
                            return {
                                bulkEdit: (selectedIds) => {
                                    if (selectedIds.length === 0) {
                                        return Promise.reject();
                                    }

                                    return Promise.resolve(bulkEditResponse);
                                },
                            };
                        },
                    },
                    repositoryFactory: {
                        create: (entity) => {
                            if (entity === 'currency') {
                                return {
                                    search: () =>
                                        Promise.resolve([
                                            {
                                                id: 'currencyId1',
                                                isSystemDefault: true,
                                            },
                                        ]),
                                    get: () => Promise.resolve({ id: '' }),
                                };
                            }

                            if (entity === 'custom_field_set') {
                                return {
                                    search: () =>
                                        Promise.resolve([
                                            { id: 'field-set-id-1' },
                                        ]),
                                    get: () => Promise.resolve({ id: '' }),
                                };
                            }

                            if (entity === 'tax') {
                                return {
                                    search: () => Promise.resolve(taxes),
                                    get: () => Promise.resolve(null),
                                };
                            }

                            if (entity === 'product') {
                                if (customMocks.productRepositoryMock) {
                                    return customMocks.productRepositoryMock;
                                }

                                return {
                                    create: () => ({
                                        isNew: () => true,
                                        ...productEntity,
                                    }),
                                    get: (productId) => {
                                        if (productId === 'failingProduct') {
                                            return Promise.reject();
                                        }

                                        return Promise.resolve(productEntity);
                                    },
                                };
                            }

                            if (entity === 'rule') {
                                return {
                                    search: () => Promise.resolve(rules),
                                    get: () => Promise.resolve(null),
                                };
                            }

                            return {
                                search: () => Promise.resolve([{ id: 'Id' }]),
                                get: () => Promise.resolve({ id: 'Id' }),
                            };
                        },
                    },
                    orderDocumentApiService: {
                        create: () => {
                            return Promise.resolve();
                        },
                        download: () => {
                            return Promise.resolve();
                        },
                    },
                    shortcutService: {
                        startEventListener: () => {},
                        stopEventListener: () => {},
                    },
                },
            },
            props: {
                title: 'Foo bar',
            },
            attachTo: document.body,
        });
    }

    beforeAll(async () => {
        routes = [
            {
                name: 'sw.product.detail.variants',
                path: '/:id?/variants',
            },
            {
                name: 'sw.bulk.edit.product',
                path: '/index/:parentId?/:includesDigital?',
                meta: {
                    $module: {
                        title: 'sw-bulk-edit-product.general.mainMenuTitle',
                    },
                },
                children: [
                    {
                        name: 'sw.bulk.edit.product.save',
                        path: 'save',
                        component: await wrapTestComponent('sw-bulk-edit-save-modal', { sync: true }),
                        meta: {
                            $module: {
                                title: 'sw-bulk-edit-product.general.mainMenuTitle',
                            },
                        },
                        redirect: {
                            name: 'sw.bulk.edit.product.save.confirm',
                        },
                        children: [
                            {
                                name: 'sw.bulk.edit.product.save.confirm',
                                path: 'confirm',
                                component: await wrapTestComponent('sw-bulk-edit-save-modal-confirm', { sync: true }),
                                meta: {
                                    $module: {
                                        title: 'sw-bulk-edit-product.general.mainMenuTitle',
                                    },
                                },
                            },
                            {
                                name: 'sw.bulk.edit.product.save.process',
                                path: 'process',
                                component: await wrapTestComponent('sw-bulk-edit-save-modal-process', { sync: true }),
                                meta: {
                                    $module: {
                                        title: 'sw-bulk-edit-product.general.mainMenuTitle',
                                    },
                                },
                            },
                            {
                                name: 'sw.bulk.edit.product.save.success',
                                path: 'success',
                                component: await wrapTestComponent('sw-bulk-edit-save-modal-success', { sync: true }),
                                meta: {
                                    $module: {
                                        title: 'sw-bulk-edit-product.general.mainMenuTitle',
                                    },
                                },
                            },
                            {
                                name: 'sw.bulk.edit.product.save.error',
                                path: 'error',
                                component: await wrapTestComponent('sw-bulk-edit-save-modal-error', { sync: true }),
                                meta: {
                                    $module: {
                                        title: 'sw-bulk-edit-product.general.mainMenuTitle',
                                    },
                                },
                            },
                        ],
                    },
                ],
            },
        ];

        Shopware.Application.getContainer('factory').apiService.register('calculate-price', {
            calculatePrice: () => {
                return new Promise((resolve) => {
                    resolve({
                        data: {
                            calculatedTaxes: [],
                        },
                    });
                });
            },
        });
    });

    beforeEach(async () => {
        const mockResponses = global.repositoryFactoryMock.responses;
        mockResponses.addResponse({
            method: 'post',
            url: '/search/custom-field-set',
            status: 200,
            response: {
                data: [
                    {
                        id: Shopware.Utils.createId(),
                        attributes: {
                            id: Shopware.Utils.createId(),
                        },
                    },
                ],
            },
        });
        Shopware.Store.get('swBulkEdit').selectedIds = [
            Shopware.Utils.createId(),
        ];
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should be handled change data', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const infoForm = wrapper.find('.sw-bulk-edit-product-base__info');
        const activeField = infoForm.find('.sw-bulk-edit-change-field-active');
        await activeField.find('.sw-bulk-edit-change-field__change input').setValue('checked');

        await flushPromises();

        await activeField.find('.sw-field--switch__input input').setValue('checked');

        expect(wrapper.vm.bulkEditProduct.active.isChanged).toBeTruthy();

        wrapper.vm.onProcessData();
        wrapper.vm.bulkEditSelected.forEach((change, index) => {
            const changeField = wrapper.vm.bulkEditSelected[index];
            expect(changeField.type).toEqual(change.type);
            expect(changeField.value).toEqual(change.value);
        });

        await flushPromises();

        await wrapper.find('.sw-bulk-edit-product__save-action').trigger('click');

        await flushPromises();

        expect(wrapper.find('.sw-bulk-edit-save-modal-confirm').exists()).toBeTruthy();
        expect(wrapper.vm.$route.path).toBe('/index/null/0/save/confirm');
    });

    it('should close confirm modal', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-bulk-edit-product__save-action').trigger('click');

        await flushPromises();

        expect(wrapper.find('.sw-bulk-edit-save-modal-confirm').exists()).toBeTruthy();

        const footerLeft = wrapper.find('.footer-left');
        await footerLeft.find('button').trigger('click');

        await flushPromises();

        expect(wrapper.vm.$route.path).toBe('/index');
    });

    it('should open process and success modal', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-bulk-edit-product__save-action').trigger('click');

        await flushPromises();

        expect(wrapper.find('.sw-bulk-edit-save-modal-confirm').exists()).toBeTruthy();

        const footerRight = wrapper.find('.footer-right');
        await footerRight.find('button').trigger('click');

        await flushPromises();

        expect(wrapper.vm.$router.options.history.state.back).toBe('/index/null/0/save/process');
        expect(wrapper.vm.$route.path).toBe('/index/null/0/save/success');
    });

    it('should open fail modal', async () => {
        bulkEditResponse = {
            data: null,
        };

        const wrapper = await createWrapper();

        await wrapper.find('.sw-bulk-edit-product__save-action').trigger('click');

        await flushPromises();

        expect(wrapper.find('.sw-bulk-edit-save-modal-confirm').exists()).toBeTruthy();

        const footerRight = wrapper.find('.footer-right');
        await footerRight.find('button').trigger('click');

        await flushPromises();

        expect(wrapper.vm.$route.path).toBe('/index/null/0/save/error');
    });

    it('should be show empty state', async () => {
        Shopware.Store.get('swBulkEdit').selectedIds = [];
        const wrapper = await createWrapper();
        await flushPromises();

        const emptyState = wrapper.find('.sw-empty-state');
        expect(emptyState.find('.sw-empty-state__title').text()).toBe('sw-bulk-edit.product.messageEmptyTitle');
    });

    it('should be selected taxRate on click change tax field', async () => {
        const productEntity = {
            taxId: null,
        };

        const wrapper = await createWrapper(productEntity, {
            name: 'sw.bulk.edit.product',
            params: { parentId: 'null' },
        });

        await flushPromises();

        const taxField = wrapper.find('.sw-bulk-edit-change-field-taxId');
        await taxField.find('.sw-bulk-edit-change-field__change input').trigger('click');

        await flushPromises();

        await taxField.find('.sw-select__selection').trigger('click');

        await flushPromises();

        const taxList = wrapper.find('.sw-select-result-list__item-list');
        const secondTax = taxList.find('.sw-select-option--1');
        await secondTax.trigger('click');

        expect(secondTax.text()).toBe('Rate 2');
        expect(wrapper.vm.taxRate.name).toBe('Rate 2');
    });

    it('should be correct data when the user overwrite minPurchase', async () => {
        const productEntity = {
            minPurchase: 2,
        };
        const wrapper = await createWrapper(productEntity, {
            name: 'sw.bulk.edit.product',
            params: { parentId: 'null' },
        });

        await flushPromises();

        const minPurchaseFieldForm = wrapper.find('.sw-bulk-edit-change-field-minPurchase');
        await minPurchaseFieldForm.find('.sw-bulk-edit-change-field__change input').setValue('checked');
        await flushPromises();

        wrapper.vm.onProcessData();

        const changeField = wrapper.vm.bulkEditSelected[0];
        expect(changeField.field).toBe('minPurchase');
        expect(changeField.type).toBe('overwrite');
        expect(changeField.value).toBe(2);
    });

    it('should be null for the value and the type is overwrite data when minPurchase set to clear type', async () => {
        const productEntity = {
            minPurchase: 2,
        };
        const wrapper = await createWrapper(productEntity, {
            name: 'sw.bulk.edit.product',
            params: { parentId: 'null' },
        });

        await flushPromises();

        const minPurchaseField = wrapper.find('.sw-bulk-edit-change-field-minPurchase');
        await minPurchaseField.find('.sw-bulk-edit-change-field__change input').setValue('checked');
        await flushPromises();

        await minPurchaseField.find('.sw-select__selection').trigger('click');
        await flushPromises();

        const changeTypeList = wrapper.find('.sw-select-result-list__item-list');
        const clearOption = changeTypeList.find('.sw-select-option--1');

        await clearOption.trigger('click');
        await flushPromises();
        expect(minPurchaseField.find('.sw-single-select__selection-text').text()).toBe('sw-bulk-edit.changeTypes.clear');

        wrapper.vm.onProcessData();

        const changeField = wrapper.vm.bulkEditSelected[0];
        expect(changeField.field).toBe('minPurchase');
        expect(changeField.type).toBe('overwrite');
        expect(changeField.value).toBeNull();
    });

    it('should be getting the price', async () => {
        const wrapper = await createWrapper({}, { name: 'sw.bulk.edit.product', params: { parentId: 'null' } });

        await flushPromises();

        const priceFieldsForm = wrapper.find('.sw-bulk-edit-change-field-price');
        const priceGrossInput = wrapper.findByLabel('global.sw-price-field.labelPriceGross');
        await priceGrossInput.setValue('6');
        await flushPromises();

        await priceFieldsForm.find('.sw-bulk-edit-change-field__change input').setValue('checked');

        wrapper.vm.onProcessData();

        const changeField = wrapper.vm.bulkEditSelected[0];
        expect(changeField.field).toBe('price');
        expect(changeField.value[0]).toHaveProperty('currencyId');
        expect(changeField.value[0]).toHaveProperty('net');
        expect(changeField.value[0]).toHaveProperty('linked');
        expect(changeField.value[0]).toHaveProperty('gross');
        expect(changeField.value[0]).not.toHaveProperty('listPrice');
        expect(wrapper.vm.bulkEditProduct.price.value).toBeTruthy();
    });

    it('should be getting the list price when the price field is exists', async () => {
        const wrapper = await createWrapper({}, { name: 'sw.bulk.edit.product', params: { parentId: 'null' } });

        await flushPromises();

        const priceFieldsForm = wrapper.find('.sw-bulk-edit-change-field-price');
        await priceFieldsForm.find('.sw-bulk-edit-change-field__change input').setValue('checked');
        const priceGrossInput = findByLabel(priceFieldsForm, 'global.sw-price-field.labelPriceGross');
        await priceGrossInput.setValue('6');
        await flushPromises();

        const listPriceFieldsForm = wrapper.find('.sw-bulk-edit-change-field-listPrice');
        await listPriceFieldsForm.find('.sw-bulk-edit-change-field__change input').setValue('checked');
        await flushPromises();

        const listPriceFields = listPriceFieldsForm.find('.sw-price-field');
        const listPriceGrossInput = findByLabel(listPriceFields, 'global.sw-price-field.labelPriceGross');
        await listPriceGrossInput.setValue('5');

        wrapper.vm.onProcessData();

        const changeField = wrapper.vm.bulkEditSelected[0];
        expect(changeField.field).toBe('price');
        expect(changeField.value[0]).toHaveProperty('currencyId');
        expect(changeField.value[0]).toHaveProperty('net');
        expect(changeField.value[0]).toHaveProperty('linked');
        expect(changeField.value[0]).toHaveProperty('gross');
        expect(changeField.value[0]).toHaveProperty('listPrice');
    });

    it('should be getting the listPrice when the price field is enabled', async () => {
        const wrapper = await createWrapper({}, { name: 'sw.bulk.edit.product', params: { parentId: 'null' } });

        await flushPromises();

        const priceFieldsForm = wrapper.find('.sw-bulk-edit-change-field-price');
        const priceGrossInput = findByLabel(priceFieldsForm, 'global.sw-price-field.labelPriceGross');
        await priceGrossInput.setValue('6');
        await flushPromises();

        await priceFieldsForm.find('.sw-bulk-edit-change-field__change input').setValue('checked');

        const listPriceFieldsForm = wrapper.find('.sw-bulk-edit-change-field-listPrice');
        const listPriceGrossInput = findByLabel(listPriceFieldsForm, 'global.sw-price-field.labelPriceGross');
        await listPriceGrossInput.setValue('5');
        await flushPromises();

        await listPriceFieldsForm.find('.sw-bulk-edit-change-field__change input').setValue('checked');
        await flushPromises();

        wrapper.vm.onProcessData();

        const changeField = wrapper.vm.bulkEditSelected[0];
        expect(changeField.field).toBe('price');
        expect(changeField.value[0]).toHaveProperty('currencyId');
        expect(changeField.value[0]).toHaveProperty('net');
        expect(changeField.value[0]).toHaveProperty('linked');
        expect(changeField.value[0]).toHaveProperty('gross');
        expect(changeField.value[0]).toHaveProperty('listPrice');
    });

    it('should be correct data when select categories', async () => {
        const productEntity = {
            categories: [
                {
                    id: 'category1',
                },
                {
                    id: 'category2',
                },
            ],
        };
        const wrapper = await createWrapper(productEntity, {
            name: 'sw.bulk.edit.product',
            params: { parentId: 'null' },
        });

        await flushPromises();

        const categoriesFieldForm = wrapper.find('.sw-bulk-edit-change-field-categories');
        await categoriesFieldForm.find('.sw-bulk-edit-change-field__change input').setValue('checked');
        await flushPromises();

        wrapper.vm.onProcessData();

        const changeField = wrapper.vm.bulkEditSelected[0];
        expect(changeField.field).toBe('categories');
        expect(changeField.type).toBe('overwrite');
        expect(changeField.value[0].id).toBe(productEntity.categories[0].id);
        expect(changeField.value[1].id).toBe(productEntity.categories[1].id);
    });

    it('should be correct data when select visibilities', async () => {
        const productEntity = {
            visibilities: [
                {
                    productId: 'productId123',
                    salesChannelId: 'salesChannelId345',
                    visibility: 30,
                },
            ],
        };
        const wrapper = await createWrapper(productEntity, {
            name: 'sw.bulk.edit.product',
            params: { parentId: 'null' },
        });

        await flushPromises();

        const categoriesFieldForm = wrapper.find('.sw-bulk-edit-change-field-visibilities');
        await categoriesFieldForm.find('.sw-bulk-edit-change-field__change input').setValue('checked');
        await flushPromises();

        wrapper.vm.onProcessData();

        const changeField = wrapper.vm.bulkEditSelected[0];
        expect(changeField.field).toBe('visibilities');
        expect(changeField.type).toBe('overwrite');
        expect(changeField.value[0].productId).toBe('productId123');
        expect(changeField.value[0].salesChannelId).toBe('salesChannelId345');
        expect(changeField.value[0].visibility).toBe(30);
    });

    it('should be correct selections data', async () => {
        const productEntity = {
            media: [
                {
                    id: 'a435755c6c4f4fb4b81ec32b4c07e06e',
                    mediaId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                },
            ],
        };
        const wrapper = await createWrapper(productEntity, {
            name: 'sw.bulk.edit.product',
            params: { parentId: 'null' },
        });

        await flushPromises();

        const categoriesFieldForm = wrapper.find('.sw-bulk-edit-change-field-media');
        await categoriesFieldForm.find('.sw-bulk-edit-change-field__change input').setValue('checked');
        await flushPromises();

        wrapper.vm.onProcessData();

        const changeMediaField = wrapper.vm.bulkEditSelected[0];
        expect(changeMediaField.field).toBe('media');
        expect(changeMediaField.type).toBe('overwrite');
        expect(changeMediaField.value[0].id).toBe('a435755c6c4f4fb4b81ec32b4c07e06e');
        expect(changeMediaField.value[0].mediaId).toBe('b7d2554b0ce847cd82f3ac9bd1c0dfca');
    });

    it('should be convert key to customSearchKeywords when the user changed searchKeywords', async () => {
        const wrapper = await createWrapper();

        await flushPromises();

        const minPurchaseFieldForm = wrapper.find('.sw-bulk-edit-change-field-searchKeywords');
        await minPurchaseFieldForm.find('.sw-bulk-edit-change-field__change input').setValue('checked');
        await flushPromises();

        wrapper.vm.onProcessData();

        const changeField = wrapper.vm.bulkEditSelected[0];
        expect(changeField.field).toBe('customSearchKeywords');
    });

    it('should be correct data when select prices', async () => {
        const productEntity = {
            prices: [
                {
                    productId: 'productId',
                    ruleId: 'ruleId',
                    ruleName: 'Cart >= 0',
                },
            ],
        };

        const wrapper = await createWrapper(productEntity, {
            name: 'sw.bulk.edit.product',
            params: { parentId: 'null' },
        });

        await flushPromises();

        const advancedPricesFieldForm = wrapper.find('.sw-bulk-edit-product-base__advanced-prices');
        await advancedPricesFieldForm.find('.sw-bulk-edit-change-field__change input').setValue('checked');

        await flushPromises();

        wrapper.vm.onProcessData();

        const changeField = wrapper.vm.bulkEditSelected[0];

        expect(changeField.field).toBe('prices');
        expect(changeField.type).toBe('overwrite');
        expect(changeField.value[0].productId).toBe('productId');
        expect(changeField.value[0].ruleId).toBe('ruleId');
    });

    it('should restrict fields on including digital products', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.deliverabilityFormFields.length).toBeGreaterThan(1);

        wrapper.vm.$router.push({
            name: 'sw.bulk.edit.product',
            params: { parentId: 'null', includesDigital: '1' },
        });

        await flushPromises();

        expect(wrapper.vm.deliverabilityFormFields).toHaveLength(1);
        expect(wrapper.vm.deliverabilityFormFields[0].name).toBe('deliveryTimeId');
    });

    it('should set route meta module when component created', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.setRouteMetaModule = jest.fn();

        wrapper.vm.createdComponent();
        expect(wrapper.vm.setRouteMetaModule).toHaveBeenCalled();
        expect(wrapper.vm.$route.meta.$module.color).toBe('#57D9A3');
        expect(wrapper.vm.$route.meta.$module.icon).toBe('regular-products');

        wrapper.vm.setRouteMetaModule.mockRestore();
    });

    it('should disable processing button', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoading: false,
            bulkEditProduct: {
                taxId: {
                    isChanged: false,
                },
                price: {
                    isChanged: false,
                },
                purchasePrices: {
                    isChanged: false,
                },
                listPrice: {
                    isChanged: false,
                },
            },
        });
        expect(wrapper.find('.sw-button-process').attributes('disabled') !== undefined).toBe(true);

        await wrapper.setData({
            isLoading: false,
            bulkEditProduct: {
                taxId: {
                    isChanged: true,
                },
                price: {
                    isChanged: true,
                },
                purchasePrices: {
                    isChanged: false,
                },
                listPrice: {
                    isChanged: false,
                },
            },
        });
        expect(wrapper.find('.sw-button-process').attributes('disabled')).toBeUndefined();
    });

    it('should get parent product when component created', async () => {
        const wrapper = await createWrapper(undefined, {
            name: 'sw.bulk.edit.product.save',
            params: {},
        });
        wrapper.vm.getParentProduct = jest.fn();

        wrapper.vm.createdComponent();
        expect(wrapper.vm.getParentProduct).toHaveBeenCalled();

        wrapper.vm.getParentProduct.mockRestore();
    });

    const dataProvider = [
        [
            true,
            'price',
            {
                currencyId: 'currencyId',
                gross: '1',
                net: '2',
            },
        ],
        [
            false,
            'purchasePrices',
            {
                currencyId: 'currencyId',
                gross: '1',
                net: '2',
            },
        ],
        [
            true,
            'price',
            true,
        ],
        [
            true,
            'price',
            null,
        ],
    ];

    it.each(dataProvider)('should have set price to product when value is not boolean', async (isChanged, item, value) => {
        const wrapper = await createWrapper(undefined, {
            name: 'sw.bulk.edit.product.save',
            params: {
                parentId: 'pArEnT_ID',
                // includesDigital: '0'
            },
        });

        await wrapper.setData({
            isLoading: false,
            bulkEditProduct: {
                price: {
                    isChanged: isChanged,
                    value: null,
                },
            },
        });

        wrapper.vm.onChangePrices(item, value);
        wrapper.vm.createdComponent();
        expect(wrapper.vm.bulkEditProduct.price.isChanged).toBe(isChanged);
        expect(wrapper.vm.bulkEditProduct.price.value).toBeNull();

        let expected;
        if (value && typeof value !== 'boolean') {
            expected = [value];
        }

        expect(wrapper.vm.product[item]).toEqual(expected);
    });

    it('should not be able to get parent product', async () => {
        const wrapper = await createWrapper(null);

        await wrapper.setData({
            $route: {
                params: {
                    parentId: 'null',
                },
            },
        });
        await wrapper.vm.getParentProduct();

        expect(wrapper.vm.parentProduct).toStrictEqual({});
    });

    it('should get parent product successful', async () => {
        const wrapper = await createWrapper(undefined, undefined, {
            productRepositoryMock: {
                get: jest.fn(() => {
                    return Promise.resolve({
                        id: 'productId',
                        name: 'productName',
                    });
                }),
            },
        });

        await wrapper.vm.$router.push({
            name: 'sw.bulk.edit.product',
            params: { parentId: 'productId' },
        });
        await wrapper.vm.getParentProduct();

        expect(wrapper.vm.parentProductFrozen).toEqual(
            JSON.stringify({
                id: 'productId',
                name: 'productName',
                stock: null,
            }),
        );
        wrapper.vm.productRepository.get.mockRestore();
    });

    it('should get parent product failed', async () => {
        const wrapper = await createWrapper(
            {},
            {
                name: 'sw.bulk.edit.product',
                params: { parentId: 'failingProduct' },
            },
        );

        await wrapper.vm.getParentProduct();

        expect(wrapper.vm.parentProduct).toStrictEqual({});
        expect(wrapper.vm.parentProductFrozen).toBeNull();
    });
});
