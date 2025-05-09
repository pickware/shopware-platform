import template from './sw-extension-card-base.html.twig';
import './sw-extension-card-base.scss';

const { Utils, Filter } = Shopware;

/**
 * @sw-package checkout
 * @private
 */
export default {
    template,

    inheritAttrs: false,

    inject: [
        'shopwareExtensionService',
        'extensionStoreActionService',
        'cacheApiService',
    ],

    emits: ['update-list'],

    mixins: ['sw-extension-error'],

    props: {
        extension: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            isLoading: false,
            showUninstallModal: false,
            showRemovalModal: false,
            showPermissionsModal: false,
            permissionsAccepted: false,
            showPrivacyModal: false,
            permissionModalActionLabel: null,
            openLink: null,
            showConsentAffirmationModal: false,
            consentAffirmationDeltas: null,
        };
    },

    computed: {
        dateFilter() {
            return Shopware.Filter.getByName('date');
        },

        defaultThemeAsset() {
            return this.assetFilter('administration/administration/static/img/theme/default_theme_preview.jpg');
        },

        extensionCardClasses() {
            return {
                'is--deactivated': this.isInstalled && !this.extension.active,
                'deactivate-prevented': this.isActive && !this.allowDisable,
                'is--not-installed': !this.isInstalled,
            };
        },

        licensedExtension() {
            return this.extension.storeLicense;
        },

        image() {
            if (this.extension.icon) {
                return this.extension.icon;
            }

            if (this.extension.iconRaw) {
                return `data:image/png;base64, ${this.extension.iconRaw}`;
            }

            return this.defaultThemeAsset;
        },

        isActive: {
            get() {
                if (!this.isInstalled) {
                    return false;
                }

                return this.extension.active;
            },
            set(active) {
                if (!this.isInstalled) {
                    return;
                }

                this.extension.active = active;

                this.$nextTick(() => {
                    this.changeExtensionStatus();
                }, 0);
            },
        },

        allowDisable() {
            return this.extension.allowDisable;
        },

        isInstalled() {
            return this.extension.installedAt !== null;
        },

        privacyPolicyLink() {
            return this.extension.privacyPolicyLink;
        },

        permissions() {
            return Object.keys(this.extension.permissions).length ? this.extension.permissions : null;
        },

        assetFilter() {
            return Filter.getByName('asset');
        },

        isRemovable() {
            if (this.extension.installedAt === null && this.extension.source === 'local') {
                return true;
            }

            return false;
        },

        isUninstallable() {
            if (this.extension.installedAt !== null) {
                return true;
            }

            return false;
        },

        isUpdateable() {
            if (!this.extension || this.extension.latestVersion === null || !this.extension.allowUpdate) {
                return false;
            }

            return this.extension.latestVersion !== this.extension.version;
        },

        openLinkExists() {
            return !!this.link;
        },

        extensionMainModule() {
            return Shopware.Store.get('extensionMainModules').mainModules.find(
                (mainModule) => mainModule.extensionName === this.extension.name,
            );
        },

        link() {
            if (this.openLink) {
                return this.openLink;
            }

            if (this.extensionMainModule) {
                return {
                    name: 'sw.extension.sdk.index',
                    params: {
                        id: this.extensionMainModule.moduleId,
                    },
                };
            }

            return null;
        },

        consentAffirmationModalActionLabel() {
            return this.$tc('sw-extension-store.component.sw-extension-permissions-modal.acceptAndUpdate');
        },

        consentAffirmationModalCloseLabel() {
            return this.$tc('global.default.cancel');
        },

        consentAffirmationModalTitle() {
            return this.$tc(
                'sw-extension-store.component.sw-extension-permissions-modal.titleNewPermissions',
                {
                    extensionLabel: this.extension.label,
                },
                1,
            );
        },

        consentAffirmationModalDescription() {
            return this.$tc(
                'sw-extension-store.component.sw-extension-permissions-modal.descriptionNewPermissions',
                {
                    extensionLabel: this.extension.label,
                },
                1,
            );
        },

        extensionManagementDisabled() {
            return Shopware.Store.get('context').app.config.settings.disableExtensionManagement;
        },

        showContextMenu() {
            if (this.isInstalled && this.extension.configurable) {
                return true;
            }

            if (this.openLinkExists && this.extension.active) {
                return true;
            }

            if (this.privacyPolicyLink) {
                return true;
            }

            if (this.isInstalled && this.extension.privacyPolicyExtension) {
                return true;
            }

            if (this.isInstalled && this.permissions) {
                return true;
            }

            if (!this.extensionManagementDisabled && this.isUpdateable) {
                return true;
            }

            if (
                !this.extensionManagementDisabled &&
                this.extension.storeLicense &&
                this.extension.storeLicense.variant === 'rent' &&
                this.extension.storeLicense.expirationDate === null
            ) {
                return true;
            }

            if (!this.extensionManagementDisabled && this.isRemovable) {
                return true;
            }

            if (!this.extensionManagementDisabled && this.isUninstallable) {
                return true;
            }

            return false;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            this.openLink = await this.shopwareExtensionService.getOpenLink(this.extension);
        },

        emitUpdateList() {
            this.$emit('update-list');
        },

        getHelp() {
            // implemented in SAAS-1137
        },

        openPrivacyAndSafety() {
            window.open(this.extension.privacyPolicyLink, '_blank');
        },

        openRemovalModal() {
            this.showRemovalModal = true;
        },

        openUninstallModal() {
            this.showUninstallModal = true;
        },

        closeRemovalModal() {
            this.showRemovalModal = false;
        },

        closeUninstallModal() {
            this.showUninstallModal = false;
        },

        async closeModalAndUninstallExtension(removeData) {
            this.showUninstallModal = false;
            this.isLoading = true;

            try {
                await this.shopwareExtensionService.uninstallExtension(this.extension.name, this.extension.type, removeData);
                this.clearCacheAndReloadPage();
            } catch (e) {
                this.showExtensionErrors(e);
            } finally {
                this.isLoading = false;
            }
        },

        async updateExtension(allowNewPermissions = false) {
            this.isLoading = true;

            try {
                if (this.extension.updateSource === 'store') {
                    await this.extensionStoreActionService.downloadExtension(this.extension.name);
                }

                if (this.extension.installedAt) {
                    await this.shopwareExtensionService.updateExtension(
                        this.extension.name,
                        this.extension.type,
                        allowNewPermissions,
                    );
                }
                this.clearCacheAndReloadPage();
            } catch (e) {
                if (e.response?.data?.errors[0]?.code === 'FRAMEWORK__EXTENSION_UPDATE_REQUIRES_CONSENT_AFFIRMATION') {
                    this.consentAffirmationDeltas = e.response.data.errors[0].meta.parameters.deltas;

                    this.openConsentAffirmationModal();

                    return;
                }

                this.showExtensionErrors(e);
            } finally {
                this.isLoading = false;
            }
        },

        async closeModalAndRemoveExtension(removeData) {
            // we close the modal in the called methods before updating the listing
            if (this.extension.storeLicense === null || this.extension.storeLicense.variant !== 'rent') {
                await this.removeExtension(removeData);
                this.showRemovalModal = false;

                return;
            }

            await this.cancelAndRemoveExtension();
            this.showRemovalModal = false;
        },

        openExtension() {
            if (this.link) {
                this.$router.push(this.link);
            }
        },

        async openPermissionsModalForInstall() {
            if (!this.permissions) {
                this.permissionsAccepted = true;
                this.isLoading = true;
                await this.installAndActivateExtension();
                return;
            }

            this.permissionModalActionLabel = this.$tc(
                'sw-extension-store.component.sw-extension-card-base.labelAcceptAndInstall',
            );
            this.showPermissionsModal = true;
        },

        openPermissionsModal() {
            this.permissionModalActionLabel = null;
            this.showPermissionsModal = true;
        },

        closePermissionsModal() {
            this.permissionModalActionLabel = null;
            this.showPermissionsModal = false;
        },

        async closePermissionsModalAndInstallExtension() {
            this.permissionsAccepted = true;
            this.closePermissionsModal();
            await this.installExtension();
        },

        /*
         * Interface for deriving components
         */
        async changeExtensionStatus() {
            Utils.debug.warn(this._name, 'No implementation of changeExtensionStatus found');
        },

        installExtension() {
            Utils.debug.warn(this._name, 'No implementation of installExtension found');
        },

        async installAndActivateExtension() {
            Utils.debug.warn(this._name, 'No implementation of installAndActivateExtension found');
        },

        async removeExtension(removeData) {
            try {
                this.showRemovalModal = false;
                this.isLoading = true;

                await this.shopwareExtensionService.removeExtension(this.extension.name, this.extension.type, removeData);
                this.extension.active = false;
            } catch (e) {
                this.showStoreError(e);
            } finally {
                this.isLoading = false;
            }
        },

        cancelAndRemoveExtension() {
            Utils.debug.warn(this._name, 'No implementation of cancelAndRemoveExtension found');
        },

        openPrivacyModal() {
            this.showPrivacyModal = true;
        },

        closePrivacyModal() {
            this.showPrivacyModal = false;
        },

        clearCacheAndReloadPage() {
            return this.cacheApiService.clear().then(() => {
                window.location.reload();
            });
        },

        openConsentAffirmationModal() {
            this.showConsentAffirmationModal = true;
        },

        closeConsentAffirmationModal() {
            this.showConsentAffirmationModal = false;
        },

        async closeConsentAffirmationModalAndUpdateExtension() {
            this.closeConsentAffirmationModal();
            await this.updateExtension(true);
        },
    },
};
