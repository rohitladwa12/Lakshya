/**
 * Lakshya Global Security Interceptor (Hardened)
 * Automatically attaches CSRF tokens to all fetch and AJAX requests.
 */
(function() {
    console.log("Lakshya Security: Hardening Interceptor...");
    
    const token = (window.CSRF_TOKEN) ? window.CSRF_TOKEN : null;
    
    if (!token) {
        console.error("Lakshya Security: CRITICAL - CSRF_TOKEN not found!");
        return;
    }

    // 1. Intercept 'fetch' using a more robust getter/setter approach
    const nativeFetch = window.fetch;
    
    Object.defineProperty(window, 'fetch', {
        configurable: true,
        enumerable: true,
        get: function() {
            return function(resource, config) {
                console.log("Lakshya Security: Intercepted fetch to", resource);
                
                // If it's a Request object, handle it
                if (resource instanceof Request) {
                    if (resource.method.toUpperCase() === 'POST') {
                        resource.headers.set('X-CSRF-TOKEN', token);
                    }
                    return nativeFetch(resource, config);
                }

                // If it's a URL string
                if (config && config.method && config.method.toUpperCase() === 'POST') {
                    console.log("Lakshya Security: !!! ATTACHED TOKEN TO POST !!!");
                    if (!config.headers) config.headers = {};
                    
                    // Add to Headers
                    if (!(config.headers instanceof Headers)) {
                        config.headers['X-CSRF-TOKEN'] = token;
                    } else {
                        config.headers.set('X-CSRF-TOKEN', token);
                    }

                    // Add to Body
                    if (config.body instanceof FormData) {
                        console.log("Lakshya Security: Appending to FormData");
                        if (!config.body.has('csrf_token')) {
                            config.body.append('csrf_token', token);
                        }
                    } else if (typeof config.body === 'string') {
                        console.log("Lakshya Security: Appending to String Body");
                        if (!config.body.includes('csrf_token=')) {
                            config.body += (config.body ? '&' : '') + 'csrf_token=' + encodeURIComponent(token);
                        }
                    } else if (!config.body) {
                        console.log("Lakshya Security: Creating Body");
                        config.body = 'csrf_token=' + encodeURIComponent(token);
                        if (!(config.headers instanceof Headers)) {
                            config.headers['Content-Type'] = 'application/x-www-form-urlencoded';
                        } else {
                            config.headers.set('Content-Type', 'application/x-www-form-urlencoded');
                        }
                    }
                } else {
                    console.log("Lakshya Security: Non-POST request, bypassing attachment.");
                }
                return nativeFetch(resource, config);
            };
        }
    });

    // 2. Intercept 'XMLHttpRequest'
    const nativeOpen = XMLHttpRequest.prototype.open;
    XMLHttpRequest.prototype.open = function(method, url) {
        this._method = method;
        return nativeOpen.apply(this, arguments);
    };

    const nativeSend = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.send = function(body) {
        if (this._method && this._method.toUpperCase() === 'POST') {
            this.setRequestHeader('X-CSRF-TOKEN', token);
            if (body instanceof FormData) {
                if (!body.has('csrf_token')) body.append('csrf_token', token);
            }
        }
        return nativeSend.apply(this, arguments);
    };

    console.log("Lakshya Security: Interceptor Lock Active.");
})();
