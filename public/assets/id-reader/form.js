/**
 * Integración del lector de documentos con el formulario de registro.
 *
 * Publica los datos leídos en un campo oculto (id_scan) y completa los campos
 * de documento y fecha de nacimiento, que quedan bloqueados para que la
 * verificación la guíe el documento y no lo que escriba la persona.
 */

import { readDocument, startCamera, setFileFromBlob, blobToBase64 } from './reader.js';

const els = {};

function cacheElements() {
  els.form = document.getElementById('idScanForm');
  els.file = document.getElementById('idScanImage');
  els.payload = document.getElementById('idScan');
  els.status = document.getElementById('idScanStatus');
  els.preview = document.getElementById('idScanPreview');
  els.summary = document.getElementById('idScanSummary');
  els.camera = document.getElementById('idScanCamera');
  els.stop = document.getElementById('idScanStop');
  els.video = document.getElementById('idScanVideo');
  els.document = document.getElementById('idScanDocument');
  els.birthDate = document.getElementById('idScanBirthDate');
  els.name = document.getElementById('idScanName');
  els.submit = document.getElementById('idScanSubmit');
}

function setStatus(message, isError = false) {
  if (!els.status) return;
  els.status.textContent = message;
  els.status.className = 'text-xs font-semibold mt-2 ' + (isError ? 'text-rose-600' : 'text-slate-500');
}

function setBusy(busy) {
  if (els.file) els.file.disabled = busy;
  if (els.camera) els.camera.disabled = busy;
}

function calculateAge(isoDate) {
  const birth = new Date(isoDate + 'T00:00:00');
  if (isNaN(birth.getTime())) return null;

  const today = new Date();
  let age = today.getFullYear() - birth.getFullYear();
  const monthDiff = today.getMonth() - birth.getMonth();

  if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
    age -= 1;
  }

  return age;
}

function fillSummary(data) {
  if (!els.summary) return;

  const rows = [
    ['Documento', data.documentInfo?.documentNumber || '—'],
    ['Nombre', [data.firstName, data.middleName, data.lastName, data.secondLastName]
      .filter(Boolean).join(' ') || '—'],
    ['Fecha de nacimiento', data.birthDateISO || '—'],
  ];

  const age = data.birthDateISO ? calculateAge(data.birthDateISO) : null;
  if (age !== null) rows.push(['Edad', age + ' años']);

  els.summary.innerHTML = rows.map(([label, value]) =>
    '<div class="flex items-center justify-between gap-3 py-1.5 border-b border-slate-100 last:border-0">'
    + '<span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">' + label + '</span>'
    + '<span class="text-xs font-semibold text-slate-800 text-right">' + value + '</span>'
    + '</div>'
  ).join('');
}

/**
 * Nombre completo tal como viene en el documento.
 */
function fullNameFrom(data) {
  return [data?.firstName, data?.middleName, data?.lastName, data?.secondLastName]
    .map((part) => String(part || '').replace(/\s+/g, ' ').trim())
    .filter(Boolean)
    .join(' ');
}

/**
 * Autocompleta el nombre con el del documento escaneado.
 *
 * El campo sigue editable: si el lector confundió una letra (el nombre viene
 * de un código de barras, no de un OCR) la persona puede corregirlo. El
 * servidor valida que el nombre coincida con el documento.
 */
function fillName(data, { onlyIfEmpty = false } = {}) {
  if (!els.name) return;

  if (onlyIfEmpty && els.name.value.trim()) return;

  const fullName = fullNameFrom(data);
  if (!fullName) return;

  els.name.value = fullName;
  els.name.classList.add('bg-slate-50');
  els.name.dataset.autofilled = '1';
}

function lockFields(locked) {
  if (els.document) {
    els.document.readOnly = locked;
    els.document.classList.toggle('bg-slate-50', locked);
  }
  if (els.birthDate) {
    els.birthDate.readOnly = locked;
    els.birthDate.classList.toggle('bg-slate-50', locked);
  }
}

async function applyResult(data, blob) {
  const documentNumber = data?.documentInfo?.documentNumber || '';
  const birthDate = data?.birthDateISO || '';

  if (!documentNumber || !birthDate) {
    setStatus('El documento se leyó pero no trae el número y la fecha de nacimiento. Repite la foto.', true);
    return;
  }

  const payload = {
    source: data.source || 'pdf417',
    firstName: data.firstName || '',
    middleName: data.middleName || '',
    lastName: data.lastName || '',
    secondLastName: data.secondLastName || '',
    birthDate: data.birthDate || '',
    birthDateISO: birthDate,
    bloodType: data.bloodType || '',
    gender: data.gender || '',
    expiryDateISO: data.expiryDateISO || '',
    documentInfo: {
      documentNumber: documentNumber,
      afisCode: data.documentInfo?.afisCode || '',
      fingerCard: data.documentInfo?.fingerCard || '',
    },
  };

  // La imagen viaja en el input de archivo y también codificada en base64:
  // el archivo es la vía normal y el base64 permite reintentar el registro
  // sin tener que volver a tomar la foto.
  const attached = els.file ? setFileFromBlob(els.file, blob, 'reverso.jpg') : false;

  payload.image_base64 = await blobToBase64(blob);

  if (!attached) {
    payload.image_source = 'camera';
  }

  els.payload.value = JSON.stringify(payload);

  if (els.preview) {
    els.preview.src = URL.createObjectURL(blob);
    els.preview.style.display = 'block';
  }

  if (els.document) els.document.value = documentNumber;
  if (els.birthDate) els.birthDate.value = birthDate;
  lockFields(true);
  fillName(payload);

  fillSummary(payload);
  setStatus('Documento leído correctamente. Revisa los datos y continúa.');
  enableSubmit();
}

function enableSubmit() {
  if (els.submit) {
    els.submit.disabled = false;
    els.submit.classList.remove('opacity-50', 'cursor-not-allowed');
  }
}

function disableSubmit() {
  if (els.submit) {
    els.submit.disabled = true;
    els.submit.classList.add('opacity-50', 'cursor-not-allowed');
  }
}

function stopCamera(controls) {
  if (controls && typeof controls.stop === 'function') controls.stop();
  if (els.video) els.video.style.display = 'none';
  if (els.stop) els.stop.hidden = true;
}

async function handleFile(file) {
  if (!file) return;

  setBusy(true);
  setStatus('Procesando la imagen del documento…');

  try {
    const { data, blob } = await readDocument(file, setStatus);
    await applyResult(data, blob);
  } catch (error) {
    setStatus(error.message || 'No se pudo leer el documento.', true);
  } finally {
    setBusy(false);
    if (els.file) els.file.value = '';
  }
}

/**
 * Restaura una lectura previa que el servidor devolvió en el formulario.
 */
function restorePreviousScan() {
  if (!els.payload || !els.payload.value) return;

  let data;
  try {
    data = JSON.parse(els.payload.value);
  } catch (_) {
    return;
  }

  const documentNumber = data?.documentInfo?.documentNumber || '';
  const birthDate = data?.birthDateISO || '';

  if (!documentNumber || !birthDate) return;

  if (els.document) els.document.value = documentNumber;
  if (els.birthDate) els.birthDate.value = birthDate;
  lockFields(true);
  // El nombre ya viene del servidor si la persona alcanzó a enviarlo; solo se
  // completa cuando el campo quedó vacío.
  fillName(data, { onlyIfEmpty: true });

  fillSummary(data);

  if (els.preview && data.image_base64) {
    els.preview.src = 'data:image/jpeg;base64,' + data.image_base64;
    els.preview.style.display = 'block';
  }

  setStatus('Ya leímos tu documento. Revisa los datos y continúa.');
  enableSubmit();
}

export function initIdScanForm() {
  cacheElements();

  if (!els.file || els.file.dataset.bound === '1') return;

  els.file.dataset.bound = '1';
  disableSubmit();
  lockFields(true);

  // Si el servidor devolvió el formulario con una lectura previa (por ejemplo,
  // porque faltaba corregir un dato del formulario), se restaura tal cual.
  restorePreviousScan();

  els.file.addEventListener('change', (event) => {
    handleFile(event.target.files[0]);
  });

  if (els.camera) {
    els.camera.addEventListener('click', async () => {
      stopCamera(null);
      els.video.style.display = 'block';
      els.stop.hidden = false;
      setBusy(true);
      setStatus('Apunta la cámara al código de barras del reverso…');

      try {
        const controls = await startCamera(els.video, async (blob) => {
          stopCamera(controls);
          setBusy(false);
          setStatus('Procesando la captura…');

          try {
            const { data } = await readDocument(blob, setStatus);
            await applyResult(data, blob);
          } catch (error) {
            setStatus(error.message || 'No se pudo leer el documento.', true);
          }
        }, setStatus);

        els.stop.onclick = () => {
          stopCamera(controls);
          setBusy(false);
          setStatus('');
        };
      } catch (error) {
        stopCamera(null);
        setBusy(false);
        setStatus('No se pudo abrir la cámara (requiere HTTPS o localhost y permisos): ' + error.message, true);
      }
    });
  }

  if (els.preview) {
    els.preview.addEventListener('click', () => {
      els.preview.src = '';
      els.preview.style.display = 'none';
    });
  }
}
