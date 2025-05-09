/**
 * @sw-package after-sales
 */
import template from './sw-settings-mailer.html.twig';
import './sw-settings-mailer.scss';

const defaultMailerSettings = {
    'core.mailerSettings.emailAgent': null,
    'core.mailerSettings.host': null,
    'core.mailerSettings.port': null,
    'core.mailerSettings.username': null,
    'core.mailerSettings.password': null,
    'core.mailerSettings.encryption': 'null',
    'core.mailerSettings.senderAddress': null,
    'core.mailerSettings.deliveryAddress': null,
    'core.mailerSettings.disableDelivery': false,
    'core.mailerSettings.sendMailOptions': null,
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['systemConfigApiService'],

    mixins: ['notification'],

    data() {
        return {
            isLoading: true,
            isSaveSuccessful: false,
            isFirstConfiguration: false,
            mailerSettings: { ...defaultMailerSettings },
            smtpHostError: null,
            smtpPortError: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        emailSendmailOptions() {
            return [
                {
                    value: '-bs',
                    name: this.$tc('sw-settings-mailer.sendmail.sync'),
                },
                {
                    value: '-t -i',
                    name: this.$tc('sw-settings-mailer.sendmail.async'),
                },
            ];
        },

        isSmtpMode() {
            return [
                'smtp',
                'smtp+oauth',
            ].includes(this.mailerSettings['core.mailerSettings.emailAgent']);
        },

        emailAgentOptions() {
            return [
                {
                    id: 1,
                    value: 'local',
                    label: this.$tc('sw-settings-mailer.mailer-configuration.local-agent'),
                },
                {
                    id: 2,
                    value: 'smtp',
                    label: this.$tc('sw-settings-mailer.mailer-configuration.smtp-server'),
                },
                {
                    id: 3,
                    value: 'smtp+oauth',
                    label: this.$tc('sw-settings-mailer.mailer-configuration.smtp-server-oauth'),
                },
                {
                    id: 3,
                    value: '',
                    label: this.$tc('sw-settings-mailer.mailer-configuration.env-file'),
                },
            ];
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            await this.loadPageContent();
        },

        async loadPageContent() {
            await this.loadMailerSettings();
            this.checkFirstConfiguration();
        },

        async loadMailerSettings() {
            this.isLoading = true;
            this.mailerSettings = await this.systemConfigApiService.getValues('core.mailerSettings');

            // Default when config is empty
            if (Object.keys(this.mailerSettings).length === 0) {
                this.mailerSettings = {
                    'core.mailerSettings.emailAgent': '',
                    'core.mailerSettings.sendMailOptions': '-t -i',
                };
            }

            this.isLoading = false;
        },

        async saveMailerSettings() {
            this.isLoading = true;

            // Validate smtp configuration
            if (this.isSmtpMode) {
                this.validateSmtpConfiguration();
            }

            // SMTP configuration invalid stop save and propagate error notification
            if (this.smtpHostError !== null || this.smtpPortError !== null) {
                this.createNotificationError({
                    title: this.$tc('global.default.error'),
                    message: this.$tc('sw-settings-mailer.card-smtp.error.notificationMessage'),
                });

                this.isLoading = false;

                return;
            }

            // Reset mailerSettings as local would take over certain values
            if (this.mailerSettings['core.mailerSettings.emailAgent'] === 'local') {
                this.mailerSettings = {
                    ...defaultMailerSettings,
                    'core.mailerSettings.emailAgent': 'local',
                    'core.mailerSettings.disableDelivery': this.mailerSettings['core.mailerSettings.disableDelivery'],
                    'core.mailerSettings.sendMailOptions': this.mailerSettings['core.mailerSettings.sendMailOptions'],
                };
            }

            await this.systemConfigApiService.saveValues(this.mailerSettings);
            this.isLoading = false;
        },

        async onSaveFinish() {
            await this.loadPageContent();
        },

        checkFirstConfiguration() {
            this.isFirstConfiguration = !this.mailerSettings['core.mailerSettings.emailAgent'];
        },

        validateSmtpConfiguration() {
            this.smtpHostError = !this.mailerSettings['core.mailerSettings.host']
                ? {
                      detail: this.$tc('global.error-codes.c1051bb4-d103-4f74-8988-acbcafc7fdc3'),
                  }
                : null;

            this.smtpPortError =
                typeof this.mailerSettings['core.mailerSettings.port'] !== 'number'
                    ? {
                          detail: this.$tc('global.error-codes.c1051bb4-d103-4f74-8988-acbcafc7fdc3'),
                      }
                    : null;
        },

        resetSmtpHostError() {
            this.smtpHostError = null;
        },

        resetSmtpPortError() {
            this.smtpPortError = null;
        },
    },
};
