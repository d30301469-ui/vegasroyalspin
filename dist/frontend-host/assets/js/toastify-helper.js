(function (global) {
    "use strict";

    function normalizeType(type) {
        type = String(type || "info").toLowerCase();
        if (type === "warn") return "warning";
        if (type !== "success" && type !== "error" && type !== "warning" && type !== "info") {
            return "info";
        }
        return type;
    }

    function palette(type) {
        var css = global.getComputedStyle ? global.getComputedStyle(document.documentElement) : null;
        var primary = css ? css.getPropertyValue("--color-primary").trim() : "";
        var danger = css ? css.getPropertyValue("--color-danger").trim() : "";
        var warning = css ? css.getPropertyValue("--color-warning").trim() : "";
        var success = css ? css.getPropertyValue("--color-success").trim() : "";
        if (type === "success") return success || "#1d8348";
        if (type === "error") return danger || "#b03a2e";
        if (type === "warning") return warning || "#d68910";
        return primary || "#2f80ed";
    }

    function showNativeToast(label, type, options) {
        var wrap = document.getElementById("maltabet-toastify-stack");
        if (!wrap) {
            wrap = document.createElement("div");
            wrap.id = "maltabet-toastify-stack";
            wrap.style.position = "fixed";
            wrap.style.top = "18px";
            wrap.style.right = "18px";
            wrap.style.zIndex = "2147483647";
            wrap.style.display = "grid";
            wrap.style.gap = "10px";
            wrap.style.maxWidth = "min(360px, calc(100vw - 36px))";
            document.body.appendChild(wrap);
        }

        var toast = document.createElement("div");
        toast.className = "toastify maltabet-toastify-fallback";
        toast.setAttribute("role", "status");
        toast.textContent = label;
        toast.style.background = palette(type);
        toast.style.color = "#fff";
        toast.style.borderRadius = "8px";
        toast.style.boxShadow = "none";
        toast.style.padding = "12px 14px";
        toast.style.fontFamily = "var(--font-sans, system-ui, sans-serif)";
        toast.style.fontSize = "14px";
        toast.style.lineHeight = "1.35";
        toast.style.opacity = "0";
        toast.style.transform = "translateX(16px)";
        toast.style.transition = "opacity 160ms ease, transform 160ms ease";
        wrap.appendChild(toast);

        global.requestAnimationFrame(function () {
            toast.style.opacity = "1";
            toast.style.transform = "translateX(0)";
        });

        global.setTimeout(function () {
            toast.style.opacity = "0";
            toast.style.transform = "translateX(16px)";
            global.setTimeout(function () {
                if (toast.parentNode) toast.parentNode.removeChild(toast);
                if (wrap && wrap.parentNode && wrap.children.length === 0) {
                    wrap.parentNode.removeChild(wrap);
                }
            }, 200);
        }, Number(options.duration || 7000));
    }

    function show(message, options) {
        options = options || {};
        var type = normalizeType(options.type);
        var text = String(message || options.message || "").trim();
        if (!text) return;
        var title = String(options.title || "").trim();
        var label = title ? title + ": " + text : text;

        if (typeof global.Toastify === "function") {
            global.Toastify({
                text: label,
                duration: Number(options.duration || 7000),
                close: options.close !== false,
                gravity: options.gravity || "top",
                position: options.position || "right",
                stopOnFocus: true,
                style: {
                    background: palette(type),
                    color: "#fff",
                    borderRadius: "8px",
                    boxShadow: "none",
                    fontFamily: "var(--font-sans, system-ui, sans-serif)",
                    fontSize: "14px",
                    lineHeight: "1.35",
                },
            }).showToast();
            return;
        }

        showNativeToast(label, type, options);
    }

    global.MaltabetToast = global.MaltabetToast || {
        show: show,
        success: function (message, title) { show(message, { type: "success", title: title }); },
        error: function (message, title) { show(message, { type: "error", title: title }); },
        warning: function (message, title) { show(message, { type: "warning", title: title }); },
        info: function (message, title) { show(message, { type: "info", title: title }); },
    };
})(window);
