/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';

describe('src/app/component/media/sw-media-field', () => {
    async function createWrapper() {
        return mount(await wrapTestComponent('sw-media-field', { sync: true }), {
            props: {
                fileAccept: '*/*',
            },
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'sw-media-media-item': true,
                    'sw-popover': {
                        template: `
                        <div>
                            <slot />
                        </div>
                    `,
                    },
                    'sw-upload-listener': true,
                    'sw-media-upload-v2': true,
                    'sw-simple-search-field': true,
                    'sw-loader': true,
                    'sw-pagination': true,
                },
                mocks: {
                    $route: {
                        query: '',
                    },
                },
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            create: () => {
                                return Promise.resolve();
                            },
                            get: () => {
                                return Promise.resolve();
                            },
                            search: () => {
                                return Promise.resolve();
                            },
                        }),
                    },
                },
            },
        });
    }

    it('should contain the default folder in criteria', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            defaultFolder: 'product',
        });
        const criteria = wrapper.vm.suggestionCriteria;
        expect(criteria.filters).toContainEqual({
            type: 'equals',
            field: 'mediaFolder.defaultFolder.entity',
            value: 'product',
        });

        expect(criteria.page).toBe(1);
        expect(criteria.limit).toBe(5);
    });

    it('should contain a property props fileAccept', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.$props.fileAccept).toBe('*/*');
    });

    it('should stop propagation when sw-popover content is clicked', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            showPicker: true,
        });

        const stopPropagation = jest.fn();
        await wrapper.find('.sw-media-field__actions_bar').trigger('click', {
            stopPropagation,
        });

        expect(stopPropagation).toHaveBeenCalled();

        expect(wrapper.vm.page).toBe(1);
        expect(wrapper.vm.limit).toBe(5);
        expect(wrapper.vm.total).toBe(0);
    });

    it('should be able to change search term', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.fetchSuggestions = jest.fn();

        wrapper.vm.onSearchTermChange('test');

        expect(wrapper.vm.searchTerm).toBe('test');
        expect(wrapper.vm.page).toBe(1);
        expect(wrapper.vm.fetchSuggestions).toHaveBeenCalled();
    });

    it('should be able to change page', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.fetchSuggestions = jest.fn();

        wrapper.vm.onPageChange({
            page: 2,
            limit: 5,
        });

        expect(wrapper.vm.page).toBe(2);
        expect(wrapper.vm.limit).toBe(5);
        expect(wrapper.vm.fetchSuggestions).toHaveBeenCalled();
    });

    it('returns empty config when picker is false', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.popoverConfig).toEqual({});
    });

    it('returns empty config when not inside a modal', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({ showPicker: true });

        wrapper.element.closest = jest.fn(() => null);
        expect(wrapper.vm.popoverConfig).toEqual({});
    });

    it('returns modal targetSelector when picker open inside modal', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({ showPicker: true });

        const el = wrapper.element;
        el.closest = jest.fn((selector) => (selector === '.mt-modal' ? el : null));

        const config = wrapper.vm.$options.computed.popoverConfig.call(wrapper.vm);
        expect(config).toEqual({
            targetSelector: '.mt-modal__content-inner',
        });
    });
});
