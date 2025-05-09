import {
    createPinia,
    defineStore,
    type Pinia,
    type _GettersTree,
    type DefineStoreOptions,
    type StateTree,
    type StoreDefinition,
} from 'pinia';

/**
 * @sw-package framework
 * @public
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class Store {
    // eslint-disable-next-line no-use-before-define
    static #instance: Store;

    static #stores = new Map<keyof PiniaRootState, StoreDefinition>();

    /**
     * @private - Only to be used by vue.adapter.ts
     */
    _rootState: Pinia;

    private constructor() {
        this._rootState = createPinia();
    }

    /**
     * @private
     */
    public static get instance(): Store {
        if (!Store.#instance) {
            Store.#instance = new Store();
        }

        return Store.#instance;
    }

    /**
     * Returns a list of all registered Pinia store ids.
     */
    public list(): string[] {
        return Array.from(Store.#stores.keys());
    }

    /**
     * Get the Pinia store with the given id.
     */
    public get<Id extends keyof PiniaRootState>(id: Id): PiniaRootState[Id] {
        const piniaStore = Store.#stores.get(id);
        if (!piniaStore) {
            throw new Error(`Store with id "${id}" not found`);
        }

        return piniaStore() as PiniaRootState[Id];
    }

    /**
     * Register a new Pinia store. Works similar like Vuex's registerModule.
     */
    public register = (<
        Id extends keyof PiniaRootState,
        S extends StateTree = NonNullable<unknown>,
        G extends _GettersTree<S> = NonNullable<unknown>,
        A = NonNullable<unknown>,
    >(
        idOrStoreDefinition: string | DefineStoreOptions<Id, S, G, A>,
        storeDefinition?: () => DefineStoreOptions<Id, S, G, A>,
    ) => {
        let id: Id;
        let definition: DefineStoreOptions<Id, S, G, A> | (() => DefineStoreOptions<Id, S, G, A>);

        if (typeof idOrStoreDefinition === 'string' && storeDefinition !== undefined) {
            id = idOrStoreDefinition as Id;
            definition = storeDefinition;
        } else if (typeof idOrStoreDefinition !== 'string' && typeof idOrStoreDefinition.id === 'string') {
            id = idOrStoreDefinition.id;
            definition = idOrStoreDefinition;
        } else {
            throw new Error('Invalid arguments registering a Store');
        }

        const store = defineStore(id, definition as DefineStoreOptions<Id, S, G, A>);
        // Cache the store in internal map
        // @ts-expect-error - Pinia type includes internals, which we don't want to mirror here because of stability
        Store.#stores.set(id, store);
        return store;
    }) as typeof defineStore;

    /**
     * Unregister a Pinia store. Works similar like Vuex's unregisterModule.
     */
    public unregister(id: keyof PiniaRootState): void {
        const piniaStore = Store.#stores.get(id);
        if (!piniaStore) {
            return;
        }

        // Stop reactive effects
        piniaStore().$dispose();

        // Delete store in root state
        delete this._rootState.state.value[piniaStore.$id];

        // Clear cached store
        Store.#stores.delete(id);
    }

    /**
     * @private
     * Clear all registered Pinia stores.
     * This is needed for testing purposes.
     */
    public clear(): void {
        Array.from(Store.#stores.keys()).forEach(Store.#instance.unregister.bind(Store.#instance));
    }
}
