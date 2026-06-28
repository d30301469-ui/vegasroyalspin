(function () {
    if (!document.body.classList.contains("mobile-site")) return;

    function ready(fn) {
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", fn);
        } else {
            fn();
        }
    }

    ready(function () {
        var oddsPage = document.getElementById("betslipOddsFullpage");
        var settingsBtn = document.getElementById("betslipPanelSettings");
        var prefLabel = document.getElementById("betslipOddsPrefLabel");
        var barClose = document.getElementById("betslipOddsFullpageCloseBar");
        var barText = document.getElementById("betslipOddsFullpageBarText");
        var list = document.getElementById("betslipOddsFullpageList");
        var loginLink = document.getElementById("betslipAuthLoginLink");
        var regLink = document.getElementById("betslipAuthRegisterLink");

        function syncBarLabel(text) {
            if (prefLabel) prefLabel.textContent = text;
            if (barText) barText.textContent = text;
        }

        function setSelectedOption(pref) {
            if (!list) return;
            list.querySelectorAll(".betslip-odds-fullpage__option").forEach(function (btn) {
                btn.classList.toggle("is-selected", btn.getAttribute("data-odds-pref") === pref);
            });
        }

        function openOddsFullpage() {
            if (!oddsPage) return;
            oddsPage.hidden = false;
            oddsPage.classList.add("is-open");
            oddsPage.setAttribute("aria-hidden", "false");
            if (settingsBtn) settingsBtn.setAttribute("aria-expanded", "true");
        }

        function closeOddsFullpage() {
            if (!oddsPage || !oddsPage.classList.contains("is-open")) return;
            oddsPage.classList.remove("is-open");
            oddsPage.setAttribute("aria-hidden", "true");
            oddsPage.hidden = true;
            if (settingsBtn) settingsBtn.setAttribute("aria-expanded", "false");
        }

        window.__closeBetslipOddsFullpage = closeOddsFullpage;

        var origCloseBetslip = window.closeBetslipPanel;
        if (typeof origCloseBetslip === "function" && !window.__betslipMobileClosePatched) {
            window.__betslipMobileClosePatched = true;
            window.closeBetslipPanel = function () {
                closeOddsFullpage();
                origCloseBetslip();
            };
        }

        if (settingsBtn) {
            settingsBtn.addEventListener("click", function (e) {
                e.preventDefault();
                if (!document.getElementById("betslipPanel") || !document.getElementById("betslipPanel").classList.contains("is-open")) {
                    return;
                }
                if (oddsPage && oddsPage.classList.contains("is-open")) {
                    closeOddsFullpage();
                } else {
                    var cur = prefLabel ? prefLabel.textContent.trim() : "Her zaman sor";
                    syncBarLabel(cur);
                    var matchPref = "always_ask";
                    if (list) {
                        list.querySelectorAll(".betslip-odds-fullpage__option").forEach(function (btn) {
                            if (btn.getAttribute("data-label") === cur) {
                                matchPref = btn.getAttribute("data-odds-pref") || matchPref;
                            }
                        });
                    }
                    setSelectedOption(matchPref);
                    openOddsFullpage();
                }
            });
        }

        if (barClose) {
            barClose.addEventListener("click", function () {
                closeOddsFullpage();
            });
        }

        if (list) {
            list.addEventListener("click", function (e) {
                var btn = e.target.closest(".betslip-odds-fullpage__option");
                if (!btn) return;
                var label = btn.getAttribute("data-label") || btn.textContent.trim();
                syncBarLabel(label);
                setSelectedOption(btn.getAttribute("data-odds-pref"));
                closeOddsFullpage();
            });
        }

        function triggerLogin() {
            if (typeof window.closeBetslipPanel === "function") window.closeBetslipPanel();
            var a = document.getElementById("Giris");
            if (a) {
                a.click();
                return;
            }
            var m = document.getElementById("login2");
            if (m && typeof window.jQuery !== "undefined") {
                window.jQuery(m).modal("show");
            }
        }

        function triggerRegister() {
            if (typeof window.closeBetslipPanel === "function") window.closeBetslipPanel();
            var b = document.getElementById("openRegister");
            if (b) {
                b.click();
                return;
            }
            var m = document.getElementById("registerModal");
            if (m && typeof window.jQuery !== "undefined") {
                window.jQuery(m).modal("show");
            }
        }

        if (loginLink) {
            loginLink.addEventListener("click", function (e) {
                e.preventDefault();
                triggerLogin();
            });
        }
        if (regLink) {
            regLink.addEventListener("click", function (e) {
                e.preventDefault();
                triggerRegister();
            });
        }

        document.addEventListener(
            "keydown",
            function (e) {
                if (e.key !== "Escape") return;
                if (!oddsPage || !oddsPage.classList.contains("is-open")) return;
                e.stopImmediatePropagation();
                closeOddsFullpage();
            },
            true
        );
    });
})();
