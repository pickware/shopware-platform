/**
 * @sw-package framework
 */

import { updateSubscriber, register, handleGet } from '@shopware-ag/meteor-admin-sdk/es/data';
import { get, debounce, cloneDeepWith } from 'lodash';
import type { App } from 'vue';
import { selectData } from '@shopware-ag/meteor-admin-sdk/es/_internals/data/selectData';
import MissingPrivilegesError from '@shopware-ag/meteor-admin-sdk/es/_internals/privileges/missing-privileges-error';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';
import Entity from 'src/core/data/entity.data';

interface scopeInterface {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    $watch(path: string, callback: (value: any) => void, options: { deep: boolean; immediate: boolean }): () => void;
    $: {
        uid: number;
    };
}
interface publishOptions {
    id: string;
    path: string;
    scope: scopeInterface;
    deprecated?: boolean;
    deprecationMessage?: string;
    showDoubleRegistrationError?: boolean;
}

type dataset = {
    id: string;
    scope: number;
    data: unknown;
    deprecated?: boolean;
    deprecationMessage?: string;
};

type transferObject = {
    [key: string | symbol]: unknown;
};

type ParsedPath = {
    pathToLastSegment: string;
    lastSegment: string;
};

// This is used by the Vue devtool extension plugin
let publishedDataSets: dataset[] = [];

/**
 * This array is used to keep track of datasets that should be unregistered
 */
let unregisterPublishDataIds: string[] = [];

/* eslint-disable @typescript-eslint/no-explicit-any */
/**
 * Deep clone with custom handling for entities and entity collections
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function deepCloneWithEntity(data: any): any {
    return cloneDeepWith(
        data,
        (value: {
            __identifier__?: () => string;
            source?: string;
            entity?: keyof EntitySchema.Entities;
            criteria?: Criteria;
            total?: number;
            aggregations?: unknown;
            id?: string;
            _entityName?: keyof EntitySchema.Entities;
            _draft?: unknown;
            _origin?: unknown;
            _isDirty?: boolean;
            _isNew?: boolean;
        }) => {
            // If value is a entity collection, we need to clone it custom
            if (
                value?.__identifier__ &&
                typeof value.__identifier__ === 'function' &&
                value.__identifier__() === 'EntityCollection'
            ) {
                return new EntityCollection(
                    value.source!,
                    value.entity!,
                    // @ts-expect-error - we don't want to provide a context
                    {},
                    value.criteria === null ? value.criteria : Criteria.fromCriteria(value.criteria!),
                    // @ts-expect-error - value is an array inside a entity collection
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
                    deepCloneWithEntity(Array.from(value)),
                    value.total,
                    value.aggregations,
                );
            }

            // If value is a entity, we need to clone it custom
            if (value?.__identifier__ && typeof value.__identifier__ === 'function' && value.__identifier__() === 'Entity') {
                return new Entity(
                    value.id!,
                    value._entityName!,
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
                    deepCloneWithEntity(value._draft),
                    {
                        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
                        originData: deepCloneWithEntity(value._origin),
                        isDirty: value._isDirty,
                        isNew: value._isNew,
                    },
                );
            }

            return undefined;
        },
    );
}

/* eslint-enable @typescript-eslint/no-explicit-any */

handleGet((data, additionalOptions) => {
    // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
    const origin = additionalOptions?._event_?.origin;
    const registeredDataSet = publishedDataSets.find((s) => s.id === data.id);

    if (!registeredDataSet) {
        return null;
    }

    if (registeredDataSet.deprecated) {
        const extension = Object.values(Shopware.Store.get('extensions').extensionsState).find((ext) =>
            ext.baseUrl.startsWith(additionalOptions._event_.origin),
        );

        if (!extension) {
            throw new Error(`Extension with the origin "${additionalOptions._event_.origin}" not found.`);
        }

        const debugArgs = [
            'CORE',
            // eslint-disable-next-line max-len
            `The extension "${extension.name}" uses a deprecated data set "${data.id}". ${registeredDataSet.deprecationMessage}`,
        ];
        // @ts-expect-error
        if (process.env !== 'prod') {
            Shopware.Utils.debug.error(...debugArgs);
        } else {
            Shopware.Utils.debug.warn(...debugArgs);
        }
    }

    const selectors = data.selectors;

    if (!selectors) {
        return registeredDataSet.data;
    }

    // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
    const clonedData = deepCloneWithEntity(registeredDataSet.data);

    // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
    const selectedData = selectData(clonedData, selectors, 'datasetGet', origin);

    if (selectedData instanceof MissingPrivilegesError) {
        console.error(selectedData);
    }

    return selectedData;
});

/**
 * Splits an object path like "foo.bar.buz" to "{ pathToLastSegment: 'foo.bar', lastSegment: 'buz' }".
 */
function parsePath(path: string): ParsedPath | null {
    if (!path.includes('.')) {
        return null;
    }

    const properties = path.split('.');
    const lastSegment = properties.pop();
    const pathToLastSegment = properties.join('.');

    if (lastSegment && lastSegment.length && pathToLastSegment && pathToLastSegment.length) {
        return {
            pathToLastSegment,
            lastSegment,
        };
    }

    return null;
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function publishData({
    id,
    path,
    scope,
    deprecated,
    deprecationMessage,
    showDoubleRegistrationError = true,
}: publishOptions): () => void {
    if (unregisterPublishDataIds.includes(id)) {
        unregisterPublishDataIds = unregisterPublishDataIds.filter((value) => value !== id);
    }
    const registeredDataSet = publishedDataSets.find((s) => s.id === id);

    // Dataset registered from different scope? Prevent update.
    if (registeredDataSet && registeredDataSet.scope !== scope?.$?.uid) {
        if (showDoubleRegistrationError) {
            console.error(`The dataset id "${id}" you tried to publish is already registered.`);
        }

        return () => {};
    }

    // Dataset registered from same scope? Update.
    if (registeredDataSet && registeredDataSet.scope === scope?.$?.uid) {
        // eslint-disable-next-line @typescript-eslint/no-empty-function
        register({ id: id, data: get(scope, path) }).catch(() => {});

        return () => {};
    }

    // Create updateSubscriber which maps back changes from the app to Vue
    updateSubscriber(id, (value) => {
        // Null updates are not allowed
        if (!value) {
            return;
        }

        function setObject(transferObject: transferObject, prePath: string | null = null): void {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call
            if (typeof transferObject?.getIsDirty === 'function' && !transferObject.getIsDirty()) {
                return;
            }

            Object.keys(transferObject).forEach((property) => {
                let realPath: string;
                if (prePath) {
                    realPath = `${prePath}.${property}`;
                } else {
                    realPath = `${path}.${property}`;
                }

                const parsedPath = parsePath(realPath);
                if (parsedPath === null) {
                    return;
                }

                if (
                    // @ts-expect-error
                    Shopware.Utils.hasOwnProperty(transferObject[property], 'getDraft', this) &&
                    // @ts-expect-error
                    typeof transferObject[property].getDraft === 'function'
                ) {
                    setObject(
                        {
                            [property]: Shopware.Utils.object.cloneDeep(transferObject[property]),
                        },
                        realPath,
                    );

                    return;
                }

                if (Array.isArray(transferObject[property])) {
                    (transferObject[property] as Array<unknown>).forEach((c, index) => {
                        setObject({ [index]: c }, realPath);
                    });

                    return;
                }

                // eslint-disable-next-line max-len,@typescript-eslint/no-unsafe-member-access
                Shopware.Utils.object.get(scope, parsedPath.pathToLastSegment)[parsedPath.lastSegment] =
                    transferObject[property];
            });
        }

        // @ts-expect-error
        if (typeof value.data?.getDraft === 'function') {
            setObject(value.data as transferObject);

            return;
        }

        if (Array.isArray(value.data)) {
            value.data.forEach((entry, index) => {
                if (entry === null || typeof entry !== 'object') {
                    return;
                }

                setObject({ [index]: entry as unknown });
            });
        } else if (typeof value.data === 'object') {
            setObject(value.data as transferObject);

            return;
        }

        // Vue.set does not resolve path's therefore we need to resolve to the last child property
        if (path.includes('.')) {
            const properties = path.split('.');
            const lastPath = properties.pop();
            const newPath = properties.join('.');
            if (!lastPath) {
                return;
            }

            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
            Shopware.Utils.object.get(scope, newPath)[lastPath] = value.data;

            return;
        }

        // @ts-expect-error
        scope[path] = value.data;
    });

    // Watch for Changes on the Reactive Vue property and automatically publish them
    const unwatch = scope.$watch(
        path,
        debounce((value: App<Element>) => {
            if (unregisterPublishDataIds.includes(id)) {
                unregisterPublishDataIds = unregisterPublishDataIds.filter((v) => v !== id);
                unwatch();

                return;
            }

            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
            const clonedValue = deepCloneWithEntity(value);

            // eslint-disable-next-line @typescript-eslint/no-empty-function
            register({ id: id, data: clonedValue }).catch(() => {});

            const dataSet = publishedDataSets.find((set) => set.id === id);
            if (dataSet) {
                dataSet.data = value;

                return;
            }

            publishedDataSets.push({
                id,
                data: clonedValue,
                scope: scope?.$?.uid,
                deprecated,
                deprecationMessage,
            });
        }, 750),
        {
            deep: true,
            immediate: true,
        },
    );

    // @ts-expect-error - Defined in meteor-sdk-data.plugin.ts
    // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
    scope.dataSetUnwatchers.push(() => {
        publishedDataSets = publishedDataSets.filter((value) => value.id !== id);
        unregisterPublishDataIds.push(id);

        unwatch();
    });

    // eslint-disable-next-line @typescript-eslint/no-empty-function
    register({ id: id, data: get(scope, path) }).catch(() => {});

    // Return method to manually deregister the dataset
    return function unregisterPublishData() {
        publishedDataSets = publishedDataSets.filter((value) => value.id !== id);
        unregisterPublishDataIds.push(id);

        unwatch();
    };
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function getPublishedDataSets(): dataset[] {
    return publishedDataSets;
}
