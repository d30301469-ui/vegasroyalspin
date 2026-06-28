#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Metropol / Vegas Royal Spin — split-deploy sistem analizi ve otomatik düzeltme.

Kapsam:
  • Yerel repo bütünlüğü (dosyalar, sync drift, auth JS)
  • .env doğrulama (frontend + backend, JWT/CORS/API URL)
  • Canlı HTTP probeleri (ping, health, sliders, login→balance, CORS)
  • --fix ile yerel .env yamaları + PHP fix scriptleri

Kullanım:
  python scripts/analyze_and_fix_system.py
  python scripts/analyze_and_fix_system.py --live
  python scripts/analyze_and_fix_system.py --login USER --password PASS --live
  python scripts/analyze_and_fix_system.py --frontend-path /www/wwwroot/vegasroyalspin.com \\
      --backend-path /www/wwwroot/bo-nexthub.site --fix --apply
  python scripts/analyze_and_fix_system.py --json --report storage/reports/system-audit.json

Bağımlılık: yalnızca Python 3.9+ stdlib.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import secrets
import shutil
import socket
import ssl
import subprocess
import sys
import textwrap
import time
import urllib.error
import urllib.request
from dataclasses import asdict, dataclass, field
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, Callable, Dict, Iterable, List, Optional, Sequence, Tuple
from urllib.parse import urljoin, urlparse

# ── Windows UTF-8 ─────────────────────────────────────────────────────────────
if sys.platform == "win32":
    for stream in (sys.stdout, sys.stderr):
        try:
            stream.reconfigure(encoding="utf-8")
        except Exception:
            pass

# ── Varsayılan production domainleri (deploy_domains.php ile uyumlu) ─────────
DEFAULTS = {
    "frontend_url": "https://vegasroyalspin.com",
    "backend_url": "https://bo-nexthub.site",
    "api_base_url": "https://api.bo-nexthub.site/api/v2",
    "frontend_hosts": [
        "vegasroyalspin.com",
        "www.vegasroyalspin.com",
        "m.vegasroyalspin.com",
    ],
    "backend_hosts": ["bo-nexthub.site", "api.bo-nexthub.site"],
    "session_cookie_domain": ".vegasroyalspin.com",
}

REQUIRED_SPLIT_FILES = [
    "install.php",
    "app/Views/install/wizard.php",
    "app/Views/install/complete.php",
    "assets/js/auth-shared.js",
    "assets/js/header-balance-poll.js",
    "services/BackendMemberApiProxy.php",
    "services/PublicApiV2Dispatcher.php",
    "config/member_api_public.php",
    "config/deploy_domains.php",
    "deploy/apache/vegasroyalspin.com.htaccess",
    "deploy/apache/bo-nexthub.site.htaccess",
    "admin/api/v2/index.php",
    "admin/api/v2/includes/member_api_cors.php",
    "admin/api/v2/includes/member_api_kernel.php",
    "admin/install.php",
]

FRONTEND_ENV_REQUIRED = {
    "APP_ENV": "production",
    "FRONTEND_API_ONLY": "1",
    "FRONTEND_DIRECT_MEMBER_API": "1",
    "SITE_URL": DEFAULTS["frontend_url"],
    "BACKEND_URL": DEFAULTS["backend_url"],
    "API_BACKEND_MAIN_BASE_URL": DEFAULTS["api_base_url"],
    "SESSION_COOKIE_DOMAIN": DEFAULTS["session_cookie_domain"],
    "CLOUDFLARE_SSL": "1",
    "ORIGIN_HTTP": "1",
}

BACKEND_ENV_REQUIRED = {
    "APP_ENV": "production",
    "API_BACKEND_MAIN_BASE_URL": DEFAULTS["api_base_url"],
    "API_PUBLIC_BASE_URL": DEFAULTS["api_base_url"],
    "SITE_URL": DEFAULTS["frontend_url"],
    "BACKEND_URL": DEFAULTS["backend_url"],
}

PLACEHOLDER_PATTERNS = (
    "change-me",
    "changeme",
    "your-secret",
    "example",
    "todo",
    "xxx",
)

AUTH_JS_MARKERS = (
    "handleMemberAuthFailure",
    "memberSessionHeaders",
    "hydrateMemberJwt",
    "metropol:member-jwt-ready",
)

AUTH_PHP_MARKERS = (
    "metropol_frontend_member_logged_in",
    "metropol_frontend_sanitize_member_session",
)

FRONTEND_ENV_EXAMPLES = (
    "deploy/env/frontend.vegasroyalspin.env.example",
    "ENV.example",
)

BACKEND_ENV_EXAMPLES = (
    "deploy/env/backend.env.example",
    "admin/.env.example",
)

API_URL_KEYS = (
    "API_BACKEND_MAIN_BASE_URL",
    "API_BACKEND_FALLBACK_BASE_URL",
    "BACKEND_API_BASE_URL",
    "API_PUBLIC_BASE_URL",
)

HOST_LIST_KEYS = (
    "ALLOWED_URL_HOSTS",
    "DEFAULT_ALLOWED_URL_HOSTS",
    "PUBLIC_URL_HOSTS",
)


def generate_jwt_secret(length: int = 64) -> str:
    """HS256 member JWT için güvenli secret (32+ karakter)."""
    raw = secrets.token_urlsafe(max(32, length))
    return raw[:max(32, length)]


def generate_app_key(length: int = 48) -> str:
    return secrets.token_urlsafe(length)[:max(32, length)]


@dataclass
class Finding:
    severity: str  # OK | WARN | ERROR | CRITICAL | FIXED
    category: str
    message: str
    hint: str = ""
    fixable: bool = False
    fix_applied: bool = False

    def to_dict(self) -> Dict[str, Any]:
        return asdict(self)


@dataclass
class AuditReport:
    started_at: str
    root: str
    findings: List[Finding] = field(default_factory=list)
    summary: Dict[str, int] = field(default_factory=dict)
    auth_token_preview: str = ""

    def add(self, finding: Finding) -> None:
        self.findings.append(finding)

    def finalize(self) -> None:
        self.summary = {}
        for f in self.findings:
            self.summary[f.severity] = self.summary.get(f.severity, 0) + 1


def parse_env_file(path: Path) -> Dict[str, str]:
    out: Dict[str, str] = {}
    if not path.is_file():
        return out
    for raw in path.read_text(encoding="utf-8", errors="replace").splitlines():
        line = raw.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, _, value = line.partition("=")
        key = key.strip()
        value = value.strip().strip('"').strip("'")
        if key:
            out[key] = value
    return out


def write_env_file(path: Path, values: Dict[str, str]) -> None:
    lines: List[str] = []
    if path.is_file():
        for raw in path.read_text(encoding="utf-8", errors="replace").splitlines():
            stripped = raw.strip()
            if stripped and not stripped.startswith("#") and "=" in stripped:
                key = stripped.split("=", 1)[0].strip()
                if key in values:
                    lines.append(f"{key}={values[key]}")
                    del values[key]
                    continue
            lines.append(raw.rstrip("\n"))
    else:
        lines.append(f"# Auto-patched {datetime.now(timezone.utc).isoformat()}")
    for key in sorted(values.keys()):
        lines.append(f"{key}={values[key]}")
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text("\n".join(lines) + "\n", encoding="utf-8")


def host_from_url(url: str) -> str:
    try:
        return (urlparse(url).hostname or "").lower()
    except Exception:
        return ""


def is_placeholder_secret(value: str, min_len: int = 32) -> bool:
    v = (value or "").strip()
    if len(v) < min_len:
        return True
    low = v.lower()
    return any(p in low for p in PLACEHOLDER_PATTERNS)


def files_differ(a: Path, b: Path) -> bool:
    if not a.is_file() or not b.is_file():
        return True
    return hashlib.sha256(a.read_bytes()).digest() != hashlib.sha256(b.read_bytes()).digest()


class HttpClient:
    """Minimal HTTP client (stdlib)."""

    def __init__(self, timeout: float = 15.0, verify_ssl: bool = False) -> None:
        self.timeout = timeout
        self.verify_ssl = verify_ssl
        self._cookie_jar: Dict[str, str] = {}

    def request(
        self,
        method: str,
        url: str,
        headers: Optional[Dict[str, str]] = None,
        body: Optional[bytes] = None,
        follow_redirects: bool = False,
    ) -> Tuple[int, Dict[str, str], str, str]:
        hdrs = {"Accept": "application/json", "User-Agent": "MetropolSystemAnalyzer/1.0"}
        if headers:
            hdrs.update(headers)
        if self._cookie_jar:
            hdrs["Cookie"] = "; ".join(f"{k}={v}" for k, v in self._cookie_jar.items())

        req = urllib.request.Request(url, data=body, headers=hdrs, method=method.upper())
        ctx = None
        if url.lower().startswith("https://") and not self.verify_ssl:
            ctx = ssl.create_default_context()
            ctx.check_hostname = False
            ctx.verify_mode = ssl.CERT_NONE

        try:
            with urllib.request.urlopen(req, timeout=self.timeout, context=ctx) as resp:
                status = resp.getcode() or 0
                resp_headers = {k.lower(): v for k, v in resp.headers.items()}
                raw = resp.read().decode("utf-8", errors="replace")
                self._store_cookies(resp_headers.get("set-cookie", ""))
                if follow_redirects and status in (301, 302, 303, 307, 308):
                    loc = resp_headers.get("location", "")
                    if loc:
                        return self.request("GET", urljoin(url, loc), headers=headers)
                return status, resp_headers, raw, ""
        except urllib.error.HTTPError as e:
            raw = e.read().decode("utf-8", errors="replace") if e.fp else ""
            resp_headers = {k.lower(): v for k, v in (e.headers.items() if e.headers else [])}
            self._store_cookies(resp_headers.get("set-cookie", ""))
            return e.code, resp_headers, raw, str(e)
        except Exception as e:
            return 0, {}, "", str(e)

    def _store_cookies(self, set_cookie: str) -> None:
        if not set_cookie:
            return
        first = set_cookie.split(";")[0]
        if "=" in first:
            k, v = first.split("=", 1)
            self._cookie_jar[k.strip()] = v.strip()

    def get_json(self, url: str, headers: Optional[Dict[str, str]] = None) -> Tuple[int, Optional[dict], str]:
        status, _, body, err = self.request("GET", url, headers=headers)
        if not body.strip():
            return status, None, err or "empty_body"
        try:
            return status, json.loads(body.lstrip("\ufeff").strip()), err
        except json.JSONDecodeError:
            return status, None, err or "invalid_json"

    def post_json(self, url: str, payload: dict, headers: Optional[Dict[str, str]] = None) -> Tuple[int, Optional[dict], str]:
        data = json.dumps(payload).encode("utf-8")
        hdrs = {"Content-Type": "application/json"}
        if headers:
            hdrs.update(headers)
        status, _, body, err = self.request("POST", url, data=data, headers=hdrs)
        try:
            parsed = json.loads(body.lstrip("\ufeff").strip()) if body.strip() else None
        except json.JSONDecodeError:
            parsed = None
        return status, parsed, err


class MetropolSystemAnalyzer:
    def __init__(self, args: argparse.Namespace) -> None:
        self.args = args
        self.root = Path(args.root).resolve()
        self.report = AuditReport(
            started_at=datetime.now(timezone.utc).isoformat(),
            root=str(self.root),
        )
        self.http = HttpClient(timeout=args.timeout)
        self.frontend_url = (args.frontend_url or DEFAULTS["frontend_url"]).rstrip("/")
        self.backend_url = (args.backend_url or DEFAULTS["backend_url"]).rstrip("/")
        self.api_base = (args.api_url or DEFAULTS["api_base_url"]).rstrip("/")
        self.frontend_env_path = Path(args.frontend_path or self.root) / ".env"
        self.backend_env_path = Path(args.backend_path or self.root / "admin") / ".env"
        if args.frontend_env:
            self.frontend_env_path = Path(args.frontend_env)
        if args.backend_env:
            self.backend_env_path = Path(args.backend_env)

    def note(
        self,
        severity: str,
        category: str,
        message: str,
        hint: str = "",
        fixable: bool = False,
        fixed: bool = False,
    ) -> None:
        self.report.add(
            Finding(
                severity="FIXED" if fixed else severity,
                category=category,
                message=message,
                hint=hint,
                fixable=fixable,
                fix_applied=fixed,
            )
        )

    # ── Repo analizi ──────────────────────────────────────────────────────

    def analyze_repo(self) -> None:
        cat = "REPO"
        missing = [rel for rel in REQUIRED_SPLIT_FILES if not (self.root / rel).is_file()]
        if missing:
            self.note("ERROR", cat, f"Eksik dosya ({len(missing)}): " + ", ".join(missing[:8]))
        else:
            self.note("OK", cat, f"Tüm kritik split-deploy dosyaları mevcut ({len(REQUIRED_SPLIT_FILES)})")

        proxy_a = self.root / "services/BackendMemberApiProxy.php"
        proxy_b = self.root / "admin/services/BackendMemberApiProxy.php"
        if proxy_a.is_file() and proxy_b.is_file() and files_differ(proxy_a, proxy_b):
            self.note(
                "ERROR",
                cat,
                "services/BackendMemberApiProxy.php ≠ admin/services/ (sync drift)",
                hint="php scripts/sync-admin-bundle-into-admin.php",
                fixable=True,
            )
        elif proxy_a.is_file():
            self.note("OK", cat, "BackendMemberApiProxy admin ile senkron")

        auth_js = self.root / "assets/js/auth-shared.js"
        if auth_js.is_file():
            src = auth_js.read_text(encoding="utf-8", errors="replace")
            missing_markers = [m for m in AUTH_JS_MARKERS if m not in src]
            if missing_markers:
                self.note(
                    "ERROR",
                    cat,
                    "auth-shared.js eski sürüm — eksik: " + ", ".join(missing_markers),
                    hint="dist/vegasroyalspin-frontend.zip yeniden yükleyin",
                )
            else:
                self.note("OK", cat, "auth-shared.js JWT oturum düzeltmeleri mevcut")

        htaccess = self.root / "deploy/apache/vegasroyalspin.com.htaccess"
        if htaccess.is_file():
            hsrc = htaccess.read_text(encoding="utf-8", errors="replace")
            if "RewriteCond %{REQUEST_FILENAME} -d" in hsrc and "pass-through" in hsrc.lower():
                self.note("WARN", cat, "Frontend .htaccess dizin pass-through redirect loop riski")
        else:
            self.note("OK", cat, "Frontend Apache htaccess redirect loop koruması")

    def analyze_auth_stack_php(self) -> None:
        cat = "AUTH_STACK"
        mp = self.root / "config/member_api_public.php"
        if mp.is_file():
            src = mp.read_text(encoding="utf-8", errors="replace")
            missing = [m for m in AUTH_PHP_MARKERS if m not in src]
            if missing:
                self.note("ERROR", cat, "member_api_public.php eksik: " + ", ".join(missing))
            else:
                self.note("OK", cat, "PHP JWT oturum yardımcıları mevcut")

        hbp = self.root / "assets/js/header-balance-poll.js"
        if hbp.is_file():
            src = hbp.read_text(encoding="utf-8", errors="replace")
            if "hasMemberJwt" not in src and "getMemberJwt" not in src:
                self.note("WARN", cat, "header-balance-poll.js JWT kontrolü zayıf")
            else:
                self.note("OK", cat, "header-balance-poll.js JWT ile poll")

        dist_zip = self.root / "dist/vegasroyalspin-frontend.zip"
        if dist_zip.is_file():
            age_h = (time.time() - dist_zip.stat().st_mtime) / 3600
            self.note("OK" if age_h < 168 else "WARN", cat, f"Frontend zip: {dist_zip.name} ({age_h:.1f} saat önce)")
        else:
            self.note("WARN", cat, "dist/vegasroyalspin-frontend.zip yok", hint="php scripts/build-split-hosts.php")

    # ── .env analizi ──────────────────────────────────────────────────────

    def analyze_env_file(self, path: Path, role: str, required: Dict[str, str]) -> Dict[str, str]:
        cat = f"ENV_{role.upper()}"
        env = parse_env_file(path)
        if not path.is_file():
            self.note(
                "ERROR",
                cat,
                f".env bulunamadı: {path}",
                hint="--fix --apply ile örnekten oluşturulur",
                fixable=True,
            )
            return env

        self.note("OK", cat, f".env okundu: {path}")

        for key, expected in required.items():
            val = env.get(key, "")
            if not val:
                self.note(
                    "ERROR",
                    cat,
                    f"Eksik anahtar: {key}",
                    hint=f"Örnek: {key}={expected}",
                    fixable=True,
                )
            elif key.endswith("_URL") or key.endswith("_BASE_URL"):
                if "api.bo-nexthub.site" in expected and "bo-nexthub.site/api" in val and "api.bo-nexthub" not in val:
                    self.note(
                        "ERROR",
                        cat,
                        f"{key} yanlış host — api subdomain kullanılmalı",
                        hint=f"Doğru: {key}={expected}",
                        fixable=True,
                    )

        if role == "frontend":
            if env.get("FRONTEND_API_ONLY", "") not in ("1", "true", "yes"):
                self.note("ERROR", cat, "FRONTEND_API_ONLY=1 değil", fixable=True)
            db_keys = [k for k in env if k.startswith(("DB_", "DATABASE_", "ADMIN_DB_")) and env.get(k)]
            if db_keys:
                self.note("WARN", cat, f"API-only frontend'de DB anahtarları olmamalı: {', '.join(db_keys[:5])}")

        jwt = env.get("MEMBER_JWT_SECRET", "")
        if is_placeholder_secret(jwt):
            self.note(
                "CRITICAL",
                cat,
                "MEMBER_JWT_SECRET geçersiz veya placeholder (bakiye 401 nedeni)",
                hint="Backend .env ile birebir aynı 32+ karakter secret kullanın",
                fixable=False,
            )
        else:
            self.note("OK", cat, f"MEMBER_JWT_SECRET uzunluk={len(jwt)}")

        purge = env.get("FRONTEND_CMS_PURGE_SECRET", "")
        if role == "frontend" and is_placeholder_secret(purge, min_len=16):
            self.note("WARN", cat, "FRONTEND_CMS_PURGE_SECRET placeholder", fixable=False)

        hosts_key = "ALLOWED_URL_HOSTS"
        hosts = [h.strip().lower() for h in env.get(hosts_key, "").split(",") if h.strip()]
        for needed in DEFAULTS["frontend_hosts"] + DEFAULTS["backend_hosts"]:
            if hosts and needed not in hosts:
                self.note(
                    "WARN",
                    cat,
                    f"{hosts_key} içinde yok: {needed}",
                    hint="CORS / redirect hatalarına yol açar",
                    fixable=True,
                )
        if hosts:
            self.note("OK", cat, f"{hosts_key} {len(hosts)} host")

        return env

    def compare_jwt_secrets(self, front: Dict[str, str], back: Dict[str, str]) -> None:
        cat = "ENV_SYNC"
        fj = front.get("MEMBER_JWT_SECRET", "")
        bj = back.get("MEMBER_JWT_SECRET", "")
        if not fj or not bj:
            self.note("ERROR", cat, "JWT secret karşılaştırması yapılamadı (.env eksik)")
            return
        if fj == bj:
            self.note("OK", cat, "MEMBER_JWT_SECRET frontend ↔ backend eşleşiyor")
        else:
            self.note(
                "CRITICAL",
                cat,
                "MEMBER_JWT_SECRET frontend ≠ backend — giriş sonrası balance 401",
                hint="Her iki sunucuda aynı secret; ardından tüm kullanıcılar yeniden giriş",
            )

        fp = front.get("FRONTEND_CMS_PURGE_SECRET", "")
        bp = back.get("FRONTEND_CMS_PURGE_SECRET", "")
        if fp and bp and fp != bp:
            self.note("WARN", cat, "FRONTEND_CMS_PURGE_SECRET eşleşmiyor")

    # ── Canlı HTTP probeleri ──────────────────────────────────────────────

    def probe_endpoint(
        self,
        label: str,
        url: str,
        expect: Callable[[Optional[dict]], bool],
        headers: Optional[Dict[str, str]] = None,
    ) -> None:
        cat = "HTTP"
        status, data, err = self.http.get_json(url, headers=headers)
        ok_http = 200 <= status < 400
        ok_body = expect(data)
        if ok_http and ok_body:
            self.note("OK", cat, f"{label}: HTTP {status}")
        else:
            detail = err or (json.dumps(data)[:120] if data else "non-json")
            self.note(
                "ERROR" if label.endswith(("balance", "loyalty", "login")) else "WARN",
                cat,
                f"{label}: HTTP {status} — {detail}",
                hint=url,
            )

    def analyze_live_http(self) -> None:
        if not self.args.live:
            self.note("WARN", "HTTP", "Canlı probeler atlandı (--live ile etkinleştirin)")
            return

        self.probe_endpoint(
            "frontend-ping",
            f"{self.frontend_url}/ping.php",
            lambda j: isinstance(j, dict),
        )
        self.probe_endpoint(
            "frontend-health",
            f"{self.frontend_url}/health.php",
            lambda j: isinstance(j, dict) and j.get("role") == "frontend",
        )
        self.probe_endpoint(
            "backend-ping",
            f"{self.backend_url}/ping.php",
            lambda j: isinstance(j, dict),
        )
        self.probe_endpoint(
            "api-site-settings",
            f"{self.api_base}/site-settings",
            lambda j: isinstance(j, dict) and j.get("success") is True,
        )
        self.probe_endpoint(
            "frontend-sliders",
            f"{self.frontend_url}/api/v2/content/sliders?category=home",
            lambda j: isinstance(j, dict) and "success" in j,
        )
        st_sess, data_sess, _ = self.http.get_json(f"{self.frontend_url}/api/v2/auth/session")
        if st_sess == 401 or (isinstance(data_sess, dict) and data_sess.get("success") is False):
            self.note("OK", "HTTP", f"frontend-auth-session-anon: HTTP {st_sess} (oturum yok — beklenen)")
        else:
            self.note("WARN", "HTTP", f"frontend-auth-session-anon: HTTP {st_sess} (401 beklenirdi)")

        st_bal, data_bal, _ = self.http.get_json(f"{self.api_base}/balance")
        if st_bal == 401:
            self.note("OK", "HTTP", "api-balance-anon: HTTP 401 (koruma aktif)")
        elif st_bal == 200 and isinstance(data_bal, dict) and data_bal.get("success"):
            self.note("CRITICAL", "HTTP", "api-balance-anon: HTTP 200 — auth bypass riski!")
        else:
            self.note("WARN", "HTTP", f"api-balance-anon: HTTP {st_bal}")

        st_api_ping, _, _ = self.http.get_json(f"{self.api_base.rsplit('/api/v2', 1)[0]}/ping.php")
        if st_api_ping == 200:
            self.note("OK", "HTTP", "api-subdomain-ping: HTTP 200")
        else:
            self.note("WARN", "HTTP", f"api-subdomain ping: HTTP {st_api_ping}")

        # CORS preflight
        cat = "CORS"
        origin = self.frontend_url
        api_balance = f"{self.api_base}/balance"
        try:
            status, hdrs, _, err = self.http.request(
                "OPTIONS",
                api_balance,
                headers={
                    "Origin": origin,
                    "Access-Control-Request-Method": "GET",
                    "Access-Control-Request-Headers": "authorization,content-type",
                },
            )
            acao = hdrs.get("access-control-allow-origin", "")
            if acao == origin or acao == "*":
                self.note("OK", cat, f"CORS preflight balance: HTTP {status}, ACAO={acao}")
            else:
                self.note(
                    "ERROR",
                    cat,
                    f"CORS preflight başarısız: HTTP {status}, ACAO='{acao}'",
                    hint="Backend .env ALLOWED_URL_HOSTS + api member_api_cors.php",
                )
        except Exception as e:
            self.note("ERROR", cat, f"CORS probe hata: {e}")

        # Auth flow
        self.analyze_auth_flow()

    def analyze_auth_flow(self) -> None:
        cat = "AUTH"
        login = (self.args.login or "").strip()
        password = (self.args.password or "").strip()
        if not login or not password:
            self.note(
                "WARN",
                cat,
                "Login→balance akışı atlandı (--login / --password verin)",
            )
            return

        login_url = f"{self.frontend_url}/api/v2/auth/login"
        client = HttpClient(timeout=self.args.timeout)
        status, data, err = client.post_json(login_url, {"login": login, "password": password})
        if not data or not data.get("success"):
            self.note(
                "ERROR",
                cat,
                f"Login başarısız HTTP {status}: {(data or {}).get('message', err)}",
                hint=login_url,
            )
            return

        token = ""
        payload = data.get("data") if isinstance(data.get("data"), dict) else {}
        token = str(payload.get("token") or data.get("token") or "").strip()
        if not token:
            self.note("CRITICAL", cat, "Login başarılı ama token yok — member_jwt_tokens / migration?")
            return

        self.report.auth_token_preview = token[:12] + "…" if len(token) > 12 else token
        self.note("OK", cat, f"Login başarılı, token alındı ({len(token)} char)")

        auth_hdr = {"Authorization": f"Bearer {token}"}

        # Direct API balance
        st_api, bal_api, _ = client.get_json(f"{self.api_base}/balance", headers=auth_hdr)
        if st_api == 200 and isinstance(bal_api, dict) and bal_api.get("success"):
            self.note("OK", cat, f"api.bo-nexthub.site balance: HTTP {st_api}")
        else:
            self.note(
                "CRITICAL",
                cat,
                f"Direkt API balance 401/ hata HTTP {st_api}",
                hint="MEMBER_JWT_SECRET uyumsuzluğu veya token DB'de yok",
            )

        # Frontend proxy balance (session cookie from login)
        st_proxy, bal_proxy, _ = client.get_json(
            f"{self.frontend_url}/api/v2/balance",
            headers=auth_hdr,
        )
        if st_proxy == 200 and isinstance(bal_proxy, dict) and bal_proxy.get("success"):
            self.note("OK", cat, f"Frontend proxy balance: HTTP {st_proxy}")
        else:
            self.note(
                "ERROR",
                cat,
                f"Frontend proxy balance HTTP {st_proxy}",
                hint="PHP session member_jwt — yeni frontend zip + çıkış/giriş",
            )

        # Loyalty
        st_loy, loy, _ = client.get_json(f"{self.api_base}/loyalty", headers=auth_hdr)
        if st_loy == 200 and isinstance(loy, dict) and loy.get("success"):
            self.note("OK", cat, f"Loyalty: HTTP {st_loy}")
        else:
            self.note("WARN", cat, f"Loyalty HTTP {st_loy}")

    # ── PHP test runner ───────────────────────────────────────────────────

    def run_php_checks(self) -> None:
        cat = "PHP"
        php = shutil.which("php") or self._find_laragon_php()
        if not php:
            self.note("WARN", cat, "PHP bulunamadı — yerel syntax/test atlandı")
            return

        scripts = [
            ("smoke-install-wizards.php", "Install wizard smoke"),
            ("verify-services-sync.php", "Services sync"),
        ]
        if self.args.full:
            scripts.append(("test-all-layers.php", "Full layer tests"))

        for script, label in scripts:
            path = self.root / "scripts" / script
            if not path.is_file():
                continue
            try:
                proc = subprocess.run(
                    [php, str(path)],
                    cwd=str(self.root),
                    capture_output=True,
                    text=True,
                    timeout=180,
                    encoding="utf-8",
                    errors="replace",
                )
                out = (proc.stdout or "") + (proc.stderr or "")
                if proc.returncode == 0:
                    self.note("OK", cat, f"{label}: PASS")
                else:
                    snippet = out.strip().replace("\n", " | ")[:200]
                    self.note(
                        "ERROR",
                        cat,
                        f"{label}: FAIL (exit {proc.returncode})",
                        hint=snippet,
                        fixable="sync" in script,
                    )
            except subprocess.TimeoutExpired:
                self.note("ERROR", cat, f"{label}: timeout")

    def _find_laragon_php(self) -> Optional[str]:
        if sys.platform != "win32":
            return None
        laragon = Path("c:/laragon/bin/php")
        if not laragon.is_dir():
            return None
        versions = sorted(laragon.glob("php-*"), reverse=True)
        for d in versions:
            exe = d / "php.exe"
            if exe.is_file():
                return str(exe)
        return None

    # ── Otomatik düzeltme ─────────────────────────────────────────────────

    def execute_pre_analysis_fixes(self) -> None:
        """Analiz öncesi: .env oluştur, örnek dosyaları düzelt, JWT senkronize et."""
        cat = "FIX"
        self.bootstrap_env_files()
        self.fix_repo_env_examples()
        self.sync_jwt_secrets_between_hosts()
        self._sync_admin_bundle_if_needed()

    def bootstrap_env_files(self) -> None:
        cat = "FIX"
        pairs = (
            ("frontend", self.frontend_env_path, FRONTEND_ENV_EXAMPLES),
            ("backend", self.backend_env_path, BACKEND_ENV_EXAMPLES),
        )
        for role, path, examples in pairs:
            if path.is_file():
                continue
            for rel in examples:
                src = self.root / rel
                if not src.is_file():
                    continue
                path.parent.mkdir(parents=True, exist_ok=True)
                shutil.copy(src, path)
                self.note("FIXED", cat, f"{role} .env oluşturuldu ← {rel}", fixed=True)
                break
            else:
                self.note("ERROR", cat, f"{role} .env örneği bulunamadı", fixable=False)

    def fix_repo_env_examples(self) -> None:
        """Repo içindeki .env.example dosyalarında yanlış API URL / host listelerini düzelt."""
        cat = "FIX"
        api_base = DEFAULTS["api_base_url"]
        allowed = ",".join(DEFAULTS["frontend_hosts"] + DEFAULTS["backend_hosts"])
        targets = [
            self.root / "admin/.env.example",
            self.root / "ENV.example",
            self.root / "deploy/env/backend.env.example",
        ]
        for path in targets:
            if not path.is_file():
                continue
            text = path.read_text(encoding="utf-8", errors="replace")
            original = text
            text = text.replace(
                "BACKEND_API_BASE_URL=https://bo-nexthub.site/api/v2",
                f"BACKEND_API_BASE_URL={api_base}",
            )
            text = text.replace(
                "API_BACKEND_MAIN_BASE_URL=https://bo-nexthub.site/api/v2",
                f"API_BACKEND_MAIN_BASE_URL={api_base}",
            )
            text = text.replace(
                "ALLOWED_URL_HOSTS=vegasroyalspin.com,www.vegasroyalspin.com,m.vegasroyalspin.com,bo-nexthub.site",
                f"ALLOWED_URL_HOSTS={allowed}",
            )
            text = text.replace(
                "DEFAULT_ALLOWED_URL_HOSTS=vegasroyalspin.com,www.vegasroyalspin.com,m.vegasroyalspin.com,bo-nexthub.site",
                f"DEFAULT_ALLOWED_URL_HOSTS={allowed}",
            )
            if "API_PUBLIC_BASE_URL=" not in text and "backend" in path.name.lower():
                text = text.replace(
                    f"API_BACKEND_MAIN_BASE_URL={api_base}",
                    f"API_PUBLIC_BASE_URL={api_base}\nAPI_BACKEND_MAIN_BASE_URL={api_base}",
                    1,
                )
            if text != original:
                path.write_text(text, encoding="utf-8")
                self.note("FIXED", cat, f"Güncellendi: {path.relative_to(self.root)}", fixed=True)

    def sync_jwt_secrets_between_hosts(self) -> None:
        cat = "FIX"
        front = parse_env_file(self.frontend_env_path)
        back = parse_env_file(self.backend_env_path)
        if not front and not back:
            return

        fj = front.get("MEMBER_JWT_SECRET", "")
        bj = back.get("MEMBER_JWT_SECRET", "")
        fp = front.get("FRONTEND_CMS_PURGE_SECRET", "")
        bp = back.get("FRONTEND_CMS_PURGE_SECRET", "")

        patches_front: Dict[str, str] = {}
        patches_back: Dict[str, str] = {}

        if is_placeholder_secret(fj) and is_placeholder_secret(bj):
            secret = generate_jwt_secret()
            patches_front["MEMBER_JWT_SECRET"] = secret
            patches_back["MEMBER_JWT_SECRET"] = secret
            self.note("FIXED", cat, "Yeni MEMBER_JWT_SECRET üretildi (her iki .env)", fixed=True)
        elif not is_placeholder_secret(fj) and is_placeholder_secret(bj):
            patches_back["MEMBER_JWT_SECRET"] = fj
            self.note("FIXED", cat, "Backend MEMBER_JWT_SECRET ← frontend", fixed=True)
        elif is_placeholder_secret(fj) and not is_placeholder_secret(bj):
            patches_front["MEMBER_JWT_SECRET"] = bj
            self.note("FIXED", cat, "Frontend MEMBER_JWT_SECRET ← backend", fixed=True)
        elif fj and bj and fj != bj:
            patches_back["MEMBER_JWT_SECRET"] = fj
            self.note("FIXED", cat, "JWT uyumsuzluğu giderildi (backend ← frontend)", fixed=True)

        if is_placeholder_secret(fp, min_len=16) and is_placeholder_secret(bp, min_len=16):
            purge = generate_jwt_secret(48)
            patches_front["FRONTEND_CMS_PURGE_SECRET"] = purge
            patches_back["FRONTEND_CMS_PURGE_SECRET"] = purge
            self.note("FIXED", cat, "Yeni FRONTEND_CMS_PURGE_SECRET üretildi", fixed=True)
        elif not is_placeholder_secret(fp, min_len=16) and is_placeholder_secret(bp, min_len=16):
            patches_back["FRONTEND_CMS_PURGE_SECRET"] = fp
        elif is_placeholder_secret(fp, min_len=16) and not is_placeholder_secret(bp, min_len=16):
            patches_front["FRONTEND_CMS_PURGE_SECRET"] = bp

        for key, val in patches_front.items():
            if self.frontend_env_path.is_file():
                write_env_file(self.frontend_env_path, {key: val})
        for key, val in patches_back.items():
            if self.backend_env_path.is_file():
                write_env_file(self.backend_env_path, {key: val})

        # APP_KEY placeholders (local dev)
        for path, patches, label in (
            (self.frontend_env_path, patches_front, "frontend"),
            (self.backend_env_path, patches_back, "backend"),
        ):
            env = parse_env_file(path)
            if env.get("APP_KEY", "").startswith("CHANGE-ME"):
                k = generate_app_key()
                write_env_file(path, {"APP_KEY": k})
                self.note("FIXED", cat, f"{label} APP_KEY üretildi", fixed=True)

    def _sync_admin_bundle_if_needed(self) -> None:
        proxy_a = self.root / "services/BackendMemberApiProxy.php"
        proxy_b = self.root / "admin/services/BackendMemberApiProxy.php"
        if proxy_a.is_file() and proxy_b.is_file() and not files_differ(proxy_a, proxy_b):
            return
        sync = self.root / "scripts/sync-admin-bundle-into-admin.php"
        php = shutil.which("php") or self._find_laragon_php()
        if php and sync.is_file():
            subprocess.run([php, str(sync)], cwd=str(self.root), check=False, capture_output=True)
            self.note("FIXED", "FIX", "admin bundle senkronize edildi", fixed=True)

    def apply_fixes(self, front_env: Dict[str, str], back_env: Dict[str, str]) -> None:
        if not self.args.fix:
            return
        if not self.args.apply:
            self.note("WARN", "FIX", "--fix verildi ama --apply yok (kuru çalışma)")
            return

        cat = "FIX"
        self.bootstrap_env_files()
        self.sync_jwt_secrets_between_hosts()

        # Sync admin bundle if drift
        drift = any(
            f.category == "REPO" and "sync drift" in f.message for f in self.report.findings
        )
        if drift:
            self._sync_admin_bundle_if_needed()

        # Patch frontend .env
        front_env = parse_env_file(self.frontend_env_path)
        back_env = parse_env_file(self.backend_env_path)
        if self.frontend_env_path.parent.exists():
            self._patch_env(self.frontend_env_path, front_env, FRONTEND_ENV_REQUIRED, "frontend", cat)

        if self.backend_env_path.parent.exists():
            self._patch_env(self.backend_env_path, back_env, BACKEND_ENV_REQUIRED, "backend", cat)

        # Run PHP fix scripts when paths look like server deploy roots
        php = shutil.which("php") or self._find_laragon_php()
        if php:
            for role, base in (("frontend", self.frontend_env_path.parent), ("backend", self.backend_env_path.parent)):
                fix_script = base / "deploy/aapanel/fix-frontend-env.php"
                if role == "backend":
                    fix_script = base / "deploy/aapanel/fix-backend-env.php"
                if fix_script.is_file() and (base / ".env").is_file():
                    subprocess.run([php, str(fix_script), str(base)], cwd=str(base), check=False)
                    self.note("FIXED", cat, f"Çalıştırıldı: {fix_script.name} ({role})", fixed=True)

            for script, label in (
                ("verify-split-deploy.php", "Split deploy verify"),
                ("test-all-layers.php", "All layers test"),
            ):
                if script == "test-all-layers.php" and not (self.args.full or self.args.fix):
                    continue
                path = self.root / "scripts" / script
                if not path.is_file():
                    continue
                proc = subprocess.run(
                    [php, str(path)],
                    cwd=str(self.root),
                    capture_output=True,
                    text=True,
                    timeout=300,
                    encoding="utf-8",
                    errors="replace",
                )
                if proc.returncode == 0:
                    self.note("FIXED", cat, f"{label}: PASS (fix sonrası)", fixed=True)
                else:
                    out = ((proc.stdout or "") + (proc.stderr or "")).strip()[-300:]
                    self.note("ERROR", cat, f"{label}: FAIL — {out}")

            build = self.root / "scripts/build-split-hosts.php"
            if build.is_file() and self.args.fix:
                proc = subprocess.run(
                    [php, str(build)],
                    cwd=str(self.root),
                    capture_output=True,
                    text=True,
                    timeout=600,
                    encoding="utf-8",
                    errors="replace",
                )
                if proc.returncode == 0:
                    self.note("FIXED", cat, "dist/*.zip yeniden oluşturuldu", fixed=True)
                else:
                    self.note("WARN", cat, "build-split-hosts.php başarısız veya timeout")

    def _patch_env(
        self,
        path: Path,
        current: Dict[str, str],
        required: Dict[str, str],
        role: str,
        cat: str,
    ) -> None:
        patches: Dict[str, str] = {}
        merged = dict(current)

        for key, expected in required.items():
            val = merged.get(key, "")
            if not val:
                patches[key] = expected

        if role == "frontend":
            if merged.get("FRONTEND_API_ONLY", "") not in ("1", "true", "yes"):
                patches["FRONTEND_API_ONLY"] = "1"
            if merged.get("FRONTEND_DIRECT_MEMBER_API", "") not in ("1", "true", "yes"):
                patches["FRONTEND_DIRECT_MEMBER_API"] = "1"
            for url_key in ("API_BACKEND_MAIN_BASE_URL", "BACKEND_API_BASE_URL", "API_BACKEND_FALLBACK_BASE_URL"):
                val = merged.get(url_key, "")
                if val and "bo-nexthub.site/api" in val and "api.bo-nexthub" not in val:
                    patches[url_key] = DEFAULTS["api_base_url"]

            allowed = DEFAULTS["frontend_hosts"] + DEFAULTS["backend_hosts"]
            for hk in HOST_LIST_KEYS:
                existing = [h.strip().lower() for h in merged.get(hk, "").split(",") if h.strip()]
                if existing or hk == "ALLOWED_URL_HOSTS":
                    merged_hosts = sorted(set(existing + [h.lower() for h in allowed]))
                    patches[hk] = ",".join(merged_hosts)

        if role == "backend":
            for url_key in API_URL_KEYS:
                val = merged.get(url_key, "")
                if val and "bo-nexthub.site/api" in val and "api.bo-nexthub" not in val:
                    patches[url_key] = DEFAULTS["api_base_url"]
            allowed = DEFAULTS["frontend_hosts"] + DEFAULTS["backend_hosts"]
            for hk in HOST_LIST_KEYS:
                existing = [h.strip().lower() for h in merged.get(hk, "").split(",") if h.strip()]
                merged_hosts = sorted(set(existing + [h.lower() for h in allowed]))
                if merged.get(hk, "") != ",".join(merged_hosts):
                    patches[hk] = ",".join(merged_hosts)

        if not patches:
            return

        if not path.is_file():
            examples = FRONTEND_ENV_EXAMPLES if role == "frontend" else BACKEND_ENV_EXAMPLES
            for rel in examples:
                ex = self.root / rel
                if ex.is_file():
                    shutil.copy(ex, path)
                    break

        write_env_file(path, patches)
        for key in patches:
            self.note("FIXED", cat, f"{path.name}: {key}={patches[key][:60]}", fixed=True)

    # ── Rapor ─────────────────────────────────────────────────────────────

    def print_report(self) -> int:
        self.report.finalize()
        if self.args.json:
            print(json.dumps(
                {
                    "report": {
                        "started_at": self.report.started_at,
                        "root": self.report.root,
                        "summary": self.report.summary,
                        "auth_token_preview": self.report.auth_token_preview,
                        "findings": [f.to_dict() for f in self.report.findings],
                    }
                },
                ensure_ascii=False,
                indent=2,
            ))
        else:
            self._print_human()

        if self.args.report:
            out = Path(self.args.report)
            out.parent.mkdir(parents=True, exist_ok=True)
            out.write_text(
                json.dumps(
                    {
                        "started_at": self.report.started_at,
                        "summary": self.report.summary,
                        "findings": [f.to_dict() for f in self.report.findings],
                    },
                    ensure_ascii=False,
                    indent=2,
                ),
                encoding="utf-8",
            )
            print(f"\nRapor kaydedildi: {out}", file=sys.stderr)

        critical = self.report.summary.get("CRITICAL", 0)
        errors = self.report.summary.get("ERROR", 0)
        if critical > 0:
            return 2
        if errors > 0:
            return 1
        return 0

    def _print_human(self) -> None:
        icons = {"OK": "✓", "WARN": "!", "ERROR": "✗", "CRITICAL": "‼", "FIXED": "+"}
        print()
        print("=" * 72)
        print("  METROPOL SPLIT-DEPLOY SİSTEM ANALİZİ")
        print(f"  {self.report.started_at}")
        print(f"  Root: {self.root}")
        print("=" * 72)

        by_cat: Dict[str, List[Finding]] = {}
        for f in self.report.findings:
            by_cat.setdefault(f.category, []).append(f)

        for cat in sorted(by_cat.keys()):
            print(f"\n── {cat} " + "─" * max(0, 60 - len(cat)))
            for f in by_cat[cat]:
                icon = icons.get(f.severity, "?")
                print(f"  [{icon}] {f.message}")
                if f.hint:
                    print(f"      → {f.hint}")

        self.report.finalize()
        print("\n" + "─" * 72)
        print(
            "  Özet: "
            + ", ".join(f"{k}={v}" for k, v in sorted(self.report.summary.items()))
        )
        if self.report.auth_token_preview:
            print(f"  Auth token: {self.report.auth_token_preview}")

        if self.report.summary.get("CRITICAL") or self.report.summary.get("ERROR"):
            print(textwrap.dedent("""
              \n  Önerilen sunucu komutları:
                # Backend (bo-nexthub.site)
                php deploy/aapanel/fix-backend-env.php
                # Frontend (vegasroyalspin.com)
                php deploy/aapanel/fix-frontend-env.php
                php deploy/aapanel/fix-apache-deploy.php
                # Kullanıcılar: çıkış + localStorage metropol_member_jwt sil + tekrar giriş
            """).rstrip())

    def run(self) -> int:
        print("Analiz başlıyor…", file=sys.stderr)
        if self.args.fix and self.args.apply:
            print("Otomatik düzeltme (ön hazırlık)…", file=sys.stderr)
            self.execute_pre_analysis_fixes()

        self.analyze_repo()
        self.analyze_auth_stack_php()
        front = self.analyze_env_file(self.frontend_env_path, "frontend", FRONTEND_ENV_REQUIRED)
        back = self.analyze_env_file(self.backend_env_path, "backend", BACKEND_ENV_REQUIRED)
        self.compare_jwt_secrets(front, back)
        self.run_php_checks()
        self.analyze_live_http()
        self.apply_fixes(front, back)

        if self.args.fix and self.args.apply:
            print("Düzeltme sonrası yeniden doğrulama…", file=sys.stderr)
            front2 = parse_env_file(self.frontend_env_path)
            back2 = parse_env_file(self.backend_env_path)
            if front2 or back2:
                self.compare_jwt_secrets(front2, back2)

        return self.print_report()


def build_arg_parser() -> argparse.ArgumentParser:
    p = argparse.ArgumentParser(
        description="Metropol split-deploy tam sistem analizi ve düzeltme",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog=textwrap.dedent(__doc__ or ""),
    )
    p.add_argument("--root", default=".", help="Proje kök dizini")
    p.add_argument("--frontend-url", help=f"Frontend URL (varsayılan: {DEFAULTS['frontend_url']})")
    p.add_argument("--backend-url", help=f"Backend URL (varsayılan: {DEFAULTS['backend_url']})")
    p.add_argument("--api-url", help=f"Member API base (varsayılan: {DEFAULTS['api_base_url']})")
    p.add_argument("--frontend-path", help="Frontend sunucu dizini (.env için)")
    p.add_argument("--backend-path", help="Backend sunucu dizini (.env için)")
    p.add_argument("--frontend-env", help="Doğrudan frontend .env dosya yolu")
    p.add_argument("--backend-env", help="Doğrudan backend .env dosya yolu")
    p.add_argument("--login", help="Canlı login test kullanıcı adı/e-posta")
    p.add_argument("--password", help="Canlı login test şifresi")
    p.add_argument("--live", action="store_true", help="Canlı HTTP probeleri çalıştır")
    p.add_argument("--full", action="store_true", help="test-all-layers.php dahil tam PHP test")
    p.add_argument("--fix", action="store_true", help="Otomatik düzeltmeleri uygula")
    p.add_argument("--apply", action="store_true", help="--fix ile dosya değişikliği yap (yoksa kuru)")
    p.add_argument("--json", action="store_true", help="JSON çıktı")
    p.add_argument("--report", help="JSON rapor dosyası yolu")
    p.add_argument("--timeout", type=float, default=15.0, help="HTTP timeout (sn)")
    return p


def main() -> int:
    args = build_arg_parser().parse_args()
    if args.root == ".":
        args.root = str(Path(__file__).resolve().parent.parent)
    if args.fix and not args.apply:
        args.apply = True
    if args.fix:
        args.live = True
        args.full = True
    analyzer = MetropolSystemAnalyzer(args)
    return analyzer.run()


if __name__ == "__main__":
    sys.exit(main())
