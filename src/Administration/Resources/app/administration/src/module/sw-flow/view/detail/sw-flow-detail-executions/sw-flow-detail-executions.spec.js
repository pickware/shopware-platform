import {mount} from '@vue/test-utils';
import flowState from 'src/module/sw-flow/state/flow.state';

/**
 * @package services-settings
 */

const flowData = [
    {
        id: '44de136acf314e7184401d36406c1e90',
    },
];

const actionTitle = 'checkout.order.placed';

async function createWrapper(privileges = [], customFlowExecutionData = [{}], customActionTitle = actionTitle) {
    return mount(await wrapTestComponent('sw-flow-detail-executions', { sync: true }), {
        global: {
            stubs: {
                'sw-page': {
                    template: `
                    <div class="sw-page">
                        <slot name="search-bar"></slot>
                        <slot name="smart-bar-back"></slot>
                        <slot name="smart-bar-header"></slot>
                        <slot name="language-switch"></slot>
                        <slot name="smart-bar-actions"></slot>
                        <slot name="side-content"></slot>
                        <slot name="content"></slot>
                        <slot name="sidebar"></slot>
                        <slot></slot>
                    </div>
                `,
                },
                'sw-icon': true,
                'sw-button': true,
                'sw-entity-listing': {
                    props: ['items'],
                    template: `
                    <div class="sw-data-grid">
                        <div class="sw-data-grid__row" v-for="item in items">
                            <slot name="column-eventName" v-bind="{ item }"></slot>
                            <slot name="actions" v-bind="{ item }"></slot>
                        </div>
                    </div>
                `,
                },
                'sw-card': await wrapTestComponent('sw-card'),
                'sw-card-deprecated': await wrapTestComponent('sw-card-deprecated', { sync: true }),
                'sw-context-menu-item': await wrapTestComponent('sw-context-menu-item'),
                'sw-empty-state': true,
                'sw-search-bar': true,
                'sw-time-ago': true,
                'router-link': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: () => {
                            return Promise.resolve(customFlowExecutionData);
                        },
                    }),
                },

                acl: {
                    can: (identifier) => {
                        if (!identifier) {
                            return true;
                        }

                        return privileges.includes(identifier);
                    },
                },
                flowBuilderService: {
                    getActionTitle: () => {
                        return customActionTitle;
                    }
                },
            },
            mocks: {
                $tc: (key) => {
                    return key;
                },
            },
        },
    });
}

describe('module/sw-flow/view/listing/sw-flow-list', () => {
    beforeAll(() => {
        Shopware.State.registerModule('swFlowState', {
            ...flowState,
            state: {
                flow: flowData,
            },
        });
    });

    it('should not be able to navigate to the failed sequence if no sequence failed', async () => {
        const wrapper = await createWrapper([
            'flow.viewer',
        ]);
        await flushPromises();

        const highlightFailedSequenceMenuItem = wrapper.find('.sw-flow-list__item-highlight-failed-sequence');

        expect(highlightFailedSequenceMenuItem.exists()).toBe(true);
        expect(highlightFailedSequenceMenuItem.classes()).toContain('is--disabled');
    });

    it('should be able to navigate to the failed sequence if a sequence failed', async () => {
        const wrapper = await createWrapper([
            'flow.viewer',
        ], [{ failedFlowSequence: 'some-failed-sequence' }]);
        await flushPromises();

        const highlightFailedSequenceMenuItem = wrapper.find('.sw-flow-list__item-highlight-failed-sequence');

        expect(highlightFailedSequenceMenuItem.exists()).toBe(true);
        expect(highlightFailedSequenceMenuItem.classes()).not.toContain('is--disabled');
    });
});
