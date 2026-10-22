/**
 * Lector de cédula / tarjeta de identidad (reverso).
 *
 * Lee el código PDF417 del documento con ZXing y, si no es posible, recurre a la
 * zona MRZ (caracteres inferiores) con OCR. El módulo expone funciones puras
 * para que el formulario de registro las use sin depender del resto del portal.
 */

import { BrowserMultiFormatReader } from 'https://cdn.jsdelivr.net/npm/@zxing/browser@0.1.5/+esm';
import { DecodeHintType, BarcodeFormat } from 'https://cdn.jsdelivr.net/npm/@zxing/library@0.21.0/+esm';
import { decodeColombianPdf417 } from 'https://cdn.jsdelivr.net/npm/colombia-id-parser@1.0.0/+esm';
import { extractMrzLines, parseTD1 } from './mrz.js';

const hints = new Map();
hints.set(DecodeHintType.POSSIBLE_FORMATS, [BarcodeFormat.PDF_417]);
hints.set(DecodeHintType.TRY_HARDER, true);
hints.set(DecodeHintType.CHARACTER_SET, 'ISO-8859-1'); // conserva los bytes 1:1
const reader = new BrowserMultiFormatReader(hints);

let ocrWorker = null;

const loadImage = (url) => new Promise((res, rej) => {
  const img = new Image();
  img.onload = () => res(img);
  img.onerror = () => rej(new Error('No se pudo abrir la imagen.'));
  img.src = url;
});

const canvasFromImage = (img, maxSide) => {
  const scale = Math.min(1, maxSide / Math.max(img.naturalWidth, img.naturalHeight));
  const canvas = document.createElement('canvas');
  canvas.width = Math.round(img.naturalWidth * scale);
  canvas.height = Math.round(img.naturalHeight * scale);
  canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
  return canvas;
};

const canvasToBlob = (canvas, type = 'image/jpeg', quality = 0.85) =>
  new Promise((resolve, reject) => {
    if (!canvas.toBlob) {
      reject(new Error('Este navegador no permite procesar la imagen.'));
      return;
    }
    canvas.toBlob((blob) => {
      if (blob) resolve(blob);
      else reject(new Error('No se pudo procesar la imagen.'));
    }, type, quality);
  });

/**
 * Reduce la imagen para que la carga al servidor sea liviana (máx. 1600 px).
 */
export async function normalizeImage(file, maxSide = 1600) {
  const url = URL.createObjectURL(file);

  try {
    const image = await loadImage(url);
    const canvas = canvasFromImage(image, maxSide);

    return await canvasToBlob(canvas, 'image/jpeg', 0.85);
  } finally {
    URL.revokeObjectURL(url);
  }
}

export const normalizeBlob = async (blob, maxSide = 1600) =>
  normalizeImage(new File([blob], 'reverso.jpg', { type: blob.type || 'image/jpeg' }), maxSide);

/**
 * Intenta decodificar el PDF417 probando varios tamaños (las fotos de celular
 * son enormes y el código puede quedar ilegible si se envía sin reducir).
 */
async function decodePdf417(blob) {
  const url = URL.createObjectURL(blob);

  try {
    const image = await loadImage(url);

    for (const side of [2000, 1400, 1000, 700]) {
      try {
        return await reader.decodeFromCanvas(canvasFromImage(image, side));
      } catch (_) {
        // siguiente tamaño
      }
    }
  } finally {
    URL.revokeObjectURL(url);
  }

  return null;
}

const PDF417_MARKER = 'PubDSK_';
const PDF417_MARKER_OFFSET = 24; // posición canónica de la marca en el esquema colombiano

/**
 * Reubica la carga cruda del PDF417 para que la marca "PubDSK_" quede en el
 * byte 24, que es la posición que espera el parser colombiano.
 *
 * El parser corta el texto en posiciones fijas: número de documento en 48,
 * primer apellido en 58, segundo apellido en 81, nombres en 104 y 127. Si la
 * carga llega corrida un solo byte, el número de documento y los nombres salen
 * corruptos (o vacíos), que es justo lo que reportan las personas al leer su
 * cédula.
 */
export function alignPdf417Raw(raw) {
  if (typeof raw !== 'string') return '';

  const marker = raw.indexOf(PDF417_MARKER);
  if (marker < 0) return raw;

  const start = marker - PDF417_MARKER_OFFSET;

  if (start === 0) return raw;
  if (start > 0) return raw.slice(start);

  // Lectura truncada: el relleno inicial que falta son bytes nulos.
  return '\0'.repeat(-start) + raw;
}

/**
 * Lista de cargas candidatas a parsear, de la más probable a la menos.
 */
function pdf417Candidates(raw) {
  const list = [];
  const marker = raw.indexOf(PDF417_MARKER);

  if (marker >= 0) {
    const base = marker - PDF417_MARKER_OFFSET;

    if (base >= 0) {
      for (let delta = 0; delta >= -3; delta--) {
        const start = base + delta;
        if (start < 0) continue;
        const candidate = raw.slice(start);
        if (!list.includes(candidate)) list.push(candidate);
      }
    } else {
      const padded = '\0'.repeat(-base) + raw;
      if (!list.includes(padded)) list.push(padded);
    }
  }

  if (!list.includes(raw)) list.push(raw);

  return list;
}

const cleanText = (value) => String(value || '').replace(/\0/g, ' ').replace(/\s+/g, ' ').trim();

const isIsoDate = (value) => {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value || '')) return false;

  const [y, m, d] = value.split('-').map(Number);
  if (m < 1 || m > 12 || d < 1 || d > 31) return false;

  const date = new Date(Date.UTC(y, m - 1, d));

  return date.getUTCFullYear() === y
    && date.getUTCMonth() === m - 1
    && date.getUTCDate() === d;
};

/**
 * El número de documento debe ser solo dígitos (4 a 12). Si trae letras es
 * que la carga quedó corrida y no sirve.
 */
const looksLikeDocument = (value) => {
  const text = String(value || '').trim();
  return /^\d{4,12}$/.test(text);
};

/**
 * Re-deriva los campos que dependen de posiciones fijas (sexo, fecha, RH)
 * usando el esquema colombiano y, si aún así no cuadran, anclando el patrón
 * real [M|F] + AAAAMMDD.
 */
function fixPdf417Fields(aligned, data) {
  const gender = aligned.substring(151, 152);
  const y = aligned.substring(152, 156);
  const mo = aligned.substring(156, 158);
  const d = aligned.substring(158, 160);

  if (/^\d{8}$/.test(y + mo + d)) {
    const year = Number(y), month = Number(mo), day = Number(d);

    if (year >= 1900 && year <= new Date().getFullYear()
      && month >= 1 && month <= 12 && day >= 1 && day <= 31) {
      data.gender = /^[MF]$/.test(gender) ? gender : data.gender;
      data.birthDate = `${d}-${mo}-${y}`;      // formato original de la librería
      data.birthDateISO = `${y}-${mo}-${d}`;   // AAAA-MM-DD, listo para tu app/BD
      data.birthDateVerified = true;
    }
  }

  if (!data.birthDateISO) {
    const START = 100; // después de los nombres, que solo contienen letras
    const m = /([MF])(\d{4})(\d{2})(\d{2})/.exec(aligned.slice(START));

    if (m) {
      const [, sex, year, month, day] = m;
      const valid = Number(year) >= 1900 && Number(year) <= new Date().getFullYear()
        && Number(month) >= 1 && Number(month) <= 12
        && Number(day) >= 1 && Number(day) <= 31;

      if (valid) {
        const idx = START + m.index;
        data.gender = sex;
        data.birthDate = `${day}-${month}-${year}`;
        data.birthDateISO = `${year}-${month}-${day}`;
        data.birthDateVerified = true;

        const rh = /(AB|A|B|O)[+-]/.exec(aligned.slice(idx + 9, idx + 30));
        if (rh) data.bloodType = rh[0];
      }
    }
  }

  data.firstName = cleanText(data.firstName);
  data.middleName = cleanText(data.middleName);
  data.lastName = cleanText(data.lastName);
  data.secondLastName = cleanText(data.secondLastName);
  data.bloodType = cleanText(data.bloodType);

  return data;
}

const isPlausibleId = (data) => {
  if (!data || !data.documentInfo) return false;
  if (!looksLikeDocument(data.documentInfo.documentNumber)) return false;
  if (!isIsoDate(data.birthDateISO)) return false;

  return !!(data.firstName || data.lastName);
};

/**
 * Interpreta la carga cruda del PDF417 de una cédula o tarjeta de identidad.
 *
 * Devuelve null si los datos no son coherentes (número de documento no
 * numérico, fecha imposible o sin nombres), para que el lector pueda
 * recurrir a la zona MRZ.
 */
export function parseCedula(raw) {
  if (typeof raw !== 'string' || !raw) return null;

  for (const candidate of pdf417Candidates(raw)) {
    const data = decodeColombianPdf417(candidate);
    if (!data) continue;

    const fixed = fixPdf417Fields(candidate, data);
    if (isPlausibleId(fixed)) return fixed;
  }

  return null;
}

/**
 * Respaldo: leer los caracteres inferiores (MRZ) con OCR.
 */
async function readMrz(blob, onStatus) {
  const url = URL.createObjectURL(blob);
  const img = await loadImage(url);
  URL.revokeObjectURL(url);

  if (!ocrWorker) {
    onStatus('Cargando el lector de caracteres (solo la primera vez)…');
    const { createWorker } = await import('https://cdn.jsdelivr.net/npm/tesseract.js@5.1.1/+esm');
    ocrWorker = await createWorker('eng');
    await ocrWorker.setParameters({
      tessedit_char_whitelist: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789<',
      tessedit_pageseg_mode: '6',
    });
  }

  let best = null;
  for (const frac of [0.4, 0.3, 0.55]) { // distintos recortes de la parte inferior
    onStatus('Leyendo los caracteres inferiores del documento…');
    const { data: { text } } = await ocrWorker.recognize(cropBottom(img, frac));
    const lines = extractMrzLines(text);
    if (!lines) continue;
    const r = parseTD1(lines);
    if (r.checks.birth && r.checks.document) return r;
    best = best || r;
  }
  if (best) return best;

  throw new Error('No se pudo leer ni el código de barras ni la zona de caracteres. '
    + 'Prueba con una foto más nítida, de frente y bien iluminada.');
}

function cropBottom(img, frac) {
  const sy = Math.round(img.naturalHeight * (1 - frac));
  const sh = img.naturalHeight - sy;
  const scale = Math.min(2.5, 1800 / img.naturalWidth);
  const canvas = document.createElement('canvas');
  canvas.width = Math.round(img.naturalWidth * scale);
  canvas.height = Math.round(sh * scale);
  const ctx = canvas.getContext('2d');
  ctx.filter = 'grayscale(1) contrast(1.6)';
  ctx.drawImage(img, 0, sy, img.naturalWidth, sh, 0, 0, canvas.width, canvas.height);
  return canvas;
}

/**
 * Lee un archivo (foto o captura) y devuelve los datos del documento.
 *
 * @returns {Promise<{data: object, blob: Blob, raw: string}>}
 */
export async function readDocument(file, onStatus = () => {}) {
  const blob = await normalizeImage(file);

  onStatus('Leyendo el código de barras del documento…');

  const result = await decodePdf417(blob);

  if (result) {
    const raw = result.getText();
    const data = parseCedula(raw);

    if (data) {
      return { data, blob, raw };
    }

    // El código se leyó pero sus datos no son coherentes: se intenta la zona
    // de caracteres inferiores antes de fallar.
    onStatus('El código de barras no dio datos válidos. Probando con los caracteres inferiores…');
  } else {
    onStatus('No se leyó el código de barras. Probando con los caracteres inferiores…');
  }

  const mrz = await readMrz(blob, onStatus);

  return { data: mrz.data, blob, raw: mrz.text };
}

/**
 * Enciende la cámara y entrega la imagen cuando el código es legible.
 */
export async function startCamera(video, onResult, onStatus = () => {}) {
  onStatus('Apunta la cámara al código de barras del reverso…');

  return reader.decodeFromConstraints(
    { video: { facingMode: { ideal: 'environment' }, width: { ideal: 1920 } } },
    video,
    (result, _error, controls) => {
      if (!result) return;

      const canvas = document.createElement('canvas');
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      canvas.getContext('2d').drawImage(video, 0, 0);

      if (controls && typeof controls.stop === 'function') controls.stop();

      canvas.toBlob(async (blob) => {
        try {
          const normalized = await normalizeBlob(blob);
          onResult(normalized);
        } catch (error) {
          onStatus(error.message || 'No se pudo procesar la captura.', true);
        }
      }, 'image/jpeg', 0.9);
    }
  );
}

/**
 * Coloca un archivo generado en un input de tipo file (para enviar la captura
 * de la cámara junto con el formulario).
 */
export function setFileFromBlob(input, blob, filename = 'reverso.jpg') {
  try {
    const transfer = new DataTransfer();
    transfer.items.add(new File([blob], filename, { type: blob.type || 'image/jpeg' }));
    input.files = transfer.files;
    return true;
  } catch (_) {
    return false;
  }
}

/**
 * Codifica la imagen en base64 (respaldo cuando el navegador no permite
 * asignar archivos a un input).
 */
export function blobToBase64(blob) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(String(reader.result).split(',')[1] ?? '');
    reader.onerror = () => reject(new Error('No se pudo procesar la imagen.'));
    reader.readAsDataURL(blob);
  });
}
