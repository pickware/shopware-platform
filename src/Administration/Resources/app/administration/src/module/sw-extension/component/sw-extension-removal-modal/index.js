import template from './sw-extension-removal-modal.html.twig';
import './sw-extension-removal-modal.scss';

/**
 * @sw-package checkout
 * @private
 */
export default {
    template,

    emits: [
        'modal-close',
        'remove-extension',
    ],

    props: {
        extensionName: {
            type: String,
            required: true,
        },
        isLicensed: {
            type: Boolean,
            required: true,
        },
        isLoading: {
            type: Boolean,
            required: true,
        },
    },

    data() {
        return {
            removePluginData: false,
        };
    },

    computed: {
        title() {
            return this.isLicensed
                ? this.$t('sw-extension-store.component.sw-extension-removal-modal.titleCancel', {
                      extensionName: this.extensionName,
                  })
                : this.$t('sw-extension-store.component.sw-extension-removal-modal.titleRemove', {
                      extensionName: this.extensionName,
                  });
        },

        alert() {
            return this.isLicensed
                ? this.$tc('sw-extension-store.component.sw-extension-removal-modal.alertCancel')
                : this.$tc('sw-extension-store.component.sw-extension-removal-modal.alertRemove');
        },

        btnLabel() {
            return this.isLicensed
                ? this.$tc('sw-extension-store.component.sw-extension-removal-modal.labelCancel')
                : this.$tc('sw-extension-store.component.sw-extension-removal-modal.labelRemove');
        },
    },

    methods: {
        emitClose() {
            if (this.isLoading) {
                return;
            }

            this.$emit('modal-close');
        },

        emitRemoval() {
            this.$emit('remove-extension');
        },
    },
};
