/**
 * Smart menü Favoriler: /api/v2/favorite-slots ve /api/v2/favorite-live-casino (oturum çerezi; JWT sunucuda).
 */
(function () {
    var Shared = window.BetcoAuthShared || {};
    function apiUrl(path) {
        return Shared.apiUrl ? Shared.apiUrl(path) : path;
    }
    function appendQuery(url, query) {
        return url + (url.indexOf("?") >= 0 ? "&" : "?") + query;
    }
    function memberAuthHeaders(extra) {
        return Shared.memberAuthHeaders ? Shared.memberAuthHeaders(extra) : (function () {
            var h = extra || {};
            var csrf = (window.__CSRF_TOKEN__ || "").trim();
            if (csrf) h["X-CSRF-Token"] = csrf;
            return h;
        })();
    }
    function loggedIn() {
        return !!window.__USER_LOGGED_IN__;
    }

    function getDrawerRoot() {
        return document.getElementById("favoritesDrawer");
    }

    function getEls() {
        return {
            guest: document.getElementById("favoritesGuestMsg"),
            tabs: document.querySelector(".favorites-sidebar__tabs"),
            body: document.getElementById("favoritesSidebarBody"),
            slotList: document.getElementById("favoritesSlotList"),
            slotEmpty: document.getElementById("favoritesSlotEmpty"),
            slotLoad: document.getElementById("favoritesSlotLoading"),
            slotErr: document.getElementById("favoritesSlotError"),
            liveList: document.getElementById("favoritesLiveList"),
            liveEmpty: document.getElementById("favoritesLiveEmpty"),
            liveLoad: document.getElementById("favoritesLiveLoading"),
            liveErr: document.getElementById("favoritesLiveError"),
        };
    }

    function setCount(kind, n) {
        document.querySelectorAll('[data-favorites-count="' + kind + '"]').forEach(function (el) {
            el.textContent = "(" + n + ")";
        });
    }

    function escapeHtml(s) {
        var d = document.createElement("div");
        d.textContent = s;
        return d.innerHTML;
    }

    function gameId(g) {
        if (!g || g.game_id == null) {
            return "";
        }
        var s = String(g.game_id).trim();
        return s === "" ? "" : s;
    }

    function showGuestMode(on) {
        var el = getEls();
        var drawer = getDrawerRoot();
        if (!drawer) {
            return;
        }
        if (el.guest) {
            el.guest.hidden = !on;
        }
        if (el.tabs) {
            el.tabs.hidden = on;
        }
        if (el.body) {
            el.body.hidden = on;
        }
    }

    function fetchJson(url, options) {
        return fetch(url, options || {}).then(function (r) {
            return r.json().then(function (j) {
                return { ok: r.ok, status: r.status, json: j };
            });
        });
    }

    function renderRows(ul, games, kind) {
        ul.innerHTML = "";
        if (!games || !games.length) {
            return;
        }
        games.forEach(function (g) {
            var gid = gameId(g);
            var code = String(g.game_id != null ? g.game_id : "");
            var name = escapeHtml(String(g.name || g.game_name || "Oyun"));
            var img = String(g.image_url || g.cover || "");
            var li = document.createElement("li");
            li.className = "favorites-game-row";
            var thumb = document.createElement("div");
            thumb.className = "favorites-game-row__thumb";
            if (img) {
                var im = document.createElement("img");
                im.src = img;
                im.alt = "";
                im.loading = "lazy";
                im.onerror = function () {
                    this.style.display = "none";
                };
                thumb.appendChild(im);
            }
            var meta = document.createElement("div");
            meta.className = "favorites-game-row__meta";
            var prov = g.provider != null ? "<span class=\"favorites-game-row__provider\">" + escapeHtml(String(g.provider)) + "</span>" : "";
            meta.innerHTML = "<span class=\"favorites-game-row__name\">" + name + "</span>" + prov;
            var actions = document.createElement("div");
            actions.className = "favorites-game-row__actions";
            if (code) {
                var play = document.createElement("a");
                play.className = "favorites-game-row__play";
                play.href = "/play?game_id=" + encodeURIComponent(code) + "&mode=real&wallet=main";
                play.textContent = "Oyna";
                actions.appendChild(play);
            }
            if (gid) {
                var rm = document.createElement("button");
                rm.type = "button";
                rm.className = "favorites-game-row__remove";
                rm.setAttribute("data-fav-remove", kind);
                rm.setAttribute("data-game-id", gid);
                rm.setAttribute("aria-label", "Favorilerden çıkar");
                rm.textContent = "Kaldır";
                actions.appendChild(rm);
            }
            li.appendChild(thumb);
            li.appendChild(meta);
            li.appendChild(actions);
            ul.appendChild(li);
        });
    }

    function updateSlotUI(games, pagination, errText) {
        var el = getEls();
        if (!el.slotList) {
            return;
        }
        if (el.slotLoad) {
            el.slotLoad.hidden = true;
        }
        if (el.slotErr) {
            el.slotErr.hidden = !errText;
            el.slotErr.textContent = errText || "";
        }
        var n = games && games.length ? games.length : 0;
        var total = pagination && pagination.total != null ? Number(pagination.total) : n;
        setCount("slot", total);
        renderRows(el.slotList, games || [], "slot");
        if (el.slotEmpty) {
            el.slotEmpty.hidden = !!(games && games.length) || !!errText;
        }
    }

    function updateLiveUI(games, pagination, errText) {
        var el = getEls();
        if (!el.liveList) {
            return;
        }
        if (el.liveLoad) {
            el.liveLoad.hidden = true;
        }
        if (el.liveErr) {
            el.liveErr.hidden = !errText;
            el.liveErr.textContent = errText || "";
        }
        var n = games && games.length ? games.length : 0;
        var total = pagination && pagination.total != null ? Number(pagination.total) : n;
        setCount("live", total);
        renderRows(el.liveList, games || [], "live");
        if (el.liveEmpty) {
            el.liveEmpty.hidden = !!(games && games.length) || !!errText;
        }
    }

    function loadSlotList() {
        var el = getEls();
        if (!el.slotList || !loggedIn()) {
            return;
        }
        if (el.slotLoad) {
            el.slotLoad.hidden = false;
        }
        if (el.slotErr) {
            el.slotErr.hidden = true;
        }
        fetchJson(appendQuery(apiUrl("/api/v2/favorite-slots"), "page=1&limit=50"), {
            credentials: "same-origin",
            headers: memberAuthHeaders({ Accept: "application/json" }),
            cache: "no-store",
        })
            .then(function (res) {
                var j = res.json || {};
                if (j.success && j.data) {
                    updateSlotUI(j.data.games, j.data.pagination, "");
                } else {
                    var msg = j.message || "Liste yüklenemedi.";
                    updateSlotUI([], null, msg);
                }
            })
            .catch(function () {
                updateSlotUI([], null, "Bağlantı hatası.");
            });
    }

    function loadLiveList() {
        var el = getEls();
        if (!el.liveList || !loggedIn()) {
            return;
        }
        if (el.liveLoad) {
            el.liveLoad.hidden = false;
        }
        if (el.liveErr) {
            el.liveErr.hidden = true;
        }
        fetchJson(appendQuery(apiUrl("/api/v2/favorite-live-casino"), "page=1&limit=50"), {
            credentials: "same-origin",
            headers: memberAuthHeaders({ Accept: "application/json" }),
            cache: "no-store",
        })
            .then(function (res) {
                var j = res.json || {};
                if (j.success && j.data) {
                    updateLiveUI(j.data.games, j.data.pagination, "");
                } else {
                    var msg = j.message || "Liste yüklenemedi.";
                    updateLiveUI([], null, msg);
                }
            })
            .catch(function () {
                updateLiveUI([], null, "Bağlantı hatası.");
            });
    }

    function fetchCountsOnly() {
        if (!loggedIn()) {
            return;
        }
        fetchJson(appendQuery(apiUrl("/api/v2/favorite-slots"), "page=1&limit=1"), {
            credentials: "same-origin",
            headers: memberAuthHeaders({ Accept: "application/json" }),
            cache: "no-store",
        }).then(function (res) {
            var j = res.json || {};
            if (j.success && j.data && j.data.pagination) {
                setCount("slot", Number(j.data.pagination.total) || 0);
            }
        });
        fetchJson(appendQuery(apiUrl("/api/v2/favorite-live-casino"), "page=1&limit=1"), {
            credentials: "same-origin",
            headers: memberAuthHeaders({ Accept: "application/json" }),
            cache: "no-store",
        }).then(function (res) {
            var j = res.json || {};
            if (j.success && j.data && j.data.pagination) {
                setCount("live", Number(j.data.pagination.total) || 0);
            }
        });
    }

    function getActiveTabName() {
        var t = document.querySelector(".favorites-sidebar__tab.is-active");
        return t ? t.getAttribute("data-favorites-tab") || "" : "";
    }

    function syncPanesVisibility() {
        var tabName = getActiveTabName();
        document.querySelectorAll(".favorites-sidebar__pane").forEach(function (pane) {
            var p = pane.getAttribute("data-favorites-pane");
            var show = p === tabName;
            pane.hidden = !show;
            pane.classList.toggle("is-active", show);
        });
    }

    function loadTab(name) {
        if (!loggedIn()) {
            return;
        }
        if (name === "slot") {
            loadSlotList();
        } else if (name === "live") {
            loadLiveList();
        }
    }

    function onDrawerOpen() {
        if (!getDrawerRoot()) {
            return;
        }
        if (!loggedIn()) {
            showGuestMode(true);
            return;
        }
        showGuestMode(false);
        syncPanesVisibility();
        fetchCountsOnly();
        loadTab(getActiveTabName());
    }

    function onListClick(e) {
        var btn = e.target.closest("[data-fav-remove]");
        if (!btn) {
            return;
        }
        var kind = btn.getAttribute("data-fav-remove");
        var gid = btn.getAttribute("data-game-id");
        if (!gid || (kind !== "slot" && kind !== "live")) {
            return;
        }
        e.preventDefault();
        var endpoint =
            kind === "live"
                ? "/api/v2/favorite-live-casino"
                : "/api/v2/favorite-slots";
        var url = appendQuery(apiUrl(endpoint), "game_id=" + encodeURIComponent(gid));
        fetch(url, {
            method: "DELETE",
            credentials: "same-origin",
            headers: memberAuthHeaders({ Accept: "application/json" }),
            cache: "no-store",
        })
            .then(function (r) {
                return r.json();
            })
            .then(function (j) {
                if (j && j.success) {
                    document.dispatchEvent(new CustomEvent("favorites:changed"));
                    if (kind === "slot") {
                        loadSlotList();
                    } else {
                        loadLiveList();
                    }
                    fetchCountsOnly();
                } else if (window.MaltabetToast) {
                    MaltabetToast.warning((j && j.message) || "Kaldırılamadı.");
                }
            })
            .catch(function () {
                if (window.MaltabetToast) {
                    MaltabetToast.error("Bağlantı hatası.");
                }
            });
    }

    function initListDelegation() {
        var el = getEls();
        if (el.slotList) {
            el.slotList.addEventListener("click", onListClick);
        }
        if (el.liveList) {
            el.liveList.addEventListener("click", onListClick);
        }
    }

    window.FavoritesDrawer = {
        onDrawerOpen: onDrawerOpen,
        loadTab: loadTab,
        refreshCounts: fetchCountsOnly,
    };

    document.addEventListener("favorites:changed", function (event) {
        fetchCountsOnly();
        var detail = (event && event.detail) || {};
        var active = getActiveTabName();
        if (detail.kind === "slot" || active === "slot") {
            loadSlotList();
        }
        if (detail.kind === "live" || active === "live") {
            loadLiveList();
        }
    });

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initListDelegation);
    } else {
        initListDelegation();
    }
})();
