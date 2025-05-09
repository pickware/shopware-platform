/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import selectMtSelectOptionByText from '../../../../../test/_helper_/select-mt-select-by-text';

const cacheInfo = {
    data: {
        httpCache: true,
        environment: 'dev',
        cacheAdapter: 'fooBar',
    },
};

async function createWrapper(indexMock = jest.fn(() => Promise.resolve()), delayMock = jest.fn(() => Promise.resolve())) {
    return mount(await wrapTestComponent('sw-settings-cache-index', { sync: true }), {
        global: {
            provide: {
                cacheInfo: cacheInfo,
                indexerSelection: [],
                cacheApiService: {
                    info: () => Promise.resolve(cacheInfo),
                    delayed: delayMock,
                    index: indexMock,
                },
            },
            stubs: {
                'sw-page': {
                    template: `
                    <div>
                        <slot name="smart-bar-header"></slot>
                        <slot name="content"></slot>
                    </div>`,
                },
                'sw-card-view': await wrapTestComponent('sw-card-view'),
                'sw-card-section': await wrapTestComponent('sw-card-section'),
                'sw-container': await wrapTestComponent('sw-container'),
                'sw-button-process': await wrapTestComponent('sw-button-process'),
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-label': await wrapTestComponent('sw-label'),
                'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field'),
                'sw-checkbox-field-deprecated': await wrapTestComponent('sw-checkbox-field-deprecated', { sync: true }),
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                'sw-error-summary': await wrapTestComponent('sw-error-summary'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-field-error': await wrapTestComponent('sw-field-error'),
                'sw-extension-component-section': await wrapTestComponent('sw-extension-component-section'),
                'sw-skeleton': true,
                'sw-ai-copilot-badge': true,
                'sw-context-button': true,
                'sw-loader': true,
                'sw-tabs-item': true,
                'sw-tabs': true,
                'sw-iframe-renderer': true,
                'router-link': true,
                'sw-inheritance-switch': true,
                'sw-help-text': true,
                'sw-color-badge': true,
                'mt-popover-deprecated': {
                    template: '<div class="mt-popover-deprecated"><slot></slot></div>',
                },
            },
        },
    });
}

describe('module/sw-settings-cache/page/sw-settings-cache-index', () => {
    it('should change label and empty text on indexing method selection changed', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const indexesSelectLabel = wrapper.find('.sw-settings-cache__indexers-select .sw-field__label label');
        const indexSelectPlaceholder = wrapper.find(
            '.sw-settings-cache__indexers-select .sw-settings-cache__indexers-placeholder .sw-label__caption',
        );

        expect(indexesSelectLabel.text()).toBe('sw-settings-cache.section.indexesSkipSelectLabel');
        expect(indexSelectPlaceholder.text()).toBe('sw-settings-cache.section.indexesSkipSelectPlaceholder');

        await selectMtSelectOptionByText(wrapper, 'sw-settings-cache.section.indexingModeOptionOnlyLabel');

        expect(indexesSelectLabel.text()).toBe('sw-settings-cache.section.indexesOnlySelectLabel');
        expect(indexSelectPlaceholder.text()).toBe('sw-settings-cache.section.indexesOnlySelectPlaceholder');
    });

    it('should send clear data cache request', async () => {
        const mock = jest.fn(() => Promise.resolve());

        const wrapper = await createWrapper(
            jest.fn(() => Promise.resolve()),
            mock,
        );
        await flushPromises();

        wrapper.vm.clearDataCache();

        expect(mock).toHaveBeenCalledTimes(1);
    });

    it('should send different values for skip and only on reindex', async () => {
        const indexMock = jest.fn(() => Promise.resolve());

        const wrapper = await createWrapper(indexMock);
        await flushPromises();

        expect(wrapper.vm.indexerSelection).toHaveLength(0);

        wrapper.vm.changeSelection(true, 'category.tree');

        expect(wrapper.vm.indexerSelection).toHaveLength(1);

        const button = wrapper.find('button[name="updateIndexesButton"]');

        await button.trigger('click');
        await flushPromises();

        expect(indexMock).toHaveBeenCalledTimes(1);
        expect(indexMock).toHaveBeenCalledWith(['category.tree'], []);

        await selectMtSelectOptionByText(wrapper, 'sw-settings-cache.section.indexingModeOptionOnlyLabel');

        await button.trigger('click');
        await flushPromises();

        expect(indexMock).toHaveBeenCalledTimes(2);
        expect(indexMock).toHaveBeenCalledWith(
            [],
            [
                'category.indexer',
                'category.tree',
            ],
        );
    });
});
