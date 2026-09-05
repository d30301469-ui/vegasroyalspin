/**
 * Desktop shell navigation — header/footer sabit, yalnızca #shellPageHost değişir.
 */
(function (w, d) {
    'use strict';

    if (w.__ShellNavInitialized || d.body.classList.contains('mobile-site')) {
        return;
    }
    w.__ShellNavInitialized = true;

    var LOADING_CLASS = 'is-shell-nav-loading';
    var NAV_PATHS = {
        '/': true,
        '/sportbook': true,
        '/sportsbook': true,
        '/slot': true,
        '/livecasino': true,
        '/bgaming': true,
        '/turnuvalar': true,
        '/promotions': true,
        '/promosyonlar': true,
        '/beni-ara': true
    };

    function normalizePath(path) {
        if (!path || path.charAt(0) !== '/') {
            return '';
        }
        var clean = path.split('?')[0].split('#')[0].replace(/\/+$/, '');
        return clean === '' ? '/' : clean;
    }

    function canShellNavigate(href) {
        try {
            var url = new URL(href, w.location.origin);
            if (url.origin !== w.location.origin) {
                return false;
            }
            var path = normalizePath(url.pathname);
            return !!NAV_PATHS[path];
        } catch (e) {
            return false;
        }
    }

    function ensureShellHost() {
        var host = d.getElementById('shellPageHost');
        if (host) {
            return host;
        }
        var wrap = d.querySelector('.mainContentWrap');
        if (!wrap) {
            return null;
        }
        host = d.createElement('div');
        host.id = 'shellPageHost';
        host.setAttribute('data-shell-path', normalizePath(w.location.pathname));
        var persist = [];
        ['#registerModal', '#login2', '#mobileMenu', '#mobileMenu-overlay', '.mobile-bottom-bar-bc'].forEach(function (sel) {
            var node = wrap.querySelector(sel);
            if (node) {
                persist.push(node);
            }
        });
        var nodes = Array.prototype.slice.call(wrap.childNodes);
        nodes.forEach(function (node) {
            if (node.nodeType === 1) {
                var id = node.id || '';
                if (id === 'registerModal' || id === 'login2' || id === 'mobileMenu' || id === 'mobileMenu-overlay') {
                    return;
                }
                if (node.classList && node.classList.contains('mobile-bottom-bar-bc')) {
                    return;
                }
            }
            host.appendChild(node);
        });
        wrap.appendChild(host);
        return host;
    }

    function mergeStyles(fromDoc) {
        if (!fromDoc) {
            return;
        }
        fromDoc.querySelectorAll('link[rel="stylesheet"][href]').forEach(function (link) {
            var href = link.getAttribute('href');
            if (!href) {
                return;
            }
            var exists = d.querySelector('link[rel="stylesheet"][href="' + href.replace(/"/g, '\\"') + '"]');
            if (exists) {
                return;
            }
            var clone = d.createElement('link');
            clone.rel = 'stylesheet';
            clone.href = href;
            d.head.appendChild(clone);
        });
        fromDoc.querySelectorAll('style').forEach(function (styleEl) {
            if (!styleEl.textContent || !styleEl.textContent.trim()) {
                return;
            }
            var marker = styleEl.getAttribute('data-shell-style');
            if (marker && d.querySelector('style[data-shell-style="' + marker + '"]')) {
                return;
            }
            var clone = d.createElement('style');
            if (marker) {
                clone.setAttribute('data-shell-style', marker);
            }
            clone.textContent = styleEl.textContent;
            d.head.appendChild(clone);
        });
    }

    function runScripts(root) {
        var scripts = root.querySelectorAll('script');
        scripts.forEach(function (oldScript) {
            var script = d.createElement('script');
            Array.prototype.slice.call(oldScript.attributes).forEach(function (attr) {
                script.setAttribute(attr.name, attr.value);
            });
            if (oldScript.src) {
                script.src = oldScript.src;
            } else {
                script.textContent = oldScript.textContent;
            }
            oldScript.parentNode.replaceChild(script, oldScript);
        });
    }

    function extractShellHtml(html, fallbackDoc) {
        var doc = fallbackDoc;
        if (!doc) {
            doc = new DOMParser().parseFromString(html, 'text/html');
        }
        var host = doc.getElementById('shellPageHost');
        if (host) {
            return { doc: doc, html: host.outerHTML };
        }
        var slotRoot = doc.querySelector('.slot-page-root');
        if (slotRoot) {
            return { doc: doc, html: '<div id="shellPageHost" data-shell-path="">' + slotRoot.outerHTML + '</div>' };
        }
        var sport = doc.querySelector('#sbStage, .sportbook-stage');
        if (sport) {
            var sportHtml = sport.outerHTML;
            var sportScripts = doc.querySelectorAll('main.sportbook-stage ~ script, #sbStage ~ script');
            sportScripts.forEach(function (s) { sportHtml += s.outerHTML; });
            return { doc: doc, html: '<div id="shellPageHost" data-shell-path="/sportbook">' + sportHtml + '</div>' };
        }
        var home = doc.querySelector('.layout-content-holder-bc');
        if (home) {
            var homeHtml = home.outerHTML;
            doc.querySelectorAll('script[src*="home.js"], script[src*="jackpot.js"], script[src*="winners.js"]').forEach(function (s) {
                homeHtml += s.outerHTML;
            });
            return { doc: doc, html: '<div id="shellPageHost" data-shell-path="/">' + homeHtml + '</div>' };
        }
        return null;
    }

    function setLoading(isLoading) {
        var host = d.getElementById('shellPageHost');
        if (host) {
            host.classList.toggle(LOADING_CLASS, isLoading);
            host.setAttribute('aria-busy', isLoading ? 'true' : 'false');
        }
    }

    function updateActiveNav(path) {
        var menu = d.querySelector('.mainMenu');
        if (!menu) {
            return;
        }
        menu.querySelectorAll('a.active, li.active').forEach(function (el) {
            el.classList.remove('active');
        });
        var links = menu.querySelectorAll('a[href]');
        var best = null;
        var bestScore = 0;
        for (var i = 0; i < links.length; i++) {
            var linkPath = normalizePath(new URL(links[i].getAttribute('href'), w.location.origin).pathname);
            var score = path === linkPath ? linkPath.length + 1000
                : (linkPath !== '/' && path.indexOf(linkPath + '/') === 0 ? linkPath.length : 0);
            if (score > bestScore) {
                bestScore = score;
                best = links[i];
            }
        }
        if (!best) {
            return;
        }
        best.classList.add('active');
        var li = best.closest('li');
        if (li) {
            li.classList.add('active');
        }
    }

    var inflight = null;

    function navigateShell(url, push) {
        if (inflight) {
            return inflight;
        }
        var host = ensureShellHost();
        if (!host) {
            w.location.href = url;
            return Promise.resolve();
        }

        setLoading(true);
        inflight = fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'X-Shell-Nav': '1',
                'Accept': 'text/html'
            },
            cache: 'no-cache'
        }).then(function (res) {
            if (!res.ok) {
                throw new Error('shell nav failed');
            }
            return res.text();
        }).then(function (html) {
            var parsed = extractShellHtml(html);
            if (!parsed || !parsed.html) {
                throw new Error('shell fragment missing');
            }
            mergeStyles(parsed.doc);
            host.outerHTML = parsed.html;
            host = d.getElementById('shellPageHost');
            if (!host) {
                throw new Error('shell host missing');
            }
            runScripts(host);
            var path = normalizePath(new URL(url, w.location.origin).pathname);
            host.setAttribute('data-shell-path', path);
            if (push !== false && w.history && w.history.pushState) {
                w.history.pushState({ shellNav: path }, '', url);
            }
            updateActiveNav(path);
            d.title = parsed.doc.title || d.title;
            w.scrollTo(0, 0);
            try {
                d.dispatchEvent(new CustomEvent('shell-nav:loaded', { detail: { url: url, path: path } }));
            } catch (eEvt) { /* ignore */ }
        }).catch(function () {
            w.location.href = url;
        }).finally(function () {
            setLoading(false);
            inflight = null;
        });

        return inflight;
    }

    function onLinkClick(e) {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
            return;
        }
        var link = e.target && e.target.closest ? e.target.closest('.mainMenu a[href]') : null;
        if (!link) {
            return;
        }
        if ((link.getAttribute('target') || '').toLowerCase() === '_blank') {
            return;
        }
        var href = link.getAttribute('href');
        if (!href || !canShellNavigate(href)) {
            return;
        }
        var nextPath = normalizePath(new URL(href, w.location.origin).pathname);
        var currentPath = normalizePath(w.location.pathname);
        if (nextPath === currentPath && !href.includes('?')) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        navigateShell(href, true);
    }

    d.addEventListener('click', onLinkClick, true);

    w.addEventListener('popstate', function (e) {
        if (e.state && e.state.shellNav) {
            navigateShell(w.location.pathname + w.location.search + w.location.hash, false);
        }
    });

    function prefetchVisibleNav() {
        var menu = d.querySelector('.mainMenu');
        if (!menu) {
            return;
        }
        var seen = Object.create(null);
        menu.querySelectorAll('a[href]').forEach(function (link) {
            var href = link.getAttribute('href');
            if (!href || !canShellNavigate(href) || seen[href]) {
                return;
            }
            seen[href] = true;
            var hint = d.createElement('link');
            hint.rel = 'prefetch';
            hint.href = href;
            hint.as = 'document';
            d.head.appendChild(hint);
        });
    }

    if (typeof w.requestIdleCallback === 'function') {
        w.requestIdleCallback(prefetchVisibleNav, { timeout: 2500 });
    } else {
        w.setTimeout(prefetchVisibleNav, 1500);
    }

    w.__shellNavigate = navigateShell;
})(window, document);
