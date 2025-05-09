import template from './sw-sales-channel-detail-theme.html.twig';
import './sw-sales-channel-detail-theme.scss';

/**
 * @package discovery
 */

const { Component, Mixin } = Shopware;
const Criteria = Shopware.Data.Criteria;

Component.register('sw-sales-channel-detail-theme', {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder')
    ],

    inject: [
        'repositoryFactory',
        'themeService',
        'acl'
    ],

    props: {
        salesChannel: {
            required: true
        }
    },

    data() {
        return {
            theme: null,
            showThemeSelectionModal: false,
            showChangeModal: false,
            newThemeId: null,
            isLoading: false,
        };
    },

    computed: {
        themeRepository() {
            return this.repositoryFactory.create('theme');
        }
    },

    watch: {
        'salesChannel.extensions.themes': {
            deep: true,
            handler() {
                this.getTheme(this.salesChannel?.extensions?.themes[0]?.id);
            }
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.salesChannel?.extensions?.themes[0]) {
                return;
            }

            this.theme = this.salesChannel.extensions.themes[0];
            this.getTheme(this.theme.id);
        },

        async getTheme(themeId) {
            if (themeId === null) {
                return;
            }

            const criteria = new Criteria();
            criteria.addAssociation('previewMedia');

            this.theme = await this.themeRepository.get(themeId, Shopware.Context.api, criteria);
        },

        openThemeModal() {
            if (!this.acl.can('sales_channel.editor')) {
                return;
            }

            this.showThemeSelectionModal = true;
        },

        closeThemeModal() {
            this.showThemeSelectionModal = false;
        },

        openInThemeManager() {
            if (!this.theme) {
                this.$router.push({ name: 'sw.theme.manager.index' });
            } else {
                this.$router.push({ name: 'sw.theme.manager.detail', params: { id: this.theme.id } });
            }
        },

        async onChangeTheme(themeId) {
            this.showThemeSelectionModal = false;

            await this.getTheme(themeId);
            this.salesChannel.extensions.themes[0] = this.theme;
        },
    },
});
