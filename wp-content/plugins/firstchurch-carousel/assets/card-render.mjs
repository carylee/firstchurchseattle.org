/* GENERATED from @church/carousel-card@0.1.0 — DO NOT EDIT.
 * Regenerate: (cd hocuspocus/packages/carousel-card && npm run build:browser)
 * Source of truth for the six carousel card layouts; shared with the slides GIF. */

// src/types.ts
var CARD_LAYOUTS = [
  "intro",
  "divider",
  "qr_callout",
  "event",
  "info",
  "feature"
];
var ANNOUNCEMENT_VARIANTS = ["preservice", "postservice"];
var CARD_W = 1280;
var CARD_H = 720;
var ACCENT = "#D4A256";

// src/fit.ts
var PX_PER_IN = CARD_W / 10;
var PT2PX = PX_PER_IN / 72;
var CARD_EM = (bold) => bold ? 0.51 : 0.49;
function cardFitSize(lines, opts) {
  const { maxWidthPt, maxHeightPt, ladder } = opts;
  const lineHeight = opts.lineHeight ?? 1.25;
  if (lines.length === 0) return ladder[0] ?? 0;
  const longest = Math.max(...lines.map((ln) => ln.length), 0);
  const n = lines.length;
  const e = CARD_EM(opts.bold ?? false);
  for (const size of ladder) {
    if (longest * size * e <= maxWidthPt && n * size * lineHeight <= maxHeightPt) return size;
  }
  return ladder[ladder.length - 1] ?? 0;
}
function wrapText(s, perLine) {
  const words = s.split(/\s+/).filter(Boolean);
  const out = [];
  let cur = "";
  for (const w of words) {
    if (cur && cur.length + 1 + w.length > perLine) {
      out.push(cur);
      cur = w;
    } else {
      cur = cur ? `${cur} ${w}` : w;
    }
  }
  if (cur) out.push(cur);
  return out.length ? out : [""];
}
function fitBodyPt(text, boxWPt, boxHPt, ladder, em = 0.52) {
  for (const size of ladder) {
    const perLine = Math.max(8, Math.floor(boxWPt / (size * em)));
    let lines = 0;
    for (const block of text.split("\n")) lines += block.trim() ? wrapText(block, perLine).length : 1;
    if (lines * size * 1.25 <= boxHPt) return size;
  }
  return ladder[ladder.length - 1] ?? 0;
}

// src/render.ts
var esc = (s) => s.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
var attr = (s) => s.replace(/&/g, "&amp;").replace(/"/g, "&quot;");
var v = (s) => s == null ? "" : String(s);
function titleEl(cls, title, ladderPt, boxWIn, boxHIn) {
  const lines = title.split("\n");
  const sizePt = cardFitSize(lines, {
    maxWidthPt: boxWIn * 72,
    maxHeightPt: boxHIn * 72,
    ladder: ladderPt,
    bold: true
  });
  const px = (sizePt * PT2PX).toFixed(1);
  return `<div class="${cls}" style="font-size:${px}px">${lines.map(esc).join("<br/>")}</div>`;
}
function bodyHtml(body) {
  return body.split("\n").map((line) => {
    const t = line.trim();
    if (!t) return "";
    return t.startsWith("- ") ? `<div class="ann-bullet">${esc(t.slice(2))}</div>` : `<div>${esc(t)}</div>`;
  }).join("");
}
function photoBg(assets, fallbackColor) {
  if (assets.image) {
    return {
      style: "",
      layers: `<img class="ann-bg" src="${attr(assets.image)}" alt=""/><div class="ann-grad"></div>`
    };
  }
  return { style: `background-color:${fallbackColor}`, layers: "" };
}
var qrCorner = (assets, extra = "") => assets.qr ? `<span class="ann-qr ann-qr-corner ${extra}"><img src="${attr(assets.qr)}" alt=""/></span>` : "";
function introCard(card, assets) {
  const bg = photoBg(assets, card.backgroundColor || "#2A4D6E");
  let top = "";
  if (assets.logo) {
    top = `<img class="ann-logo" src="${attr(assets.logo)}" alt=""/>`;
  } else {
    const headline = v(card.headline) || v(card.title);
    if (headline) {
      top = `<div class="ann-intro-head">${titleEl("ann-headline", headline, [114, 96, 84, 72, 60], 9, 1.4)}` + (card.subline ? `<div class="ann-subline">${esc(v(card.subline))}</div>` : "") + `</div>`;
    }
  }
  return `<div class="ann-stage ann-intro" style="${bg.style}">${bg.layers}${top}<div class="ann-rule ann-rule-center"></div><div class="ann-intro-body">${esc(v(card.body))}</div></div>`;
}
function dividerCard(card, assets) {
  const bg = photoBg(assets, card.backgroundColor || "#2A2A2A");
  const title = titleEl("ann-title ann-title-xl", v(card.title), [80, 72, 64, 56, 48], 8.4, 1.9);
  return `<div class="ann-stage ann-divider" style="${bg.style}">${bg.layers}<div class="ann-center">${title}<div class="ann-rule ann-rule-center"></div></div>` + qrCorner(assets) + `</div>`;
}
function qrCalloutCard(card, assets) {
  const color = card.backgroundColor || "#1F1F1F";
  const prompt = v(card.prompt) || v(card.body) || v(card.title);
  const lines = prompt.split("\n");
  const sizePt = cardFitSize(lines, { maxWidthPt: 9 * 72, maxHeightPt: 1.1 * 72, ladder: [44, 40, 36, 32, 28], bold: true });
  const px = (sizePt * PT2PX).toFixed(1);
  const qr = assets.qr ? `<span class="ann-qr ann-qr-big"><img src="${attr(assets.qr)}" alt=""/></span>` : "";
  return `<div class="ann-stage ann-callout" style="background-color:${color}"><div class="ann-prompt" style="font-size:${px}px">${lines.map(esc).join("<br/>")}</div><div class="ann-rule ann-rule-center"></div>${qr}</div>`;
}
function eventCard(card, assets) {
  const bg = photoBg(assets, card.backgroundColor || "#2A2A2A");
  const title = titleEl("ann-title ann-title-lg", v(card.title), [54, 48, 44, 40, 36, 32, 28], 8.4, 1.6);
  const where = v(card.where);
  const when = v(card.when);
  const sub = `<div class="ann-sub">` + (where ? `<div class="ann-where">${esc(where)}</div>` : "") + (when ? `<div class="ann-when">${esc(when)}</div>` : "") + `</div>`;
  return `<div class="ann-stage ann-event" style="${bg.style}">${bg.layers}<div class="ann-center">${title}<div class="ann-rule ann-rule-center"></div>${sub}</div>` + qrCorner(assets) + `</div>`;
}
function infoCard(card, assets) {
  const bg = photoBg(assets, card.backgroundColor || "#2A2A2A");
  const title = titleEl("ann-title ann-title-left", v(card.title), [44, 40, 36, 32, 28], 8.4, 1);
  const body = v(card.body);
  const px = (fitBodyPt(body, 8.8 * 72 - 16, 2.6 * 72 - 16, [28, 24, 22, 20, 18, 16]) * PT2PX).toFixed(1);
  return `<div class="ann-stage ann-info" style="${bg.style}">${bg.layers}<div class="ann-left">${title}<div class="ann-rule ann-rule-full"></div><div class="ann-body" style="font-size:${px}px">${bodyHtml(body)}</div></div>` + qrCorner(assets, "ann-qr-lg") + `</div>`;
}
function featureCard(card, assets) {
  const color = card.backgroundColor || "#2A2A2A";
  const cover = assets.image ? `<img class="ann-cover" src="${attr(assets.image)}" alt=""/>` : "";
  const title = titleEl("ann-title ann-title-md", v(card.title), [32, 28, 26, 24, 22], 4.5, 1.8);
  const qr = assets.qr ? `<span class="ann-qr ann-qr-feature"><img src="${attr(assets.qr)}" alt=""/></span>` : "";
  return `<div class="ann-stage ann-feature" style="background-color:${color}">${cover}<div class="ann-feature-right">${title}<div class="ann-details">${esc(v(card.details))}</div><div class="ann-rule ann-rule-left"></div>${qr}</div></div>`;
}
var LAYOUTS = {
  intro: introCard,
  divider: dividerCard,
  qr_callout: qrCalloutCard,
  event: eventCard,
  info: infoCard,
  feature: featureCard
};
function renderCardHtml(card, assets = {}) {
  const fn = LAYOUTS[card.layout];
  if (!fn) return `<div class="ann-stage ann-unknown">Unknown card layout: ${esc(v(card.layout))}</div>`;
  return fn(card, assets);
}

// src/css.ts
function cardCss(opts = {}) {
  return `${opts.fontFaces ?? ""}
.ann-stage{position:relative;width:${CARD_W}px;height:${CARD_H}px;overflow:hidden;
  font-family:'Raleway',-apple-system,system-ui,sans-serif;color:#fff;
  background-color:#1f1f1f;box-sizing:border-box;}
.ann-stage *{box-sizing:border-box;}
.ann-bg{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;
  filter:blur(3px) brightness(.65);transform:scale(1.06);}
.ann-grad{position:absolute;inset:0;
  background:linear-gradient(to bottom,rgba(0,0,0,.65),rgba(0,0,0,.05));}
.ann-rule{background:${ACCENT};height:6px;border-radius:1px;}
.ann-rule-center{width:96px;margin:18px auto;}
.ann-rule-full{width:100%;margin:14px 0;}
.ann-rule-left{width:64px;margin:18px 0;}
.ann-title{position:relative;font-weight:700;line-height:1.1;}
.ann-center{position:absolute;inset:0;display:flex;flex-direction:column;
  align-items:center;justify-content:center;text-align:center;padding:48px 80px;}
.ann-sub{position:relative;margin-top:4px;}
.ann-where{font-style:italic;font-size:42px;margin-bottom:8px;}
.ann-when{font-size:40px;}
.ann-intro{display:flex;flex-direction:column;align-items:center;
  justify-content:flex-start;text-align:center;padding:64px 96px;}
.ann-logo{position:relative;width:760px;max-height:300px;object-fit:contain;margin-top:24px;}
.ann-intro-head{position:relative;width:100%;text-align:left;margin-top:24px;}
.ann-headline{font-weight:700;line-height:1.05;}
.ann-subline{font-size:36px;font-style:italic;margin-top:4px;}
.ann-intro-body{position:relative;font-size:28px;max-width:1024px;margin-top:8px;line-height:1.3;}
.ann-callout{display:flex;flex-direction:column;align-items:center;
  justify-content:flex-start;text-align:center;padding:48px 80px;}
.ann-prompt{position:relative;font-weight:700;line-height:1.15;margin-top:24px;}
.ann-left{position:absolute;inset:0;display:flex;flex-direction:column;
  align-items:flex-start;justify-content:flex-start;text-align:left;padding:56px 76px;}
.ann-body{position:relative;line-height:1.3;max-width:1090px;margin-top:6px;}
.ann-bullet{position:relative;padding-left:1.1em;margin:.12em 0;}
.ann-bullet:before{content:'\u2022';position:absolute;left:0;color:${ACCENT};}
.ann-feature-right{position:absolute;left:640px;top:0;bottom:0;width:600px;
  display:flex;flex-direction:column;justify-content:center;
  align-items:flex-start;text-align:left;padding:64px 56px 64px 0;}
.ann-cover{position:absolute;left:64px;top:52px;height:615px;object-fit:contain;}
.ann-details{font-style:italic;font-size:28px;margin-top:14px;line-height:1.3;}
.ann-qr{background:#fff;padding:14px;border-radius:4px;}
.ann-qr img{display:block;width:100%;height:100%;image-rendering:pixelated;}
.ann-qr-corner{position:absolute;right:54px;bottom:54px;width:170px;height:170px;}
.ann-qr-lg{width:210px;height:210px;}
.ann-qr-big{position:relative;width:430px;height:430px;margin-top:12px;}
.ann-qr-feature{position:relative;width:170px;height:170px;margin-top:22px;}
.ann-unknown{display:grid;place-items:center;color:#888;font-size:28px;}
`;
}

// src/util.ts
function selectCards(cards, variant) {
  return variant === "postservice" ? cards.filter((c) => !c.preserviceOnly) : cards;
}
function withUtm(url, opts) {
  const qi = url.indexOf("?");
  const base = qi === -1 ? url : url.slice(0, qi);
  const params = new URLSearchParams(qi === -1 ? "" : url.slice(qi + 1));
  if (!params.has("utm_source")) params.set("utm_source", opts.source);
  if (!params.has("utm_medium")) params.set("utm_medium", opts.medium);
  if (opts.campaign && !params.has("utm_campaign")) params.set("utm_campaign", `service_${opts.campaign}`);
  const qs = params.toString();
  return qs ? `${base}?${qs}` : base;
}
function cardHasContent(card) {
  switch (card.layout) {
    case "intro":
      return Boolean(card.body || card.headline || card.title || card.logo);
    case "qr_callout":
      return Boolean(card.prompt || card.body || card.title);
    case "feature":
      return Boolean(card.title || card.details);
    default:
      return Boolean(card.title);
  }
}
export {
  ACCENT,
  ANNOUNCEMENT_VARIANTS,
  CARD_H,
  CARD_LAYOUTS,
  CARD_W,
  PT2PX,
  cardCss,
  cardFitSize,
  cardHasContent,
  fitBodyPt,
  renderCardHtml,
  selectCards,
  withUtm,
  wrapText
};
