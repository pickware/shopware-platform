import Flatpickr from 'flatpickr';
import 'flatpickr/dist/l10n';
import { zonedTimeToUtc, utcToZonedTime } from 'date-fns-tz';
import template from './sw-datepicker-deprecated.html.twig';
import 'flatpickr/dist/flatpickr.css';
import './sw-datepicker.scss';

const { Mixin } = Shopware;

/**
 * @sw-package framework
 *
 * @private
 * @description Datepicker wrapper for date inputs. For all configuration options visit:
 * <a href="https://flatpickr.js.org/options/">https://flatpickr.js.org/options/</a>.
 * Be careful when changing the config object. To add a parameter to the config at runtime use:
 * <a href="https://vuejs.org/v2/api/#Vue-set">https://vuejs.org/v2/api/#Vue-set</a>.
 *
 * @status ready
 * @example-type static
 * @component-example
 * <sw-datepicker-deprecated
 *      dateType="date"
 *      label="SW-Field Date"
 *      size="default"
 *      placeholder="Enter date..."
 *      value="12.10.2019">
 * </sw-datepicker-deprecated>
 */
const allEvents = [
    'onChange',
    'onClose',
    'onDestroy',
    'onMonthChange',
    'onOpen',
    'onYearChange',
    'onValueUpdate',
    'onDayCreate',
    'onParseConfig',
    'onReady',
    'onPreCalendarPosition',
    'onKeyDown',
];

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,
    inheritAttrs: false,

    emits: [
        'update:value',
        'inheritance-restore',
        'inheritance-remove',
    ],

    inject: ['feature'],

    mixins: [
        Mixin.getByName('sw-form-field'),
        Mixin.getByName('remove-api-error'),
    ],

    props: {
        value: {
            type: String,
            required: false,
            default: null,
        },

        config: {
            type: Object,
            default() {
                return {};
            },
        },

        dateType: {
            type: String,
            default: 'date',
            validValues: [
                'time',
                'date',
                'datetime',
            ],
            validator(value) {
                return [
                    'time',
                    'date',
                    'datetime',
                ].includes(value);
            },
        },

        placeholder: {
            type: String,
            default: '',
            required: false,
        },

        required: {
            type: Boolean,
            default: false,
            required: false,
        },

        disabled: {
            type: Boolean,
            default: false,
            required: false,
        },

        hideHint: {
            type: Boolean,
            default: false,
            required: false,
        },
    },

    data() {
        return {
            flatpickrInstance: null,
            isDatepickerOpen: false,
            defaultConfig: {},
        };
    },

    computed: {
        locale() {
            return Shopware.Store.get('session').adminLocaleLanguage || 'en';
        },

        currentFlatpickrConfig() {
            if (this.flatpickrInstance === null) {
                return {};
            }

            return this.flatpickrInstance.config;
        },

        placeholderText() {
            if (this.placeholder.length > 0) {
                return this.placeholder;
            }

            if (this.flatpickrInstance === null) {
                return this.defaultConfig.altFormat;
            }

            return this.flatpickrInstance.config.altFormat;
        },

        suffixName() {
            if (this.noCalendar) {
                return 'regular-clock';
            }

            return 'regular-calendar';
        },

        noCalendar() {
            return this.dateType === 'time';
        },

        enableTime() {
            return this.noCalendar || this.dateType === 'datetime';
        },

        additionalAttrs() {
            const attrs = {};

            /**
             * Do not pass "change" or "input" event listeners to the form elements.
             */
            Object.keys(this.$attrs).forEach((key) => {
                if (
                    ![
                        'onChange',
                        'onInput',
                    ].includes(key)
                ) {
                    attrs[key] = this.$attrs[key];
                }
            });

            /**
             * Convert the events for the date picker to another format:
             * from: 'on-month-change' to: { camelCase: 'onMonthChange', kebabCase: 'on-month-change' }
             * So this can be used as a parameter to flatpickr to specify which events will be thrown
             * and also emit the right event from vue.
             */
            Object.entries(attrs).forEach(
                ([
                    key,
                    value,
                ]) => {
                    // Check if the key is an event, e.g. starts with "on-"
                    if (!key.startsWith('on-')) {
                        return;
                    }

                    // Remove the "on-" prefix
                    const eventName = key.replace('on-', '');
                    // Convert the kebab-case to camelCase
                    const camelCase = this.kebabToCamel(eventName);
                    // Add the new event name to the object
                    attrs[camelCase] = value;
                    // Remove the old event name from the object
                    delete attrs[key];
                },
            );

            return attrs;
        },

        userTimeZone() {
            return Shopware?.Store?.get('session')?.currentUser?.timeZone ?? 'UTC';
        },

        timezoneFormattedValue: {
            get() {
                if (!this.value) {
                    return null;
                }

                if (
                    [
                        'time',
                        'date',
                    ].includes(this.dateType)
                ) {
                    return this.value;
                }

                // convert from UTC timezone to user timezone (represented as UTC)
                const userTimezoneDate = utcToZonedTime(this.value, this.userTimeZone);

                // get the time converted to the user timezone
                return userTimezoneDate.toISOString();
            },
            set(newValue) {
                if (newValue === null) {
                    this.$emit('update:value', null);

                    return;
                }

                if (
                    [
                        'time',
                        'date',
                    ].includes(this.dateType)
                ) {
                    this.$emit('update:value', newValue);

                    return;
                }
                // convert from user timezone (represented as UTC) to UTC timezone
                const utcDate = zonedTimeToUtc(new Date(newValue), this.userTimeZone);

                // emit the UTC time so that the v-model value always work in UTC time (which is needed for the server)
                this.$emit('update:value', utcDate.toISOString());
            },
        },

        showTimeZoneHint() {
            return !this.hideHint;
        },

        timeZoneHint() {
            if (this.dateType === 'datetime') {
                return this.userTimeZone;
            }

            return 'UTC';
        },

        is24HourFormat() {
            const locale = Shopware.Store.get('session').currentLocale;
            const formatter = new Intl.DateTimeFormat(locale, { hour: 'numeric' });
            const intlOptions = formatter.resolvedOptions();
            return !intlOptions.hour12;
        },
    },

    watch: {
        config: {
            deep: true,
            handler() {
                this.updateFlatpickrInstance();
            },
        },

        dateType() {
            this.createConfig();
            this.updateFlatpickrInstance();
        },

        locale: {
            immediate: true,
            handler() {
                this.defaultConfig.locale = this.locale;
                this.updateFlatpickrInstance(this.config);
            },
        },

        /**
         * Watch for changes from parent component and update DOM
         *
         * @param newValue
         */
        timezoneFormattedValue(newValue) {
            this.setDatepickerValue(newValue);
        },

        disabled(isDisabled) {
            this.flatpickrInstance._input.disabled = isDisabled;
        },
    },

    created() {
        this.createdComponent();
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        createdComponent() {
            this.createConfig();
        },

        mountedComponent() {
            if (this.flatpickrInstance === null) {
                return;
            }

            this.updateFlatpickrInstance();
        },

        /**
         * Free up memory
         */
        beforeDestroyComponent() {
            // NextTick is needed to avoid patching issue when used in a modal
            this.$nextTick(() => {
                if (this.flatpickrInstance !== null) {
                    this.flatpickrInstance.destroy();
                    this.flatpickrInstance = null;
                }
            });
        },

        /**
         * Update with the new value.
         *
         * @param value
         */
        setDatepickerValue(value) {
            // Make sure we have a flatpickr instance
            if (this.flatpickrInstance !== null) {
                // Notify flatpickr instance that there is a change in value
                this.flatpickrInstance.setDate(value, false);
            }
        },

        /**
         * Merge the newConfig parameter with the defaultConfig and other options.
         *
         * @param newConfig
         * @returns {any}
         */
        getMergedConfig(newConfig) {
            if (newConfig.mode !== undefined) {
                console.warn(
                    "[sw-datepicker] The only allowed mode is the default 'single' mode " +
                        '(the specified mode will be ignored!). ' +
                        "The modes 'multiple' or 'range' are currently not supported",
                );
            }

            // To fix receiving `time_24hr` as a string
            if (typeof newConfig.time_24hr === 'string') {
                newConfig.time_24hr = newConfig.time_24hr === 'true';
            }

            return {
                ...this.defaultConfig,
                enableTime: this.enableTime,
                noCalendar: this.noCalendar,
                ...newConfig,
                mode: 'single',
            };
        },

        /**
         * Update the flatpickr instance with a new config.
         */
        updateFlatpickrInstance() {
            if (this.flatpickrInstance === null) {
                return;
            }

            const mergedConfig = this.getMergedConfig(this.config);

            if (
                mergedConfig.enableTime !== undefined &&
                mergedConfig.enableTime !== this.currentFlatpickrConfig.enableTime
            ) {
                // The instance must be recreated for some config options to take effect like 'enableTime' changes.
                // See https://github.com/flatpickr/flatpickr/issues/1108 for details.
                this.createFlatpickrInstance(this.config);
                return;
            }
            // Workaround: Don't allow to pass hooks to configs again otherwise
            // previously registered hooks will stop working
            // Notice: we are looping through all events
            // This also means that new callbacks can not passed once component has been initialized
            allEvents.forEach((hook) => {
                delete mergedConfig[hook];
            });

            // Update the flatpickr config.
            this.flatpickrInstance.set(mergedConfig);

            // Workaround: Allow to change locale dynamically
            [
                'locale',
                'showMonths',
            ].forEach((name) => {
                if (typeof mergedConfig[name] !== 'undefined') {
                    this.flatpickrInstance.set(name, mergedConfig[name]);
                }
            });
        },

        /**
         * Create the flatpickr instance. If already one exists it will be recreated.
         */
        createFlatpickrInstance() {
            if (this.flatpickrInstance !== null) {
                this.flatpickrInstance.destroy();
                this.flatpickrInstance = null;
            }

            const mergedConfig = this.getMergedConfig(this.config);

            // Set event hooks in config.
            this.getEventNames().forEach(({ kebabCase, camelCase }) => {
                mergedConfig[camelCase] = (...args) => {
                    this.$emit(kebabCase, ...args);
                };
            });

            // Init flatpickr only if it is not already loaded.
            this.flatpickrInstance = new Flatpickr(this.$refs.flatpickrInput, mergedConfig);
            this.flatpickrInstance.config.onOpen.push(() => {
                this.isDatepickerOpen = true;
            });

            this.flatpickrInstance.config.onClose.push((...args) => {
                this.emitValue(args[1]);
                this.isDatepickerOpen = false;
            });

            this.flatpickrInstance.config.onChange.push((...args) => {
                this.emitValue(args[1]);
            });

            // Set the right datepicker value from the property.
            this.setDatepickerValue(this.timezoneFormattedValue);
        },

        /**
         * Convert the events for the date picker to another format:
         * from: 'on-month-change' to: { camelCase: 'onMonthChange', kebabCase: 'on-month-change' }
         * So this can be used as a parameter to flatpickr to specify which events will be thrown
         * and also emit the right event from vue.
         *
         * @returns {Array}
         */
        getEventNames() {
            const events = [];
            Object.keys(this.additionalAttrs).forEach((event) => {
                // Check if the key is an event, e.g. starts with "on-"
                if (!event.startsWith('on-')) {
                    return;
                }
                events.push({
                    kebabCase: event,
                    camelCase: this.kebabToCamel(event),
                });
            });

            return events;
        },

        /**
         * Opens the datepicker.
         */
        openDatepicker() {
            this.$nextTick(() => {
                this.flatpickrInstance.open();
            });
        },

        /**
         * Get a camel case ("camelCase") string from a kebab case ("kebab-case") string.
         *
         * @param string
         * @returns {*}
         */
        kebabToCamel(string) {
            return string.replace(/-([a-z])/g, (m, g1) => {
                return g1.toUpperCase();
            });
        },

        unsetValue() {
            this.$nextTick(() => {
                this.emitValue(null);
            });
        },

        emitValue(value) {
            // Prevent emitting an empty date, to reset a date, null should be emitted
            if (value === '') {
                value = null;
            }

            // Prevent emit if value is already up to date
            if (value === this.timezoneFormattedValue) {
                return;
            }

            this.timezoneFormattedValue = value;
        },

        createConfig() {
            this.defaultConfig = {
                time_24hr: this.is24HourFormat,
                locale: this.locale,
                altInput: true,
                allowInput: true,
            };

            let dateFormat = 'Y-m-dTH:i:S';
            let altFormat = this.getDateStringFormat({
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
            });

            if (this.dateType === 'time') {
                dateFormat = 'H:i:S';
                altFormat = this.getDateStringFormat({
                    hour: '2-digit',
                    minute: '2-digit',
                });
            }

            if (this.dateType === 'date') {
                altFormat = this.getDateStringFormat({
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                });
            }

            Object.assign(this.defaultConfig, {
                dateFormat,
                altFormat,
            });
        },

        getDateStringFormat(options) {
            const locale = Shopware.Store.get('session').currentLocale;
            const formatter = new Intl.DateTimeFormat(locale, options);
            const parts = formatter.formatToParts(new Date(2000, 0, 1, 0, 0, 0));
            const mergedConfig = this.getMergedConfig(this.config);
            const flatpickrMapping = {
                // https://flatpickr.js.org/formatting/
                year: 'Y', // 4-digit year
                month: 'm', // 2-digit month
                day: 'd', // 2-digit day
                hour: mergedConfig.time_24hr ? 'H' : 'h', // 24-hour or 12-hour
                minute: 'i', // 2-digit minute
                dayPeriod: mergedConfig.time_24hr ? '' : 'K', // AM/PM
            };
            // 'literal' parts are the separators
            return parts.map((part) => (part.type === 'literal' ? part.value : flatpickrMapping[part.type])).join('');
        },
    },
};
