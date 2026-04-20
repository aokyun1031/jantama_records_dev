/**
 * OGP 画像生成スクリプト
 * scripts/ogp-template.html を Playwright chromium でレンダリングし、
 * public/img/ogp-landing.{png,jpg} を出力する。
 *
 * 実行: (tests/e2e/node_modules の playwright を使う)
 *   node scripts/generate-ogp.js
 */
'use strict';

const fs = require('fs');
const path = require('path');
const { chromium } = require(path.join(__dirname, '..', 'tests', 'e2e', 'node_modules', 'playwright'));

const ROOT = path.resolve(__dirname, '..');
const TEMPLATE = path.join(__dirname, 'ogp-template.html');
const OUT_DIR = path.join(ROOT, 'public', 'img');
const CHARA_DIR = path.join(ROOT, 'public', 'img', 'chara_deformed');

(async () => {
  const files = fs.readdirSync(CHARA_DIR).filter(f => f.endsWith('.png'));
  if (files.length < 3) {
    console.error('キャラ画像不足');
    process.exit(1);
  }
  // 重複なしランダム3体
  const pool = files.slice();
  const picks = [];
  for (let i = 0; i < 3; i++) {
    const idx = Math.floor(Math.random() * pool.length);
    picks.push(pool.splice(idx, 1)[0]);
  }
  console.log('選出キャラ:', picks.join(', '));

  let html = fs.readFileSync(TEMPLATE, 'utf8');
  picks.forEach((file, i) => {
    const url = 'file://' + path.join(CHARA_DIR, file);
    html = html.replace('CHARA_' + (i + 1), url);
  });

  const tmpHtml = path.join(__dirname, '.ogp-rendered.html');
  fs.writeFileSync(tmpHtml, html);

  const browser = await chromium.launch();
  const context = await browser.newContext({
    viewport: { width: 1200, height: 630 },
    deviceScaleFactor: 2,
  });
  const page = await context.newPage();
  await page.goto('file://' + tmpHtml, { waitUntil: 'networkidle' });
  // Google Fonts の安定化
  await page.waitForTimeout(500);

  const pngPath = path.join(OUT_DIR, 'ogp-landing.png');
  const jpgPath = path.join(OUT_DIR, 'ogp-landing.jpg');

  await page.screenshot({
    path: pngPath,
    type: 'png',
    clip: { x: 0, y: 0, width: 1200, height: 630 },
  });
  await page.screenshot({
    path: jpgPath,
    type: 'jpeg',
    quality: 92,
    clip: { x: 0, y: 0, width: 1200, height: 630 },
  });

  await browser.close();
  fs.unlinkSync(tmpHtml);

  console.log('OGP生成完了:');
  console.log('  PNG:', pngPath);
  console.log('  JPG:', jpgPath);
})();
