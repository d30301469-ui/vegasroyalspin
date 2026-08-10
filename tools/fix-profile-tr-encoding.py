# -*- coding: utf-8 -*-
"""Second-pass Turkish fixes for profile.js modal strings."""
from pathlib import Path
import re

path = Path(r"C:\laragon\www\vegasroyalspin\assets\js\profile.js")
text = path.read_text(encoding="utf-8")

repls = [
    # Remaining deposit/withdraw copy
    ("'IADE EDILMEZ'", "'İADE EDİLMEZ'"),
    ("\\'IADE EDILMEZ\\'", "\\'İADE EDİLMEZ\\'"),
    ("Minimum tutar alti yatırımlar", "Minimum tutar altı yatırımlar"),
    ("Minimum tutar alti yatirimlar", "Minimum tutar altı yatırımlar"),
    ("kurallara uygun yatirim yapiniz.", "kurallara uygun yatırım yapınız."),
    ("kurallara uygun yatırım yapiniz.", "kurallara uygun yatırım yapınız."),
    # Generic remaining ASCII Turkish in user-facing literals
    ("tekrar giris yapin", "tekrar giriş yapın"),
    ("yeniden giris yapin", "yeniden giriş yapın"),
    ("kimligi alinamadi", "kimliği alınamadı"),
    ("Sayfayi yenileyip", "Sayfayı yenileyip"),
    ("geçmisiniz", "geçmişiniz"),
    ("geçmisini", "geçmişini"),
    ("açiliyor", "açılıyor"),
    ("onaylandi", "onaylandı"),
    ("onaylandiginda", "onaylandığında"),
    ("tamamlanamadi", "tamamlanamadı"),
    ("dogrulama kapatildi", "doğrulama kapatıldı"),
    ("Iki faktörlü", "İki faktörlü"),
    ("Iki faktorlu", "İki faktörlü"),
    ("Para yatirma", "Para yatırma"),
    ("listelenen yontem", "listelenen yöntem"),
    ("Su an ", "Şu an "),
    ("hos geldiniz", "hoş geldiniz"),
    ("Iyi eglenceler", "İyi eğlenceler"),
    ("bol sanslar", "bol şanslar"),
    ("asagidaki", "aşağıdaki"),
    ("alanlari ", "alanları "),
    ("kazanciniz adina", "kazancınız adına"),
    ("Baglanti hatasi", "Bağlantı hatası"),
    ("Kullanici ID kopyalandi", "Kullanıcı ID kopyalandı"),
    ("kopyalandi.", "kopyalandı."),
    ("'Kopyalandi'", "'Kopyalandı'"),
    ("Islem ", "İşlem "),
    ("olustu", "oluştu"),
    ("yapilamadi", "yapılamadı"),
    ("alindi.", "alındı."),
    ("olusturulamadi", "oluşturulamadı"),
    ("Basarili", "Başarılı"),
]

report = []
applied = 0
for old, new in repls:
    c = text.count(old)
    if c:
        text = text.replace(old, new)
        applied += c
        report.append(f"OK x{c}: {old} -> {new}")
    else:
        report.append(f"MISS: {old}")

# Ensure all money concatenations use ₺
before = text
text = re.sub(r"\+ ' \?'", "+ ' ₺'", text)
text = re.sub(r"return text \+ ' \?'", "return text + ' ₺'", text)
text = text.replace("'0,00 ?'", "'0,00 ₺'")
if text != before:
    report.append("OK: currency ? -> ₺")

path.write_text(text, encoding="utf-8")

# Scan leftover suspicious patterns in string-ish context
suspicious = []
for m in re.finditer(r"'[^'\\]*(?:\\.[^'\\]*)*'", text):
    s = m.group(0)
    if any(x in s for x in ["Baglanti", "alindi", "Basarili", "l?tfen", "Islem ", "olustu", "Su an", "hos geld", "IADE EDILMEZ", "yapilamadi", "giris yap", "+ ' ?'"]):
        suspicious.append(s[:120])

report.append(f"applied_ops={applied}")
report.append(f"lira_count={text.count('₺')}")
report.append(f"suspicious_left={len(suspicious)}")
report.extend(suspicious[:40])
Path(r"C:\laragon\www\vegasroyalspin\tools\_tr-fix-report.txt").write_text("\n".join(report), encoding="utf-8")
print("done")
