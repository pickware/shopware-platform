import { mount } from '@vue/test-utils';
import findByText from '../../../../../test/_helper_/find-by-text';

async function createWrapper(propsData) {
    return mount(
        await wrapTestComponent('sw-extension-permissions-modal', {
            sync: true,
        }),
        {
            global: {
                mocks: {
                    $t: (...args) => JSON.stringify([...args]),
                    $tc: (...args) => JSON.stringify([...args]),
                },
                stubs: {
                    'sw-modal': {
                        props: ['title'],
                        // eslint-disable-next-line max-len
                        template:
                            '<div><div class="sw-modal__title">{{ title }}</div><slot/><slot name="modal-footer"></slot></div>',
                    },
                    'sw-extension-permissions-details-modal': true,
                    'sw-extension-domains-modal': true,
                    'router-link': true,
                    'sw-loader': true,
                },
            },
            props: {
                ...propsData,
            },
        },
    );
}

/**
 * @sw-package checkout
 */
describe('src/module/sw-extension/component/sw-extension-permissions-modal', () => {
    it('should have the correct title, discription and icon', async () => {
        const wrapper = await createWrapper({
            extensionLabel: 'Sample Extension Label',
            actionLabel: null,
            permissions: {
                product: [{}],
                promotion: [{}],
            },
        });

        expect(wrapper.find('.sw-modal__title').text()).toBe(
            JSON.stringify([
                'sw-extension-store.component.sw-extension-permissions-modal.title',
                {
                    extensionLabel: 'Sample Extension Label',
                },
                1,
            ]),
        );

        expect(wrapper.find('.sw-extension-permissions-modal__description').text()).toBe(
            JSON.stringify([
                'sw-extension-store.component.sw-extension-permissions-modal.description',
                { extensionLabel: 'Sample Extension Label' },
                1,
            ]),
        );

        expect(wrapper.find('.sw-extension-permissions-modal__image').attributes().src).toBe(
            'administration/administration/static/img/extension-store/permissions.svg',
        );
    });

    it('should display two detail links and open the correct detail page', async () => {
        const wrapper = await createWrapper({
            extensionLabel: 'Sample Extension Label',
            actionLabel: null,
            permissions: {
                product: [{}],
                promotion: [{}],
            },
        });

        const category = wrapper.findAll('.sw-extension-permissions-modal__category');

        expect(category.at(0).find('.sw-extension-permissions-modal__category-label').text()).toBe(
            JSON.stringify(['entityCategories.product.title']),
        );

        let toggleButton = await findByText(
            category.at(0),
            'button',
            JSON.stringify([
                'sw-extension-store.component.sw-extension-permissions-modal.textEntities',
            ]),
        );
        expect(toggleButton.exists()).toBe(true);

        // open details modal
        await toggleButton.trigger('click');
        expect(wrapper.vm.selectedEntity).toBe('product');
        expect(wrapper.vm.showDetailsModal).toBe(true);

        // close details modal
        wrapper.vm.closeDetailsModal();
        expect(wrapper.vm.selectedEntity).toBe('');
        expect(wrapper.vm.showDetailsModal).toBe(false);

        expect(category.at(1).find('.sw-extension-permissions-modal__category-label').text()).toBe(
            JSON.stringify(['entityCategories.promotion.title']),
        );

        toggleButton = await findByText(
            category.at(1),
            'button',
            JSON.stringify([
                'sw-extension-store.component.sw-extension-permissions-modal.textEntities',
            ]),
        );

        // open details modal
        await toggleButton.trigger('click');
        expect(wrapper.vm.selectedEntity).toBe('promotion');
        expect(wrapper.vm.showDetailsModal).toBe(true);
    });

    [
        ['http://www.google.com'],
        [
            'http://www.google.com',
            'https://www.facebook.com',
        ],
        [
            'http://www.google.com',
            'https://www.facebook.com',
            'https://www.amazon.com',
        ],
    ].forEach((domains) => {
        it(`should display domains hint with domain length of ${domains.length}`, async () => {
            const wrapper = await createWrapper({
                extensionLabel: 'Sample Extension Label',
                permissions: {
                    product: [{}],
                    promotion: [{}],
                },
                domains: domains,
            });

            expect(wrapper.text()).toContain('sw-extension-store.component.sw-extension-permissions-modal.domainHint');
        });
    });

    [
        ['http://www.google.com'],
        [
            'http://www.google.com',
            'https://www.facebook.com',
        ],
        [
            'http://www.google.com',
            'https://www.facebook.com',
            'https://www.amazon.com',
        ],
    ].forEach((domains) => {
        it('should display category domains', async () => {
            const wrapper = await createWrapper({
                extensionLabel: 'Sample Extension Label',
                permissions: {
                    product: [{}],
                    promotion: [{}],
                },
                domains,
            });

            // check for show domains entry
            expect(wrapper.text()).toContain('sw-extension-store.component.sw-extension-permissions-modal.domains');

            // check for button text
            expect(wrapper.text()).toContain('sw-extension-store.component.sw-extension-permissions-modal.showDomains');
        });
    });

    [
        [],
        null,
        undefined,
    ].forEach((domains) => {
        it(`should not display domains hint when prop domains contains ${domains}`, async () => {
            const wrapper = await createWrapper({
                extensionLabel: 'Sample Extension Label',
                permissions: {
                    product: [{}],
                    promotion: [{}],
                },
                domains,
            });

            expect(wrapper.text()).not.toContain('sw-extension-store.component.sw-extension-permissions-modal.domainHint');
        });
    });

    [
        [],
        null,
        undefined,
    ].forEach((domains) => {
        it('should not display category domains', async () => {
            const wrapper = await createWrapper({
                extensionLabel: 'Sample Extension Label',
                permissions: {
                    product: [{}],
                    promotion: [{}],
                },
                domains,
            });

            expect(wrapper.text()).not.toContain('sw-extension-store.component.sw-extension-permissions-modal.showDomains');
        });
    });
});
