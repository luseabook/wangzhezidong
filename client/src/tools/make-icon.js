// 生成应用图标 icon.ico（多尺寸 PNG 打包；零依赖）
// 运行： node src/tools/make-icon.js
import fs from 'node:fs';
import path from 'node:path';
import zlib from 'node:zlib';

const CRC_TABLE = (() => {
  const t = new Int32Array(256);
  for (let n = 0; n < 256; n++) { let c = n; for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1; t[n] = c; }
  return t;
})();
function crc32(buf) { let c = -1; for (let i = 0; i < buf.length; i++) c = CRC_TABLE[(c ^ buf[i]) & 0xff] ^ (c >>> 8); return (c ^ -1) >>> 0; }
function chunk(type, data) {
  const len = Buffer.alloc(4); len.writeUInt32BE(data.length);
  const td = Buffer.concat([Buffer.from(type, 'ascii'), data]);
  const crc = Buffer.alloc(4); crc.writeUInt32BE(crc32(td));
  return Buffer.concat([len, td, crc]);
}

/** 圆角方块 + 橙色渐变 + 白色播放三角 */
function drawIcon(size) {
  const raw = Buffer.alloc((size * 4 + 1) * size);
  const r = size * 0.22;            // 圆角半径
  const inset = size * 0.06;
  let p = 0;
  for (let y = 0; y < size; y++) {
    raw[p++] = 0;
    for (let x = 0; x < size; x++) {
      // 圆角矩形内？
      const x0 = inset, y0 = inset, x1 = size - inset, y1 = size - inset;
      const inRounded = (() => {
        if (x < x0 || x > x1 || y < y0 || y > y1) return false;
        const cx = Math.min(Math.max(x, x0 + r), x1 - r);
        const cy = Math.min(Math.max(y, y0 + r), y1 - r);
        return Math.hypot(x - cx, y - cy) <= r + 0.5;
      })();

      if (!inRounded) { raw[p++] = 0; raw[p++] = 0; raw[p++] = 0; raw[p++] = 0; continue; }

      // 橙色渐变
      const t = (x / size) * 0.55 + (y / size) * 0.45;
      const R = Math.round(255 - t * 40);
      const G = Math.round(138 - t * 60);
      const B = Math.round(30 + t * 10);

      // 中间白色三角（播放符），表示"自动运行"
      const cx = size / 2, cy = size / 2;
      const tw = size * 0.26, th = size * 0.30;
      const dx = x - (cx - tw * 0.35);
      const dy = Math.abs(y - cy);
      const inTri = dx >= 0 && dx <= tw && dy <= (th / 2) * (1 - dx / tw);

      if (inTri) { raw[p++] = 255; raw[p++] = 255; raw[p++] = 255; raw[p++] = 255; }
      else { raw[p++] = R; raw[p++] = G; raw[p++] = B; raw[p++] = 255; }
    }
  }
  const ihdr = Buffer.alloc(13);
  ihdr.writeUInt32BE(size, 0); ihdr.writeUInt32BE(size, 4);
  ihdr[8] = 8; ihdr[9] = 6;
  return Buffer.concat([
    Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
    chunk('IHDR', ihdr),
    chunk('IDAT', zlib.deflateSync(raw, { level: 9 })),
    chunk('IEND', Buffer.alloc(0)),
  ]);
}

/** 多尺寸 ICO（内含 PNG） */
function buildIco(sizes) {
  const images = sizes.map((s) => drawIcon(s));
  const count = images.length;
  const header = Buffer.alloc(6);
  header.writeUInt16LE(0, 0); header.writeUInt16LE(1, 2); header.writeUInt16LE(count, 4);

  const entries = [];
  let offset = 6 + 16 * count;
  for (let i = 0; i < count; i++) {
    const s = sizes[i];
    const e = Buffer.alloc(16);
    e[0] = s >= 256 ? 0 : s;
    e[1] = s >= 256 ? 0 : s;
    e[2] = 0; e[3] = 0;
    e.writeUInt16LE(1, 4);
    e.writeUInt16LE(32, 6);
    e.writeUInt32LE(images[i].length, 8);
    e.writeUInt32LE(offset, 12);
    entries.push(e);
    offset += images[i].length;
  }
  return Buffer.concat([header, ...entries, ...images]);
}

const outDir = path.join(process.cwd(), 'build');
fs.mkdirSync(outDir, { recursive: true });
const ico = buildIco([16, 24, 32, 48, 64, 128, 256]);
fs.writeFileSync(path.join(outDir, 'icon.ico'), ico);
fs.writeFileSync(path.join(outDir, 'icon.png'), drawIcon(256));
console.log('icon 生成完成:', path.join(outDir, 'icon.ico'), ico.length, 'bytes');