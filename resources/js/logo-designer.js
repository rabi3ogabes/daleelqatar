// Branded logo designer for the admin logo studio.
// Drawn on a canvas in the admin's browser so Arabic names are shaped by the
// browser's text engine with the site's own fonts (Inter, IBM Plex Sans Arabic).

export const SIZE = 600;

const ARABIC = /[؀-ۿݐ-ݿﭐ-﷿ﹰ-﻿]/;

const LATIN_SKIP = new Set(['al', 'el', 'the', 'and', '&', 'of', 'for', 'a', 'co', 'company', 'llc', 'wll', 'spc', 'qpsc', 'qsc', 'qfz', 'est', 'establishment', 'trading', 'group']);
const ARABIC_SKIP = new Set(['شركة', 'مؤسسة', 'مجموعة', 'محل', 'مكتب', 'و', 'ذ.م.م', 'للتجارة']);

const STYLES = {
    navy: {
        bg: ['#18284D', '#0B1426'], glow: 'rgba(215,184,117,0.22)', frame: 'rgba(215,184,117,0.38)',
        ring: 'gold', disc: null, initials: 'gold', name: '#F7F4ED', secondary: '#E3C88C', signature: 'rgba(227,200,140,0.85)', icon: 'dark',
    },
    ivory: {
        bg: ['#FCFAF4', '#F1EADA'], glow: 'rgba(215,184,117,0.20)', frame: 'rgba(168,132,63,0.35)',
        ring: 'gold', disc: '#101C32', initials: 'gold', name: '#101C32', secondary: '#8C6D33', signature: 'rgba(140,109,51,0.9)', icon: 'light',
    },
    gold: {
        bg: ['#EBD6A4', '#B48D47'], glow: 'rgba(255,255,255,0.30)', frame: 'rgba(16,28,50,0.28)',
        ring: '#101C32', disc: null, initials: '#101C32', name: '#101C32', secondary: '#2A3A5C', signature: 'rgba(16,28,50,0.78)', icon: 'mono',
    },
    white: {
        bg: ['#FFFFFF', '#FFFFFF'], glow: null, frame: 'rgba(16,28,50,0.10)',
        ring: 'gold', disc: null, initials: '#101C32', name: '#101C32', secondary: '#8C6D33', signature: 'rgba(16,28,50,0.6)', icon: 'light',
    },
};

export const STYLE_NAMES = Object.keys(STYLES);

export const isArabic = (text) => ARABIC.test(text || '');

/** Monogram letters: first letters of the first meaningful words, as written. */
export function monogram(name) {
    const text = (name || '').trim();
    if (!text) return '';
    if (isArabic(text)) {
        const words = text.split(/\s+/).filter((w) => !ARABIC_SKIP.has(w));
        const word = (words[0] || text).replace(/^ال(?=\S{2,})/, '');
        return [...word][0] || '';
    }
    const words = text.split(/[\s\-–—/]+/).map((w) => w.replace(/[^\p{L}\p{N}&]/gu, '')).filter(Boolean);
    const meaningful = words.filter((w) => !LATIN_SKIP.has(w.toLowerCase()));
    const pick = (meaningful.length ? meaningful : words).slice(0, 2);
    return pick.map((w) => [...w][0]).join('').toUpperCase();
}

const icons = {};
function brandIcon(variant) {
    const file = { dark: 'daleel-qatar-icon-dark.svg', light: 'daleel-qatar-icon-light.svg', mono: 'daleel-qatar-icon-mono.svg' }[variant];
    icons[file] ??= new Promise((resolve) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = () => resolve(null);
        img.src = `/brand/${file}`;
    });
    return icons[file];
}

let fontsReady = null;
export function loadFonts(sample = '') {
    fontsReady ??= Promise.all([
        document.fonts.load('700 48px "Inter Variable"', 'AZ'),
        document.fonts.load('600 48px "Inter Variable"', 'AZ'),
        document.fonts.load('700 48px "IBM Plex Sans Arabic"', 'دليل قطر'),
        document.fonts.load('500 48px "IBM Plex Sans Arabic"', 'دليل قطر'),
    ]).catch(() => null);
    return sample ? fontsReady.then(() => document.fonts.load('700 48px "IBM Plex Sans Arabic"', sample).catch(() => null)) : fontsReady;
}

const family = (text, weight) => (isArabic(text) ? `${weight} SIZEpx "IBM Plex Sans Arabic", "Inter Variable", sans-serif` : `${weight} SIZEpx "Inter Variable", "IBM Plex Sans Arabic", sans-serif`);

function setFont(ctx, text, weight, size) {
    ctx.font = family(text, weight).replace('SIZE', String(size));
    ctx.direction = isArabic(text) ? 'rtl' : 'ltr';
    // Letter spacing would break Arabic joining; a touch of air suits Latin.
    if ('letterSpacing' in ctx) ctx.letterSpacing = isArabic(text) ? '0px' : `${Math.round(size * 0.01 * 10) / 10}px`;
}

function wrap(ctx, text, maxWidth) {
    const words = text.split(/\s+/).filter(Boolean);
    const lines = [];
    let line = '';
    for (const word of words) {
        const next = line ? `${line} ${word}` : word;
        if (line && ctx.measureText(next).width > maxWidth) {
            lines.push(line);
            line = word;
        } else {
            line = next;
        }
    }
    if (line) lines.push(line);
    return lines;
}

/** Largest size at which the name fits in the given width and number of lines. */
function fit(ctx, text, weight, { max, min, width, lines: maxLines }) {
    for (let size = max; size >= min; size -= 2) {
        setFont(ctx, text, weight, size);
        const lines = wrap(ctx, text, width);
        if (lines.length <= maxLines && lines.every((l) => ctx.measureText(l).width <= width)) return { size, lines };
    }
    setFont(ctx, text, weight, min);
    return { size: min, lines: wrap(ctx, text, width) };
}

function goldGradient(ctx, x0, y0, x1, y1) {
    const g = ctx.createLinearGradient(x0, y0, x1, y1);
    g.addColorStop(0, '#F1DFB1');
    g.addColorStop(0.55, '#D7B875');
    g.addColorStop(1, '#B8924C');
    return g;
}

function roundRect(ctx, x, y, w, h, r) {
    ctx.beginPath();
    if (ctx.roundRect) ctx.roundRect(x, y, w, h, r);
    else ctx.rect(x, y, w, h);
}

/**
 * @param {HTMLCanvasElement} canvas
 * @param {{style: string, name: string, secondary?: string, initials?: string}} opts
 */
export async function drawLogo(canvas, { style, name, secondary = '', initials = '' }) {
    const s = STYLES[style] || STYLES.navy;
    await loadFonts(`${name} ${secondary} ${initials}`);
    const icon = await brandIcon(s.icon);

    canvas.width = SIZE;
    canvas.height = SIZE;
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, SIZE, SIZE);
    ctx.textAlign = 'center';
    ctx.textBaseline = 'alphabetic';

    // Background, soft glow and a fine inner frame.
    const bg = ctx.createLinearGradient(0, 0, SIZE, SIZE);
    bg.addColorStop(0, s.bg[0]);
    bg.addColorStop(1, s.bg[1]);
    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, SIZE, SIZE);
    if (s.glow) {
        const glow = ctx.createRadialGradient(SIZE * 0.5, SIZE * 0.18, 10, SIZE * 0.5, SIZE * 0.18, SIZE * 0.62);
        glow.addColorStop(0, s.glow);
        glow.addColorStop(1, 'rgba(0,0,0,0)');
        ctx.fillStyle = glow;
        ctx.fillRect(0, 0, SIZE, SIZE);
    }
    ctx.strokeStyle = s.frame;
    ctx.lineWidth = 2;
    roundRect(ctx, 24, 24, SIZE - 48, SIZE - 48, 34);
    ctx.stroke();

    // Pick the largest type scale whose whole block (monogram, name, Arabic line) fits above the signature.
    const letters = (initials || monogram(name)).slice(0, 3);
    const available = 452;
    let layout = null;
    for (const max of [60, 54, 48, 42, 36, 30, 26]) {
        const radius = max >= 48 ? 84 : max >= 36 ? 74 : 64;
        const gap = max >= 48 ? 36 : 28;
        const nameFit = fit(ctx, name, 700, { max, min: Math.min(max, 22), width: 468, lines: 3 });
        const nameLine = Math.round(nameFit.size * (isArabic(name) ? 1.42 : 1.16));
        const secondFit = secondary ? fit(ctx, secondary, 500, { max: Math.min(32, Math.round(nameFit.size * 0.62)), min: 16, width: 448, lines: 2 }) : null;
        const secondLine = secondFit ? Math.round(secondFit.size * (isArabic(secondary) ? 1.5 : 1.22)) : 0;
        const height = radius * 2 + gap + nameFit.lines.length * nameLine + (secondFit ? 14 + secondFit.lines.length * secondLine : 0);
        layout = { radius, gap, nameFit, nameLine, secondFit, secondLine, height };
        if (height <= available) break;
    }
    const { radius, gap, nameFit, nameLine, secondFit, secondLine } = layout;
    const top = Math.max(44, Math.round(44 + (available - layout.height) / 2));

    // Monogram: a ring (or a navy disc) holding the initials.
    const cx = SIZE / 2;
    const cy = top + radius;
    if (s.disc) {
        ctx.fillStyle = s.disc;
        ctx.beginPath();
        ctx.arc(cx, cy, radius, 0, Math.PI * 2);
        ctx.fill();
    }
    ctx.lineWidth = 5;
    ctx.strokeStyle = s.ring === 'gold' ? goldGradient(ctx, cx - radius, cy - radius, cx + radius, cy + radius) : s.ring;
    ctx.beginPath();
    ctx.arc(cx, cy, radius, 0, Math.PI * 2);
    ctx.stroke();
    ctx.lineWidth = 1.5;
    ctx.globalAlpha = 0.5;
    ctx.beginPath();
    ctx.arc(cx, cy, radius - 11, 0, Math.PI * 2);
    ctx.stroke();
    ctx.globalAlpha = 1;

    if (letters) {
        const count = [...letters].length;
        const letterSize = Math.round((isArabic(letters) || count === 1 ? 92 : count === 2 ? 74 : 56) * (radius / 84));
        setFont(ctx, letters, 700, letterSize);
        ctx.fillStyle = s.initials === 'gold' ? goldGradient(ctx, cx - radius, cy - radius, cx + radius, cy + radius) : s.initials;
        const m = ctx.measureText(letters);
        const ascent = m.actualBoundingBoxAscent || letterSize * 0.72;
        const descent = m.actualBoundingBoxDescent || 0;
        ctx.fillText(letters, cx, cy + (ascent - descent) / 2);
    }

    // The company name, exactly as written.
    let y = top + radius * 2 + gap;
    setFont(ctx, name, 700, nameFit.size);
    ctx.fillStyle = s.name;
    for (const line of nameFit.lines) {
        y += nameLine;
        ctx.fillText(line, cx, y - Math.round(nameLine * 0.22));
    }
    if (secondFit) {
        y += 14;
        setFont(ctx, secondary, 500, secondFit.size);
        ctx.fillStyle = s.secondary;
        for (const line of secondFit.lines) {
            y += secondLine;
            ctx.fillText(line, cx, y - Math.round(secondLine * 0.24));
        }
    }

    // Daleel Qatar signature.
    const label = 'DALEEL QATAR';
    setFont(ctx, label, 600, 13);
    if ('letterSpacing' in ctx) ctx.letterSpacing = '3.5px';
    const textWidth = ctx.measureText(label).width;
    const iconSize = icon ? 26 : 0;
    const iconGap = icon ? 10 : 0;
    const startX = cx - (iconSize + iconGap + textWidth) / 2;
    const baseY = SIZE - 58;
    if (icon) ctx.drawImage(icon, startX, baseY - 19, iconSize, iconSize);
    ctx.fillStyle = s.signature;
    ctx.textAlign = 'left';
    ctx.direction = 'ltr';
    ctx.fillText(label, startX + iconSize + iconGap, baseY);
    ctx.textAlign = 'center';
    if ('letterSpacing' in ctx) ctx.letterSpacing = '0px';

    return canvas;
}

export const toBlob = (canvas) => new Promise((resolve, reject) => canvas.toBlob((b) => (b ? resolve(b) : reject(new Error('canvas'))), 'image/png'));

/** Draws a vector logo (as an image, so it cannot run scripts) onto a transparent canvas. */
export async function rasterizeSvg(dataUri) {
    const img = new Image();
    img.decoding = 'async';
    img.src = dataUri;
    await img.decode();
    const w = img.naturalWidth || 1200;
    const h = img.naturalHeight || 1200;
    const scale = Math.min(1, 1200 / Math.max(w, h));
    const dw = Math.round(w * scale);
    const dh = Math.round(h * scale);
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(dw, 240);
    canvas.height = Math.max(dh, 240);
    canvas.getContext('2d').drawImage(img, (canvas.width - dw) / 2, (canvas.height - dh) / 2, dw, dh);
    return toBlob(canvas);
}
