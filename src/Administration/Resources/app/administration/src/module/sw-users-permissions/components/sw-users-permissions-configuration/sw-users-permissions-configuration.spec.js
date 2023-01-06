/**
 * @package system-settings
 */
import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-users-permissions/components/sw-users-permissions-configuration';

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-users-permissions-configuration'), {
        stubs: {
            'sw-system-config': true,
        },
    });
}

describe('module/sw-users-permissions/components/sw-users-permissions-configuration', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.element).toMatchSnapshot();
    });

    it('should emit the event correctly', () => {
        wrapper.vm.onChangeLoading(true);
        expect(wrapper.emitted('loading-change')).toBeTruthy();
    });
});
