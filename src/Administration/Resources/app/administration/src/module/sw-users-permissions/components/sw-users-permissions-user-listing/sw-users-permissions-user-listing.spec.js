/**
 * @sw-package fundamentals@framework
 */
import { mount } from '@vue/test-utils';

async function createWrapper(privileges = []) {
    return mount(
        await wrapTestComponent('sw-users-permissions-user-listing', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                provide: {
                    acl: {
                        can: (identifier) => {
                            if (!identifier) {
                                return true;
                            }

                            return privileges.includes(identifier);
                        },
                    },
                    userService: {},
                    repositoryFactory: {
                        create: () => ({
                            search: () => Promise.resolve([]),
                        }),
                    },
                    loginService: {},
                    searchRankingService: {},
                },
                mocks: {
                    $route: { query: '' },
                },
                stubs: {
                    'sw-container': true,
                    'sw-simple-search-field': true,
                    'sw-data-grid': {
                        props: [
                            'dataSource',
                            'columns',
                        ],
                        template: `
<div class="sw-data-grid-stub">
  <template v-for="item in dataSource">
      <slot name="actions" v-bind="{ item }"></slot>
  </template>
</div>
`,
                    },
                    'sw-context-menu-item': true,
                    'sw-avatar': true,
                    'router-link': true,
                    'sw-pagination': true,
                },
            },
        },
    );
}

describe('module/sw-users-permissions/components/sw-users-permissions-user-listing', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('the data-grid should show the right columns', async () => {
        const swDataGrid = wrapper.findComponent('.sw-data-grid-stub');
        expect(swDataGrid.props().columns).toStrictEqual([
            {
                property: 'username',
                label: 'sw-users-permissions.users.user-grid.labelUsername',
            },
            {
                property: 'firstName',
                label: 'sw-users-permissions.users.user-grid.labelFirstName',
            },
            {
                property: 'lastName',
                label: 'sw-users-permissions.users.user-grid.labelLastName',
            },
            {
                property: 'aclRoles',
                sortable: false,
                label: 'sw-users-permissions.users.user-grid.labelRoles',
            },
            {
                property: 'email',
                label: 'sw-users-permissions.users.user-grid.labelEmail',
            },
        ]);
    });

    it('the data-grid should get the right user data', async () => {
        const swDataGrid = wrapper.findComponent('.sw-data-grid-stub');
        expect(swDataGrid.props().dataSource).toStrictEqual([]);

        await wrapper.setData({
            user: [
                {
                    localeId: '12345',
                    username: 'maxmuster',
                    firstName: 'Max',
                    lastName: 'Mustermann',
                    email: 'max@mustermann.com',
                },
                {
                    localeId: '7dc07b43229843d387bb5f59233c2d66',
                    username: 'admin',
                    firstName: '',
                    lastName: 'admin',
                    email: 'info@shopware.com',
                },
            ],
        });

        expect(swDataGrid.props().dataSource).toStrictEqual([
            {
                localeId: '12345',
                username: 'maxmuster',
                firstName: 'Max',
                lastName: 'Mustermann',
                email: 'max@mustermann.com',
            },
            {
                localeId: '7dc07b43229843d387bb5f59233c2d66',
                username: 'admin',
                firstName: '',
                lastName: 'admin',
                email: 'info@shopware.com',
            },
        ]);
    });

    it('the card should contain the right title', async () => {
        const title = wrapper.findByText('div', 'sw-users-permissions.users.general.cardLabel');
        expect(title.exists()).toBe(true);
    });

    it('the add user button should be disabled', async () => {
        const addUser = wrapper.find('.sw-users-permissions-user-listing__add-user-button');
        expect(addUser.attributes('disabled')).toBeDefined();
    });

    it('the add user button should be enabled', async () => {
        wrapper = await createWrapper(['users_and_permissions.creator']);
        await wrapper.vm.$nextTick();

        const addUser = wrapper.find('.sw-users-permissions-user-listing__add-user-button');
        expect(addUser.attributes().disabled).toBeUndefined();
    });

    it('the context menu should be disabled', async () => {
        wrapper = await createWrapper([]);
        await wrapper.vm.$nextTick();
        await wrapper.setData({
            user: [
                {},
            ],
        });

        const contextMenuEdit = wrapper.find('.sw-settings-user-list__user-view-action');
        const contextMenuDelete = wrapper.find('.sw-settings-user-list__user-delete-action');

        expect(contextMenuEdit.attributes().disabled).toBe('true');
        expect(contextMenuDelete.attributes().disabled).toBe('true');
    });

    it('the context menu edit should be enabled', async () => {
        wrapper = await createWrapper(['users_and_permissions.editor']);
        await wrapper.vm.$nextTick();
        await wrapper.setData({
            user: [
                {},
            ],
        });

        const contextMenuEdit = wrapper.find('.sw-settings-user-list__user-view-action');
        const contextMenuDelete = wrapper.find('.sw-settings-user-list__user-delete-action');

        expect(contextMenuEdit.attributes().disabled).toBeUndefined();
        expect(contextMenuDelete.attributes().disabled).toBe('true');
    });

    it('the context menu delete should be enabled', async () => {
        wrapper = await createWrapper(['users_and_permissions.deleter']);
        await wrapper.vm.$nextTick();
        await wrapper.setData({
            user: [
                {},
            ],
        });

        const contextMenuEdit = wrapper.find('.sw-settings-user-list__user-view-action');
        const contextMenuDelete = wrapper.find('.sw-settings-user-list__user-delete-action');

        expect(contextMenuEdit.attributes().disabled).toBe('true');
        expect(contextMenuDelete.attributes().disabled).toBeUndefined();
    });

    it('should add avatar media as association', async () => {
        wrapper = await createWrapper(['users_and_permissions.editor']);
        await flushPromises();

        expect(wrapper.vm.userCriteria.associations[1].association).toBe('avatarMedia');
    });
});
