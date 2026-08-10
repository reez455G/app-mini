#!/usr/bin/env python3
"""Bikin docs/FAQ.pdf dari docs/FAQ.md.

Jalankan ulang tiap kali FAQ.md diubah:  python3 docs/build-faq-pdf.py

Pakai modul `markdown` + chromium yang sudah ada di sistem — sengaja tidak
memakai pandoc/wkhtmltopdf supaya tidak menambah dependensi baru.
"""

import base64
import pathlib
import subprocess
import sys
import tempfile

import markdown

ROOT = pathlib.Path(__file__).resolve().parent.parent
SRC = ROOT / "docs" / "FAQ.md"
OUT = ROOT / "docs" / "FAQ.pdf"
LOGO = ROOT / "assets" / "logo.png"

# Cover dipisah dari isi: baris pertama FAQ.md (judul + alamat toko) dirender
# sebagai halaman sampul sendiri, sisanya jadi isi biasa.
COVER = """
<div class="cover">
  {logo}
  <h1>FAQ — App-mini</h1>
  <div class="cover-sub">Sistem Stok &amp; Kasir Toko</div>
  <div class="cover-toko">
    <strong>PUTRA JAYA MOTOR</strong><br />
    Jl. Jati Raya Blok J No. 11, Banyumanik, Semarang<br />
    0815-5608-055
  </div>
</div>
"""

CSS = """
@page { size: A4; margin: 18mm 16mm; }
body {
  font-family: Georgia, "Times New Roman", serif;
  font-size: 10.5pt; line-height: 1.55; color: #1b1b1b; margin: 0;
}
/* Sengaja TIDAK pakai flex + min-height: kombinasi itu bikin isi cover
   melebihi tinggi halaman dan logonya terlempar ke halaman sendiri.
   padding-top biasa cukup untuk menaruh cover di tengah halaman A4. */
.cover { text-align: center; page-break-after: always; padding-top: 60mm; }
.cover img { display: block; width: 170px; margin: 0 auto 26px; }
/* page-break-before:avoid wajib: aturan h1 umum di bawah memakai
   page-break-before:always, yang kalau tidak ditimpa bikin judul cover
   terlempar ke halaman kedua dan logonya tertinggal sendirian. */
.cover h1 { font-size: 30pt; margin: 0; border: none; page-break-before: avoid; }
.cover-sub { font-size: 14pt; color: #555; margin-top: 6px; }
.cover-toko { margin-top: 46px; font-size: 11pt; line-height: 1.8; color: #333; }

/* Tiap bab (# Bagian A / # Bagian B) mulai di halaman baru. h1 pertama
   sesudah cover tidak perlu dipaksa, tapi tidak masalah karena cover sudah
   page-break-after. */
h1 {
  font-size: 20pt; margin: 0 0 14px; padding-bottom: 6px;
  border-bottom: 2.5px solid #1b1b1b; page-break-before: always;
}
h2 {
  font-size: 15pt; margin: 26px 0 10px; padding-bottom: 4px;
  border-bottom: 1px solid #bbb; page-break-after: avoid;
}
h3 {
  font-size: 11.5pt; margin: 18px 0 6px; color: #000;
  page-break-after: avoid;
}
p, ul, ol { margin: 0 0 9px; }
li { margin-bottom: 3px; }
code {
  font-family: "DejaVu Sans Mono", Consolas, monospace; font-size: 9pt;
  background: #f0f0f0; padding: 1px 4px; border-radius: 3px;
}
pre {
  background: #f5f5f5; border-left: 3px solid #999; padding: 8px 11px;
  overflow-x: auto; page-break-inside: avoid;
}
pre code { background: none; padding: 0; }
table {
  border-collapse: collapse; width: 100%; margin: 10px 0 14px;
  font-size: 9.5pt; page-break-inside: avoid;
}
th, td { border: 1px solid #bbb; padding: 5px 8px; text-align: left; vertical-align: top; }
th { background: #ececec; font-weight: bold; }
blockquote {
  margin: 10px 0; padding: 7px 13px; border-left: 3px solid #888;
  background: #f7f7f7; page-break-inside: avoid;
}
blockquote p:last-child { margin-bottom: 0; }
hr { border: none; border-top: 1px solid #ddd; margin: 20px 0; }
/* Baris <hr> pemisah tepat sebelum h1 tidak perlu, h1 sudah page-break. */
h1 + p, h2 + p { margin-top: 0; }
strong { color: #000; }
"""

HTML = """<!doctype html>
<html lang="id"><head><meta charset="utf-8">
<title>FAQ — App-mini</title>
<style>{css}</style></head>
<body>{cover}{content}</body></html>
"""


def main() -> int:
    if not SRC.exists():
        print(f"tidak ketemu: {SRC}", file=sys.stderr)
        return 1

    text = SRC.read_text(encoding="utf-8")
    # Buang blok judul+alamat toko di paling atas (sudah jadi cover), yaitu
    # semuanya sampai '---' pertama.
    _, _, body = text.partition("\n---\n")
    body = body or text

    content = markdown.markdown(
        body,
        extensions=["tables", "attr_list", "fenced_code", "sane_lists"],
    )

    if LOGO.exists():
        b64 = base64.b64encode(LOGO.read_bytes()).decode()
        logo_tag = f'<img src="data:image/png;base64,{b64}" alt="" />'
    else:
        logo_tag = ""

    html = HTML.format(css=CSS, cover=COVER.format(logo=logo_tag), content=content)

    with tempfile.NamedTemporaryFile("w", suffix=".html", delete=False, encoding="utf-8") as f:
        f.write(html)
        tmp = pathlib.Path(f.name)

    try:
        subprocess.run(
            [
                "chromium", "--headless=new", "--disable-gpu", "--no-sandbox",
                "--no-pdf-header-footer", "--virtual-time-budget=3000",
                f"--print-to-pdf={OUT}", tmp.as_uri(),
            ],
            check=True, capture_output=True,
        )
    finally:
        tmp.unlink(missing_ok=True)

    print(f"OK: {OUT} ({OUT.stat().st_size // 1024} KB)")
    return 0


if __name__ == "__main__":
    sys.exit(main())
