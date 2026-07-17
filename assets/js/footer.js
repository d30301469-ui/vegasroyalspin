// Header ve mobil menü ile ilgili tüm JS fonksiyonları
(function () {
    var Shared = window.BetcoAuthShared || {};
    function isLoggedInUser() {
        if (window.__USER_LOGGED_IN__ === true) {
            return true;
        }
        if (document.body && document.body.classList) {
            return document.body.classList.contains('hdr-auth-user');
        }
        return false;
    }
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
    /** Üye gelen kutusu: /api/v2/member-inbox-messages + localStorage ile okunmamış rozeti. */
    window.MemberInboxBadges = window.MemberInboxBadges || (function () {
        var API = apiUrl("/api/v2/member-inbox-messages");
        var LS = "member_inbox_read_v1";
        function readMap() {
            try {
                var o = JSON.parse(localStorage.getItem(LS) || "{}");
                return o && typeof o === "object" ? o : {};
            } catch (e) {
                return {};
            }
        }
        function unreadCount(messages) {
            var m = readMap();
            var n = 0;
            for (var i = 0; i < messages.length; i++) {
                var msg = messages[i];
                if (!msg) continue;
                var id = String(msg.id != null ? msg.id : "");
                var u = String(msg.updated_at != null ? msg.updated_at : "");
                var stored = m[id];
                if (!stored || stored !== u) n++;
            }
            return n;
        }
        function setSmartBadges(count) {
            document.querySelectorAll(".smart-panel-messages-entry .sp-badge").forEach(function (badge) {
                badge.setAttribute("data-badge", count > 0 ? String(count) : "");
            });
        }
        function setProfileSidebarBadges(count) {
            var t = count > 0 ? String(count) : "";
            document.querySelectorAll(".js-profile-inbox-unread").forEach(function (el) {
                el.textContent = t;
            });
        }
        return {
            apiUrl: API,
            markRead: function (id, updatedAt) {
                var m = readMap();
                m[String(id)] = String(updatedAt != null ? updatedAt : "");
                try {
                    localStorage.setItem(LS, JSON.stringify(m));
                } catch (e) {}
            },
            isUnread: function (id, updatedAt) {
                var m = readMap();
                var stored = m[String(id)];
                var u = String(updatedAt != null ? updatedAt : "");
                return !stored || stored !== u;
            },
            applyUnreadToDom: function (root) {
                root = root || document;
                if (!root.querySelectorAll) return;
                var self = this;
                root.querySelectorAll(".js-inbox-item").forEach(function (li) {
                    var id = li.getAttribute("data-inbox-id");
                    var u = li.getAttribute("data-inbox-updated") || "";
                    if (self.isUnread(id, u)) li.classList.add("unread");
                    else li.classList.remove("unread");
                });
            },
            syncBadges: function () {
                var self = this;
                return fetch(API, { credentials: "same-origin", headers: memberAuthHeaders({ Accept: "application/json" }) })
                    .then(function (r) {
                        return r.json();
                    })
                    .then(function (json) {
                        var list = [];
                        if (json && json.success && json.data && Array.isArray(json.data.messages)) {
                            list = json.data.messages;
                        }
                        var c = unreadCount(list);
                        setSmartBadges(c);
                        setProfileSidebarBadges(c);
                        return list;
                    })
                    .catch(function () {
                        setSmartBadges(0);
                        setProfileSidebarBadges(0);
                    });
            }
        };
    })();

    // Toastr genel ayarları
    if (window.toastr) {
        toastr.options = {
            closeButton: true,
            debug: false,
            newestOnTop: true,
            progressBar: true,
            positionClass: "toast-top-right",
            preventDuplicates: false,
            onclick: null,
            showDuration: "300",
            hideDuration: "1000",
            timeOut: "5000",
            extendedTimeOut: "1000",
            showEasing: "swing",
            hideEasing: "linear",
            showMethod: "fadeIn",
            hideMethod: "fadeOut"
        };
    }

    function updateTurkeyTime() {
        var turkeyTimeEl = document.getElementById("turkeyTime");
        if (!turkeyTimeEl) return;

        var turkeyTime = new Date().toLocaleTimeString("tr-TR", {
            timeZone: "Europe/Istanbul",
            hour12: false
        });
        turkeyTimeEl.textContent = turkeyTime;
    }

    function redirectToDeposit() {
        // Footer / header deposit butonu ile uyumlu: paneli otomatik aç.
        if (typeof window.__openProfileModalUrl === "function" && window.__openProfileModalUrl("/profile/deposit-withdraw?openDepositPanel=1")) {
            return;
        }
        window.location.href = "/profile/deposit-withdraw?openDepositPanel=1";
    }

    function openGame(gameId) {
        if (!gameId) return;
        window.location.href = "/play?game_id=" + encodeURIComponent(gameId) + "&mode=real&wallet=main";
    }

    function initFooterLanguageDropdown() {
        var codeByLang = { tr: "TUR", en: "ENG", de: "DEU" };
        var flagByLang = {
            tr: "/assets/images/flag/tr.svg",
            en: "/assets/images/flag/gb.svg",
            de: "/assets/images/flag/de.svg"
        };

        function setOpen(wrap, open) {
            if (!wrap) return;
            var trigger = wrap.querySelector(".footerLanguageTrigger");
            var menu = wrap.querySelector(".footerLanguageMenu");
            if (!trigger || !menu) return;
            wrap.classList.toggle("is-open", open);
            trigger.setAttribute("aria-expanded", open ? "true" : "false");
            if (open) menu.removeAttribute("hidden");
            else menu.setAttribute("hidden", "");
        }

        function setActiveLang(wrap, lang) {
            if (!wrap) return;
            var menu = wrap.querySelector(".footerLanguageMenu");
            var codeEl = wrap.querySelector(".footerLanguageCode");
            var flagEl = wrap.querySelector(".footerLanguageFlag");
            if (!menu || !codeEl || !flagEl) return;
            var normalized = (lang || "tr").toLowerCase();
            if (!codeByLang[normalized]) normalized = "tr";
            codeEl.textContent = codeByLang[normalized];
            flagEl.src = flagByLang[normalized];
            flagEl.alt = codeByLang[normalized];

            menu.querySelectorAll(".footerLanguageOption").forEach(function (opt) {
                var isActive = (opt.getAttribute("data-lang") || "").toLowerCase() === normalized;
                opt.classList.toggle("is-active", isActive);
                opt.setAttribute("aria-selected", isActive ? "true" : "false");
            });
        }

        var currentLang = (new URLSearchParams(window.location.search)).get("lang") || "tr";
        document.querySelectorAll(".footerLanguageDropdown").forEach(function (wrap) {
            setActiveLang(wrap, currentLang);
            setOpen(wrap, false);
        });

        if (!window.__footerLanguageDelegatedBound) {
            document.addEventListener("click", function (e) {
                var trigger = e.target.closest(".footerLanguageTrigger");
                if (trigger) {
                    e.preventDefault();
                    e.stopPropagation();
                    var wrap = trigger.closest(".footerLanguageDropdown");
                    var willOpen = !(wrap && wrap.classList.contains("is-open"));
                    document.querySelectorAll(".footerLanguageDropdown.is-open").forEach(function (openWrap) {
                        if (openWrap !== wrap) setOpen(openWrap, false);
                    });
                    setOpen(wrap, willOpen);
                    return;
                }

                var option = e.target.closest(".footerLanguageOption");
                if (option) {
                    var wrap = option.closest(".footerLanguageDropdown");
                    var lang = (option.getAttribute("data-lang") || "tr").toLowerCase();
                    setActiveLang(wrap, lang);
                    setOpen(wrap, false);
                    return;
                }

                document.querySelectorAll(".footerLanguageDropdown.is-open").forEach(function (openWrap) {
                    if (!openWrap.contains(e.target)) setOpen(openWrap, false);
                });
            });
            window.__footerLanguageDelegatedBound = true;
        }
    }

    function bonusKoduKullan() {
        if (typeof Swal === "undefined") {
            console.error("SweetAlert2 (Swal) yüklü değil.");
            return;
        }

        Swal.fire({
            title: "Bonus Kodunuzu Girin",
            input: "text",
            inputLabel: "Bonus Kodu",
            inputPlaceholder: "Kodu buraya girin",
            showCancelButton: true,
            confirmButtonText: "Kullan",
            cancelButtonText: "İptal"
        }).then(function (result) {
            if (!result.isConfirmed) return;

            var kod = result.value;
            fetch(apiUrl("/api/v2/bonus/use-code"), {
                method: "POST",
                credentials: "same-origin",
                headers: memberAuthHeaders({
                    "Content-Type": "application/json",
                    Accept: "application/json"
                }),
                body: JSON.stringify({ kod: kod })
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    var msg = data.mesaj || data.message || "İşlem tamamlanamadı.";
                    if (data.status === "success") {
                        window.MaltabetToast ? MaltabetToast.success(msg, "Başarılı") : alert(msg);
                    } else {
                        window.MaltabetToast ? MaltabetToast.error(msg, "Hata") : alert(msg);
                    }
                })
                .catch(function (error) {
                    if (window.MaltabetToast) MaltabetToast.error("Hata oluştu, lütfen tekrar deneyin.", "Hata");
                    else alert("Hata oluştu, lütfen tekrar deneyin.");
                    console.error("Error:", error);
                });
        });
    }

    function initHeaderScripts() {
        function isLoggedInUser() {
            if (typeof window.__USER_LOGGED_IN__ === "boolean") {
                return window.__USER_LOGGED_IN__;
            }
            if (document.body && document.body.classList) {
                if (document.body.classList.contains("hdr-auth-user")) return true;
                if (document.body.classList.contains("hdr-auth-guest")) return false;
            }
            return false;
        }

        function openLoginModalFor(nextPath) {
            var nextEl = document.getElementById("loginFormNext");
            if (nextEl) {
                nextEl.value = nextPath || "/";
            }
            if (typeof window.__openLoginModal === "function") {
                window.__openLoginModal();
                return true;
            }
            if (window.MaltabetAuth && typeof window.MaltabetAuth.showLoginModal === "function") {
                window.MaltabetAuth.showLoginModal();
                return true;
            }
            var loginBtn = document.getElementById("Giris");
            if (loginBtn && typeof loginBtn.click === "function") {
                loginBtn.click();
                return true;
            }
            return false;
        }

        // Kullanıcı menüsü: toggle davranışı header.js + profile modal (__openProfileModalInitial); çift dinleyici kaldırıldı.
        var bonusRequestLink = document.getElementById("bonusRequestLink");
        var bonusModal = document.getElementById("custome-promotion");
        var closeButton = bonusModal ? bonusModal.querySelector(".close") : null;

        if (bonusRequestLink && bonusModal) {
            bonusRequestLink.addEventListener("click", function (event) {
                event.preventDefault();
                bonusModal.style.display = "block";
                bonusModal.classList.add("show");
                bonusModal.setAttribute("aria-hidden", "false");
            });
        }

        if (closeButton && bonusModal) {
            closeButton.addEventListener("click", function () {
                bonusModal.style.display = "none";
                bonusModal.classList.remove("show");
                bonusModal.setAttribute("aria-hidden", "true");
            });

            window.addEventListener("click", function (event) {
                if (event.target === bonusModal) {
                    bonusModal.style.display = "none";
                    bonusModal.classList.remove("show");
                    bonusModal.setAttribute("aria-hidden", "true");
                }
            });
        }

        // Smart panel — YENİ YAPI: sağ taraftan kayan sabit panel (header.js yönetir)
        // footer.js sadece sp-button tıklamalarını right-sidebar ile köprüler.
        function closeNewSmartPanel() {
            if (typeof window.__closeSmartPanel === "function") {
                window.__closeSmartPanel();
            }
            var panel  = document.getElementById("smartPanelFixed");
            var toggle = document.getElementById("smart-panel-holder");
            var arrowHolder = document.querySelector(".hdr-smart-panel-holder-arrow-bc");
            if (panel)  { panel.classList.remove("is-open");  panel.setAttribute("aria-hidden", "true"); }
            if (toggle) { toggle.classList.remove("is-open"); toggle.setAttribute("aria-expanded", "false"); }
            if (arrowHolder) {
                arrowHolder.classList.remove("is-open");
                arrowHolder.style.display = "";
            }
        }

        function handleSmartPanelAction(event, openFn) {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
            closeNewSmartPanel();
            if (typeof openFn === "function") openFn();
        }

        var notificationBtn = document.getElementById("smart-panel-notification-btn");
        if (notificationBtn) {
            notificationBtn.addEventListener("click", function (event) {
                handleSmartPanelAction(event, function () { openRightSidebar("notification"); });
            }, true);
        }
        var favoritesBtn = document.getElementById("smart-panel-favorites-btn");
        if (favoritesBtn) {
            favoritesBtn.addEventListener("click", function (event) {
                handleSmartPanelAction(event, function () { openRightSidebar("favorites"); });
            }, true);
        }
        var settingsBtn = document.getElementById("smart-panel-settings-btn");
        if (settingsBtn) {
            settingsBtn.addEventListener("click", function (event) {
                handleSmartPanelAction(event, function () { openRightSidebar("settings"); });
            }, true);
        }
        var betHistoryBtn = document.getElementById("smart-panel-bet-history-btn");
        if (betHistoryBtn) {
            betHistoryBtn.addEventListener("click", function (event) {
                handleSmartPanelAction(event, function () {
                    if (typeof window.__openProfileModalUrl === "function" && window.__openProfileModalUrl("/profile/bet-history")) {
                        return;
                    }
                    window.location.href = "/profile/bet-history";
                });
            }, true);
        }
        var betslipSmartBtn = document.getElementById("smart-panel-betslip-btn");
        document.querySelectorAll(".smart-panel-messages-entry").forEach(function (msgLink) {
            msgLink.addEventListener("click", function () { closeNewSmartPanel(); }, true);
        });
        document.querySelectorAll('.hdr-smart-panel-holder-bc a[data-nav-mode="page"], .hdr-smart-panel-holder-bc a[href="/promotions"], .hdr-smart-panel-holder-bc a[href="/promosyonlar"]').forEach(function (promoLink) {
            promoLink.addEventListener("click", function () { closeNewSmartPanel(); }, true);
        });
        document.querySelectorAll('.hdr-smart-panel-holder-bc a[data-nav-mode="modal"]').forEach(function (modalLink) {
            modalLink.addEventListener("click", function () { closeNewSmartPanel(); }, true);
        });

        document.querySelectorAll('a[href="/sportbook"], a[href="/sportsbook"]').forEach(function (sportsbookLink) {
            sportsbookLink.addEventListener("click", function () {
                closeNewSmartPanel();
            }, true);
        });

        // Right sidebar (genel: bildirim, favoriler vb.)
        var rightSidebarOverlay = document.getElementById("rightSidebarOverlay");
        var rightSidebarPanels = document.querySelectorAll(".right-sidebar[data-right-sidebar]");
        var currentOpenSidebar = null;

        function updateNotificationDrawerDate() {
            var drawerDate = document.getElementById("notificationDrawerDate");
            if (!drawerDate) return;
            var now = new Date();
            var months = ["Ocak", "Şubat", "Mart", "Nisan", "Mayıs", "Haziran", "Temmuz", "Ağustos", "Eylül", "Ekim", "Kasım", "Aralık"];
            drawerDate.textContent = now.getDate() + " " + months[now.getMonth()] + " " + now.getFullYear();
        }

        var announcementsLoadToken = 0;
        function fetchJsonSafe(url) {
            return fetch(url, { credentials: "same-origin", headers: memberAuthHeaders({ Accept: "application/json" }) })
                .then(function (r) {
                    return r.text().then(function (text) {
                        if (!text) return null;
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            return null;
                        }
                    });
                })
                .catch(function () {
                    return null;
                });
        }

        function fetchFreespinNotificationItems() {
            var aktifUrl = appendQuery(apiUrl("/api/v2/freespins.php"), "tab=aktif");
            var yeniUrl = appendQuery(apiUrl("/api/v2/freespins.php"), "tab=yeni");

            return Promise.all([fetchJsonSafe(aktifUrl), fetchJsonSafe(yeniUrl)]).then(function (responses) {
                var merged = [];
                var seen = {};

                responses.forEach(function (json) {
                    var items = json && json.success && json.data && Array.isArray(json.data.items) ? json.data.items : [];
                    items.forEach(function (row) {
                        var code = row && row.campaign_code != null ? String(row.campaign_code) : '';
                        var status = row && row.status != null ? String(row.status) : 'active';
                        var uniq = code + '::' + status;
                        if (!code || seen[uniq]) {
                            return;
                        }
                        seen[uniq] = true;
                        merged.push(row);
                    });
                });

                return merged;
            });
        }

        function notifyFreespinToast(items) {
            if (!window.MaltabetToast || !Array.isArray(items) || items.length === 0) {
                return;
            }

            var activeCount = items.filter(function (x) {
                var st = String((x && x.status) || '').toLowerCase();
                return st === 'active' || st === 'new';
            }).length;
            if (activeCount <= 0) {
                return;
            }

            var fingerprint = items
                .map(function (x) { return String((x && x.campaign_code) || '') + ':' + String((x && x.status) || ''); })
                .sort()
                .join('|');
            var key = 'metropol_freespin_notice_' + fingerprint;
            try {
                if (sessionStorage.getItem(key) === '1') {
                    return;
                }
                sessionStorage.setItem(key, '1');
            } catch (e) {
                // ignore storage errors
            }

            MaltabetToast.warning(activeCount + ' adet kullanılabilir freespin bulundu. Profil > Casino Freespinleri bölümünü kontrol edin.', 'Freespin Uyarısı');
        }

        function loadAnnouncementsForDrawer() {
            var drawerList = document.getElementById("notificationDrawerList");
            if (!drawerList) return;
            var baseUrl = drawerList.getAttribute("data-announcements-url") || "/api/v2/announcements";
            var url = appendQuery(apiUrl(baseUrl), "action=all");
            var token = ++announcementsLoadToken;
            drawerList.innerHTML = "<p class=\"notification-drawer__loading\" role=\"status\">Yükleniyor…</p>";
            var freespinPromise = isLoggedInUser() ? fetchFreespinNotificationItems() : Promise.resolve([]);
            Promise.all([fetchJsonSafe(url), freespinPromise])
                .then(function (result) {
                    if (token !== announcementsLoadToken) return;
                    var json = result[0];
                    var freespinItems = Array.isArray(result[1]) ? result[1] : [];
                    if (json === null) {
                        drawerList.innerHTML = "";
                        if (freespinItems.length === 0) {
                            var parseErr = document.createElement("p");
                            parseErr.className = "notification-drawer__error";
                            parseErr.setAttribute("role", "alert");
                            parseErr.textContent = "Bildirimler yüklenemedi. Lütfen tekrar deneyin.";
                            drawerList.appendChild(parseErr);
                            updateNotificationBadge(0);
                            return;
                        }
                    }

                    var items = [];
                    if (json && json.success && json.data && Array.isArray(json.data.announcements)) {
                        items = json.data.announcements.filter(function (a) {
                            if (!a) return false;
                            var act = a.is_active;
                            if (act === false || act === 0 || act === "0") return false;
                            return true;
                        });
                    }
                    drawerList.innerHTML = "";
                    items.forEach(function (a) {
                        var id = a.id != null ? String(a.id) : "";
                        var title = (a.title != null ? String(a.title) : "").trim();
                        var bodySrc = a.content != null ? a.content : (a.description != null ? a.description : "");
                        var raw = String(bodySrc).replace(/<[^>]*>/g, " ");
                        var content = raw.replace(/\s+/g, " ").trim();
                        var item = document.createElement("div");
                        item.className = "notification-drawer__item";
                        if (id) item.setAttribute("data-id", id);
                        var icons = document.createElement("span");
                        icons.className = "notification-drawer__icons";
                        icons.innerHTML = "<i class=\"sp-button-icon-bc bc-i-notification\" aria-hidden=\"true\"></i><i class=\"fa-solid fa-star notification-drawer__star\" aria-hidden=\"true\"></i>";
                        var body = document.createElement("div");
                        body.className = "notification-drawer__body";
                        var text = document.createElement("span");
                        text.className = "notification-drawer__text";
                        text.textContent = title || "Duyuru";
                        body.appendChild(text);
                        if (content) {
                            var det = document.createElement("p");
                            det.className = "notification-drawer__detail";
                            det.textContent = content.length > 220 ? content.slice(0, 217) + "\u2026" : content;
                            body.appendChild(det);
                        }
                        var closeBtn = document.createElement("button");
                        closeBtn.type = "button";
                        closeBtn.className = "notification-drawer__item-close";
                        closeBtn.setAttribute("aria-label", "Kaldır");
                        closeBtn.innerHTML = "&times;";
                        item.appendChild(icons);
                        item.appendChild(body);
                        item.appendChild(closeBtn);
                        drawerList.appendChild(item);
                    });

                    freespinItems.forEach(function (row, index) {
                        var campaignCode = row && row.campaign_code != null ? String(row.campaign_code) : '';
                        var status = String((row && row.status) || 'active');
                        var spins = Number((row && row.freespins_per_player) || 0);
                        var game = String((row && row.game_identifier) || '');
                        var detailText = spins + ' spin · Durum: ' + status + (game ? ' · Oyun: ' + game : '');

                        var item = document.createElement("div");
                        item.className = "notification-drawer__item";
                        item.setAttribute("data-id", "freespin-" + index);

                        var icons = document.createElement("span");
                        icons.className = "notification-drawer__icons";
                        icons.innerHTML = "<i class=\"sp-button-icon-bc bc-i-promotions-3\" aria-hidden=\"true\"></i><i class=\"fa-solid fa-star notification-drawer__star\" aria-hidden=\"true\"></i>";

                        var body = document.createElement("div");
                        body.className = "notification-drawer__body";

                        var text = document.createElement("span");
                        text.className = "notification-drawer__text";
                        text.textContent = 'Freespin: ' + (campaignCode || 'Kampanya');
                        body.appendChild(text);

                        var det = document.createElement("p");
                        det.className = "notification-drawer__detail";
                        det.textContent = detailText;
                        body.appendChild(det);

                        var closeBtn = document.createElement("button");
                        closeBtn.type = "button";
                        closeBtn.className = "notification-drawer__item-close";
                        closeBtn.setAttribute("aria-label", "Kaldır");
                        closeBtn.innerHTML = "&times;";

                        item.appendChild(icons);
                        item.appendChild(body);
                        item.appendChild(closeBtn);
                        drawerList.appendChild(item);
                    });

                    updateNotificationBadge(items.length + freespinItems.length);
                    if (isLoggedInUser()) {
                        notifyFreespinToast(freespinItems);
                    }
                })
                .catch(function () {
                    if (token !== announcementsLoadToken) return;
                    drawerList.innerHTML = "";
                    var err = document.createElement("p");
                    err.className = "notification-drawer__error";
                    err.setAttribute("role", "alert");
                    err.textContent = "Duyurular yüklenemedi. Lütfen tekrar deneyin.";
                    drawerList.appendChild(err);
                    updateNotificationBadge(0);
                });
        }

        /** Mobil smart menü: bildirimler eski right-sidebar yerine MobileRightSheet. */
        function openNotificationMobileSheet() {
            if (!window.MobileRightSheet) return;
            var drawer = document.getElementById("notificationDrawer");
            var toolbar = document.querySelector(".notification-drawer__toolbar");
            var list = document.getElementById("notificationDrawerList");
            if (!drawer || !toolbar || !list) return;

            var titleEl = drawer.querySelector(".right-sidebar__title");
            var dynamicTitle = titleEl && titleEl.textContent ? titleEl.textContent.trim() : "YENILIKLER";

            closeRightSidebar();
            closeNewSmartPanel();

            updateNotificationDrawerDate();
            loadAnnouncementsForDrawer();

            if (window.MobileRightSheet.isOpen()) {
                window.MobileRightSheet.close();
            }

            /* Header konumunu sheet açılmadan önce sync et */
            if (typeof window.__syncHeaderStickyTop === "function") {
                window.__syncHeaderStickyTop();
            }

            var wrap = document.createElement("div");
            wrap.className = "mobile-notification-sheet-content";
            wrap.appendChild(list);

            /* Toolbar'ı clone et (outerHTML) - subbar'da göstermek için */
            var toolbarClone = toolbar.cloneNode(true);
            var subbarHtml = toolbarClone.outerHTML;

            window.MobileRightSheet.open({
                title: dynamicTitle,
                subbarHtml: subbarHtml,
                bodyElement: wrap,
                onClose: function () {
                    var headerEl = drawer.querySelector(".right-sidebar__header");
                    if (headerEl && headerEl.parentNode === drawer) {
                        headerEl.after(list);
                    } else {
                        drawer.appendChild(list);
                    }
                }
            });

            /* "Temizle" butonu tıklanınca tüm notification'ları temizle */
            setTimeout(function () {
                var clearBtn = document.querySelector('.mobile-right-sheet__subbar .notification-drawer__clear');
                if (clearBtn) {
                    clearBtn.addEventListener('click', function (e) {
                        e.preventDefault();
                        var notificationList = document.getElementById("notificationDrawerList");
                        if (notificationList) {
                            notificationList.innerHTML = '';
                        }
                    });
                }
            }, 0);
        }

        /** Mobil smart menü: favoriler eski right-sidebar yerine MobileRightSheet. */
        function openFavoritesMobileSheet() {
            if (!window.MobileRightSheet) return;
            var drawer = document.getElementById("favoritesDrawer");
            var tabs = drawer ? drawer.querySelector(".favorites-sidebar__tabs") : null;
            var body = drawer ? drawer.querySelector(".favorites-sidebar__body") : null;
            if (!drawer || !tabs || !body) return;

            closeRightSidebar();
            closeNewSmartPanel();

            /* Header konumunu sheet açılmadan önce sync et */
            if (typeof window.__syncHeaderStickyTop === "function") {
                window.__syncHeaderStickyTop();
            }

            if (window.MobileRightSheet.isOpen()) {
                window.MobileRightSheet.close();
            }

            var wrap = document.createElement("div");
            wrap.className = "mobile-favorites-sheet-content";
            wrap.appendChild(tabs);
            wrap.appendChild(body);

            window.MobileRightSheet.open({
                title: "FAVORİLER",
                bodyElement: wrap,
                onClose: function () {
                    var headerEl = drawer.querySelector(".right-sidebar__header");
                    if (headerEl && headerEl.parentNode === drawer) {
                        headerEl.after(tabs, body);
                    } else {
                        drawer.appendChild(tabs);
                        drawer.appendChild(body);
                    }
                }
            });
            if (window.FavoritesDrawer && typeof window.FavoritesDrawer.onDrawerOpen === "function") {
                window.FavoritesDrawer.onDrawerOpen();
            }
        }

        /** Mobil smart menü: ayarlar eski right-sidebar yerine MobileRightSheet. */
        function openSettingsMobileSheet() {
            if (!window.MobileRightSheet) return;
            var drawer = document.getElementById("settingsDrawer");
            var bodyBlock = drawer ? drawer.querySelector(".settings-sidebar__body") : null;
            if (!drawer || !bodyBlock) return;

            closeRightSidebar();
            closeNewSmartPanel();

            /* Header konumunu sheet açılmadan önce sync et */
            if (typeof window.__syncHeaderStickyTop === "function") {
                window.__syncHeaderStickyTop();
            }

            if (window.MobileRightSheet.isOpen()) {
                window.MobileRightSheet.close();
            }

            var wrap = document.createElement("div");
            wrap.className = "mobile-settings-sheet-content";
            wrap.appendChild(bodyBlock);

            window.MobileRightSheet.open({
                title: "AYARLAR",
                bodyElement: wrap,
                onClose: function () {
                    var headerEl = drawer.querySelector(".right-sidebar__header");
                    if (headerEl && headerEl.parentNode === drawer) {
                        headerEl.after(bodyBlock);
                    } else {
                        drawer.appendChild(bodyBlock);
                    }
                }
            });
        }

        function openRightSidebar(type) {
            if (type === "notification" && document.body.classList.contains("mobile-site") && window.MobileRightSheet) {
                openNotificationMobileSheet();
                return;
            }
            if (type === "favorites" && document.body.classList.contains("mobile-site") && window.MobileRightSheet) {
                openFavoritesMobileSheet();
                return;
            }
            if (type === "settings" && document.body.classList.contains("mobile-site") && window.MobileRightSheet) {
                openSettingsMobileSheet();
                return;
            }
            if (!rightSidebarOverlay) return;
            var panel = document.querySelector(".right-sidebar[data-right-sidebar=\"" + type + "\"]");
            if (!panel) return;
            if (document.body.classList.contains("mobile-site") && typeof window.__closeMobileNavMenu === "function") {
                window.__closeMobileNavMenu();
            }
            if (document.body.classList.contains("mobile-site") && typeof window.__syncHeaderStickyTop === "function") {
                window.__syncHeaderStickyTop();
            }
            closeRightSidebar();
            if (type === "notification") {
                updateNotificationDrawerDate();
                loadAnnouncementsForDrawer();
            }
            rightSidebarOverlay.classList.add("is-open");
            panel.classList.add("is-open");
            rightSidebarOverlay.setAttribute("aria-hidden", "false");
            panel.setAttribute("aria-hidden", "false");
            document.body.style.overflow = "hidden";
            currentOpenSidebar = type;
            if (type === "favorites" && window.FavoritesDrawer && typeof window.FavoritesDrawer.onDrawerOpen === "function") {
                window.FavoritesDrawer.onDrawerOpen();
            }
        }

        function closeRightSidebar() {
            if (!rightSidebarOverlay) return;
            rightSidebarPanels.forEach(function (panel) {
                panel.classList.remove("is-open");
                panel.setAttribute("aria-hidden", "true");
            });
            rightSidebarOverlay.classList.remove("is-open");
            rightSidebarOverlay.setAttribute("aria-hidden", "true");
            document.body.style.overflow = "";
            currentOpenSidebar = null;
        }

        document.querySelectorAll("[data-right-sidebar-close]").forEach(function (btn) {
            btn.addEventListener("click", function () {
                closeRightSidebar();
            });
        });
        if (rightSidebarOverlay) {
            rightSidebarOverlay.addEventListener("click", closeRightSidebar);
        }
        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape" && currentOpenSidebar) {
                closeRightSidebar();
            }
        });

        window.openRightSidebar = openRightSidebar;
        window.closeRightSidebar = closeRightSidebar;

        // Kupon / Açık Bahisler paneli (ortadan yukarı açılır)
        var betslipOverlay = document.getElementById("betslipPanelOverlay");
        var betslipPanel = document.getElementById("betslipPanel");
        var betslipClose = document.getElementById("betslipPanelClose");

        function openBetslipPanel() {
            if (!betslipOverlay || !betslipPanel) return;
            if (document.body.classList.contains("mobile-site") && typeof window.__closeMobileNavMenu === "function") {
                window.__closeMobileNavMenu();
            }
            betslipOverlay.classList.add("is-open");
            betslipPanel.classList.add("is-open");
            betslipOverlay.setAttribute("aria-hidden", "false");
            betslipPanel.setAttribute("aria-hidden", "false");
            document.body.style.overflow = "hidden";
        }

        function closeBetslipPanel() {
            if (!betslipOverlay || !betslipPanel) return;
            betslipOverlay.classList.remove("is-open");
            betslipPanel.classList.remove("is-open");
            betslipOverlay.setAttribute("aria-hidden", "true");
            betslipPanel.setAttribute("aria-hidden", "true");
            document.body.style.overflow = "";
        }

        if (betslipClose) betslipClose.addEventListener("click", closeBetslipPanel);
        if (betslipOverlay) betslipOverlay.addEventListener("click", closeBetslipPanel);
        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape" && betslipPanel && betslipPanel.classList.contains("is-open")) {
                closeBetslipPanel();
            }
        });

        var betslipTabs = document.querySelectorAll(".betslip-panel__tab");
        var betslipPanes = document.querySelectorAll(".betslip-panel__pane");
        betslipTabs.forEach(function (tab) {
            tab.addEventListener("click", function () {
                var t = tab.getAttribute("data-tab");
                betslipTabs.forEach(function (tb) {
                    tb.setAttribute("aria-selected", tb.getAttribute("data-tab") === t ? "true" : "false");
                });
                betslipPanes.forEach(function (pane) {
                    pane.hidden = pane.getAttribute("data-pane") !== t;
                });
            });
        });

        window.openBetslipPanel = openBetslipPanel;
        window.closeBetslipPanel = closeBetslipPanel;

        if (betslipSmartBtn) {
            betslipSmartBtn.addEventListener("click", function (event) {
                handleSmartPanelAction(event, openBetslipPanel);
            }, true);
        }

        var betslipAuthLogin = document.getElementById("betslipAuthLoginLink");
        var betslipAuthRegister = document.getElementById("betslipAuthRegisterLink");
        if (betslipAuthLogin) {
            betslipAuthLogin.addEventListener("click", function (e) {
                e.preventDefault();
                closeBetslipPanel();
                var loginTrigger = document.getElementById("Giris");
                if (loginTrigger) {
                    loginTrigger.click();
                    return;
                }
                if (typeof window.showModalById === "function") {
                    window.showModalById("login2");
                }
            });
        }
        if (betslipAuthRegister) {
            betslipAuthRegister.addEventListener("click", function (e) {
                e.preventDefault();
                closeBetslipPanel();
                var regTrigger = document.getElementById("openRegister");
                if (regTrigger) {
                    regTrigger.click();
                    return;
                }
                if (typeof window.showModalById === "function") {
                    window.showModalById("registerModal");
                }
            });
        }

        var mobBetKupon = document.getElementById("mob-bet-kupon");
        if (mobBetKupon) {
            mobBetKupon.addEventListener("click", function (e) {
                e.preventDefault();
                openBetslipPanel();
            });
        }

        // Bildirim: Temizle ve tekil kaldır
        var drawerClear = document.getElementById("notificationDrawerClear");
        var drawerList = document.getElementById("notificationDrawerList");
        if (drawerClear && drawerList) {
            drawerClear.addEventListener("click", function () {
                drawerList.innerHTML = "";
                updateNotificationBadge(0);
            });
        }
        if (drawerList) {
            drawerList.addEventListener("click", function (e) {
                var btn = e.target.closest(".notification-drawer__item-close");
                if (!btn) return;
                var item = btn.closest(".notification-drawer__item");
                if (item) {
                    item.remove();
                    var count = drawerList.querySelectorAll(".notification-drawer__item").length;
                    updateNotificationBadge(count);
                }
            });
        }
        function updateNotificationBadge(count) {
            var badge = document.querySelector("#smart-panel-notification-btn .sp-badge");
            if (badge) {
                badge.setAttribute("data-badge", count > 0 ? String(count) : "");
            }
            var hdrToggle = document.getElementById("smart-panel-holder");
            if (hdrToggle) {
                hdrToggle.setAttribute("data-badge", count > 0 ? String(count) : "");
                hdrToggle.classList.toggle("count-odd-animation", count > 0);
            }
        }

        // Favoriler: sekme seçimi (masaüstü sidebar + mobil MobileRightSheet içeriği)
        document.querySelectorAll(".favorites-sidebar__tab").forEach(function (tab) {
            tab.addEventListener("click", function () {
                var container = this.closest(".right-sidebar") || this.closest(".mobile-favorites-sheet-content");
                if (container) {
                    container.querySelectorAll(".favorites-sidebar__tab").forEach(function (t) { t.classList.remove("is-active"); });
                    this.classList.add("is-active");
                    var tabName = this.getAttribute("data-favorites-tab") || "";
                    container.querySelectorAll(".favorites-sidebar__pane").forEach(function (pane) {
                        var paneName = pane.getAttribute("data-favorites-pane");
                        var show = paneName === tabName;
                        pane.hidden = !show;
                        pane.classList.toggle("is-active", show);
                    });
                }
                if (window.FavoritesDrawer && typeof window.FavoritesDrawer.loadTab === "function") {
                    window.FavoritesDrawer.loadTab(this.getAttribute("data-favorites-tab") || "");
                }
            });
        });

        // Ayarlar: dropdown aç/kapa ve seçim
        var settingsDrawer = document.getElementById("settingsDrawer");
        if (settingsDrawer) {
            var settingsFields = settingsDrawer.querySelectorAll(".settings-sidebar__field");
            function closeAllSettingsDropdowns() {
                settingsFields.forEach(function (field) {
                    field.classList.remove("is-open");
                    var select = field.querySelector(".settings-sidebar__select");
                    var options = field.querySelector(".settings-sidebar__options");
                    if (select) select.setAttribute("aria-expanded", "false");
                    if (options) options.setAttribute("hidden", "");
                });
            }
            settingsFields.forEach(function (field) {
                var select = field.querySelector(".settings-sidebar__select");
                var valueEl = field.querySelector(".settings-sidebar__value");
                var options = field.querySelector(".settings-sidebar__options");
                if (!select || !valueEl || !options) return;
                select.addEventListener("click", function (e) {
                    e.stopPropagation();
                    var wasOpen = field.classList.contains("is-open");
                    closeAllSettingsDropdowns();
                    if (!wasOpen) {
                        field.classList.add("is-open");
                        select.setAttribute("aria-expanded", "true");
                        options.removeAttribute("hidden");
                    }
                });
                options.querySelectorAll(".settings-sidebar__option").forEach(function (opt) {
                    opt.addEventListener("click", function (e) {
                        e.stopPropagation();
                        var val = this.getAttribute("data-value");
                        if (valueEl.classList.contains("settings-sidebar__value--with-icon")) {
                            valueEl.innerHTML = this.innerHTML;
                        } else {
                            valueEl.textContent = val;
                        }
                        options.querySelectorAll(".settings-sidebar__option").forEach(function (o) { o.classList.remove("is-selected"); });
                        this.classList.add("is-selected");
                        closeAllSettingsDropdowns();
                    });
                });
            });
            document.addEventListener("click", function () {
                closeAllSettingsDropdowns();
            });
        }

        if (isLoggedInUser() && (document.querySelector(".smart-panel-messages-entry") || document.querySelector(".js-profile-inbox-unread"))) {
            window.MemberInboxBadges.syncBadges();
        }

        if (document.getElementById("smart-panel-holder") && document.getElementById("notificationDrawerList")) {
            loadAnnouncementsForDrawer();
        }
    }

    function onReady(fn) {
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", fn);
        } else {
            fn();
        }
    }

    /**
     * Sabit header + ana menü (overflow) dahil gerçek alt sınır.
     * --header-sticky-top sadece .headBar yüksekliği ile kalırsa sağ panel üstünde boşluk kalıyordu.
     */
    function syncHeaderStickyTopVar() {
        if (document.body.classList.contains("mobile-site")) {
            var mobileHeader = document.querySelector("#root.layout-bc .layout-header-holder-bc, .layout-header-holder-bc");
            if (mobileHeader) {
                var mb = mobileHeader.getBoundingClientRect().bottom;
                if (mb > 0) {
                    document.documentElement.style.setProperty("--header-sticky-top", Math.ceil(mb) + "px");
                }
                /* Sağ sheet / bonus modal: logo şeridi altı (hdr-main-content-bc) */
                var innerBar = mobileHeader.querySelector("[data-mobile-header-main], .hdr-main-content-bc, .mobileHeader-inner");
                if (innerBar) {
                    var logoBottom = innerBar.getBoundingClientRect().bottom;
                    if (logoBottom > 0) {
                        document.documentElement.style.setProperty("--mobile-promo-sheet-top", Math.ceil(logoBottom) + "px");
                    }
                }
                return;
            }
        }
        var header = document.querySelector("header.headBar");
        if (!header) return;
        var menu = header.querySelector(".mainMenu");
        var bottom = menu ? menu.getBoundingClientRect().bottom : header.getBoundingClientRect().bottom;
        if (!(bottom > 0)) return;
        document.documentElement.style.setProperty("--header-sticky-top", Math.ceil(bottom) + "px");
    }

    function runSyncHeaderStickyTopAfterLayout() {
        requestAnimationFrame(function () {
            requestAnimationFrame(syncHeaderStickyTopVar);
        });
    }

    /* Masaüstü: ödeme logoları alanında fare tekerleği ile yatay kaydırma */
    function initFooterPaymentScroll() {
        var el = document.getElementById("footer-payment-modes");
        if (!el) return;
        el.addEventListener("wheel", function (e) {
            if (e.deltaY === 0) return;
            var canScrollLeft = el.scrollLeft > 0;
            var canScrollRight = el.scrollLeft < el.scrollWidth - el.clientWidth - 1;
            if (e.deltaY > 0 && canScrollRight) {
                e.preventDefault();
                el.scrollLeft += e.deltaY;
            } else if (e.deltaY < 0 && canScrollLeft) {
                e.preventDefault();
                el.scrollLeft += e.deltaY;
            }
        }, { passive: false });
    }

    onReady(function () {
        // Türkiye saati sadece header.js tarafından güncelleniyor (çift setInterval CPU yükü önlendi)
        updateTurkeyTime();
        initFooterLanguageDropdown();
        initHeaderScripts();
        initFooterPaymentScroll();
        runSyncHeaderStickyTopAfterLayout();
    });

    window.addEventListener("resize", runSyncHeaderStickyTopAfterLayout);
    window.addEventListener("load", runSyncHeaderStickyTopAfterLayout);

    /* Mobil: kaydırınca .mainMenu gizlenince sağ panellerin top değeri güncellensin */
    var mobileStickyTopScrollTicking = false;
    window.addEventListener(
        "scroll",
        function () {
            if (!document.body.classList.contains("mobile-site")) return;
            if (mobileStickyTopScrollTicking) return;
            mobileStickyTopScrollTicking = true;
            requestAnimationFrame(function () {
                mobileStickyTopScrollTicking = false;
                syncHeaderStickyTopVar();
            });
        },
        { passive: true }
    );

    // Footer policy modalleri (Gizlilik / İptal-İade)
    function openPrivacyPolicyModal() {
        var el = document.getElementById("privacyPolicyModal");
        if (el) el.classList.add("show");
    }
    function closePrivacyPolicyModal() {
        var el = document.getElementById("privacyPolicyModal");
        if (el) el.classList.remove("show");
    }
    function openPolicyModal() {
        var el = document.getElementById("policyModal");
        if (el) el.classList.add("show");
    }
    function closePolicyModal() {
        var el = document.getElementById("policyModal");
        if (el) el.classList.remove("show");
    }

    // Global fonksiyonlar (HTML onclick için)
    window.redirectToDeposit = redirectToDeposit;
    window.openGame = openGame;
    window.bonusKoduKullan = bonusKoduKullan;
    window.openPrivacyPolicyModal = openPrivacyPolicyModal;
    window.closePrivacyPolicyModal = closePrivacyPolicyModal;
    window.openModal = openPolicyModal;
    window.closeModal = closePolicyModal;
    window.__syncHeaderStickyTop = syncHeaderStickyTopVar;
})();

