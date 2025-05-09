import template from './sw-cms-el-config-youtube-video.html.twig';
import './sw-cms-el-config-youtube-video.scss';

const { Mixin } = Shopware;

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    inject: ['repositoryFactory'],

    emits: ['element-update'],

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    data() {
        return {
            mediaModalIsOpen: false,
            initialFolderId: null,
        };
    },

    computed: {
        startValue() {
            return this.convertTimeToInputFormat(this.element.config.start.value).string;
        },

        endValue() {
            return this.convertTimeToInputFormat(this.element.config.end.value).string;
        },

        videoID: {
            get() {
                return this.element.config.videoID.value;
            },

            set(link) {
                this.element.config.videoID.value = this.shortenLink(link);
            },
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        uploadTag() {
            return `cms-element-youtube-video-config-${this.element.id}`;
        },

        previewSource() {
            if (this.element.data && this.element.data.previewMedia && this.element.data.previewMedia.id) {
                return this.element.data.previewMedia;
            }

            return this.element.config.previewMedia.value;
        },

        displayModeOptions() {
            return [
                {
                    id: 1,
                    value: 'standard',
                    label: this.$tc('sw-cms.elements.general.config.label.displayModeStandard'),
                },
                {
                    id: 2,
                    value: 'streched',
                    label: this.$tc('sw-cms.elements.general.config.label.displayModeStretch'),
                },
            ];
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        setTimeValue(value, type) {
            this.element.config[type].value = this.convertTimeToUrlFormat(value).string;
        },

        createdComponent() {
            this.initElementConfig('youtube-video');
        },

        convertTimeToInputFormat(time) {
            /* converting the time to a human readable format.
             * e.g. 1337 (seconds) -> 22:17
             */

            const returnValues = {};
            let incomingTime = time;

            const regex = /^[0-9]*$/;
            const isValidFormat = regex.test(time);

            if (!isValidFormat) {
                incomingTime = 0;
            }

            const minutes = Math.floor(incomingTime / 60);
            let seconds = incomingTime - minutes * 60;

            returnValues.minutes = minutes;
            returnValues.seconds = seconds;

            if (seconds.toString().length === 1) {
                seconds = `0${seconds}`;
            }

            returnValues.string = `${minutes}:${seconds}`;

            return returnValues;
        },

        convertTimeToUrlFormat(time) {
            /* converting the time to an url format so the YouTube iFrame-API can read the time.
             * e.g. 0:42 -> 42 (seconds)
             */

            const returnValues = {};
            let incomingTime = time;

            const regex = /[0-9]?[0-9]:[0-9][0-9]/;
            const isValidFormat = regex.test(incomingTime);

            if (!isValidFormat) {
                incomingTime = '00:00';
            }

            const splittedTime = incomingTime.split(':');
            returnValues.minutes = Number(splittedTime[0]);
            returnValues.seconds = Number(splittedTime[1]);
            returnValues.string = returnValues.minutes * 60 + returnValues.seconds;

            return returnValues;
        },

        shortenLink(link) {
            /* shareLink is the link you get when you click the share button under a YouTube video.
             *  e.g. https://youtu.be/bG57TZPYsyw
             *
             * urlLink is the link of the YouTube video from the searchbar. e.g. https://www.youtube.com/watch?v=bG57TZPYsyw
             */
            const url = new URL(link);

            switch (url.hostname) {
                case 'www.youtu.be':
                case 'youtu.be':
                    return url.pathname.substring(1);
                case 'www.youtube.com':
                case 'youtube.com':
                    return url.searchParams.get('v');
                default:
                    return link;
            }
        },

        async onImageUpload({ targetId }) {
            const mediaEntity = await this.mediaRepository.get(targetId);

            this.element.config.previewMedia.value = mediaEntity.id;

            this.updateElementData(mediaEntity);

            this.$emit('element-update', this.element);
        },

        onImageRemove() {
            this.element.config.previewMedia.value = null;

            this.updateElementData();

            this.$emit('element-update', this.element);
        },

        onCloseModal() {
            this.mediaModalIsOpen = false;
        },

        onSelectionChanges(mediaEntity) {
            const media = mediaEntity[0];
            this.element.config.previewMedia.value = media.id;

            this.updateElementData(media);

            this.$emit('element-update', this.element);
        },

        updateElementData(media = null) {
            this.element.data.previewMediaId = media === null ? null : media.id;
            this.element.data.previewMedia = media;
        },

        onOpenMediaModal() {
            this.mediaModalIsOpen = true;
        },
    },
};
