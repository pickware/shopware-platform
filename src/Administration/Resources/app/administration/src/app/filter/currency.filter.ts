/**
 * @sw-package framework
 */
import type { CurrencyOptions } from 'src/core/service/utils/format.utils';

const { currency } = Shopware.Utils.format;

/**
 * @private
 */
Shopware.Filter.register(
    'currency',
    (value: string | boolean, format: string, decimalPlaces: number, additionalOptions: CurrencyOptions) => {
        if (
            (!value || value === true) &&
            (!Shopware.Utils.types.isNumber(value) || Shopware.Utils.types.isEqual(value, NaN))
        ) {
            return '-';
        }

        if (Shopware.Utils.types.isEqual(parseInt(value, 10), NaN)) {
            return value;
        }

        return currency(parseFloat(value), format, decimalPlaces, additionalOptions);
    },
);
