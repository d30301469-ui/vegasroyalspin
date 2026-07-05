/**
 * Slot karti yildizi: game_id (data-game-id) ile favori API.
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

    function toastWarn(msg) {
        if (window.MaltabetToast) MaltabetToast.warning(msg);
        else window.alert(msg);
    }

    function toastOk(msg) {
        if (window.MaltabetToast) MaltabetToast.success(msg);
    }

    function openLoginModal() {
        if (typeof window.__openLoginModal === "function") {
            window.__openLoginModal();
            return;
        }
        if (window.MaltabetAuth && typeof window.MaltabetAuth.showLoginModal === "function") {
            window.MaltabetAuth.showLoginModal();
            return;
        }
        var loginBtn = document.getElementById("Giris");
        if (loginBtn) {
            loginBtn.click();
        }
    }

    function extractGameId(item) {
        if (!item) {
            return "";
        }
        var direct = (item.getAttribute("data-game-id") || item.getAttribute("data-catalog-id") || "").trim();
        if (direct) {
            return direct;
        }
        var onclick = item.getAttribute("onclick") || "";
        var m = onclick.match(/handlePlay\((['"]?)([^'")]+)\1\)/);
        return m && m[2] ? String(m[2]).trim() : "";
    }

    function resolveFavoriteKind(item) {
        if (item) {
            var fromItem = (item.getAttribute("data-favorite-kind") || "").trim().toLowerCase();
            if (fromItem) {
                return fromItem;
            }
        }
        var cfg = window.SLOT_CONFIG || {};
        var gameType = cfg.gameType != null ? parseInt(String(cfg.gameType), 10) : 0;
        var source = cfg.apiParams && cfg.apiParams.source ? String(cfg.apiParams.source).toLowerCase() : "";
        if (source === "bgaming") {
            return "bgaming";
        }
        if (gameType === 1) {
            return "live";
        }
        return "slot";
    }

    function favoriteApiBase(kind) {
        if (kind === "bgaming") {
            return null;
        }
        if (kind === "live") {
            return "/api/v2/favorite-live-casino";
        }
        return "/api/v2/favorite-slots";
    }

    function setFavoriteVisual(fav, on) {
        var icon = fav.matches("i") ? fav : fav.querySelector("i");
        fav.classList.toggle("is-favorite", on);
        if (icon) {
            icon.classList.toggle("is-favorite", on);
            if (icon.classList.contains("far") || icon.classList.contains("fas")) {
                icon.classList.toggle("far", !on);
                icon.classList.toggle("fas", on);
            }
        }
    }

    document.addEventListener("click", function (e) {
        var fav = e.target.closest(".game-fav, .casinoGameItemFavBc");
        if (!fav) {
            return;
        }
        var item = fav.closest(".game-item, .game-cta, .casinoGameItemContent");
        if (!item) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        if (!loggedIn()) {
            openLoginModal();
            return;
        }
        var kind = resolveFavoriteKind(item);
        var apiBase = favoriteApiBase(kind);
        if (!apiBase) {
            toastWarn("BGaming oyunlarÄ± iÃ§in favori henÃ¼z desteklenmiyor.");
            return;
        }
        var gameId = extractGameId(item);
        if (!gameId) {
            toastWarn("Bu oyun iÃ§in katalog kimliÄŸi yok; favori eklenemiyor.");
            return;
        }
        var isFav = fav.classList.contains("is-favorite");

        if (isFav) {
            fetch(appendQuery(apiUrl(apiBase), "game_id=" + encodeURIComponent(gameId)), {
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
                        setFavoriteVisual(fav, false);
                        document.dispatchEvent(new CustomEvent("favorites:changed", { detail: { kind: kind, game_id: gameId, favorited: false } }));
                    } else {
                        toastWarn((j && j.message) || "Favoriden Ã§Ä±karÄ±lamadÄ±.");
                    }
                })
                .catch(function () {
                    toastWarn("BaÄŸlantÄ± hatasÄ±.");
                });
            return;
        }

        fetch(apiUrl(apiBase), {
            method: "POST",
            credentials: "same-origin",
            headers: memberAuthHeaders({ Accept: "application/json", "Content-Type": "application/json" }),
            cache: "no-store",
            body: JSON.stringify({ game_id: gameId }),
        })
            .then(function (r) {
                return r.json();
            })
            .then(function (j) {
                if (j && j.success) {
                    setFavoriteVisual(fav, true);
                    if (!j.data || !j.data.already_favorite) {
                        toastOk("Favorilere eklendi.");
                    }
                    document.dispatchEvent(new CustomEvent("favorites:changed", { detail: { kind: kind, game_id: gameId, favorited: true } }));
                } else {
                    toastWarn((j && j.message) || "Favori eklenemedi.");
                }
            })
            .catch(function () {
                toastWarn("BaÄŸlantÄ± hatasÄ±.");
            });
    }, true);
})();
