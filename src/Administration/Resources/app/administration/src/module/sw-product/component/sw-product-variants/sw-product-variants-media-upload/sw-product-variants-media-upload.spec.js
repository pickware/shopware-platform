/**
 * @sw-package inventory
 */

import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';

describe('src/module/sw-product/component/sw-product-variants/sw-product-variants-media-upload', () => {
    let wrapper;
    const listEntity = [];

    beforeEach(async () => {
        wrapper = mount(
            await wrapTestComponent('sw-product-variants-media-upload', {
                sync: true,
            }),
            {
                props: {
                    uploadTag: 'upload-tag',
                    source: {
                        media: new EntityCollection(
                            '/test-entity',
                            'testEntity',
                            null,
                            { isShopwareContext: true },
                            listEntity,
                            listEntity.length,
                            null,
                        ),
                    },
                    parentProduct: {
                        media: new EntityCollection(
                            '/test-entity',
                            'testEntity',
                            null,
                            { isShopwareContext: true },
                            listEntity,
                            listEntity.length,
                            null,
                        ),
                    },
                },
                global: {
                    stubs: {
                        'sw-context-button': {
                            template: '<div class="sw-context-button"><slot></slot></div>',
                        },
                        'sw-context-menu-item': await wrapTestComponent('sw-context-menu-item'),
                        'sw-media-url-form': true,
                        'sw-media-preview-v2': true,
                        'sw-upload-listener': true,
                        'sw-media-modal-v2': true,
                        'sw-image-preview-modal': true,
                        'router-link': true,
                    },
                    mocks: {
                        $t: (v) => v,
                        $tc: (v) => v,
                    },
                    provide: {
                        repositoryFactory: {
                            create: () => {
                                return {
                                    create: () => {
                                        return Promise.resolve();
                                    },
                                    search: () => {
                                        return Promise.resolve();
                                    },
                                };
                            },
                        },
                        mediaDefaultFolderService: {
                            getDefaultFolderId: () => {
                                return Promise.resolve('id');
                            },
                        },
                        configService: {},
                        mediaService: {
                            removeByTag: () => null,
                            removeListener: () => null,
                        },
                        fileValidationService: {},
                    },
                },
            },
        );
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should contain the default accept value', async () => {
        const fileInput = wrapper.find('.sw-media-upload-v2__file-input');

        expect(fileInput.attributes().accept).toBe('*/*');
    });

    it('should contain "application/pdf" value', async () => {
        await wrapper.setProps({
            fileAccept: 'application/pdf',
        });
        const fileInput = wrapper.find('.sw-media-upload-v2__file-input');

        expect(fileInput.attributes().accept).toBe('application/pdf');
    });

    it('should contain file upload', async () => {
        await wrapper.setData({
            inputType: 'file-upload',
        });

        const urlForm = wrapper.find('.sw-media-upload-v2__url-form');
        const uploadBtn = wrapper.find('.sw-media-upload-v2__button.upload');

        expect(urlForm.exists()).toBeFalsy();
        expect(uploadBtn.exists()).toBeTruthy();
    });

    it('should show image and have buttons action', async () => {
        const entities = [
            {
                mediaId: 'mediaId',
                id: 'id1',
            },
        ];

        await wrapper.setProps({
            uploadTag: 'upload-tag',
            source: {
                media: new EntityCollection(
                    '/test-entity',
                    'testEntity',
                    null,
                    { isShopwareContext: true },
                    entities,
                    entities.length,
                    null,
                ),
            },
            parentProduct: {
                media: new EntityCollection(
                    '/test-entity',
                    'testEntity',
                    null,
                    { isShopwareContext: true },
                    entities,
                    entities.length,
                    null,
                ),
            },
        });

        const image = wrapper.find('.sw-media-upload-v2__preview');
        const buttons = wrapper.find('.sw-product-variants-media-upload__context-button');

        expect(image.exists()).toBeTruthy();
        expect(buttons.exists()).toBeTruthy();
    });

    it('should mark media as cover correctly.', async () => {
        const entities = [
            {
                mediaId: 'mediaId1',
                media: 'media1',
                id: 'id1',
            },
            {
                mediaId: 'mediaId2',
                media: 'media2',
                id: 'id2',
            },
        ];

        await wrapper.setProps({
            uploadTag: 'upload-tag',
            source: {
                media: new EntityCollection(
                    '/test-entity',
                    'testEntity',
                    null,
                    { isShopwareContext: true },
                    entities,
                    entities.length,
                    null,
                ),
                coverId: 'id2',
            },
            parentProduct: {
                media: new EntityCollection(
                    '/test-entity',
                    'testEntity',
                    null,
                    { isShopwareContext: true },
                    entities,
                    entities.length,
                    null,
                ),
            },
        });

        await flushPromises();

        const cover = wrapper.find('.sw-product-variants-media-upload__preview-cover sw-media-preview-v2-stub');

        expect(cover.attributes().source).toBe('media2');

        const images = wrapper.findAll('.sw-product-variants-media-upload__images .sw-product-variants-media-upload__image');
        const media = images.at(0);
        const button = media.findAll('.sw-context-button .sw-context-menu-item').at(0);

        await button.trigger('click');

        expect(cover.attributes().source).toBe('media1');
    });

    it('should remove media correctly.', async () => {
        const entities = [
            {
                mediaId: 'mediaId1',
                id: 'id1',
            },
            {
                mediaId: 'mediaId2',
                id: 'id2',
            },
        ];

        await wrapper.setProps({
            uploadTag: 'upload-tag',
            source: {
                media: new EntityCollection(
                    '/test-entity',
                    'testEntity',
                    null,
                    { isShopwareContext: true },
                    entities,
                    entities.length,
                    null,
                ),
                coverId: 'id1',
            },
            parentProduct: {
                media: new EntityCollection(
                    '/test-entity',
                    'testEntity',
                    null,
                    { isShopwareContext: true },
                    entities,
                    entities.length,
                    null,
                ),
            },
        });

        await flushPromises();

        const images = wrapper.findAll('.sw-product-variants-media-upload__images .sw-product-variants-media-upload__image');
        expect(images).toHaveLength(2);
        expect(wrapper.find('sw-media-preview-v2-stub[source="mediaId2"]').exists()).toBeTruthy();

        const media = images.at(1);
        const button = media.findAll('.sw-context-button .sw-context-menu-item').at(2);

        await button.trigger('click');

        expect(
            wrapper.findAll('.sw-product-variants-media-upload__images .sw-product-variants-media-upload__image'),
        ).toHaveLength(1);
        expect(wrapper.find('sw-media-preview-v2-stub[source="mediaId2"]').exists()).toBeFalsy();
    });

    it('should get media default folder id when component got created', async () => {
        await wrapper.vm.$nextTick();

        wrapper.vm.getMediaDefaultFolderId = jest.fn(() => {
            return Promise.resolve('id');
        });

        wrapper.vm.createdComponent();

        expect(wrapper.vm.getMediaDefaultFolderId).toHaveBeenCalledTimes(1);
        wrapper.vm.getMediaDefaultFolderId.mockRestore();
    });

    it('should get media default folder id successfully', async () => {
        await wrapper.vm.$nextTick();

        wrapper.vm.getMediaDefaultFolderId = jest.fn(() => {
            return Promise.resolve('id');
        });

        wrapper.vm.createdComponent();

        expect(wrapper.vm.mediaDefaultFolderId).toBe('id');
        expect(wrapper.vm.defaultFolderId).toBe('id');
        wrapper.vm.getMediaDefaultFolderId.mockRestore();
    });

    it('should be able to add a new media', async () => {
        await wrapper.vm.$nextTick();

        const newMedia = [{ id: 'id', fileName: 'fileName', fileSize: 101 }];
        wrapper.vm.addMedia = jest.fn(() => {
            return Promise.resolve();
        });

        await wrapper.vm.onAddMedia(newMedia);
        await wrapper.setProps({ source: { media: newMedia } });

        expect(wrapper.vm.addMedia).toHaveBeenCalledWith(newMedia[0]);
        expect(wrapper.vm.source.media).toEqual(expect.arrayContaining(newMedia));

        wrapper.vm.addMedia.mockRestore();
    });

    it('should reject duplicated media', async () => {
        await wrapper.vm.$nextTick();

        const entities = [{ productId: 101, mediaId: 'mediaId', position: 0 }];
        const newMedia = [{ id: 'id', fileName: 'fileName', fileSize: 101 }];

        await wrapper.setProps({
            source: {
                id: 'id',
                media: new EntityCollection('', '', {}, null, entities),
            },
        });

        await wrapper.vm.addMedia(newMedia[0]);
        await expect(wrapper.vm.addMedia(newMedia[0])).rejects.toEqual(newMedia[0]);
    });

    it('should not be able to add duplicated uploaded media', async () => {
        await wrapper.vm.$nextTick();

        const entities = [{ productId: 101, mediaId: 'mediaId', position: 0 }];

        await wrapper.setProps({
            source: {
                id: 'id',
                media: new EntityCollection('', '', {}, null, entities),
            },
        });

        await wrapper.vm.onUploadMediaSuccessful({ targetId: 'targetId' });
        await wrapper.vm.onUploadMediaSuccessful({ targetId: 'targetId' });

        expect(wrapper.vm.source.media).toHaveLength(2);
    });

    it('should not be able to add a new media', async () => {
        await wrapper.vm.$nextTick();

        const newMedia = [{ id: 'id', fileName: 'fileName', fileSize: 101 }];
        wrapper.vm.createNotificationError = jest.fn();
        wrapper.vm.addMedia = jest.fn(() => {
            return Promise.reject(newMedia[0]);
        });

        await wrapper.vm.onAddMedia(newMedia);

        expect(wrapper.vm.addMedia).toHaveBeenCalledWith(newMedia[0]);
        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-product.mediaForm.errorMediaItemDuplicated',
        });

        wrapper.vm.addMedia.mockRestore();
        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should be able to upload a new media', async () => {
        await wrapper.vm.$nextTick();

        const entities = [{ productId: 101, mediaId: 'mediaId', position: 0 }];

        await wrapper.setProps({
            source: {
                id: 'id',
                media: new EntityCollection('', '', {}, null, entities),
            },
        });

        await wrapper.vm.onUploadMediaSuccessful({ targetId: 'targetId' });

        expect(wrapper.vm.source.media).toEqual(
            expect.arrayContaining([
                expect.objectContaining({ mediaId: 'mediaId' }),
                expect.objectContaining({ mediaId: 'targetId' }),
            ]),
        );
    });

    it('should not be able to upload a new media', async () => {
        await wrapper.vm.$nextTick();

        const entities = [{ productId: 101, mediaId: 'mediaId', position: 0 }];

        await wrapper.setProps({
            source: {
                id: 'id',
                media: new EntityCollection('', '', {}, null, entities),
            },
        });

        await wrapper.vm.onUploadMediaFailed({ targetId: 'mediaId' });

        expect(wrapper.vm.source.media).not.toEqual(
            expect.arrayContaining([
                expect.objectContaining({ mediaId: 'mediaId' }),
            ]),
        );
    });

    it('should show regular upload button when having less than 3 media files', async () => {
        const entities = [
            {
                mediaId: 'mediaId1',
                id: 'id1',
            },
            {
                mediaId: 'mediaId2',
                id: 'id2',
            },
        ];

        await wrapper.setProps({
            uploadTag: 'upload-tag',
            source: {
                media: new EntityCollection(
                    '/test-entity',
                    'testEntity',
                    null,
                    { isShopwareContext: true },
                    entities,
                    entities.length,
                    null,
                ),
                coverId: 'id2',
            },
            parentProduct: {
                media: new EntityCollection(
                    '/test-entity',
                    'testEntity',
                    null,
                    { isShopwareContext: true },
                    entities,
                    entities.length,
                    null,
                ),
            },
        });

        expect(wrapper.find('.sw-product-variants-media-upload__regular-button').exists()).toBeTruthy();
    });

    it('should show regular compact button when having 3 or more media files', async () => {
        const entities = [
            {
                mediaId: 'mediaId1',
                id: 'id1',
            },
            {
                mediaId: 'mediaId2',
                id: 'id2',
            },
            {
                mediaId: 'mediaId3',
                id: 'id3',
            },
        ];

        await wrapper.setProps({
            uploadTag: 'upload-tag',
            source: {
                media: new EntityCollection(
                    '/test-entity',
                    'testEntity',
                    null,
                    { isShopwareContext: true },
                    entities,
                    entities.length,
                    null,
                ),
                coverId: 'id2',
            },
            parentProduct: {
                media: new EntityCollection(
                    '/test-entity',
                    'testEntity',
                    null,
                    { isShopwareContext: true },
                    entities,
                    entities.length,
                    null,
                ),
            },
        });

        expect(wrapper.find('.sw-product-variants-media-upload__compact-button').exists()).toBeTruthy();
    });
});
