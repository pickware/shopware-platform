import template from './sw-category-link-settings.html.twig';
import './sw-category-link-settings.scss';

const { Criteria } = Shopware.Data;

/**
 * @sw-package discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'acl',
        'repositoryFactory',
    ],

    props: {
        category: {
            type: Object,
            required: true,
        },

        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            categoriesCollection: [],
        };
    },

    computed: {
        linkTypeValues() {
            return [
                {
                    value: 'external',
                    label: this.$t('sw-category.base.link.type.external'),
                },
                {
                    value: 'internal',
                    label: this.$t('sw-category.base.link.type.internal'),
                },
            ];
        },

        entityValues() {
            return [
                {
                    value: 'category',
                    label: this.$t('global.entities.category'),
                },
                {
                    value: 'product',
                    label: this.$t('global.entities.product'),
                },
                {
                    value: 'landing_page',
                    label: this.$t('global.entities.landing_page'),
                },
            ];
        },

        mainType: {
            get() {
                if (this.isExternal || !this.category.linkType) {
                    return this.category.linkType;
                }

                return 'internal';
            },

            set(value) {
                if (value === 'external') {
                    this.category.internalLink = null;
                } else {
                    this.category.externalLink = null;
                }

                this.category.linkType = value;
            },
        },

        isExternal() {
            return this.category.linkType === 'external';
        },

        isInternal() {
            return !!this.category.linkType && this.category.linkType !== 'external';
        },

        productCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('options.group');

            return criteria;
        },

        categoryCriteria() {
            return new Criteria(1, null);
        },

        internalLinkCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('id', this.category.internalLink));

            return criteria;
        },

        categoryRepository() {
            return this.repositoryFactory.create('category');
        },

        categoryLinkPlaceholder() {
            return this.category.internalLink ? '' : this.$t('sw-category.base.link.categoryPlaceholder');
        },

        allowedCategoryTypes() {
            return ['page'];
        },

        categoryLinkHelpText() {
            return this.$t('sw-category.base.link.categoryHelpText', {
                types: this.allowedCategoryTypes
                    .map((type) => {
                        return this.$t(`sw-category.base.general.types.${type}`);
                    })
                    .join(', '),
            });
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.category.linkType && this.category.externalLink) {
                this.category.linkType = 'external';
            }

            this.createCategoryCollection();
        },

        changeEntity() {
            if (!this.category.linkType) {
                this.category.linkType = 'internal';
            }

            this.category.internalLink = null;
        },

        createCategoryCollection() {
            this.categoryRepository.search(this.internalLinkCriteria, Shopware.Context.api).then((result) => {
                this.categoriesCollection = result;
            });
        },

        onSelectionAdd(item) {
            this.category.internalLink = item.id;
        },

        onSelectionRemove() {
            this.category.internalLink = null;
        },
    },
};
