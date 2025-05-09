/**
 * @sw-package framework
 */

import baseComponents from 'src/app/component/components';
import registerAsyncComponents from 'src/app/asyncComponent/asyncComponents';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default async function initializeBaseComponents() {
    registerAsyncComponents();

    // eslint-disable-next-line no-restricted-syntax
    for (const component of baseComponents()) {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-call,no-await-in-loop
        await component();
    }

    return Promise.resolve();
}
