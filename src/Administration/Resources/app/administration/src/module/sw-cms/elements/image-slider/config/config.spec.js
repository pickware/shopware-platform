/**
 * @sw-package discovery
 */
/* eslint-disable max-len */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';
import { MtSwitch, MtUrlField } from '@shopware-ag/meteor-component-library';
import selectMtSelectOptionByText from '../../../../../../test/_helper_/select-mt-select-by-text';

async function createWrapper(activeTab = 'content', sliderItems = []) {
    return mount(
        await wrapTestComponent('sw-cms-el-config-image-slider', {
            sync: true,
        }),
        {
            attachTo: document.body,
            global: {
                renderStubDefaultSlot: true,
                provide: {
                    cmsService: Shopware.Service('cmsService'),
                    repositoryFactory: {
                        create: () => {
                            return {
                                search: () =>
                                    Promise.resolve({
                                        get: (mediaId) => {
                                            /* if media is not found, return null, otherwise return a valid mediaItem */
                                            return mediaId === 'deletedId'
                                                ? null
                                                : {
                                                      id: '0',
                                                      position: 0,
                                                  };
                                        },
                                    }),
                            };
                        },
                    },
                    mediaService: {},
                },
                stubs: {
                    'sw-tabs': {
                        props: ['defaultItem'],
                        data() {
                            return { active: activeTab };
                        },
                        template: '<div><slot></slot><slot name="content" v-bind="{ active }"></slot></div>',
                    },
                    'sw-tabs-item': true,
                    'sw-select-field': {
                        template:
                            '<select class="sw-select-field" :value="value" @change="$emit(\'change\', $event.target.value)"><slot></slot></select>',
                        props: [
                            'value',
                            'options',
                        ],
                    },
                    'sw-container': true,
                    'sw-field': true,
                    'sw-text-field': true,
                    'sw-cms-mapping-field': await wrapTestComponent('sw-cms-mapping-field'),
                    'sw-media-list-selection-v2': await wrapTestComponent('sw-media-list-selection-v2'),

                    'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field'),
                    'sw-checkbox-field-deprecated': await wrapTestComponent('sw-checkbox-field-deprecated', { sync: true }),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-help-text': true,
                    'sw-field-error': true,
                    'sw-upload-listener': true,
                    'sw-media-upload-v2': true,
                    'sw-media-list-selection-item-v2': {
                        template: '<div class="sw-media-item">{{item.id}}</div>',
                        props: ['item'],
                    },
                    'sw-media-modal-v2': true,
                    'sw-loader': true,
                    'sw-inheritance-switch': true,
                    'sw-ai-copilot-badge': true,
                    'mt-switch': MtSwitch,
                    'mt-url-field': MtUrlField,
                },
            },
            props: {
                element: {
                    config: {
                        sliderItems: {
                            source: 'static',
                            value: sliderItems,
                            required: true,
                            entity: {
                                name: 'media',
                            },
                        },
                        navigationArrows: {
                            source: 'static',
                            value: 'outside',
                        },
                        navigationDots: {
                            source: 'static',
                            value: null,
                        },
                        displayMode: {
                            source: 'static',
                            value: 'standard',
                        },
                        minHeight: {
                            source: 'static',
                            value: '300px',
                        },
                        verticalAlign: {
                            source: 'static',
                            value: null,
                        },
                        autoSlide: {
                            source: 'static',
                            value: false,
                        },
                        speed: {
                            source: 'static',
                            value: 300,
                        },
                        autoplayTimeout: {
                            source: 'static',
                            value: 5000,
                        },
                        isDecorative: {
                            source: 'static',
                            value: false,
                        },
                    },
                    data: {},
                },
                defaultConfig: {},
            },
            data() {
                return {
                    mediaItems: [
                        {
                            id: '0',
                            position: 0,
                        },
                        {
                            id: '1',
                            position: 1,
                        },
                        {
                            id: '2',
                            position: 2,
                        },
                        {
                            id: '3',
                            position: 3,
                        },
                        {
                            id: 'deletedId',
                            position: 4,
                        },
                    ],
                };
            },
        },
    );
}

describe('src/module/sw-cms/elements/image-slider/config', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
        await import('src/module/sw-cms/elements/image-slider');
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should keep minHeight value when changing display mode', async () => {
        const wrapper = await createWrapper('settings');

        await selectMtSelectOptionByText(wrapper, 'sw-cms.elements.general.config.label.displayModeCover');

        expect(wrapper.vm.element.config.minHeight.value).toBe('300px');

        await selectMtSelectOptionByText(wrapper, 'sw-cms.elements.general.config.label.displayModeStandard');

        // Should still have the previous value
        expect(wrapper.vm.element.config.minHeight.value).toBe('300px');
    });

    it('should change the isDecorative value', async () => {
        const wrapper = await createWrapper('settings');
        const isDecorativeSwitch = wrapper.find('.sw-cms-el-config-image-slider__settings-is-decorative input');

        await isDecorativeSwitch.setValue(true);

        expect(wrapper.vm.element.config.isDecorative.value).toBe(true);

        await isDecorativeSwitch.setValue(false);

        expect(wrapper.vm.element.config.isDecorative.value).toBe(false);
    });

    /**
     * Re-implement after properly implementing/fixing auto slide.
     * This feature is currently unusable, since it's unstyled and re-enables itself, while creating broken states.
     */
    // eslint-disable-next-line jest/no-disabled-tests
    it.skip('should be able to show auto slide switch', async () => {
        const wrapper = await createWrapper('settings');
        const autoSlideOption = wrapper.find('.sw-cms-el-config-image-slider__setting-auto-slide');
        expect(autoSlideOption.exists()).toBeTruthy();
    });

    /**
     * Re-implement after properly implementing/fixing auto slide.
     * This feature is currently unusable, since it's unstyled and re-enables itself, while creating broken states.
     */
    // eslint-disable-next-line jest/no-disabled-tests
    it.skip('should disable delay element and speed element when auto slide switch is falsy', async () => {
        const wrapper = await createWrapper('settings');
        const delaySlide = wrapper.find('.sw-cms-el-config-image-slider__setting-delay-slide');
        const speedSlide = wrapper.find('.sw-cms-el-config-image-slider__setting-speed-slide');
        expect(delaySlide.attributes().disabled).toBe('true');
        expect(speedSlide.attributes().disabled).toBe('true');
    });

    /**
     * Re-implement after properly implementing/fixing auto slide.
     * This feature is currently unusable, since it's unstyled and re-enables itself, while creating broken states.
     */
    // eslint-disable-next-line jest/no-disabled-tests
    it.skip('should not disable delay element and speed element when auto slide switch is truthy', async () => {
        const wrapper = await createWrapper('settings');
        await flushPromises();

        const delaySlide = wrapper.find('.sw-cms-el-config-image-slider__setting-delay-slide');
        const speedSlide = wrapper.find('.sw-cms-el-config-image-slider__setting-speed-slide');
        const autoSlideOption = wrapper.find('.sw-cms-el-config-image-slider__setting-auto-slide input');
        await autoSlideOption.setChecked();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.showSlideConfig).toBe(true);
        expect(delaySlide.attributes().disabled).toBeUndefined();
        expect(speedSlide.attributes().disabled).toBeUndefined();
    });

    it('should sort the item list on drag and drop', async () => {
        const wrapper = await createWrapper('content');
        await flushPromises();

        const mediaListSelectionV2Vm = wrapper.findComponent('.sw-media-list-selection-v2').vm;
        mediaListSelectionV2Vm.$emit(
            'item-sort',
            mediaListSelectionV2Vm.mediaItems[1],
            mediaListSelectionV2Vm.mediaItems[2],
            true,
        );
        await wrapper.vm.$nextTick();

        const items = wrapper.findAll('.sw-media-item');

        expect(items).toHaveLength(5);
        expect(items.at(0).text()).toBe('0');
        expect(items.at(1).text()).toBe('2');
        expect(items.at(2).text()).toBe('1');
        expect(items.at(3).text()).toBe('3');
        expect(items.at(4).text()).toBe('deletedId');
    });

    it('should remove deleted media from imageSlider', async () => {
        const sliderItems = [
            { filename: 'a.jpg', mediaId: 'a' },
            { filename: 'b.jpg', mediaId: 'b' },
            { filename: 'c.jpg', mediaId: 'c' },
            { filename: 'd.jpg', mediaId: 'd' },
            { filename: 'notfound.jpg', mediaId: 'deletedId' },
        ];

        const wrapper = await createWrapper('content', sliderItems);
        await flushPromises();
        const validItems = wrapper.findAll('.sw-media-item');

        expect(sliderItems).toHaveLength(5);
        expect(validItems).toHaveLength(4);
    });

    it('should remove previous mediaItem if it already exists after upload', async () => {
        const wrapper = await createWrapper('content');
        await flushPromises();

        // Check length of sliderItems values
        expect(wrapper.vm.element.config.sliderItems.value).toHaveLength(0);

        // Simulate the upload of the first media item
        wrapper.vm.onImageUpload({
            id: '1',
            url: 'http://shopware.com/image1.jpg',
        });
        expect(wrapper.vm.element.config.sliderItems.value).toHaveLength(1);
        expect(wrapper.vm.element.config.sliderItems.value[0].mediaUrl).toBe('http://shopware.com/image1.jpg');

        // Simulate the upload of the same media item with different URL and same ID (replacement)
        wrapper.vm.onImageUpload({
            id: '1',
            url: 'http://shopware.com/image1-updated.jpg',
        });

        // Should still only have one item and the URL should be updated
        expect(wrapper.vm.element.config.sliderItems.value).toHaveLength(1);
        expect(wrapper.vm.element.config.sliderItems.value[0].mediaUrl).toBe('http://shopware.com/image1-updated.jpg');
    });
});
