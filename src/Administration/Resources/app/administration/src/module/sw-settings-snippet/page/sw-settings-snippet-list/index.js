/**
 * @sw-package discovery
 */
import Sanitizer from 'src/core/helper/sanitizer.helper';
import template from './sw-settings-snippet-list.html.twig';
import './sw-settings-snippet-list.scss';

const {
    Mixin,
    Data: { Criteria },
} = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'snippetSetService',
        'snippetService',
        'userService',
        'repositoryFactory',
        'acl',
        'userConfigService',
    ],

    mixins: [
        Mixin.getByName('sw-settings-list'),
    ],

    data() {
        return {
            entityName: 'snippet',
            sortBy: 'id',
            sortDirection: 'ASC',
            metaId: '',
            currentAuthor: '',
            snippetSets: null,
            hasResetableItems: true,
            showOnlyEdited: false,
            showOnlyAdded: false,
            emptySnippets: false,
            grid: [],
            resetItems: [],
            filterItems: [],
            authorFilters: [],
            appliedFilter: [],
            appliedAuthors: [],
            emptyIcon: this.$route.meta.$module.icon,
            skeletonItemAmount: 25,
            filterSettings: null,
            modalDeleteAll: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        identifier() {
            return this.snippetSets
                ? this.$tc(
                      'sw-settings-snippet.list.identifier',
                      {
                          setName: this.metaName,
                      },
                      this.snippetSets.length,
                  )
                : '';
        },

        columns() {
            return this.getColumns();
        },

        snippetRepository() {
            return this.repositoryFactory.create('snippet');
        },

        snippetSetRepository() {
            return this.repositoryFactory.create('snippet_set');
        },

        queryIds() {
            return Array.isArray(this.$route.query.ids) ? this.$route.query.ids : [this.$route.query.ids];
        },

        snippetSetCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addFilter(Criteria.equalsAny('id', this.queryIds));
            criteria.addSorting(Criteria.sort('name', 'ASC'));

            if (this.term) {
                criteria.setTerm(this.term);
            }

            return criteria;
        },

        queryIdCount() {
            return this.queryIds.length;
        },

        metaName() {
            return this.snippetSets[0]?.name;
        },

        filter() {
            const filter = {};
            if (this.showOnlyEdited) {
                filter.edited = true;
            }
            if (this.showOnlyAdded) {
                filter.added = true;
            }
            if (this.emptySnippets) {
                filter.empty = true;
            }
            if (this.term) {
                filter.term = this.term;
            }
            if (this.appliedFilter.length > 0) {
                filter.namespace = this.appliedFilter;
            }
            if (this.appliedAuthors.length > 0) {
                filter.author = this.appliedAuthors;
            }

            return filter;
        },

        contextMenuEditSnippet() {
            return this.acl.can('snippet.editor') ? this.$tc('global.default.edit') : this.$tc('global.default.view');
        },

        hasActiveFilters() {
            if (!this.filterSettings) {
                return false;
            }

            return Object.values(this.filterSettings).some((value) => value === true);
        },

        activeFilters() {
            let filter = {};

            if (!this.hasActiveFilters) {
                return filter;
            }

            if (this.filterSettings.editedSnippets) {
                filter = { ...filter, edited: true };
            }
            if (this.filterSettings.addedSnippets) {
                filter = { ...filter, added: true };
            }
            if (this.filterSettings.emptySnippets) {
                filter = { ...filter, empty: true };
            }

            filter = { ...filter, author: [] };
            this.authorFilters.forEach((item) => {
                if (this.filterSettings[item] === true) {
                    filter.author.push(item);
                }
            });

            filter = { ...filter, namespace: [] };
            this.filterItems.forEach((item) => {
                if (this.filterSettings[item] === true) {
                    filter.namespace.push(item);
                }
            });

            return filter;
        },
    },

    created() {
        this.createdComponent();
    },

    beforeUnmount() {
        this.beforeDestroyComponent();
    },

    methods: {
        async createdComponent() {
            this.addEventListeners();

            this.snippetSetRepository.search(this.snippetSetCriteria).then((sets) => {
                this.snippetSets = sets;
            });

            this.userService.getUser().then((response) => {
                this.currentAuthor = `user/${response.data.username}`;
            });

            const filterItems = await this.snippetService.getFilter();
            this.filterItems = filterItems.data;

            const authorFilters = await this.snippetSetService.getAuthors();
            this.authorFilters = authorFilters.data;

            await this.getFilterSettings();

            if (this.hasActiveFilters) {
                this.initializeSnippetSet(this.activeFilters);
            }
        },

        beforeDestroyComponent() {
            this.saveUserConfig();
            this.removeEventListeners();
        },

        addEventListeners() {
            window.addEventListener('beforeunload', this.beforeUnloadListener);
        },

        removeEventListeners() {
            window.removeEventListener('beforeunload', this.beforeUnloadListener);
        },

        // eslint-disable-next-line no-unused-vars
        beforeUnloadListener(event) {
            this.saveUserConfig();
        },

        async getFilterSettings() {
            const userConfig = await this.getUserConfig();

            this.filterSettings = userConfig.data['grid.filter.setting-snippet-list']
                ? userConfig.data['grid.filter.setting-snippet-list']
                : this.createFilterSettings();
        },

        getUserConfig() {
            return this.userConfigService.search([
                'grid.filter.setting-snippet-list',
            ]);
        },

        saveUserConfig() {
            return this.userConfigService.upsert({
                'grid.filter.setting-snippet-list': this.filterSettings,
            });
        },

        createFilterSettings() {
            const authorFilters = this.authorFilters.reduce((acc, item) => ({ ...acc, [item]: false }), {});
            const moreFilters = this.filterItems.reduce((acc, item) => ({ ...acc, [item]: false }), {});

            return {
                emptySnippets: false,
                editedSnippets: false,
                addedSnippets: false,
                ...authorFilters,
                ...moreFilters,
            };
        },

        getList() {
            if (this.hasActiveFilters) {
                this.initializeSnippetSet(this.activeFilters);
            } else {
                this.initializeSnippetSet();
            }
        },

        getColumns() {
            const columns = [
                {
                    property: 'id',
                    label: 'sw-settings-snippet.list.columnKey',
                    inlineEdit: true,
                    allowResize: true,
                    rawData: true,
                    primary: true,
                },
            ];

            if (this.snippetSets) {
                this.snippetSets.forEach((item) => {
                    columns.push({
                        property: item.id,
                        label: item.name,
                        allowResize: true,
                        inlineEdit: 'string',
                        rawData: true,
                    });
                });
            }
            return columns;
        },

        initializeSnippetSet(filter = this.filter) {
            if (!this.$route.query.ids) {
                this.backRoutingError();
                return;
            }

            this.isLoading = true;

            const sort = {
                sortBy: this.sortBy,
                sortDirection: this.sortDirection,
            };

            this.snippetSetService.getCustomList(this.page, this.limit, filter, sort).then((response) => {
                this.metaId = this.queryIds[0];
                this.total = response.total;
                this.grid = this.prepareGrid(response.data);
                this.isLoading = false;
            });
        },

        prepareGrid(grid) {
            function prepareContent(items) {
                const content = items.reduce((acc, item) => {
                    item.resetTo = item.value;
                    acc[item.setId] = item;
                    acc.isCustomSnippet = item.author.includes('user/') || item.author.length < 1;
                    return acc;
                }, {});
                content.id = items[0].translationKey;

                return content;
            }

            return Object.values(grid).reduce((accumulator, items) => {
                accumulator.push(prepareContent(items));
                return accumulator;
            }, []);
        },

        onEdit(snippet) {
            if (snippet?.id) {
                this.$router.push({
                    name: 'sw.settings.snippet.detail',
                    params: {
                        id: snippet.id,
                    },
                });
            }
        },

        onInlineEditSave(result) {
            const responses = [];
            const key = result[this.metaId].translationKey;

            this.snippetSets.forEach((item) => {
                const snippet = result[item.id];
                snippet.value = Sanitizer.sanitize(snippet.value);

                if (!snippet.value && typeof snippet.value !== 'string') {
                    snippet.value = snippet.origin;
                }

                if (!snippet.hasOwnProperty('author') || snippet.author === '') {
                    snippet.author = this.currentAuthor;
                }

                if (snippet.origin !== snippet.value) {
                    const snippetEntity = this.snippetRepository.create();

                    if (snippet.id) {
                        snippetEntity._isNew = false;
                    }

                    snippetEntity.author = snippet.author;
                    snippetEntity.id = snippet.id;
                    snippetEntity.value = snippet.value;
                    snippetEntity.origin = snippet.origin;
                    snippetEntity.translationKey = snippet.translationKey;
                    snippetEntity.setId = snippet.setId;

                    responses.push(this.snippetRepository.save(snippetEntity));
                } else if (snippet.id !== null && !snippet.author.startsWith('user/')) {
                    responses.push(this.snippetRepository.delete(snippet.id));
                }
            });

            Promise.all(responses)
                .catch(() => {
                    this.inlineSaveErrorMessage(key);
                })
                .finally(() => {
                    this.getList();
                });
        },

        onInlineEditCancel(rowItems) {
            Object.keys(rowItems).forEach((itemKey) => {
                const item = rowItems[itemKey];
                if (typeof item !== 'object' || item.value === undefined) {
                    return;
                }

                item.value = item.resetTo;
            });
        },

        onEmptyClick() {
            this.showOnlyEdited = false;
            this.getList();
        },

        onSearch(term) {
            this.term = term;
            this.page = 1;

            this.updateRoute(
                {
                    term: term,
                    page: 1,
                },
                {
                    ids: this.queryIds,
                },
            );
        },

        backRoutingError() {
            this.$router.push({ name: 'sw.settings.snippet.index' });

            this.createNotificationError({
                message: this.$tc('sw-settings-snippet.general.errorBackRoutingMessage'),
            });
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement
         */
        inlineSaveSuccessMessage(key) {
            const messageSaveSuccess = this.$tc('sw-settings-snippet.list.messageSaveSuccess', { key }, this.queryIdCount);

            this.createNotificationSuccess({
                message: messageSaveSuccess,
            });
        },

        inlineSaveErrorMessage(key) {
            const messageSaveError = this.$tc('sw-settings-snippet.list.messageSaveError', { key }, this.queryIdCount);

            this.createNotificationError({
                message: messageSaveError,
            });
        },

        onReset(item) {
            this.isLoading = true;
            this.hasResetableItems = false;

            this.snippetSetRepository
                .search(this.snippetSetCriteria)
                .then((response) => {
                    const resetItems = [];
                    const ids = Array.isArray(this.$route.query.ids) ? this.$route.query.ids : [this.$route.query.ids];

                    Object.values(item).forEach((currentItem, index) => {
                        if (!(currentItem instanceof Object) || !ids.find((id) => id === currentItem.setId)) {
                            return;
                        }

                        currentItem.setName = this.getName(response, currentItem.setId);
                        if (currentItem.id === null) {
                            currentItem.id = index;
                            currentItem.isFileSnippet = true;
                        }

                        resetItems.push(currentItem);
                    });

                    this.resetItems = resetItems.sort((a, b) => {
                        return a.setName <= b.setName ? -1 : 1;
                    });
                    this.showDeleteModal = item;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        getName(list, id) {
            let name = '';
            list.forEach((item) => {
                if (item.id === id) {
                    name = item.name;
                }
            });

            return name;
        },

        onSelectionChanged(selection) {
            this.snippetSelection = selection;
            this.hasResetableItems = selection && Object.keys(selection).length !== 0;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
            this.modalDeleteAll = false;
            this.hasResetableItems = false;
            this.resetItems = [];
        },

        onConfirmReset(fullSelection) {
            let items;
            const promises = [];

            if (this.showOnlyEdited || this.modalDeleteAll) {
                items = Object.values(fullSelection).filter((item) => typeof item === 'object');
            } else if (this.snippetSelection !== undefined) {
                items = Object.values(this.snippetSelection);
            } else {
                this.onCloseDeleteModal();

                return;
            }

            this.onCloseDeleteModal();

            items.forEach((item) => {
                if (item.hasOwnProperty('isFileSnippet') || item.id === null) {
                    return;
                }

                if (item.translationKey && typeof item.translationKey !== 'string') {
                    item.translationKey = `${item.translationKey}`;
                }

                item.isCustomSnippet = fullSelection.isCustomSnippet;
                this.isLoading = true;

                promises.push(
                    this.snippetRepository.delete(item.id).catch(() => {
                        this.createResetErrorNote(item);
                    }),
                );

                Promise.all(promises).finally(() => {
                    this.isLoading = false;
                    this.getList();
                });
            });
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement
         */
        createSuccessMessage(item) {
            const message = this.$t(
                'sw-settings-snippet.list.resetSuccessMessage',
                {
                    key: item.value,
                },
                item.isCustomSnippet ? 0 : 1,
            );

            this.createNotificationSuccess({
                message,
            });
        },

        createResetErrorNote(item) {
            const message = this.$t(
                'sw-settings-snippet.list.resetErrorMessage',
                {
                    key: item.value,
                },
                item.isCustomSnippet ? 0 : 1,
            );

            this.createNotificationError({
                message,
            });
        },

        onChange(field) {
            this.filterSettings[[field.name]] = field.value;

            this.page = 1;
            if (field.group === 'editedSnippets') {
                this.showOnlyEdited = field.value;
                this.initializeSnippetSet();
                return;
            }

            if (field.group === 'addedSnippets') {
                this.showOnlyAdded = field.value;
                this.initializeSnippetSet();
                return;
            }

            if (field.group === 'emptySnippets') {
                this.emptySnippets = field.value;
                this.initializeSnippetSet();
                return;
            }

            let selector = 'appliedFilter';
            if (field.group === 'authorFilter') {
                selector = 'appliedAuthors';
            }

            if (field.value) {
                if (this[selector].indexOf(field.name) !== -1) {
                    return;
                }

                this[selector].push(field.name);
                this.initializeSnippetSet();
                return;
            }

            this[selector].splice(this[selector].indexOf(field.name), 1);
            this.initializeSnippetSet();
        },

        onSidebarClose() {
            this.showOnlyEdited = false;
            this.emptySnippets = false;
            this.appliedAuthors = [];
            this.appliedFilter = [];
            this.initializeSnippetSet();
        },

        onSortColumn(column) {
            if (this.sortDirection === 'ASC' && column.dataIndex === this.sortBy) {
                this.sortDirection = 'DESC';
            } else {
                this.sortDirection = 'ASC';
            }
            this.updateRoute(
                {
                    sortDirection: this.sortDirection,
                    sortBy: column.dataIndex,
                },
                {
                    ids: this.queryIds,
                },
            );
        },

        onPageChange({ page, limit }) {
            this.updateRoute(
                { page, limit },
                {
                    ids: this.queryIds,
                },
            );
        },

        getNoPermissionsTooltip(role, showOnDisabledElements = true) {
            return {
                showDelay: 300,
                appearance: 'dark',
                showOnDisabledElements,
                disabled: this.acl.can(role),
                message: this.$tc('sw-privileges.tooltip.warning'),
            };
        },

        onResetAll() {
            this.showOnlyEdited = false;
            this.showOnlyAdded = false;
            this.emptySnippets = false;
            this.appliedFilter = [];
            this.appliedAuthors = [];

            Object.keys(this.filterSettings).forEach((key) => {
                this.filterSettings[key] = false;
            });

            this.initializeSnippetSet({});
        },
    },
};
