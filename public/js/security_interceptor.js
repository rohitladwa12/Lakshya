/**
 * Lakshya Global Security Interceptor (Hardened)
 * Automatically attaches CSRF tokens to all fetch, AJAX requests, and traditional HTML POST forms.
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
                        const trimmed = config.body.trim();
                        const isJson = trimmed.startsWith('{') || trimmed.startsWith('[');
                        if (isJson) {
                            try {
                                const parsed = JSON.parse(config.body);
                                if (parsed && typeof parsed === 'object') {
                                    parsed.csrf_token = token;
                                    config.body = JSON.stringify(parsed);
                                }
                            } catch (e) {
                                console.warn("Lakshya Security: Failed parsing JSON body to inject csrf_token", e);
                            }
                        } else {
                            if (!config.body.includes('csrf_token=')) {
                                config.body += (config.body ? '&' : '') + 'csrf_token=' + encodeURIComponent(token);
                            }
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

    // 3. Auto-inject into all traditional HTML POST forms (static and dynamic)
    const injectToken = () => {
        document.querySelectorAll('form[method="POST"], form[method="post"]').forEach(form => {
            if (!form.querySelector('input[name="csrf_token"]')) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'csrf_token';
                input.value = token;
                form.appendChild(input);
                console.log("Lakshya Security: Injected CSRF token into form", form);
            }
        });
    };
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', injectToken);
    } else {
        injectToken();
    }
    
    // MutationObserver to watch for dynamic modals/forms being added
    const observer = new MutationObserver((mutations) => {
        let addedForm = false;
        for (const mutation of mutations) {
            for (const node of mutation.addedNodes) {
                if (node.nodeType === Node.ELEMENT_NODE) {
                    if (node.tagName === 'FORM' && node.getAttribute('method')?.toUpperCase() === 'POST') {
                        addedForm = true;
                    } else if (node.querySelector && node.querySelector('form[method="POST"], form[method="post"]')) {
                        addedForm = true;
                    }
                }
            }
        }
        if (addedForm) injectToken();
    });
    observer.observe(document.body || document.documentElement, { childList: true, subtree: true });

    // 4. Intercept programmatic HTMLFormElement.prototype.submit() (fires synchronously before navigation)
    const nativeSubmit = HTMLFormElement.prototype.submit;
    HTMLFormElement.prototype.submit = function() {
        if (this.getAttribute('method')?.toUpperCase() === 'POST' || this.method?.toUpperCase() === 'POST') {
            if (!this.querySelector('input[name="csrf_token"]')) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'csrf_token';
                input.value = token;
                this.appendChild(input);
                console.log("Lakshya Security: Injected CSRF token on programmatic submit() call");
            }
        }
        return nativeSubmit.apply(this, arguments);
    };

    // 5. Intercept standard submit events (fallback for standard buttons/enter submit)
    document.addEventListener('submit', function(event) {
        const form = event.target;
        if (form.getAttribute('method')?.toUpperCase() === 'POST' || form.method?.toUpperCase() === 'POST') {
            if (!form.querySelector('input[name="csrf_token"]')) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'csrf_token';
                input.value = token;
                form.appendChild(input);
                console.log("Lakshya Security: Injected CSRF token on submit event");
            }
        }
    }, true);

    console.log("Lakshya Security: Interceptor Lock Active.");
})();
