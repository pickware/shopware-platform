/**
 * @sw-package framework
 */
class CacheApiService {
    constructor(httpClient, loginService) {
        this.httpClient = httpClient;
        this.loginService = loginService;
        this.name = 'cacheApiService';
    }

    info() {
        const headers = this.getHeaders();
        return this.httpClient.get('/_action/cache_info', { headers });
    }

    index(skip = [], only = []) {
        const headers = this.getHeaders();
        return this.httpClient.post('/_action/index', { skip, only }, { headers });
    }

    indexProducts(ids, skip) {
        const headers = this.getHeaders();
        return this.httpClient.post('/_action/index-products', { skip, ids: ids }, { headers });
    }

    delayed() {
        const headers = this.getHeaders();
        return this.httpClient.delete('/_action/cache-delayed', { headers });
    }

    clear() {
        const headers = this.getHeaders();
        return this.httpClient.delete('/_action/cache', { headers }).then((response) => {
            if (response.status === 204) {
                return this.httpClient.delete('/_action/container_cache', {
                    headers,
                });
            }
            return Promise.reject();
        });
    }

    cleanupOldCaches() {
        const headers = this.getHeaders();
        return this.httpClient.delete('/_action/cleanup', { headers });
    }

    getHeaders() {
        return {
            Accept: 'application/json',
            Authorization: `Bearer ${this.loginService.getToken()}`,
            'Content-Type': 'application/json',
        };
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default CacheApiService;
