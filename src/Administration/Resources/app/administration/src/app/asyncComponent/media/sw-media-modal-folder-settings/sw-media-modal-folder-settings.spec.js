/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';

let repositoryFactoryCreateMock;
let repositoryFactorySearchMock;
let repositoryFactorySearchIdsMock;
let repositoryFactorySaveMock;

async function createWrapper() {
    repositoryFactoryCreateMock = jest.fn(() => Promise.resolve());
    repositoryFactorySearchMock = jest.fn(() => Promise.resolve([]));
    repositoryFactorySearchIdsMock = jest.fn(() => Promise.resolve([]));
    repositoryFactorySaveMock = jest.fn(() => Promise.resolve());

    return mount(
        await wrapTestComponent('sw-media-modal-folder-settings', {
            sync: true,
        }),
        {
            props: {
                mediaFolderId: '12345',
                disabled: false,
            },
            global: {
                stubs: {
                    'sw-modal': await wrapTestComponent('sw-modal', {
                        sync: true,
                    }),
                    'sw-tabs': await wrapTestComponent('sw-tabs', {
                        sync: true,
                    }),
                    'sw-tabs-item': true,
                    'sw-text-field': true,
                    'sw-highlight-text': true,
                    'sw-select-result': true,
                    'sw-entity-single-select': true,
                    'sw-container': true,
                    'sw-field': true,
                    'mt-number-field': true,
                    'sw-media-add-thumbnail-form': true,
                    'sw-loader': true,
                    'mt-tabs': true,
                    'sw-tabs-deprecated': true,
                },
                provide: {
                    repositoryFactory: {
                        create: (entity) => {
                            return {
                                create: repositoryFactoryCreateMock,
                                search: repositoryFactorySearchMock,
                                searchIds: repositoryFactorySearchIdsMock,
                                save: repositoryFactorySaveMock,
                                get: () => {
                                    switch (entity) {
                                        case 'media_folder_configuration':
                                            return Promise.resolve({
                                                mediaThumbnailSizes: {
                                                    entity: 'media_thumbnail_size',
                                                    source: 'media_thumbnail_size',
                                                },
                                            });
                                        default:
                                            return Promise.resolve({
                                                id: '12345',
                                                name: 'Test folder',
                                                parentId: null,
                                                configurationId: '12345',
                                            });
                                    }
                                },
                            };
                        },
                    },
                    shortcutService: {
                        startEventListener: () => {},
                        stopEventListener: () => {},
                    },
                },
            },
        },
    );
}

describe('src/app/asyncComponent/media/sw-media-modal-folder-settings', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should get thumbnail sizes and unused thumbnail sizes with the correct criteria', async () => {
        const searchIds = jest.spyOn(wrapper.vm.mediaThumbnailSizeRepository, 'searchIds');
        const search = jest.spyOn(wrapper.vm.mediaThumbnailSizeRepository, 'search');

        const getUnusedThumbnailSizes = jest.spyOn(wrapper.vm, 'getUnusedThumbnailSizes');
        const getThumbnailSizes = jest.spyOn(wrapper.vm, 'getThumbnailSizes');

        await wrapper.vm.createdComponent();

        expect(getUnusedThumbnailSizes).toHaveBeenCalled();
        expect(getThumbnailSizes).toHaveBeenCalled();

        expect(searchIds).toHaveBeenCalledWith(
            expect.objectContaining({
                filters: [
                    {
                        field: 'mediaFolderConfigurations.mediaFolders.id',
                        type: 'equals',
                        value: null,
                    },
                ],
            }),
        );
        expect(search).toHaveBeenCalledWith(
            expect.objectContaining({
                sortings: [
                    {
                        field: 'width',
                        naturalSorting: false,
                        order: 'ASC',
                    },
                ],
            }),
        );
    });

    it('should update thumbnail sizes correctly', async () => {
        repositoryFactorySearchIdsMock = jest.fn(() => {
            return Promise.resolve({
                data: ['12345'],
            });
        });
        repositoryFactorySearchMock = jest.fn(() => {
            return Promise.resolve([
                {
                    id: '12345',
                    width: 100,
                    height: 100,
                },
                {
                    id: '67890',
                    width: 200,
                    height: 200,
                },
            ]);
        });

        await wrapper.vm.createdComponent();

        expect(wrapper.vm.unusedThumbnailSizes).toEqual(['12345']);
        expect(wrapper.vm.thumbnailSizes).toEqual([
            {
                id: '12345',
                width: 100,
                height: 100,
                deletable: true,
            },
            {
                id: '67890',
                width: 200,
                height: 200,
                deletable: false,
            },
        ]);
    });

    it('should be able to add a new thumbnail size', async () => {
        repositoryFactoryCreateMock = jest.fn(() => {
            return { _isNew: true };
        });

        await wrapper.setData({
            thumbnailSizes: [
                {
                    width: 10,
                    height: 10,
                    deletable: true,
                },
                {
                    width: 20,
                    height: 20,
                    deletable: false,
                },
            ],
        });
        await wrapper.vm.addThumbnail({
            width: 30,
            height: 30,
        });

        expect(repositoryFactoryCreateMock).toHaveBeenCalled();
        expect(repositoryFactorySaveMock).toHaveBeenCalledWith(
            expect.objectContaining({
                _isNew: true,
                width: 30,
                height: 30,
            }),
            expect.any(Object),
        );
    });

    it('should not be able to add a new thumbnail size if the size already exists', async () => {
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.setData({
            thumbnailSizes: [
                {
                    width: 10,
                    height: 10,
                    deletable: true,
                },
                {
                    width: 20,
                    height: 20,
                    deletable: false,
                },
            ],
        });
        await wrapper.vm.addThumbnail({
            width: 10,
            height: 10,
        });

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'global.sw-media-modal-folder-settings.notification.error.messageThumbnailSizeExisted',
        });
    });
});
