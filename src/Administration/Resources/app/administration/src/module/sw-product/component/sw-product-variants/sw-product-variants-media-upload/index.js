/*
 * @sw-package inventory
 */

import template from './sw-product-variants-media-upload.html.twig';
import './sw-product-variants-media-upload.scss';

const { Mixin, Context } = Shopware;
const { isEmpty } = Shopware.Utils.types;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'mediaDefaultFolderService',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        source: {
            type: Object,
            required: true,
        },

        parentProduct: {
            type: Object,
            required: true,
        },

        isInherited: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            showMediaModal: false,
            mediaDefaultFolderId: null,
            showPreviewModal: false,
            activeItemId: null,
        };
    },

    computed: {
        productMediaRepository() {
            return this.repositoryFactory.create(this.source.media.entity);
        },

        product() {
            if (this.isInherited) {
                return this.parentProduct;
            }

            return this.source;
        },

        mediaSource() {
            if (!this.product) {
                return [];
            }
            const media = [...this.product.media];

            return media.sort((a, b) => a.position - b.position);
        },

        cover() {
            if (!this.product) {
                return null;
            }

            return this.product.media.find((media) => media.id === this.product.coverId);
        },

        coverImageSource() {
            if (this.cover) {
                return this.cover.media ?? this.cover.mediaId;
            }

            return this.product?.cover?.mediaId ?? this.product?.cover?.media;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getMediaDefaultFolderId()
                .then((id) => {
                    this.mediaDefaultFolderId = id;
                    this.defaultFolderId = id;
                    this.updateMediaItemPositions();
                })
                .catch(() => {
                    this.mediaDefaultFolderId = null;
                    this.defaultFolderId = null;
                });
        },

        getMediaDefaultFolderId() {
            return this.mediaDefaultFolderService.getDefaultFolderId('product');
        },

        isCover(productMedia) {
            const coverId = this.product.cover ? this.product.cover.id : this.product.coverId;

            if (this.product.media.length === 0) {
                return false;
            }

            return productMedia.id === coverId;
        },

        markMediaAsCover(productMedia) {
            this.product.cover = productMedia;
            this.product.coverId = productMedia.id;
        },

        removeMedia(productMedia) {
            if (this.product.coverId === productMedia.id) {
                this.product.cover = null;
                this.product.coverId = null;
            }

            if (this.product.coverId === null && this.product.media.length > 0) {
                this.product.coverId = this.product.media.first().id;
            }

            this.product.media.remove(productMedia.id);
        },

        onAddMedia(media) {
            if (isEmpty(media)) {
                return;
            }

            media.forEach((item) => {
                this.addMedia(item).catch(({ fileName }) => {
                    this.createNotificationError({
                        message: this.$tc('sw-product.mediaForm.errorMediaItemDuplicated', { fileName }, 0),
                    });
                });
            });
            this.updateMediaItemPositions();
        },

        addMedia(media) {
            if (this.isExistingMedia(media)) {
                return Promise.reject(media);
            }

            const newMedia = this.productMediaRepository.create(Context.api);
            newMedia.mediaId = media.id;
            newMedia.media = { url: media.url, id: media.id };

            if (isEmpty(this.source.media)) {
                this.source.cover = newMedia;
                this.source.coverId = newMedia.id;
            }

            this.source.media.add(newMedia);

            return Promise.resolve();
        },

        isExistingMedia(media) {
            return this.source.media.some(({ id, mediaId }) => {
                return id === media.id || mediaId === media.id;
            });
        },

        onUploadMediaSuccessful({ targetId }) {
            if (this.isReplacedMedia(targetId)) {
                return;
            }

            const newMedia = this.productMediaRepository.create(Context.api);
            newMedia.productId = this.source.id;
            newMedia.mediaId = targetId;

            if (isEmpty(this.source.media)) {
                newMedia.position = 0;
                this.source.coverId = newMedia.id;
            } else {
                newMedia.position = this.source.media.length;
            }

            this.source.media.add(newMedia);
            this.updateMediaItemPositions();
        },

        isReplacedMedia(targetId) {
            return this.source.media.find((sourceMedia) => {
                return sourceMedia.mediaId === targetId;
            });
        },

        onUploadMediaFailed({ targetId }) {
            const newMedia = this.source.media.find((sourceMedia) => {
                return sourceMedia.mediaId === targetId;
            });

            if (newMedia) {
                if (this.source.coverId === newMedia.id) {
                    this.source.coverId = null;
                }
                this.source.media.remove(newMedia.id);
            }

            this.source.isLoading = false;
        },

        previewMedia(item) {
            this.activeItemId = item.id;
            this.showPreviewModal = true;
        },

        onClosePreviewModal() {
            this.activeItemId = null;
            this.showPreviewModal = false;
        },

        updateMediaItemPositions() {
            this.source.media.forEach((medium, index) => {
                medium.position = index;
            });
        },
    },
};
