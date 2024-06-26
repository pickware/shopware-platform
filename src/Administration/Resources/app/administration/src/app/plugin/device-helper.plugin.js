/**
 * @package admin
 */

const { warn } = Shopware.Utils.debug;
const { DeviceHelper } = Shopware.Helper;

let pluginInstalled = false;

/**
 * @private
 */
export default {
    install(Vue) {
        if (pluginInstalled) {
            warn('DeviceHelper', 'This plugin is already installed');
            return false;
        }

        const deviceHelper = new DeviceHelper();

        Object.defineProperties(Vue.prototype, {
            $device: {
                get() {
                    return deviceHelper;
                },
            },
        });

        Vue.mixin({
            unmounted() {
                this.$device.removeResizeListener(this);
            },
        });

        pluginInstalled = true;

        return true;
    },
};
