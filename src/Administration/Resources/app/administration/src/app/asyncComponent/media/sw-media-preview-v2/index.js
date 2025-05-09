import template from './sw-media-preview-v2.html.twig';
import './sw-media-preview-v2.scss';

const { Context, Filter } = Shopware;
const { fileReader } = Shopware.Utils;

/**
 * @status ready
 * @description The <u>sw-media-preview-v2</u> component is used to show a preview of media objects.
 * @sw-package discovery
 * @example-type code-only
 * @component-example
 * <sw-media-preview-v2
 *      :source="item.id"
 *      :show-controls="true"
 *      :autoplay="false"
 *      :use-thumbnails="false">
 * </sw-media-preview-v2>
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'feature',
    ],

    emits: [
        'click',
        'media-preview-play',
    ],

    playableVideoFormats: [
        'video/mp4',
        'video/ogg',
        'video/webm',
    ],

    playableAudioFormats: [
        'audio/mp3',
        'audio/mpeg',
        'audio/ogg',
        'audio/wav',
    ],

    placeholderThumbnailsBasePath: '/administration/administration/static/img/media-preview/',

    placeHolderThumbnails: {
        application: {
            'adobe.illustrator': 'icons-multicolor-file-thumbnail-ai',
            illustrator: 'icons-multicolor-file-thumbnail-ai',
            postscript: 'icons-multicolor-file-thumbnail-ai',
            msword: 'icons-multicolor-file-thumbnail-doc',
            'vnd.openxmlformats-officedocument.wordprocessingml.document': 'icons-multicolor-file-thumbnail-doc',
            pdf: 'icons-multicolor-file-thumbnail-pdf',
            'vnd.ms-excel': 'icons-multicolor-file-thumbnail-xls',
            'vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'icons-multicolor-file-thumbnail-xls',
            'vnd.ms-powerpoint': 'icons-multicolor-file-thumbnail-ppt',
            'vnd.openxmlformats-officedocument.presentationml.presentation': 'icons-multicolor-file-thumbnail-ppt',
            glb: 'icons-multicolor-file-thumbnail-glb',
            'octet-stream': 'icons-multicolor-file-thumbnail-glb',
        },
        video: {
            'x-msvideo': 'icons-multicolor-file-thumbnail-avi',
            quicktime: 'icons-multicolor-file-thumbnail-mov',
            mp4: 'icons-multicolor-file-thumbnail-mp4',
        },
        text: {
            csv: 'icons-multicolor-file-thumbnail-csv',
            plain: 'icons-multicolor-file-thumbnail-csv',
        },
        image: {
            gif: 'icons-multicolor-file-thumbnail-gif',
            jpeg: 'icons-multicolor-file-thumbnail-jpg',
            'svg+xml': 'icons-multicolor-file-thumbnail-svg',
        },
        model: {
            'gltf-binary': 'icons-multicolor-file-thumbnail-glb',
        },
    },

    props: {
        // eslint-disable-next-line vue/require-prop-types
        source: {
            required: true,
        },

        showControls: {
            type: Boolean,
            required: false,
            default: false,
        },

        autoplay: {
            type: Boolean,
            required: false,
            default: false,
        },

        transparency: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },

        useThumbnails: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },

        hideTooltip: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },

        mediaIsPrivate: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            trueSource: null,
            width: 0,
            dataUrl: '',
            urlPreviewFailed: false,
            imagePreviewFailed: false,
        };
    },

    computed: {
        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        mediaPreviewClasses() {
            return {
                'is--icon': this.isIcon,
                'is--no-media': !this.source,
            };
        },

        transparencyClass() {
            return {
                'shows--transparency': this.canBeTransparent,
            };
        },

        canBeTransparent() {
            if (!this.transparency) {
                return false;
            }

            return this.isIcon || this.mimeTypeGroup === 'image';
        },

        mimeType() {
            if (!this.trueSource) {
                return '';
            }

            if (this.trueSource instanceof File) {
                return this.trueSource.type;
            }

            if (this.trueSource instanceof URL) {
                return 'application/octet-stream';
            }

            return this.trueSource.mimeType;
        },

        mimeTypeGroup() {
            if (!this.mimeType) {
                return '';
            }

            return this.mimeType.split('/')[0];
        },

        isPlayable() {
            if (this.$options.playableVideoFormats.includes(this.mimeType)) {
                return true;
            }

            return this.$options.playableAudioFormats.includes(this.mimeType);
        },

        isIcon() {
            return /.*svg.*/.test(this.mimeType);
        },

        placeholderIcon() {
            if (!this.mimeType) {
                return 'icons-multicolor-file-thumbnail-broken';
            }

            const mediaTypeIconGroup = this.$options.placeHolderThumbnails[this.mimeTypeGroup];
            if (mediaTypeIconGroup) {
                const mediaTypeIcon = mediaTypeIconGroup[`${this.mimeType.split('/')[1]}`];
                if (mediaTypeIcon) {
                    return mediaTypeIcon;
                }
            }

            return 'icons-multicolor-file-thumbnail-normal';
        },

        placeholderIconPath() {
            return `${this.$options.placeholderThumbnailsBasePath}${this.placeholderIcon}.svg`;
        },

        lockIsVisible() {
            return this.width > 40;
        },

        previewUrl() {
            if (this.isFile) {
                this.getDataUrlFromFile();
                return this.dataUrl;
            }

            if (this.isUrl) {
                return this.trueSource.href;
            }

            if (this.isRelativePath) {
                return this.trueSource;
            }

            return this.trueSource.url;
        },

        isUrl() {
            return this.trueSource instanceof URL;
        },

        isFile() {
            return this.trueSource instanceof File;
        },

        isRelativePath() {
            return typeof this.trueSource === 'string';
        },

        alt() {
            if (this.trueSource.alt) {
                return this.trueSource.alt;
            }
            return this.trueSource.fileName;
        },

        mediaName() {
            if (!this.trueSource) {
                return this.$tc('global.sw-media-preview-v2.textNoMedia');
            }

            return this.mediaNameFilter(this.trueSource, this.trueSource.fileName);
        },

        mediaNameFilter() {
            return Filter.getByName('mediaName');
        },

        assetFilter() {
            return Filter.getByName('asset');
        },

        sourceSet() {
            if (this.isFile || this.isUrl) {
                return '';
            }

            if (this.trueSource.thumbnails.length === 0) {
                return '';
            }

            const sources = [];
            this.trueSource.thumbnails.forEach((thumbnail) => {
                const url = thumbnail.url;

                const encoded = encodeURI(url);
                sources.push(`${encoded} ${thumbnail.width}w`);
            });

            return sources.join(', ');
        },
    },

    watch: {
        source() {
            this.urlPreviewFailed = false;
            this.imagePreviewFailed = false;
            this.fetchSourceIfNecessary();
        },
    },

    created() {
        this.createdComponent();
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        createdComponent() {
            this.fetchSourceIfNecessary();
        },

        mountedComponent() {
            this.width = this.$el.offsetWidth;
        },

        async fetchSourceIfNecessary() {
            if (!this.source) {
                return;
            }

            if (typeof this.source !== 'string') {
                this.trueSource = this.source[0] ?? this.source;

                return;
            }

            try {
                this.trueSource = await this.mediaRepository.get(this.source, Context.api);
            } catch {
                this.trueSource = this.source;
            }
        },

        onPlayClick(originalDomEvent) {
            if (!(originalDomEvent.shiftKey || originalDomEvent.ctrlKey)) {
                originalDomEvent.stopPropagation();
                this.$emit('media-preview-play', {
                    originalDomEvent,
                    item: this.trueSource,
                });
            }
        },

        async getDataUrlFromFile() {
            if (this.mimeTypeGroup !== 'image') {
                return;
            }

            this.dataUrl = await fileReader.readAsDataURL(this.trueSource);
        },

        removeUrlPreview() {
            this.urlPreviewFailed = true;
        },

        showEvent() {
            if (!this.isFile) {
                this.imagePreviewFailed = true;
            }
        },
    },
};
