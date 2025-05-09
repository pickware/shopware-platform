import template from './sw-first-run-wizard-welcome.html.twig';
import './sw-first-run-wizard-welcome.scss';

const { Criteria } = Shopware.Data;

/**
 * @sw-package fundamentals@after-sales
 *
 * @private
 */
export default {
    template,

    inject: [
        'languagePluginService',
        'userService',
        'loginService',
        'repositoryFactory',
    ],

    emits: [
        'extension-activated',
        'frw-set-title',
        'buttons-update',
    ],

    mixins: [
        'notification',
    ],

    data() {
        return {
            languages: [],
            languagePlugins: [],
            latestTouchedPlugin: null,
            showConfirmLanguageSwitchModal: false,
            newLocaleId: null,
            user: { localeId: '', pw: '' },
            userProfile: {},
            userPromise: null,
            isLoading: false,
            localeOptions: [],
        };
    },

    computed: {
        languageRepository() {
            return this.repositoryFactory.create('language');
        },

        userRepository() {
            return this.repositoryFactory.create('user');
        },

        languageId() {
            return Shopware.Store.get('session').languageId;
        },

        languageCriteria() {
            return this.getLanguageCriteria();
        },

        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },
    },

    watch: {
        languageId() {
            this.createdComponent();
        },
    },

    beforeMount() {
        this.beforeMountComponent();
    },

    created() {
        this.createdComponent();
    },

    methods: {
        beforeMountComponent() {
            this.userPromise.then((user) => {
                this.user = user;
            });
        },

        createdComponent() {
            this.updateButtons();
            this.setTitle();
            this.getLanguagePlugins();

            const languagePromise = new Promise((resolve) => {
                resolve(this.languageId);
            });

            this.userPromise = this.userService.getUser().then((response) => {
                return this.setUserData(response.data);
            });

            const promises = [
                languagePromise,
                this.userPromise,
            ];

            Promise.all(promises).then(() => {
                this.loadLanguages();
            });
        },

        setTitle() {
            this.$emit('frw-set-title', this.$tc('sw-first-run-wizard.welcome.modalTitle'));
        },

        updateButtons() {
            const disabledExtensionManagement = Shopware.Store.get('context').app.config.settings.disableExtensionManagement;
            const nextRoute = disabledExtensionManagement ? 'defaults' : 'data-import';

            const buttonConfig = [
                {
                    key: 'next',
                    label: this.$tc('sw-first-run-wizard.general.buttonNext'),
                    position: 'right',
                    variant: 'primary',
                    action: `sw.first.run.wizard.index.${nextRoute}`,
                    disabled: false,
                },
            ];

            this.$emit('buttons-update', buttonConfig);
        },

        setUserData(userProfile) {
            this.userProfile = userProfile;
            return new Promise((resolve) => {
                resolve(this.userRepository.get(this.userProfile.id));
            });
        },

        getLanguagePlugins() {
            if (Shopware.Store.get('context').app.config.settings.disableExtensionManagement) {
                this.languagePlugins = [];
                return;
            }

            this.languagePluginService.getPlugins().then((response) => {
                this.languagePlugins = response.items;
            });
        },

        onPluginInstalled(plugin) {
            this.latestTouchedPlugin = this.getPluginByName(plugin);

            this.getLanguagePlugins();
            this.isLoading = true;
            this.loadLanguages().then(() => {
                this.showConfirmLanguageSwitchModal = true;
                this.isLoading = false;
            });
        },

        onPluginRemoved(plugin) {
            this.latestTouchedPlugin = this.getPluginByName(plugin);

            this.getLanguagePlugins();
        },

        onConfirmLanguageSwitch() {
            this.loginService
                .verifyUserToken(this.user.pw)
                .then((verifiedToken) => {
                    const context = { ...Shopware.Context.api };
                    context.authToken.access = verifiedToken;

                    this.userRepository
                        .save(this.user, context)
                        .then(async () => {
                            await Shopware.Service('localeHelper').setLocaleWithId(this.user.localeId);
                        })
                        .finally(() => {
                            this.showConfirmLanguageSwitchModal = false;
                        });
                })
                .catch(() => {
                    /* eslint-disable max-len */
                    this.createNotificationError({
                        title: this.$tc(
                            'sw-users-permissions.users.user-detail.passwordConfirmation.notificationPasswordErrorTitle',
                        ),
                        message: this.$tc(
                            'sw-users-permissions.users.user-detail.passwordConfirmation.notificationPasswordErrorMessage',
                        ),
                    });
                })
                .finally(() => {
                    this.confirmPassword = '';
                });
        },

        onCancelSwitch() {
            this.showConfirmLanguageSwitchModal = false;
        },

        getPluginByName(name) {
            if (this.languagePlugins.length < 1) {
                return null;
            }

            return this.languagePlugins.find((p) => p.name === name);
        },

        getLanguageCriteria() {
            const languageCriteria = new Criteria(1, null);
            languageCriteria.addAssociation('locale');
            languageCriteria.addSorting(Criteria.sort('locale.name', 'ASC'));
            languageCriteria.addSorting(Criteria.sort('locale.territory', 'ASC'));

            return languageCriteria;
        },

        showPluginErrorNotification(message, errorMessage) {
            const tryLater = this.$tc('sw-first-run-wizard.welcome.tryAgainLater');

            this.createNotificationError({
                message: `${message}\n${errorMessage}\n${tryLater}`,
            });
        },

        loadLanguages() {
            return this.languageRepository.search(this.languageCriteria).then((result) => {
                this.languages = [];
                this.localeOptions = [];

                result.forEach((lang) => {
                    lang.customLabel = `${lang.locale.translated.name} (${lang.locale.translated.territory})`;
                    this.languages.push(lang);
                    this.localeOptions.push({
                        id: lang.locale.id,
                        value: lang.locale.id,
                        label: lang.customLabel,
                    });
                });

                return this.languages;
            });
        },
    },
};
