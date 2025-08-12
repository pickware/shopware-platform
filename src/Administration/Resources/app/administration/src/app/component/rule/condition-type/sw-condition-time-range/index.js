import template from './sw-condition-time-range.html.twig';
import './sw-condition-time-range.scss';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();
const defaultTimeValue = '12:00';

/**
 * @sw-package fundamentals@after-sales
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    data() {
        return {
            datepickerConfig: {
                enableTime: true,
                dateFormat: 'H:i',
            },
        };
    },

    computed: {
        fromTime: {
            get() {
                this.ensureValueExist();
                if (!this.condition.value.fromTime) {
                    // eslint-disable-next-line vue/no-side-effects-in-computed-properties
                    this.condition.value.fromTime = defaultTimeValue;
                }

                return this.condition.value.fromTime;
            },
            set(fromTime) {
                this.ensureValueExist();
                this.condition.value.fromTime = fromTime;
            },
        },
        toTime: {
            get() {
                this.ensureValueExist();
                if (!this.condition.value.toTime) {
                    // eslint-disable-next-line vue/no-side-effects-in-computed-properties
                    this.condition.value.toTime = defaultTimeValue;
                }

                return this.condition.value.toTime;
            },
            set(toTime) {
                this.ensureValueExist();
                this.condition.value.toTime = toTime;
            },
        },
        timezone: {
            get() {
                this.ensureValueExist();
                return this.condition.value.timezone;
            },
            set(timezone) {
                this.ensureValueExist();
                this.condition.value.timezone = timezone;
            },
        },

        ...mapPropertyErrors('condition', [
            'value.fromTime',
            'value.toTime',
            'value.timezone',
        ]),

        timezoneOptions() {
            return Shopware.Service('timezoneService').getTimezoneOptions();
        },

        currentError() {
            return this.conditionValueFromTimeError || this.conditionValueToTimeError;
        },
    },
};
