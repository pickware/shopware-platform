import template from './sw-theme-modal.html.twig';
import './sw-theme-modal.scss';

/**
 * @package discovery
 */

const { Component, Mixin } = Shopware;
const Criteria = Shopware.Data.Criteria;

Component.register('sw-theme-modal', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('listing')
    ],

    props: {
        selectedThemeId: {
            type: String,
            default: null,
            required: false,
        },
    },

    data() {
        return {
            selected: null,
            isLoading: false,
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            term: '',
            total: null,
            themes: []
        };
    },

    computed: {
        themeRepository() {
            return this.repositoryFactory.create('theme');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.selected = this.selectedThemeId;
        },

        getList() {
            this.isLoading = true;
            const criteria = new Criteria(this.page, this.limit);
            criteria.addAssociation('previewMedia');
            criteria.addAssociation('salesChannels');
            criteria.addFilter(Criteria.equals('active', true));

            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));
            criteria.setTerm(this.term);

            return this.themeRepository.search(criteria, Shopware.Context.api).then((searchResult) => {
                this.total = searchResult.total;
                this.themes = searchResult;
                this.isLoading = false;

                return this.themes;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        selectLayout() {
            this.$emit('modal-theme-select', this.selected);
            this.closeModal();
        },

        selectItem(themeId) {
            this.selected = themeId;
        },

        onSearch(value) {
            this.term = value;
            this.page = 1;
            this.getList();
        },

        onSelection(themeId) {
            this.selected = themeId;
        },

        closeModal() {
            this.$emit('modal-close');
            this.selected = null;
            this.term = null;
        }
    }
});
