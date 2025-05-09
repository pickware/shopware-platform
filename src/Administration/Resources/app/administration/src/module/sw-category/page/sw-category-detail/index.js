import './store';
import template from './sw-category-detail.html.twig';
import './sw-category-detail.scss';

const { Context, Mixin } = Shopware;
const { Criteria, ChangesetGenerator, EntityCollection } = Shopware.Data;
const { cloneDeep, merge } = Shopware.Utils.object;
const type = Shopware.Utils.types;

/**
 * @sw-package discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'acl',
        'cmsService',
        'repositoryFactory',
        'seoUrlService',
        'systemConfigApiService',
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
    ],

    shortcuts: {
        'SYSTEMKEY+S': {
            active() {
                return this.acl.can('category.editor');
            },
            method: 'onSave',
        },
        ESCAPE: 'cancelEdit',
    },

    props: {
        categoryId: {
            type: String,
            required: false,
            default: null,
        },
        landingPageId: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            term: '',
            isLoading: false,
            isCustomFieldLoading: false,
            isSaveSuccessful: false,
            isMobileViewport: null,
            splitBreakpoint: 1024,
            isDisplayingLeavePageWarning: false,
            nextRoute: null,
            currentLanguageId: Shopware.Context.api.languageId,
            forceDiscardChanges: false,
            categoryCheckedItem: 0,
            landingPageCheckedItem: 0,
            entryPointOverwriteConfirmed: false,
            entryPointOverwriteSalesChannels: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        changesetGenerator() {
            return new ChangesetGenerator();
        },

        showEmptyState() {
            return !this.category && !this.landingPage;
        },

        identifier() {
            return this.category ? this.placeholder(this.category, 'name') : '';
        },

        landingPageRepository() {
            return this.repositoryFactory.create('landing_page');
        },

        categoryRepository() {
            return this.repositoryFactory.create('category');
        },

        cmsPageRepository() {
            return this.repositoryFactory.create('cms_page');
        },

        landingPage() {
            if (!Shopware.Store.get('swCategoryDetail')) {
                return {};
            }

            return Shopware.Store.get('swCategoryDetail').landingPage;
        },

        category() {
            if (!Shopware.Store.get('swCategoryDetail')) {
                return {};
            }

            return Shopware.Store.get('swCategoryDetail').category;
        },

        showEntryPointOverwriteModal() {
            return this.entryPointOverwriteSalesChannels !== null && this.entryPointOverwriteSalesChannels.length;
        },

        cmsPage() {
            return Shopware.Store.get('cmsPage').currentPage;
        },

        cmsPageState() {
            return Shopware.Store.get('cmsPage');
        },

        cmsPageId() {
            if (this.landingPage) {
                return this.landingPage.cmsPageId ?? null;
            }

            return this.category ? this.category.cmsPageId : null;
        },

        customFieldSetRepository() {
            return this.repositoryFactory.create('custom_field_set');
        },

        customFieldSetCriteria() {
            const criteria = new Criteria(1, null);

            criteria.addFilter(Criteria.equals('relations.entityName', 'category'));

            return criteria;
        },

        customFieldSetLandingPageCriteria() {
            const criteria = new Criteria(1, null);

            criteria.addFilter(Criteria.equals('relations.entityName', 'landing_page'));

            return criteria;
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        pageClasses() {
            return {
                'has--category': !!this.category,
                'is--mobile': !!this.isMobileViewport,
            };
        },

        tooltipSave() {
            if (!this.acl.can('category.editor')) {
                return {
                    message: this.$tc('sw-privileges.tooltip.warning'),
                    disabled: this.acl.can('category.editor'),
                    showOnDisabledElements: true,
                };
            }

            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light',
            };
        },

        landingPageTooltipSave() {
            if (!this.acl.can('landing_page.editor')) {
                return {
                    message: this.$tc('sw-privileges.tooltip.warning'),
                    disabled: this.acl.can('landing_page.editor'),
                    showOnDisabledElements: true,
                };
            }

            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light',
            };
        },

        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light',
            };
        },

        categoryCriteria() {
            const criteria = new Criteria(1, 1);
            criteria.getAssociation('seoUrls').addFilter(Criteria.equals('isCanonical', true));

            criteria
                .addAssociation('tags')
                .addAssociation('media')
                .addAssociation('navigationSalesChannels.homeCmsPage.previewMedia')
                .addAssociation('serviceSalesChannels')
                .addAssociation('footerSalesChannels')
                .addAssociation('translations');

            return criteria;
        },

        landingPageCriteria() {
            const criteria = new Criteria(1, 1);

            criteria.addAssociation('tags');
            criteria.addAssociation('salesChannels');

            return criteria;
        },

        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },
    },

    watch: {
        landingPageId() {
            this.setLandingPage();
        },

        categoryId() {
            this.setCategory();
        },

        cmsPageId() {
            if (this.isLoading) {
                return;
            }

            if (this.category) {
                this.cmsPageState.resetCmsPageState();
                this.getAssignedCmsPage();
            }

            if (this.landingPage) {
                this.cmsPageState.resetCmsPageState();
                this.getAssignedCmsPageForLandingPage();
            }
        },
    },

    beforeCreate() {
        Shopware.Store.get('cmsPage').resetCmsPageState();
    },

    created() {
        this.createdComponent();
    },

    beforeRouteLeave(to, from, next) {
        if (this.forceDiscardChanges) {
            this.forceDiscardChanges = false;
            Shopware.Store.get('shopwareApps').selectedIds = [];
            next();

            return;
        }

        if (!this.category) {
            Shopware.Store.get('shopwareApps').selectedIds = [];
            next();

            return;
        }

        /*
         * Generate change set for category and delete `id` and `versionId` to only consider actual changes.
         * A new version without changes should not trigger the navigation guard.
         */
        const { changes, deletionQueue } = this.changesetGenerator.generate(this.category);
        if (changes === null) {
            Shopware.Store.get('shopwareApps').selectedIds = [];
            next();

            return;
        }

        const keysToDelete = [
            'id',
            'versionId',
        ];
        const changedKeys = Object.keys(changes).filter((key) => !keysToDelete.includes(key));
        const hasDeletions = deletionQueue.length > 0;

        /*
         * Allow exiting the route to the `cms.page.create` route
         * when just the cmsPage assignment has been cleared.
         */
        if (
            to.name === 'sw.cms.create' &&
            changedKeys.length === 1 &&
            changedKeys[0] === 'cmsPageId' &&
            changes.cmsPageId === null &&
            !hasDeletions
        ) {
            Shopware.Store.get('shopwareApps').selectedIds = [];
            next();

            return;
        }

        if (changedKeys.length === 0 && !hasDeletions) {
            Shopware.Store.get('shopwareApps').selectedIds = [];
            next();

            return;
        }

        this.isDisplayingLeavePageWarning = true;
        this.nextRoute = to;
        next(false);
    },

    methods: {
        createdComponent() {
            Shopware.ExtensionAPI.publishData({
                id: 'sw-category-detail__category',
                path: 'category',
                scope: this,
            });

            Shopware.ExtensionAPI.publishData({
                id: 'sw-category-detail__cmsPage',
                path: 'cmsPage',
                scope: this,
            });

            this.isLoading = true;
            this.checkViewport();
            this.registerListener();

            if (this.categoryId !== null) {
                this.setCategory();

                return;
            }

            this.setLandingPage();
        },

        categoryCheckedElementsCount(count) {
            this.categoryCheckedItem = count;
        },

        landingPageCheckedElementsCount(count) {
            this.landingPageCheckedItem = count;
        },

        registerListener() {
            this.$device.onResize({
                listener: this.checkViewport,
            });
        },

        onSearch(value) {
            if (value.length === 0) {
                value = undefined;
            }
            this.term = value;
        },

        checkViewport() {
            this.isMobileViewport = this.$device.getViewportWidth() < this.splitBreakpoint;
        },

        getAssignedCmsPage() {
            if (this.cmsPageId === null) {
                return Promise.resolve(null);
            }

            const cmsPageId = this.cmsPageId;
            const criteria = new Criteria(1, 1);
            criteria.setIds([cmsPageId]);
            criteria.addAssociation('previewMedia');
            criteria.addAssociation('sections');
            criteria.getAssociation('sections').addSorting(Criteria.sort('position'));

            criteria.addAssociation('sections.blocks');
            criteria.getAssociation('sections.blocks').addSorting(Criteria.sort('position', 'ASC')).addAssociation('slots');

            return this.cmsPageRepository.search(criteria).then((response) => {
                const cmsPage = response.get(cmsPageId);

                if (cmsPageId !== this.cmsPageId) {
                    return null;
                }

                if (this.category.slotConfig !== null) {
                    cmsPage.sections.forEach((section) => {
                        section.blocks.forEach((block) => {
                            block.slots.forEach((slot) => {
                                if (this.category.slotConfig[slot.id]) {
                                    if (slot.config === null) {
                                        slot.config = {};
                                    }
                                    merge(slot.config, cloneDeep(this.category.slotConfig[slot.id]));
                                }
                            });
                        });
                    });
                }

                this.updateCmsPageDataMapping();
                this.cmsPageState.setCurrentPage(cmsPage);

                return this.cmsPage;
            });
        },

        updateCmsPageDataMapping() {
            this.cmsPageState.setCurrentMappingEntity('category');
            this.cmsPageState.setCurrentMappingTypes(this.cmsService.getEntityMappingTypes('category'));
            this.cmsPageState.setCurrentDemoEntity(this.category);
        },

        getAssignedCmsPageForLandingPage() {
            if (this.cmsPageId === null) {
                return Promise.resolve(null);
            }

            const cmsPageId = this.cmsPageId;
            const criteria = new Criteria(1, 1);
            criteria.setIds([cmsPageId]);
            criteria.addAssociation('previewMedia');
            criteria.addAssociation('sections');
            criteria.getAssociation('sections').addSorting(Criteria.sort('position'));

            criteria.addAssociation('sections.blocks');
            criteria
                .getAssociation('sections.blocks')
                .addSorting(Criteria.sort('position', 'ASC'))
                .getAssociation('slots')
                .addAssociation('translations');

            return this.cmsPageRepository.search(criteria).then((response) => {
                const cmsPage = response.get(cmsPageId);
                if (cmsPageId !== this.cmsPageId) {
                    return null;
                }

                if (this.landingPage.slotConfig !== null) {
                    cmsPage.sections.forEach((section) => {
                        section.blocks.forEach((block) => {
                            block.slots.forEach((slot) => {
                                if (this.landingPage.slotConfig[slot.id]) {
                                    if (slot.config === null) {
                                        slot.config = {};
                                    }
                                    merge(slot.config, cloneDeep(this.landingPage.slotConfig[slot.id]));
                                }
                            });
                        });
                    });
                }

                this.updateCmsPageDataMappingForLandingPage();
                this.cmsPageState.setCurrentPage(cmsPage);
                return this.cmsPage;
            });
        },

        updateCmsPageDataMappingForLandingPage() {
            this.cmsPageState.setCurrentMappingEntity('landing_page');
            this.cmsPageState.setCurrentMappingTypes(this.cmsService.getEntityMappingTypes('landing_page'));
            this.cmsPageState.setCurrentDemoEntity(this.landingPage);
        },

        async setLandingPage() {
            this.isLoading = true;

            try {
                if (this.landingPageId === null) {
                    Shopware.Store.get('shopwareApps').selectedIds = [];

                    Shopware.Store.get('swCategoryDetail').landingPage = null;
                    this.cmsPageState.resetCmsPageState();

                    return;
                }

                Shopware.Store.get('shopwareApps').selectedIds = [
                    this.landingPageId,
                ];
                await Shopware.Store.get('swCategoryDetail').loadActiveLandingPage({
                    repository: this.landingPageRepository,
                    apiContext: Shopware.Context.api,
                    id: this.landingPageId,
                    criteria: this.landingPageCriteria,
                });

                this.cmsPageState.resetCmsPageState();
                await this.getAssignedCmsPageForLandingPage();
                await this.loadLandingPageCustomFieldSet();
            } catch {
                this.createNotificationError({
                    title: this.$tc('global.default.error'),
                    message: this.$tc('global.notification.unspecifiedSaveErrorMessage'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        setCategory() {
            this.isLoading = true;

            if (this.categoryId === null) {
                Shopware.Store.get('shopwareApps').selectedIds = [];

                Shopware.Store.get('swCategoryDetail').category = null;
                this.cmsPageState.resetCmsPageState();
                this.isLoading = false;
                return;
            }

            Shopware.Store.get('shopwareApps').selectedIds = [
                this.categoryId,
            ];
            Shopware.Store.get('swCategoryDetail')
                .loadActiveCategory({
                    repository: this.categoryRepository,
                    apiContext: Shopware.Context.api,
                    id: this.categoryId,
                    criteria: this.categoryCriteria,
                })
                .then(() => {
                    this.cmsPageState.resetCmsPageState();
                    return Promise.resolve();
                })
                .then(this.getAssignedCmsPage)
                .then(this.loadCustomFieldSet)
                .then(() => {
                    this.isLoading = false;
                });
        },

        loadCustomFieldSet() {
            this.isCustomFieldLoading = true;

            return this.customFieldSetRepository
                .search(this.customFieldSetCriteria)
                .then((customFieldSet) => {
                    Shopware.Store.get('swCategoryDetail').customFieldSets = customFieldSet;
                })
                .finally(() => {
                    this.isCustomFieldLoading = true;
                });
        },

        loadLandingPageCustomFieldSet() {
            this.isCustomFieldLoading = true;

            return this.customFieldSetRepository
                .search(this.customFieldSetLandingPageCriteria)
                .then((customFieldSet) => {
                    Shopware.Store.get('swCategoryDetail').customFieldSets = customFieldSet;
                })
                .finally(() => {
                    this.isCustomFieldLoading = true;
                });
        },

        onSaveCategories() {
            return this.categoryRepository.save(this.category);
        },

        openChangeModal(destination) {
            this.nextRoute = destination;
            this.isDisplayingLeavePageWarning = true;
        },

        onLeaveModalClose() {
            this.nextRoute = null;
            this.isDisplayingLeavePageWarning = false;
        },

        onLeaveModalConfirm(destination) {
            // Discard all category related errors that may have occurred
            Shopware.Store.get('error').removeApiError('category');

            this.forceDiscardChanges = true;
            this.isDisplayingLeavePageWarning = false;

            this.$nextTick(() => {
                this.$router.push({
                    name: destination.name,
                    params: destination.params,
                });
            });
        },

        cancelEdit() {
            this.resetCategory();
        },

        resetCategory() {
            this.$router.push({ name: 'sw.category.index' });
        },

        onChangeLanguage(newLanguageId) {
            this.currentLanguageId = newLanguageId;

            if (this.landingPageId !== null) {
                this.setLandingPage();
            }

            this.setCategory();
        },

        abortOnLanguageChange() {
            if (this.landingPage) {
                return this.landingPage ? this.categoryRepository.hasChanges(this.landingPage) : false;
            }

            return this.category ? this.categoryRepository.hasChanges(this.category) : false;
        },

        saveOnLanguageChange() {
            if (this.landingPage) {
                return this.onSaveLandingPage();
            }

            return this.onSave();
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        async onSave() {
            this.isSaveSuccessful = false;

            const pageOverrides = this.getCmsPageOverrides();

            if (type.isPlainObject(pageOverrides)) {
                this.category.slotConfig = cloneDeep(pageOverrides);
            }

            if (!this.entryPointOverwriteConfirmed) {
                this.checkForEntryPointOverwrite();
                if (this.showEntryPointOverwriteModal) {
                    return Promise.resolve();
                }
            }

            this.isLoading = true;
            await this.updateSeoUrls();

            const response = await this.systemConfigApiService.getValues('core.cms');

            this.defaultCategoryId = response['core.cms.default_category_cms_page'];

            if (this.category.cmsPageId === this.defaultCategoryId) {
                this.category.cmsPageId = null;
            }

            return this.categoryRepository
                .save(this.category, { ...Shopware.Context.api })
                .then(() => {
                    this.isSaveSuccessful = true;
                    this.entryPointOverwriteConfirmed = false;
                    return this.setCategory();
                })
                .catch(() => {
                    this.isLoading = false;
                    this.entryPointOverwriteConfirmed = false;

                    this.createNotificationError({
                        message: this.$tc('global.notification.notificationSaveErrorMessageRequiredFieldsInvalid'),
                    });
                });
        },

        checkForEntryPointOverwrite() {
            this.entryPointOverwriteSalesChannels = new EntityCollection('/sales_channel', 'sales_channel', Context.api);

            this.category.navigationSalesChannels.forEach((salesChannel) => {
                if (salesChannel.navigationCategoryId !== null && salesChannel.navigationCategoryId !== this.categoryId) {
                    this.entryPointOverwriteSalesChannels.add(salesChannel);
                }
            });

            this.category.footerSalesChannels.forEach((salesChannel) => {
                if (salesChannel.footerCategoryId !== null && salesChannel.footerCategoryId !== this.categoryId) {
                    this.entryPointOverwriteSalesChannels.add(salesChannel);
                }
            });

            this.category.serviceSalesChannels.forEach((salesChannel) => {
                if (salesChannel.serviceCategoryId !== null && salesChannel.serviceCategoryId !== this.categoryId) {
                    this.entryPointOverwriteSalesChannels.add(salesChannel);
                }
            });
        },

        cancelEntryPointOverwrite() {
            this.entryPointOverwriteSalesChannels = null;
        },

        confirmEntryPointOverwrite() {
            this.entryPointOverwriteSalesChannels = null;
            this.entryPointOverwriteConfirmed = true;
            this.$nextTick(() => {
                this.onSave();
            });
        },

        onSaveLandingPage() {
            this.isSaveSuccessful = false;

            const pageOverrides = this.getCmsPageOverrides();

            if (type.isPlainObject(pageOverrides)) {
                this.landingPage.slotConfig = cloneDeep(pageOverrides);
            }

            if (this.landingPageId !== 'create') {
                if (this.landingPage.salesChannels.length === 0) {
                    this.addLandingPageSalesChannelError();

                    return Promise.resolve();
                }
            }

            this.isLoading = true;
            return this.landingPageRepository
                .save(this.landingPage, Shopware.Context.api)
                .then(() => {
                    this.isSaveSuccessful = true;

                    if (this.landingPageId === 'create') {
                        this.$router.push({
                            name: 'sw.category.landingPageDetail',
                            params: { id: this.landingPage.id },
                        });
                        return Promise.resolve();
                    }

                    return this.setLandingPage();
                })
                .catch(() => {
                    this.isLoading = false;

                    if (this.landingPage.salesChannels.length === 0) {
                        this.addLandingPageSalesChannelError();

                        return;
                    }

                    this.createNotificationError({
                        message: this.$tc('global.notification.notificationSaveErrorMessageRequiredFieldsInvalid'),
                    });
                });
        },

        addLandingPageSalesChannelError() {
            const shopwareError = new Shopware.Classes.ShopwareError({
                code: 'landing_page_sales_channel_blank',
                detail: 'This value should not be blank.',
                status: '400',
            });

            Shopware.Store.get('error').addApiError({
                expression: `landing_page.${this.landingPage.id}.salesChannels`,
                error: shopwareError,
            });

            this.createNotificationError({
                message: this.$tc('global.notification.notificationSaveErrorMessageRequiredFieldsInvalid'),
            });
        },

        getCmsPageOverrides() {
            if (this.cmsPage === null) {
                return null;
            }

            this.deleteSpecifcKeys(this.cmsPage.sections);

            const { changes } = this.changesetGenerator.generate(this.cmsPage);

            const slotOverrides = {};
            if (changes === null) {
                return slotOverrides;
            }

            if (type.isArray(changes.sections)) {
                changes.sections.forEach((section) => {
                    if (type.isArray(section.blocks)) {
                        section.blocks.forEach((block) => {
                            if (type.isArray(block.slots)) {
                                block.slots.forEach((slot) => {
                                    slotOverrides[slot.id] = slot.config;
                                });
                            }
                        });
                    }
                });
            }

            return slotOverrides;
        },

        deleteSpecifcKeys(sections) {
            if (!sections) {
                return;
            }

            sections.forEach((section) => {
                if (!section.blocks) {
                    return;
                }

                section.blocks.forEach((block) => {
                    if (!block.slots) {
                        return;
                    }

                    block.slots.forEach((slot) => {
                        if (!slot.config) {
                            return;
                        }

                        Object.values(slot.config).forEach((configField) => {
                            if (configField.entity) {
                                delete configField.entity;
                            }
                            if (configField.hasOwnProperty('required')) {
                                delete configField.required;
                            }
                            if (configField.type) {
                                delete configField.type;
                            }
                        });
                    });
                });
            });
        },

        updateSeoUrls() {
            if (!Shopware.Store.list().includes('swSeoUrl')) {
                return Promise.resolve();
            }

            const seoUrls = Shopware.Store.get('swSeoUrl').newOrModifiedUrls;

            return Promise.all(
                seoUrls.map((seoUrl) => {
                    if (seoUrl.seoPathInfo) {
                        seoUrl.isModified = true;
                        return this.seoUrlService.updateCanonicalUrl(seoUrl, seoUrl.languageId);
                    }

                    return Promise.resolve();
                }),
            );
        },

        onLandingPageDelete() {
            Shopware.Store.get('swCategoryDetail').landingPagesToDelete = null;
        },

        onCategoryDelete() {
            Shopware.Store.get('swCategoryDetail').categoriesToDelete = null;
        },
    },
};
