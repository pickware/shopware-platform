import { type PropType } from 'vue';
import template from './sw-cms-block-config.html.twig';
import './sw-cms-block-config.scss';
import type MediaUploadResult from '../../../shared/MediaUploadResult';

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: [
        'repositoryFactory',
        'cmsService',
    ],

    emits: [
        'block-delete',
        'block-duplicate',
    ],

    mixins: [
        Shopware.Mixin.getByName('cms-state'),
    ],

    props: {
        block: {
            type: Object as PropType<Entity<'cms_block'>>,
            required: true,
        },
    },

    computed: {
        uploadTag() {
            return `cms-block-media-config-${this.block.id}`;
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        cmsPageState() {
            return Shopware.Store.get('cmsPage');
        },

        cmsBlocks() {
            return this.cmsService.getCmsBlockRegistry();
        },

        blockConfig() {
            return this.cmsBlocks[this.block.type];
        },

        quickactionsDisabled() {
            return !this.isSystemDefaultLanguage || this.blockConfig?.removable === false;
        },

        duplicateDisabled() {
            return !this.block.id || this.block._isNew;
        },

        combinedDuplicateDisabled() {
            return this.quickactionsDisabled || this.duplicateDisabled;
        },

        combinedDuplicateClasses() {
            return {
                'is--disabled': this.combinedDuplicateDisabled,
            };
        },

        quickactionClasses() {
            return {
                'is--disabled': this.quickactionsDisabled,
            };
        },

        backgroundModeOptions() {
            return [
                {
                    id: 1,
                    value: 'auto',
                    label: this.$tc('sw-cms.detail.label.backgroundMediaModeAuto'),
                },
                {
                    id: 2,
                    value: 'contain',
                    label: this.$tc('sw-cms.detail.label.backgroundMediaModeContain'),
                },
                {
                    id: 3,
                    value: 'cover',
                    label: this.$tc('sw-cms.detail.label.backgroundMediaModeCover'),
                },
            ];
        },
    },

    methods: {
        onSetBackgroundMedia([mediaItem]: Entity<'media'>[]) {
            this.block.backgroundMediaId = mediaItem.id;
            this.block.backgroundMedia = mediaItem;
        },

        async successfulUpload(uploadedMedia: MediaUploadResult) {
            this.block.backgroundMediaId = uploadedMedia.targetId;

            this.block.backgroundMedia = (await this.mediaRepository.get(uploadedMedia.targetId)) ?? undefined;
        },

        removeMedia() {
            this.block.backgroundMediaId = undefined;
            this.block.backgroundMedia = undefined;
        },

        onBlockDelete() {
            if (this.quickactionsDisabled) {
                return;
            }

            this.$emit('block-delete', this.block);
        },

        onBlockDuplicate() {
            if (this.combinedDuplicateDisabled) {
                return;
            }

            this.$emit('block-duplicate', this.block);
        },

        onBlockNameChange(value: string) {
            this.block.name = value;
        },
    },
});
