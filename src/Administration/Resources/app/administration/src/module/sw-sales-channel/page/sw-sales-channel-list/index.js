/**
 * @sw-package discovery
 */

import template from './sw-sales-channel-list.html.twig';
import './sw-sales-channel-list.scss';

const { Mixin, Defaults } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
        'domainLinkService',
    ],

    mixins: [
        Mixin.getByName('listing'),
    ],

    data() {
        return {
            salesChannels: null,
            productsForSalesChannel: {},
            isLoading: true,
            sortBy: 'name',
            searchConfigEntity: 'sales_channel',
            lastSortedColumn: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        salesChannelColumns() {
            const columns = [
                {
                    property: 'name',
                    dataIndex: 'name',
                    allowResize: true,
                    routerLink: 'sw.sales.channel.detail',
                    label: 'sw-sales-channel.list.columnName',
                    primary: true,
                },
                {
                    property: 'status',
                    dataIndex: 'status',
                    allowResize: true,
                    sortable: false,
                    label: 'sw-sales-channel.list.columnStatus',
                },
                {
                    property: 'id',
                    dataIndex: 'id',
                    allowResize: true,
                    sortable: false,
                    label: 'sw-sales-channel.list.columnFavourite',
                    align: 'center',
                },
                {
                    property: 'createdAt',
                    dataIndex: 'createdAt',
                    allowResize: true,
                    label: 'sw-sales-channel.list.columnCreatedAt',
                },
            ];

            columns.splice(1, 0, {
                property: 'type.name',
                dataIndex: 'type.name',
                allowResize: true,
                label: 'sw-sales-channel.list.columnType',
            });

            return columns;
        },

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        salesChannelCriteria() {
            const salesChannelCriteria = new Criteria(this.page, this.limit);

            salesChannelCriteria.setTerm(this.term);
            salesChannelCriteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));
            salesChannelCriteria.addAssociation('type');
            salesChannelCriteria.addAssociation('domains');

            return salesChannelCriteria;
        },

        salesChannelFavoritesService() {
            return Shopware.Service('salesChannelFavorites');
        },

        dateFilter() {
            return Shopware.Filter.getByName('date');
        },
    },

    methods: {
        onAddSalesChannel() {
            Shopware.Utils.EventBus.emit('sw-sales-channel-list-add-new-channel');
        },

        async getList() {
            this.isLoading = true;

            const criteria = await this.addQueryScores(this.term, this.salesChannelCriteria);
            if (!this.entitySearchable) {
                this.isLoading = false;
                this.total = 0;

                return false;
            }

            if (this.freshSearchTerm) {
                criteria.resetSorting();
            }

            return this.salesChannelRepository.search(criteria).then((searchResult) => {
                this.salesChannels = searchResult;
                this.total = searchResult.total;
                this.isLoading = false;
            });
        },

        checkForDomainLink(salesChannel) {
            const domainLink = this.domainLinkService.getDomainLink(salesChannel);

            if (domainLink === null) {
                return false;
            }

            salesChannel.domainLink = domainLink;

            return true;
        },

        openStorefrontLink(storeFrontLink) {
            window.open(storeFrontLink, '_blank');
        },

        isFavorite(salesChannelId) {
            return this.salesChannelFavoritesService.isFavorite(salesChannelId);
        },

        isStorefrontSalesChannel(salesChannel) {
            return salesChannel.type.id === Defaults.storefrontSalesChannelTypeId;
        },
    },
};
