import template from './sw-cms-el-config-form.html.twig';
import './sw-cms-el-config-form.scss';

const { Mixin } = Shopware;

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    inject: ['systemConfigApiService'],

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    computed: {
        getLastMailClass() {
            if (this.element.config.mailReceiver.value.length === 1) {
                return 'is--last';
            }
            return '';
        },

        formTypeOptions() {
            return [
                {
                    id: 1,
                    value: '',
                    label: this.$tc('sw-cms.elements.form.config.label.type'),
                },
                {
                    id: 2,
                    value: 'contact',
                    label: this.$tc('sw-cms.elements.form.config.label.typeContact'),
                },
                {
                    id: 3,
                    value: 'newsletter',
                    label: this.$tc('sw-cms.elements.form.config.label.typeNewsletter'),
                },
            ];
        },
    },

    created() {
        this.createdComponent();
        this.setShopMail();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('form');
        },

        async getShopMail() {
            const response = await this.systemConfigApiService.getValues('core.basicInformation');
            return response['core.basicInformation.email'];
        },

        async setShopMail() {
            const shopMail = await this.getShopMail();

            if (
                this.element.config.defaultMailReceiver.value &&
                !this.element.config.mailReceiver.value.includes(shopMail)
            ) {
                this.element.config.mailReceiver.value.push(shopMail);
            }
        },

        async updateMailReceiver(value) {
            this.element.config.mailReceiver.value = value;

            if (!this.validateMail()) {
                return;
            }

            const shopMail = await this.getShopMail();
            this.element.config.defaultMailReceiver.value = this.element.config.mailReceiver.value.includes(shopMail);
        },

        validateMail() {
            const lastMail = this.element.config.mailReceiver.value.at(-1);

            if (lastMail) {
                if (!lastMail.includes('@')) {
                    this.element.config.mailReceiver.value.pop();
                    return false;
                }
            }
            return true;
        },
    },
};
