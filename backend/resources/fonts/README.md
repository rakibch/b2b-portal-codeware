# PDF fonts

`NotoSansBengali-Regular.ttf` / `NotoSansBengali-Bold.ttf` — Noto Sans Bengali by Google,
licensed under the SIL Open Font License 1.1 (https://openfontlicense.org). Source: Debian `fonts-noto-core`.

Registered in `config/pdf.php` as the `notobengali` family with OpenType Layout enabled (`useOTL => 0xFF`),
which mPDF needs to shape Bangla conjuncts (যুক্তবর্ণ), reph and pre-base vowel signs.

- Bangla documents use `notobengali` as the body font; glyphs it lacks are substituted from DejaVu Sans.
- English documents use DejaVu Sans; the ৳ sign is rendered in `notobengali` via the `.taka` CSS class
  (`App\Support\Localized::currencyHtml()`), since DejaVu has no ৳ glyph.
