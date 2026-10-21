# Brand guide — Daleel Qatar | دليل قطر

## Mark
A bold **D** (for *Daleel*, "guide") holding a Doha skyline — a spired tower, Doha Tower with its dome and
needle, and the Tornado Tower — rising from the Gulf. Two sweeping waves form the base: the sea, and the
journey of discovery. Built on a 120 × 120 grid; the mark is fully vector and legible down to 16 px.

## Files (`public/brand/`)

| File | Use |
|---|---|
| `daleel-qatar-logo-stacked-light.svg` / `-dark.svg` | Primary lockup (mark, دليل قطر, DALEELQATAR) — matches the reference artwork |
| `daleel-qatar-logo-bilingual-light.svg` / `-dark.svg` | Horizontal combined Arabic + Latin lockup |
| `daleel-qatar-logo-ar-light.svg` / `-dark.svg` | Arabic lockup |
| `daleel-qatar-logo-en-light.svg` / `-dark.svg` | English lockup with "QATAR BUSINESS DIRECTORY" |
| `daleel-qatar-icon-light.svg` / `-dark.svg` / `-mono.svg` | Icon only (mono uses `currentColor`) |
| `daleel-qatar-app-icon.svg`, `icon-192.png`, `icon-512.png`, `icon-1024.png`, `icon-maskable-512.png` | App / PWA icons (navy tile) |
| `daleel-qatar-app-icon-ivory.svg` | Light app tile |
| `favicon.svg`, `favicon-16.png`, `favicon-32.png`, `/favicon.ico`, `apple-touch-icon.png` | Browser icons |
| `og-image.jpg` | 1200 × 630 social sharing image |
| `/images/doha-skyline.svg` | Original hero illustration (replaceable from *Admin → Homepage*) |

All wordmarks are converted to vector outlines (no font dependency). "light" = for light backgrounds, "dark" = for navy backgrounds.
On the website the logo renders inline via `<x-logo>` (`App\Support\Brand::mark()`).

## Colours

| Token | Hex | Use |
|---|---|---|
| Midnight Navy | `#101C32` | Primary surfaces, text, buttons |
| Champagne Gold | `#D7B875` | Accent only (icons, highlights, primary CTA on dark) |
| Gold (text on light) | `#8A6A2E` | Gold-coloured text on white/ivory (WCAG AA) |
| Warm Ivory | `#F7F4ED` | Page background |
| Pure White | `#FFFFFF` | Cards |
| Charcoal | `#20242C` | Body text |
| Muted Gray | `#747B86` | Secondary/icon colour (use `#5F6672` for small text) |

Chart palette (validated for colour-blind separation): `#2E68B0 #C48A1C #9B2246 #16917F #E07B39 #7262B8` — see `App\Support\ChartPalette`.

## Typography
* Arabic: **IBM Plex Sans Arabic** (300–700), line-height 1.75
* English: **Inter** (variable)
* Both are self-hosted via Fontsource (no third-party font requests).

## Voice
* Slogan — AR: «دليل قطر — اكتشف قطر، بكل ما فيها.» · EN: "Daleel Qatar — Discover Qatar. Find Everything."
* Subtitle — AR: «دليلك الشامل للأعمال والخدمات في قطر.» · EN: "Your complete guide to businesses and services in Qatar."
* Editable in *Admin → Settings → Brand*.
