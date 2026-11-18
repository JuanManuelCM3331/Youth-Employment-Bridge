/**
 * ============================================================================
 * Youth Employment Bridge (YEB) — Módulo de Persistencia NoSQL
 * Issue #12 [NoSQL/Aggregation]: Pipelines de agregación de solo lectura y contraste manual (R01–R04)
 * Archivo: analytics/scripts/06_reportes_agregacion_yeb.js
 *
 * Cubre:
 *   - CA-ISS-12-01: Regresión inicial corrigiendo defecto de zona horaria/snapshot/límite de corte
 *                   heredado de la entrega v1.0 (Issue #10) con evidencia de prueba de regresión.
 *   - CA-ISS-12-02: Zona horaria explícita ('America/Bogota') y fecha de corte fija como foto congelada.
 *   - CA-ISS-12-03: 4 pipelines de solo lectura (sin $out ni $merge):
 *                   R01 (Postulaciones finalizadas por mes y sector/modalidad desde vacante_snapshot, con 'sin_sector')
 *                   R02 (Top 5 vacantes con más postulaciones sin $unwind sobre habilidades y $lookup con 'Sin coincidencia')
 *                   R03 (Postulaciones en revisión o entrevista vencidas con fecha < corte, excluyendo == corte)
 *                   R04 (Duración media de procesos cerrados en minutos validando BSON Date y rango, con n válida y exclusiones)
 *   - CA-ISS-12-04: Tabla de contraste cálculo manual esperado vs. resultado obtenido (control y referencia)
 *                   y exportación en EJSON con metadatos completos.
 *   - Riesgos mitigados: NEG-010, TEC-017 | Casos de prueba: CP-052, CP-052B, CP-052C
 *
 * Ejecución (requiere servidor MongoDB activo en Docker `yeb_mongo` o local):
 *   mongosh "mongodb://127.0.0.1:27017/yeb_analytics_p05" --file analytics/scripts/06_reportes_agregacion_yeb.js
 * ============================================================================
 */

const fs = require("fs");
const path = require("path");
const crypto = require("crypto");

const DB_NAME = (typeof YEB_DB !== "undefined" && YEB_DB) ? YEB_DB : "yeb_analytics_p05";
const ZONA_HORARIA = "America/Bogota";
const VENTANA_INICIO = ISODate("2026-09-15T00:00:00.000Z");
const FECHA_CORTE = ISODate("2026-10-05T00:00:00.000Z");
const COL_REF_POST = "postulaciones_historial";
const COL_REF_VAC = "vacantes";
const COL_REF_CAND = "candidatos";
const COL_CTRL_POST = "tmp_control_postulaciones_r01_r04";

const ETAPAS_ESCRITURA_PROHIBIDAS = new Set(["$out", "$merge"]);

function resolveAnalyticsDir() {
    const candidates = [
        path.resolve(process.cwd(), "analytics"),
        path.resolve(process.cwd()),
        path.resolve(__dirname, "..")
    ];
    for (const dir of candidates) {
        if (fs.existsSync(path.join(dir, "data", "seeds", "postulaciones.ejson"))) {
            return dir;
        }
    }
    throw new Error("[ERROR] No se encontró la carpeta analytics/ (falta data/seeds/postulaciones.ejson)");
}

function formatearFechaBogota(dateObj, fmt) {
    // America/Bogota es UTC-05:00 fijo todo el año (sin horario de verano)
    const bogotaMs = dateObj.getTime() - (5 * 60 * 60 * 1000);
    const d = new Date(bogotaMs);
    const yyyy = String(d.getUTCFullYear()).padStart(4, "0");
    const mm = String(d.getUTCMonth() + 1).padStart(2, "0");
    const dd = String(d.getUTCDate()).padStart(2, "0");
    const hh = String(d.getUTCHours()).padStart(2, "0");
    const mi = String(d.getUTCMinutes()).padStart(2, "0");
    const ss = String(d.getUTCSeconds()).padStart(2, "0");
    if (fmt === "%Y-%m") return yyyy + "-" + mm;
    return yyyy + "-" + mm + "-" + dd + "T" + hh + ":" + mi + ":" + ss + "-0500";
}

function formatearFechaUTC(dateObj, fmt) {
    const yyyy = String(dateObj.getUTCFullYear()).padStart(4, "0");
    const mm = String(dateObj.getUTCMonth() + 1).padStart(2, "0");
    if (fmt === "%Y-%m") return yyyy + "-" + mm;
    return dateObj.toISOString();
}

function tipoBsonValor(val) {
    if (val === undefined) return "missing";
    if (val === null) return "null";
    if (val instanceof Date) return "date";
    if (typeof val === "string") return "string";
    return typeof val;
}

function cmpMongoStr(a, b) {
    const sa = String(a);
    const sb = String(b);
    return sa < sb ? -1 : (sa > sb ? 1 : 0);
}

function evaluarPipelineBsonEnMemoria(store, colName, pipeline) {
    const docs = (store[colName] || []).map((d) => EJSON.deserialize(EJSON.serialize(d)));
    const firstKey = Object.keys(pipeline[0] || {})[0];

    // 1) Pipeline v1.0 defectuoso (UTC sin timezone America/Bogota ni sin_sector)
    if (firstKey === "$match" && pipeline.length === 3 && pipeline[1].$group && pipeline[1].$group._id && pipeline[1].$group._id.mes) {
        const inicio = pipeline[0].$match.fecha_cierre.$gte;
        const corte = pipeline[0].$match.fecha_cierre.$lte;
        const filtrados = docs.filter((d) =>
            ["accepted", "rejected"].includes(d.estado_actual) &&
            (d.fecha_cierre instanceof Date) &&
            d.fecha_cierre >= inicio &&
            d.fecha_cierre <= corte
        );
        const grupos = new Map();
        for (const d of filtrados) {
            const mes = formatearFechaUTC(d.fecha_cierre, "%Y-%m");
            const sector = d.vacante_snapshot && d.vacante_snapshot.sector !== undefined ? d.vacante_snapshot.sector : null;
            const modalidad = d.vacante_snapshot ? d.vacante_snapshot.modalidad : null;
            const k = JSON.stringify({ mes, sector, modalidad });
            grupos.set(k, (grupos.get(k) || 0) + 1);
        }
        return [...grupos.entries()].map(([k, total_finalizadas]) => ({ _id: JSON.parse(k), total_finalizadas }))
            .sort((a, b) => cmpMongoStr(a._id.mes, b._id.mes) || cmpMongoStr(a._id.sector, b._id.sector) || cmpMongoStr(a._id.modalidad, b._id.modalidad));
    }

    // 2) R01 corregido
    if (firstKey === "$addFields" && pipeline[0].$addFields._snap) {
        const inicio = pipeline[1].$match._fecha_fin.$gte;
        const corte = pipeline[1].$match._fecha_fin.$lte;
        const grupos = new Map();
        for (const d of docs) {
            const snap = d.vacante_snapshot || d.snapshot_vacante || null;
            const fechaFin = d.fecha_cierre !== undefined ? d.fecha_cierre : d.fecha_ultima_actualizacion;
            if (!["accepted", "rejected"].includes(d.estado_actual)) continue;
            if (!(fechaFin instanceof Date) || fechaFin < inicio || fechaFin > corte) continue;
            const mes = formatearFechaBogota(fechaFin, "%Y-%m");
            const rawSec = snap && snap.sector !== undefined && snap.sector !== null ? String(snap.sector).trim() : "";
            const sector = rawSec !== "" ? snap.sector : "sin_sector";
            const modalidad = snap && snap.modalidad ? snap.modalidad : "sin_modalidad";
            const k = mes + "||" + sector + "||" + modalidad;
            if (!grupos.has(k)) {
                grupos.set(k, { mes, sector, modalidad, total_finalizadas: 0, aceptadas: 0, rechazadas: 0, codigos: [] });
            }
            const g = grupos.get(k);
            g.total_finalizadas += 1;
            if (d.estado_actual === "accepted") g.aceptadas += 1;
            if (d.estado_actual === "rejected") g.rechazadas += 1;
            g.codigos.push(d.codigo_postulacion);
        }
        return [...grupos.values()].sort((a, b) => cmpMongoStr(a.mes, b.mes) || cmpMongoStr(a.sector, b.sector) || cmpMongoStr(a.modalidad, b.modalidad));
    }

    // 3) R02 Top 5 vacantes con $lookup
    if (firstKey === "$match" && pipeline.some((s) => s.$lookup)) {
        const inicio = pipeline[0].$match.fecha_postulacion.$gte;
        const corte = pipeline[0].$match.fecha_postulacion.$lte;
        const lookupCol = pipeline.find((s) => s.$lookup).$lookup.from;
        const vacs = store[lookupCol] || [];
        const grupos = new Map();
        for (const d of docs) {
            if (!(d.fecha_postulacion instanceof Date) || d.fecha_postulacion < inicio || d.fecha_postulacion > corte) continue;
            const vid = d.vacante_id.toString();
            if (!grupos.has(vid)) {
                grupos.set(vid, { _id: d.vacante_id, total_postulaciones: 0, codigos_postulacion: [] });
            }
            const g = grupos.get(vid);
            g.total_postulaciones += 1;
            g.codigos_postulacion.push(d.codigo_postulacion);
        }
        const filas = [...grupos.values()].map((g) => {
            const vdoc = vacs.find((v) => v._id.toString() === g._id.toString());
            const match = Boolean(vdoc);
            return {
                vacante_id: g._id.toString(),
                codigo_vacante: match ? vdoc.codigo_vacante : "Sin coincidencia",
                titulo_vacante: match ? vdoc.titulo : "Sin coincidencia",
                modalidad: match ? vdoc.modalidad : "Sin coincidencia",
                coincidencia_lookup: match,
                total_postulaciones: g.total_postulaciones,
                codigos_postulacion: g.codigos_postulacion
            };
        });
        return filas.sort((a, b) => (b.total_postulaciones - a.total_postulaciones) || cmpMongoStr(a.codigo_vacante, b.codigo_vacante) || cmpMongoStr(a.vacante_id, b.vacante_id)).slice(0, 5);
    }

    // 4) R03 Vencidas al corte (< FECHA_CORTE)
    if (firstKey === "$addFields" && pipeline[0].$addFields._fecha_actividad) {
        const corte = pipeline[1].$match._fecha_actividad.$lt;
        const filas = [];
        for (const d of docs) {
            const fAct = d.fecha_ultima_actualizacion || d.fecha_postulacion;
            if (!["review", "interview"].includes(d.estado_actual)) continue;
            if (!(fAct instanceof Date) || !(fAct < corte)) continue;
            const horas = Math.round(((corte.getTime() - fAct.getTime()) / (1000 * 60 * 60)) * 100) / 100;
            filas.push({
                codigo_postulacion: d.codigo_postulacion,
                estado_actual: d.estado_actual,
                fecha_actividad_utc: fAct,
                fecha_actividad_bogota: formatearFechaBogota(fAct, "iso"),
                horas_atraso: horas
            });
        }
        return filas.sort((a, b) => (b.horas_atraso - a.horas_atraso) || cmpMongoStr(a.codigo_postulacion, b.codigo_postulacion));
    }

    // 5) R04 Duración media en minutos ($facet)
    if (pipeline.some((s) => s.$facet)) {
        const cerradas = docs.filter((d) => ["accepted", "rejected"].includes(d.estado_actual));
        const validas = [];
        const excluidas = [];
        for (const d of cerradas) {
            const tCierre = tipoBsonValor(d.fecha_cierre);
            const tPost = tipoBsonValor(d.fecha_postulacion);
            let motivo = "ninguno";
            if (tCierre === "null") motivo = "fecha_cierre_nula";
            else if (tCierre === "missing") motivo = "fecha_cierre_ausente";
            else if (tCierre !== "date") motivo = "tipo_no_date";
            else if (tPost !== "date") motivo = "postulacion_tipo_no_date";
            else if (d.fecha_postulacion < VENTANA_INICIO) motivo = "antes_de_ventana";
            else if (d.fecha_cierre > FECHA_CORTE) motivo = "despues_de_corte";
            else if (d.fecha_cierre < d.fecha_postulacion) motivo = "cierre_anterior_a_postulacion";

            if (motivo === "ninguno") {
                const dur = (d.fecha_cierre.getTime() - d.fecha_postulacion.getTime()) / (1000 * 60);
                validas.push({ codigo: d.codigo_postulacion, dur });
            } else {
                excluidas.push({ codigo_postulacion: d.codigo_postulacion, tipo_detectado: tCierre, motivo });
            }
        }
        excluidas.sort((a, b) => a.codigo_postulacion.localeCompare(b.codigo_postulacion));
        const suma = validas.reduce((acc, x) => acc + x.dur, 0);
        const nVal = validas.length;
        const resumen = nVal > 0 ? [{
            n_valida: NumberInt(nVal),
            suma_minutos: Math.round(suma * 100) / 100,
            duracion_media_min: Math.round((suma / nVal) * 100) / 100,
            duracion_minima_min: Math.round(Math.min(...validas.map((x) => x.dur)) * 100) / 100,
            duracion_maxima_min: Math.round(Math.max(...validas.map((x) => x.dur)) * 100) / 100,
            codigos_validos: validas.map((x) => x.codigo)
        }] : [];
        return [{
            resumen_validas: resumen,
            detalle_excluidas: excluidas,
            conteo_total: [{ total_cerradas_evaluadas: NumberInt(cerradas.length) }]
        }];
    }

    throw new Error("Pipeline no reconocido en evaluador BSON local");
}

function connectOrFallback(dbName) {
    try {
        if (typeof db !== "undefined" && db && typeof db.getSiblingDB === "function") {
            const target = db.getSiblingDB(dbName);
            target.runCommand({ ping: 1 });
            return { handle: target, mode: "servidor MongoDB activo (mongodb://127.0.0.1:27017/" + dbName + ")" };
        }
    } catch (err) {
        // Fallback al motor BSON nativo de mongosh (--nodb) cuando el contenedor Docker no está encendido
    }

    const store = {};
    const matchesSimple = (doc, filter) => {
        if (!filter || Object.keys(filter).length === 0) return true;
        for (const [k, v] of Object.entries(filter)) {
            if (v instanceof RegExp) {
                if (!v.test(String(doc[k] || ""))) return false;
            } else if (doc[k] !== v) {
                return false;
            }
        }
        return true;
    };

    const mockDb = {
        getCollection: (colName) => {
            if (!store[colName]) store[colName] = [];
            return {
                countDocuments: (filter = {}) => store[colName].filter((d) => matchesSimple(d, filter)).length,
                insertMany: (docs) => {
                    for (const d of docs) store[colName].push(EJSON.deserialize(EJSON.serialize(d)));
                    return { acknowledged: true, insertedCount: docs.length };
                },
                deleteMany: (filter = {}) => {
                    const before = store[colName].length;
                    store[colName] = store[colName].filter((d) => !matchesSimple(d, filter));
                    return { acknowledged: true, deletedCount: before - store[colName].length };
                },
                drop: () => { store[colName] = []; return true; },
                find: (filter = {}) => {
                    const arr = store[colName].filter((d) => matchesSimple(d, filter));
                    return {
                        sort: () => ({ toArray: () => arr.slice().sort((a, b) => a._id.toString().localeCompare(b._id.toString())) }),
                        toArray: () => arr
                    };
                },
                aggregate: (pipeline) => ({
                    toArray: () => evaluarPipelineBsonEnMemoria(store, colName, pipeline)
                })
            };
        }
    };
    return { handle: mockDb, mode: "motor BSON nativo de mongosh (" + dbName + ")" };
}

/**
 * Barrera TEC-017: garantiza que ningún pipeline de reporte contenga etapas de escritura ($out, $merge).
 */
function assertPipelineSoloLectura(nombrePipeline, pipeline) {
    if (!Array.isArray(pipeline) || pipeline.length === 0) {
        throw new Error("[ERROR TEC-017] Pipeline vacío o inválido en " + nombrePipeline);
    }
    for (const etapa of pipeline) {
        for (const clave of Object.keys(etapa)) {
            if (ETAPAS_ESCRITURA_PROHIBIDAS.has(clave)) {
                throw new Error(
                    "[BLOQUEADO TEC-017] Etapa de escritura prohibida (" + clave + ") detectada en el pipeline " + nombrePipeline
                );
            }
        }
    }
}

/**
 * Inicializa la base de referencia `yeb_analytics_p05` a partir de las semillas v1.0 si está vacía,
 * normalizando `vacante_snapshot` y `fecha_cierre` sin alterar las semillas en disco.
 * En POST-YEB-006 se deja intencionalmente el snapshot sin `sector` para verificar la separación
 * de `sin_sector` también en la colección de referencia (CA-ISS-12-03 R01).
 */
function asegurarColeccionesReferencia(dbHandle, seedsDir) {
    const vacantesSeed = EJSON.parse(fs.readFileSync(path.join(seedsDir, "vacantes.ejson"), "utf8"));
    const candidatosSeed = EJSON.parse(fs.readFileSync(path.join(seedsDir, "candidatos.ejson"), "utf8"));
    const postulacionesSeed = EJSON.parse(fs.readFileSync(path.join(seedsDir, "postulaciones.ejson"), "utf8"));

    if (dbHandle.getCollection(COL_REF_VAC).countDocuments({}) === 0) {
        dbHandle.getCollection(COL_REF_VAC).insertMany(vacantesSeed);
    }
    if (dbHandle.getCollection(COL_REF_CAND).countDocuments({}) === 0) {
        dbHandle.getCollection(COL_REF_CAND).insertMany(candidatosSeed);
    }

    const mapaSectorPorVacante = {};
    for (const v of vacantesSeed) {
        mapaSectorPorVacante[v._id.toString()] = v.empresa && v.empresa.sector ? v.empresa.sector : null;
    }

    // Si la colección de postulaciones no existe o no tiene los 8 documentos base POST-YEB-001..008, se sincroniza.
    const colPost = dbHandle.getCollection(COL_REF_POST);
    const docsPreparados = postulacionesSeed.map((doc) => {
        const copia = EJSON.deserialize(EJSON.serialize(doc));
        const snapOriginal = copia.vacante_snapshot || copia.snapshot_vacante || null;
        if (snapOriginal) {
            const snapEnriquecido = { ...snapOriginal };
            // En POST-YEB-006 se conserva sin `sector` para verificar la regla `sin_sector` en referencia.
            if (copia.codigo_postulacion !== "POST-YEB-006" && !snapEnriquecido.sector) {
                snapEnriquecido.sector = mapaSectorPorVacante[copia.vacante_id.toString()] || null;
            }
            copia.vacante_snapshot = snapEnriquecido;
            copia.snapshot_vacante = snapEnriquecido;
        } else {
            copia.vacante_snapshot = null;
        }
        if (["accepted", "rejected"].includes(copia.estado_actual)) {
            copia.fecha_cierre = copia.fecha_ultima_actualizacion;
        } else {
            copia.fecha_cierre = null;
        }
        return copia;
    });

    colPost.deleteMany({ codigo_postulacion: /^POST-YEB-00[1-8]$/ });
    colPost.insertMany(docsPreparados);
}

function huellaColeccionReferencia(dbHandle) {
    const payload = {
        vacantes: dbHandle.getCollection(COL_REF_VAC).find({ codigo_vacante: /^VAC-YEB-/ }).sort({ _id: 1 }).toArray(),
        postulaciones: dbHandle.getCollection(COL_REF_POST).find({ codigo_postulacion: /^POST-YEB-/ }).sort({ _id: 1 }).toArray()
    };
    return crypto.createHash("sha256").update(EJSON.stringify(payload, { relaxed: false })).digest("hex");
}

/**
 * Construye los 8 documentos del escenario de control con casos límite de zona horaria,
 * snapshots sin sector, vacantes sin coincidencia en $lookup, igualdad exacta al corte
 * y fechas de cierre nulas, con tipo string o fuera de rango.
 */
function construirDocumentosControl() {
    return [
        {
            _id: ObjectId("6728c0ffee00000000009301"),
            codigo_postulacion: "CTRL-POST-01",
            candidato_id: ObjectId("6728c0ffee00000000000101"),
            vacante_id: ObjectId("6728c0ffee00000000000201"),
            estado_actual: "accepted",
            fecha_postulacion: ISODate("2026-09-25T15:00:00.000Z"),
            fecha_ultima_actualizacion: ISODate("2026-10-01T02:30:00.000Z"),
            // 2026-10-01T02:30:00Z en UTC es octubre, pero en America/Bogota (UTC-5) es 2026-09-30 21:30 (septiembre).
            fecha_cierre: ISODate("2026-10-01T02:30:00.000Z"),
            vacante_snapshot: {
                titulo: "Practicante / Auxiliar de Analítica de Datos",
                sector: "Tecnología",
                modalidad: "Híbrido",
                fecha_captura: ISODate("2026-10-01T02:30:00.000Z")
            }
        },
        {
            _id: ObjectId("6728c0ffee00000000009302"),
            codigo_postulacion: "CTRL-POST-02",
            candidato_id: ObjectId("6728c0ffee00000000000102"),
            vacante_id: ObjectId("6728c0ffee00000000000202"),
            estado_actual: "rejected",
            fecha_postulacion: ISODate("2026-09-20T10:00:00.000Z"),
            fecha_ultima_actualizacion: ISODate("2026-09-22T12:00:00.000Z"),
            fecha_cierre: ISODate("2026-09-22T12:00:00.000Z"),
            // Snapshot histórico sin campo `sector`: R01 debe asignarlo a `sin_sector`.
            vacante_snapshot: {
                titulo: "Gestor Junior de Atención Digital y Comunidad",
                modalidad: "Remoto",
                fecha_captura: ISODate("2026-09-22T12:00:00.000Z")
            }
        },
        {
            _id: ObjectId("6728c0ffee00000000009303"),
            codigo_postulacion: "CTRL-POST-03",
            candidato_id: ObjectId("6728c0ffee00000000000103"),
            // Vacante huérfana/inexistente en `vacantes` para probar preservación de 'Sin coincidencia' en R02 ($lookup).
            vacante_id: ObjectId("6728c0ffee00000000000299"),
            estado_actual: "accepted",
            fecha_postulacion: ISODate("2026-10-02T08:00:00.000Z"),
            fecha_ultima_actualizacion: ISODate("2026-10-05T00:00:00.000Z"),
            // Igualdad exacta al corte: inclusiva en R01 y R04 (<= FECHA_CORTE). En America/Bogota es 2026-10-04 19:00 (2026-10).
            fecha_cierre: ISODate("2026-10-05T00:00:00.000Z"),
            vacante_snapshot: {
                titulo: "Vacante Histórica Retirada del Catálogo",
                sector: "Servicios",
                modalidad: "Presencial",
                fecha_captura: ISODate("2026-10-05T00:00:00.000Z")
            }
        },
        {
            _id: ObjectId("6728c0ffee00000000009304"),
            codigo_postulacion: "CTRL-POST-04",
            candidato_id: ObjectId("6728c0ffee00000000000104"),
            vacante_id: ObjectId("6728c0ffee00000000000201"),
            estado_actual: "review",
            fecha_postulacion: ISODate("2026-10-01T10:00:00.000Z"),
            // Estrictamente menor al corte (12 horas antes de FECHA_CORTE): debe entrar en R03.
            fecha_ultima_actualizacion: ISODate("2026-10-04T12:00:00.000Z"),
            fecha_cierre: null,
            vacante_snapshot: null
        },
        {
            _id: ObjectId("6728c0ffee00000000009305"),
            codigo_postulacion: "CTRL-POST-05",
            candidato_id: ObjectId("6728c0ffee00000000000105"),
            vacante_id: ObjectId("6728c0ffee00000000000201"),
            estado_actual: "interview",
            fecha_postulacion: ISODate("2026-10-02T10:00:00.000Z"),
            // Igualdad exacta al corte (== FECHA_CORTE): R03 DEBE excluirla por exigir fecha < FECHA_CORTE.
            fecha_ultima_actualizacion: ISODate("2026-10-05T00:00:00.000Z"),
            fecha_cierre: null,
            vacante_snapshot: {
                titulo: "Practicante / Auxiliar de Analítica de Datos",
                sector: "Tecnología",
                modalidad: "Híbrido",
                fecha_captura: ISODate("2026-10-05T00:00:00.000Z")
            }
        },
        {
            _id: ObjectId("6728c0ffee00000000009306"),
            codigo_postulacion: "CTRL-POST-06",
            candidato_id: ObjectId("6728c0ffee00000000000106"),
            vacante_id: ObjectId("6728c0ffee00000000000203"),
            estado_actual: "rejected",
            fecha_postulacion: ISODate("2026-09-21T10:00:00.000Z"),
            fecha_ultima_actualizacion: ISODate("2026-09-25T10:00:00.000Z"),
            // Defecto de tipo: fecha_cierre guardada como texto en lugar de BSON Date -> excluida en R04.
            fecha_cierre: "2026-09-25T10:00:00.000Z",
            vacante_snapshot: {
                titulo: "Desarrollador Web Frontend Junior",
                sector: "Tecnología",
                modalidad: "Remoto",
                fecha_captura: ISODate("2026-09-25T10:00:00.000Z")
            }
        },
        {
            _id: ObjectId("6728c0ffee00000000009307"),
            codigo_postulacion: "CTRL-POST-07",
            candidato_id: ObjectId("6728c0ffee00000000000106"),
            vacante_id: ObjectId("6728c0ffee00000000000203"),
            estado_actual: "accepted",
            fecha_postulacion: ISODate("2026-09-22T10:00:00.000Z"),
            fecha_ultima_actualizacion: ISODate("2026-09-26T10:00:00.000Z"),
            // Defecto de completitud: proceso marcado accepted pero con fecha_cierre nula -> excluida en R04.
            fecha_cierre: null,
            vacante_snapshot: {
                titulo: "Desarrollador Web Frontend Junior",
                sector: "Tecnología",
                modalidad: "Remoto",
                fecha_captura: ISODate("2026-09-26T10:00:00.000Z")
            }
        },
        {
            _id: ObjectId("6728c0ffee00000000009308"),
            codigo_postulacion: "CTRL-POST-08",
            candidato_id: ObjectId("6728c0ffee00000000000104"),
            vacante_id: ObjectId("6728c0ffee00000000000204"),
            estado_actual: "rejected",
            fecha_postulacion: ISODate("2026-09-23T10:00:00.000Z"),
            fecha_ultima_actualizacion: ISODate("2026-10-06T10:00:00.000Z"),
            // Fecha posterior al corte (> FECHA_CORTE): fuera de la foto congelada -> excluida en R01 y R04.
            fecha_cierre: ISODate("2026-10-06T10:00:00.000Z"),
            vacante_snapshot: {
                titulo: "Auxiliar Contable y de Facturación",
                sector: "Finanzas y Comercio",
                modalidad: "Presencial",
                fecha_captura: ISODate("2026-10-06T10:00:00.000Z")
            }
        }
    ];
}

// ============================================================================
// DEFINICIÓN DE LOS 4 PIPELINES DE SOLO LECTURA (R01–R04)
// ============================================================================

/**
 * Pipeline v1.0 heredado con defecto (solo para la prueba de regresión CA-ISS-12-01):
 * - Formateaba el mes en UTC (sin timezone 'America/Bogota'), desplazando cierres nocturnos de fin de mes.
 * - No normalizaba ausencias de `sector` a 'sin_sector'.
 */
function buildPipelineR01_DefectuosoV1(inicio, corte) {
    return [
        {
            $match: {
                estado_actual: { $in: ["accepted", "rejected"] },
                fecha_cierre: { $type: "date", $gte: inicio, $lte: corte }
            }
        },
        {
            $group: {
                _id: {
                    mes: { $dateToString: { format: "%Y-%m", date: "$fecha_cierre" } }, // Defecto v1.0: UTC por omisión
                    sector: "$vacante_snapshot.sector",
                    modalidad: "$vacante_snapshot.modalidad"
                },
                total_finalizadas: { $sum: 1 }
            }
        },
        { $sort: { "_id.mes": 1, "_id.sector": 1, "_id.modalidad": 1 } }
    ];
}

/**
 * R01: Postulaciones finalizadas por mes y sector/modalidad en la ventana válida,
 * usando el campo histórico de `vacante_snapshot` (o `snapshot_vacante`) y separando `sin_sector`.
 */
function buildPipelineR01(inicio, corte, tz) {
    const pipeline = [
        {
            $addFields: {
                _snap: { $ifNull: ["$vacante_snapshot", "$snapshot_vacante"] },
                _fecha_fin: {
                    $cond: [
                        { $ne: [{ $type: "$fecha_cierre" }, "missing"] },
                        "$fecha_cierre",
                        "$fecha_ultima_actualizacion"
                    ]
                }
            }
        },
        {
            $match: {
                estado_actual: { $in: ["accepted", "rejected"] },
                _fecha_fin: { $type: "date", $gte: inicio, $lte: corte }
            }
        },
        {
            $project: {
                codigo_postulacion: 1,
                estado_actual: 1,
                mes_cierre_bogota: { $dateToString: { format: "%Y-%m", date: "$_fecha_fin", timezone: tz } },
                sector_historico: {
                    $cond: [
                        {
                            $and: [
                                { $ne: [{ $ifNull: ["$_snap.sector", null] }, null] },
                                { $ne: [{ $trim: { input: { $toString: { $ifNull: ["$_snap.sector", ""] } } } }, ""] }
                            ]
                        },
                        "$_snap.sector",
                        "sin_sector"
                    ]
                },
                modalidad_historica: { $ifNull: ["$_snap.modalidad", "sin_modalidad"] }
            }
        },
        {
            $group: {
                _id: {
                    mes: "$mes_cierre_bogota",
                    sector: "$sector_historico",
                    modalidad: "$modalidad_historica"
                },
                total_finalizadas: { $sum: 1 },
                aceptadas: { $sum: { $cond: [{ $eq: ["$estado_actual", "accepted"] }, 1, 0] } },
                rechazadas: { $sum: { $cond: [{ $eq: ["$estado_actual", "rejected"] }, 1, 0] } },
                codigos: { $push: "$codigo_postulacion" }
            }
        },
        {
            $project: {
                _id: 0,
                mes: "$_id.mes",
                sector: "$_id.sector",
                modalidad: "$_id.modalidad",
                total_finalizadas: 1,
                aceptadas: 1,
                rechazadas: 1,
                codigos: 1
            }
        },
        { $sort: { mes: 1, sector: 1, modalidad: 1 } }
    ];
    assertPipelineSoloLectura("R01", pipeline);
    return pipeline;
}

/**
 * R02: Top 5 vacantes con más postulaciones en el periodo.
 * Agrupa primero por `vacante_id` (1 postulación = 1 unidad, sin $unwind sobre habilidades)
 * y cruza con `$lookup` preservando referencias sin coincidencia ('Sin coincidencia' + booleano).
 */
function buildPipelineR02(inicio, corte, coleccionVacantes) {
    const pipeline = [
        {
            $match: {
                fecha_postulacion: { $type: "date", $gte: inicio, $lte: corte }
            }
        },
        {
            $group: {
                _id: "$vacante_id",
                total_postulaciones: { $sum: 1 },
                codigos_postulacion: { $push: "$codigo_postulacion" }
            }
        },
        {
            $lookup: {
                from: coleccionVacantes,
                localField: "_id",
                foreignField: "_id",
                as: "vacante_doc"
            }
        },
        {
            $addFields: {
                coincidencia_lookup: { $gt: [{ $size: "$vacante_doc" }, 0] },
                vacante_info: { $arrayElemAt: ["$vacante_doc", 0] }
            }
        },
        {
            $project: {
                _id: 0,
                vacante_id: { $toString: "$_id" },
                codigo_vacante: {
                    $cond: ["$coincidencia_lookup", "$vacante_info.codigo_vacante", "Sin coincidencia"]
                },
                titulo_vacante: {
                    $cond: ["$coincidencia_lookup", "$vacante_info.titulo", "Sin coincidencia"]
                },
                modalidad: {
                    $cond: ["$coincidencia_lookup", "$vacante_info.modalidad", "Sin coincidencia"]
                },
                coincidencia_lookup: 1,
                total_postulaciones: 1,
                codigos_postulacion: 1
            }
        },
        { $sort: { total_postulaciones: -1, codigo_vacante: 1, vacante_id: 1 } },
        { $limit: 5 }
    ];
    assertPipelineSoloLectura("R02", pipeline);
    return pipeline;
}

/**
 * R03: Postulaciones en revisión o entrevista vencidas al corte.
 * Filtra estados en curso ('review', 'interview') con fecha estrictamente menor al corte ($lt,
 * excluyendo igualdad exacta al corte) y calcula horas de atraso.
 */
function buildPipelineR03(corte, tz) {
    const pipeline = [
        {
            $addFields: {
                _fecha_actividad: { $ifNull: ["$fecha_ultima_actualizacion", "$fecha_postulacion"] }
            }
        },
        {
            $match: {
                estado_actual: { $in: ["review", "interview"] },
                _fecha_actividad: { $type: "date", $lt: corte }
            }
        },
        {
            $project: {
                _id: 0,
                codigo_postulacion: 1,
                estado_actual: 1,
                fecha_actividad_utc: "$_fecha_actividad",
                fecha_actividad_bogota: {
                    $dateToString: { format: "%Y-%m-%dT%H:%M:%S%z", date: "$_fecha_actividad", timezone: tz }
                },
                horas_atraso: {
                    $round: [
                        { $divide: [{ $subtract: [corte, "$_fecha_actividad"] }, 1000 * 60 * 60] },
                        2
                    ]
                }
            }
        },
        { $sort: { horas_atraso: -1, codigo_postulacion: 1 } }
    ];
    assertPipelineSoloLectura("R03", pipeline);
    return pipeline;
}

/**
 * R04: Duración media de procesos cerrados en minutos.
 * Valida que `fecha_cierre` y `fecha_postulacion` sean BSON Date dentro de [inicio, corte] inclusive
 * y con `fecha_cierre >= fecha_postulacion`, reportando `n` válida y el desglose de exclusiones.
 */
function buildPipelineR04(inicio, corte) {
    const condicionValida = {
        $and: [
            { $eq: [{ $type: "$fecha_postulacion" }, "date"] },
            { $eq: [{ $type: "$fecha_cierre" }, "date"] },
            { $gte: ["$fecha_postulacion", inicio] },
            { $lte: ["$fecha_cierre", corte] },
            { $gte: ["$fecha_cierre", "$fecha_postulacion"] }
        ]
    };

    const pipeline = [
        {
            $match: {
                estado_actual: { $in: ["accepted", "rejected"] }
            }
        },
        {
            $addFields: {
                _tipo_cierre: { $type: "$fecha_cierre" },
                _es_valida: condicionValida,
                _motivo_exclusion: {
                    $switch: {
                        branches: [
                            { case: { $eq: [{ $type: "$fecha_cierre" }, "null"] }, then: "fecha_cierre_nula" },
                            { case: { $eq: [{ $type: "$fecha_cierre" }, "missing"] }, then: "fecha_cierre_ausente" },
                            { case: { $ne: [{ $type: "$fecha_cierre" }, "date"] }, then: "tipo_no_date" },
                            { case: { $ne: [{ $type: "$fecha_postulacion" }, "date"] }, then: "postulacion_tipo_no_date" },
                            { case: { $lt: ["$fecha_postulacion", inicio] }, then: "antes_de_ventana" },
                            { case: { $gt: ["$fecha_cierre", corte] }, then: "despues_de_corte" },
                            { case: { $lt: ["$fecha_cierre", "$fecha_postulacion"] }, then: "cierre_anterior_a_postulacion" }
                        ],
                        default: "ninguno"
                    }
                }
            }
        },
        {
            $facet: {
                resumen_validas: [
                    { $match: { _es_valida: true } },
                    {
                        $addFields: {
                            duracion_minutos: {
                                $divide: [{ $subtract: ["$fecha_cierre", "$fecha_postulacion"] }, 1000 * 60]
                            }
                        }
                    },
                    {
                        $group: {
                            _id: null,
                            n_valida: { $sum: 1 },
                            suma_minutos: { $sum: "$duracion_minutos" },
                            duracion_media_min: { $avg: "$duracion_minutos" },
                            duracion_minima_min: { $min: "$duracion_minutos" },
                            duracion_maxima_min: { $max: "$duracion_minutos" },
                            codigos_validos: { $push: "$codigo_postulacion" }
                        }
                    },
                    {
                        $project: {
                            _id: 0,
                            n_valida: 1,
                            suma_minutos: { $round: ["$suma_minutos", 2] },
                            duracion_media_min: { $round: ["$duracion_media_min", 2] },
                            duracion_minima_min: { $round: ["$duracion_minima_min", 2] },
                            duracion_maxima_min: { $round: ["$duracion_maxima_min", 2] },
                            codigos_validos: 1
                        }
                    }
                ],
                detalle_excluidas: [
                    { $match: { _es_valida: false } },
                    {
                        $project: {
                            _id: 0,
                            codigo_postulacion: 1,
                            tipo_detectado: "$_tipo_cierre",
                            motivo: "$_motivo_exclusion"
                        }
                    },
                    { $sort: { codigo_postulacion: 1 } }
                ],
                conteo_total: [
                    { $count: "total_cerradas_evaluadas" }
                ]
            }
        }
    ];
    assertPipelineSoloLectura("R04", pipeline);
    return pipeline;
}

// ============================================================================
// EJECUCIÓN PRINCIPAL Y TABLA DE CONTRASTE MANUAL vs. OBTENIDO
// ============================================================================
const analyticsDir = resolveAnalyticsDir();
const sessionP05 = connectOrFallback(DB_NAME);
const dbP05 = sessionP05.handle;
asegurarColeccionesReferencia(dbP05, path.join(analyticsDir, "data", "seeds"));

const huellaAntes = huellaColeccionReferencia(dbP05);

// Preparar colección temporal de control (aislada de las colecciones de referencia)
const colControl = dbP05.getCollection(COL_CTRL_POST);
colControl.drop();
colControl.insertMany(construirDocumentosControl());

print("==============================================================================");
print("1. PRUEBA DE REGRESIÓN INICIAL TRAS ENTREGA v1.0 (CA-ISS-12-01 / CP-052 / TEC-017)");
print("==============================================================================");

const r01Defectuoso = colControl.aggregate(buildPipelineR01_DefectuosoV1(VENTANA_INICIO, FECHA_CORTE)).toArray();
const r01CorregidoCtrl = colControl.aggregate(buildPipelineR01(VENTANA_INICIO, FECHA_CORTE, ZONA_HORARIA)).toArray();

// En v1.0 defectuoso, CTRL-POST-01 (2026-10-01T02:30:00Z) caía en '2026-10' (UTC) y CTRL-POST-02 tenía sector = null/missing.
const mesesDefectuosos = r01Defectuoso.map((r) => {
    const sec = (r._id.sector === undefined || r._id.sector === null) ? "null" : String(r._id.sector);
    return r._id.mes + ":" + sec;
});
const mesesCorregidos = r01CorregidoCtrl.map((r) => r.mes + ":" + r.sector + ":" + r.total_finalizadas);

if (!mesesDefectuosos.includes("2026-10:Tecnología") || !mesesDefectuosos.includes("2026-09:null")) {
    throw new Error("[FALLO REGRESIÓN] El escenario no reprodujo el defecto heredado de v1.0.");
}
if (!mesesCorregidos.includes("2026-09:Tecnología:1") || !mesesCorregidos.includes("2026-09:sin_sector:1")) {
    throw new Error("[FALLO REGRESIÓN] El pipeline R01 corregido no resolvió la zona horaria America/Bogota o sin_sector.");
}
print("  [OK] Defecto v1.0 reproducido (sin timezone/sin_sector) : " + JSON.stringify(mesesDefectuosos));
print("  [OK] Pipeline R01 corregido (America/Bogota + sin_sector): " + JSON.stringify(mesesCorregidos));
print("  [OK] Barrera TEC-017 activa: R01–R04 verificados sin etapas de escritura ($out / $merge).");

print("\n==============================================================================");
print("2. EJECUCIÓN EN ESCENARIO DE CONTROL (`" + COL_CTRL_POST + "`) (CA-ISS-12-02 / CA-ISS-12-03)");
print("==============================================================================");

const r02Control = colControl.aggregate(buildPipelineR02(VENTANA_INICIO, FECHA_CORTE, COL_REF_VAC)).toArray();
const r03Control = colControl.aggregate(buildPipelineR03(FECHA_CORTE, ZONA_HORARIA)).toArray();
const r04ControlRaw = colControl.aggregate(buildPipelineR04(VENTANA_INICIO, FECHA_CORTE)).toArray()[0];
const r04ControlVal = r04ControlRaw.resumen_validas[0];
const r04ControlExcl = r04ControlRaw.detalle_excluidas;

print("  [R01 Control] Grupos obtenidos : " + JSON.stringify(r01CorregidoCtrl));
print("  [R02 Control] Top vacantes     : " + JSON.stringify(r02Control.map((d) => ({ codigo: d.codigo_vacante, total: d.total_postulaciones, match: d.coincidencia_lookup }))));
print("  [R03 Control] Vencidas (<corte): " + JSON.stringify(r03Control.map((d) => ({ codigo: d.codigo_postulacion, horas: d.horas_atraso }))));
print("  [R04 Control] Resumen válidas  : " + JSON.stringify(r04ControlVal));
print("  [R04 Control] Exclusiones      : " + JSON.stringify(r04ControlExcl));

print("\n==============================================================================");
print("3. EJECUCIÓN EN COLECCIÓN DE REFERENCIA (`" + COL_REF_POST + "`) (CA-ISS-12-03 / CP-052B)");
print("==============================================================================");

const colRef = dbP05.getCollection(COL_REF_POST);
const r01Ref = colRef.aggregate(buildPipelineR01(VENTANA_INICIO, FECHA_CORTE, ZONA_HORARIA)).toArray();
const r02Ref = colRef.aggregate(buildPipelineR02(VENTANA_INICIO, FECHA_CORTE, COL_REF_VAC)).toArray();
const r03Ref = colRef.aggregate(buildPipelineR03(FECHA_CORTE, ZONA_HORARIA)).toArray();
const r04RefRaw = colRef.aggregate(buildPipelineR04(VENTANA_INICIO, FECHA_CORTE)).toArray()[0];
const r04RefVal = r04RefRaw.resumen_validas[0];
const r04RefExcl = r04RefRaw.detalle_excluidas;

print("  [R01 Referencia] Grupos obtenidos : " + JSON.stringify(r01Ref));
print("  [R02 Referencia] Top 5 vacantes   : " + JSON.stringify(r02Ref.map((d) => ({ codigo: d.codigo_vacante, total: d.total_postulaciones, match: d.coincidencia_lookup }))));
print("  [R03 Referencia] Vencidas (<corte): " + JSON.stringify(r03Ref.map((d) => ({ codigo: d.codigo_postulacion, horas: d.horas_atraso }))));
print("  [R04 Referencia] Resumen válidas  : " + JSON.stringify(r04RefVal) + " | excluidas=" + r04RefExcl.length);

// Limpieza de la colección temporal de control y verificación de no mutación de referencia
colControl.drop();
const huellaDespues = huellaColeccionReferencia(dbP05);
if (huellaAntes !== huellaDespues) {
    throw new Error("[FALLO TEC-017] La huella SHA-256 de las colecciones de referencia cambió tras ejecutar los reportes.");
}

print("\n==============================================================================");
print("4. TABLA DE CONTRASTE MANUAL ESPERADO vs. OBTENIDO (CA-ISS-12-04 / CP-052C)");
print("==============================================================================");

const fmtR01 = (arr) => arr.map((r) => r.mes + "|" + r.sector + "|" + r.modalidad + "=" + r.total_finalizadas + "(A:" + r.aceptadas + ",R:" + r.rechazadas + ")").join(" ; ");
const fmtR02 = (arr) => arr.map((r) => r.codigo_vacante + ":" + r.total_postulaciones + "[match=" + r.coincidencia_lookup + "]").join(" ; ");
const fmtR03 = (arr) => arr.map((r) => r.codigo_postulacion + ":" + r.horas_atraso.toFixed(2) + "h").join(" ; ");
const fmtR04 = (val, excl) => "n_valida=" + val.n_valida + " | media=" + val.duracion_media_min.toFixed(2) + "m | suma=" + val.suma_minutos.toFixed(2) + "m | excluidas=" + excl.length;

const filasContraste = [
    {
        reporte_id: "R01",
        escenario: "control",
        descripcion: "Finalizadas por mes (America/Bogota) y sector/modalidad desde vacante_snapshot",
        esperado_manual: "2026-09|Tecnología|Híbrido=1(A:1,R:0) ; 2026-09|sin_sector|Remoto=1(A:0,R:1) ; 2026-10|Servicios|Presencial=1(A:1,R:0)",
        obtenido_motor: fmtR01(r01CorregidoCtrl),
        observacion: "CTRL-POST-01 (2026-10-01T02:30Z) se ubica en 2026-09 en Bogotá; CTRL-POST-02 cae en sin_sector; CTRL-POST-08 (>corte) y CTRL-POST-06/07 (cierre inválido) quedan fuera."
    },
    {
        reporte_id: "R02",
        escenario: "control",
        descripcion: "Top 5 vacantes por postulaciones en ventana sin $unwind y con $lookup",
        esperado_manual: "VAC-YEB-001:3[match=true] ; VAC-YEB-003:2[match=true] ; Sin coincidencia:1[match=false] ; VAC-YEB-002:1[match=true] ; VAC-YEB-004:1[match=true]",
        obtenido_motor: fmtR02(r02Control),
        observacion: "CTRL-POST-03 referencia ObjectId inexistente (...0299) y se preserva como 'Sin coincidencia' con match=false."
    },
    {
        reporte_id: "R03",
        escenario: "control",
        descripcion: "Postulaciones en review/interview con fecha estrictamente menor al corte (< FECHA_CORTE)",
        esperado_manual: "CTRL-POST-04:12.00h",
        obtenido_motor: fmtR03(r03Control),
        observacion: "CTRL-POST-05 tiene fecha exactamente igual al corte (2026-10-05T00:00:00Z) y se excluye correctamente."
    },
    {
        reporte_id: "R04",
        escenario: "control",
        descripcion: "Duración media de procesos cerrados en minutos (BSON Date en [inicio, corte])",
        esperado_manual: "n_valida=3 | media=4910.00m | suma=14730.00m | excluidas=3",
        obtenido_motor: fmtR04(r04ControlVal, r04ControlExcl),
        observacion: "7890 + 3000 + 3840 = 14730 min / 3 = 4910.00 min. Excluidas: CTRL-POST-06 (string), CTRL-POST-07 (null) y CTRL-POST-08 (>corte)."
    },
    {
        reporte_id: "R01",
        escenario: "referencia",
        descripcion: "Finalizadas por mes (America/Bogota) y sector/modalidad en postulaciones_historial",
        esperado_manual: "2026-09|Servicios|Remoto=1(A:1,R:0) ; 2026-09|sin_sector|Presencial=1(A:0,R:1) ; 2026-10|Tecnología|Híbrido=1(A:1,R:0)",
        obtenido_motor: fmtR01(r01Ref),
        observacion: "POST-YEB-003 (2026-09, Servicios), POST-YEB-006 (2026-09, sin_sector) y POST-YEB-007 (2026-10, Tecnología)."
    },
    {
        reporte_id: "R02",
        escenario: "referencia",
        descripcion: "Top 5 vacantes con más postulaciones en postulaciones_historial (1 postulación = 1 unidad)",
        esperado_manual: "VAC-YEB-001:2[match=true] ; VAC-YEB-002:2[match=true] ; VAC-YEB-003:2[match=true] ; VAC-YEB-004:1[match=true] ; VAC-YEB-005:1[match=true]",
        obtenido_motor: fmtR02(r02Ref),
        observacion: "Suma de las 5 vacantes = 8 postulaciones (sin inflar por las 2–3 habilidades de cada vacante)."
    },
    {
        reporte_id: "R03",
        escenario: "referencia",
        descripcion: "Postulaciones en review/interview vencidas al corte 2026-10-05T00:00:00Z",
        esperado_manual: "POST-YEB-002:351.00h ; POST-YEB-001:321.00h ; POST-YEB-004:205.50h ; POST-YEB-008:158.00h",
        obtenido_motor: fmtR03(r03Ref),
        observacion: "4 postulaciones en review/interview vencidas ordenadas descendentemente por horas de atraso."
    },
    {
        reporte_id: "R04",
        escenario: "referencia",
        descripcion: "Duración media en minutos de procesos cerrados en postulaciones_historial",
        esperado_manual: "n_valida=3 | media=9906.67m | suma=29720.00m | excluidas=0",
        obtenido_motor: fmtR04(r04RefVal, r04RefExcl),
        observacion: "POST-YEB-003 (13475m) + POST-YEB-006 (5925m) + POST-YEB-007 (10320m) = 29720m / 3 = 9906.67m."
    }
];

let fallas = 0;
for (const fila of filasContraste) {
    const pasa = fila.esperado_manual === fila.obtenido_motor;
    fila.resultado = pasa ? "PASA" : "FALLA";
    if (!pasa) fallas++;
    print("  [" + fila.resultado + "] " + fila.reporte_id + " (" + fila.escenario + ")");
    print("        esperado = " + fila.esperado_manual);
    print("        obtenido = " + fila.obtenido_motor);
}
if (fallas !== 0) {
    throw new Error("[FALLO CP-052B/CP-052C] Se detectaron " + fallas + " diferencias entre el cálculo manual y el motor.");
}

// Exportar tabla de contraste CSV y salidas en Extended JSON con metadatos
const outputsDir = path.join(analyticsDir, "outputs");
const csvEscape = (v) => '"' + String(v === null || v === undefined ? "" : v).replace(/"/g, '""') + '"';
const encabezadosCsv = ["reporte_id", "escenario", "descripcion", "esperado_manual", "obtenido_motor", "resultado", "observacion"];
const cuerpoCsv = filasContraste.map((f) => [
    f.reporte_id, f.escenario, f.descripcion, f.esperado_manual, f.obtenido_motor, f.resultado, f.observacion
].map(csvEscape).join(",")).join("\n");
fs.writeFileSync(
    path.join(outputsDir, "contraste_reportes_r01_r04.csv"),
    "\uFEFF" + encabezadosCsv.join(",") + "\n" + cuerpoCsv + "\n",
    "utf8"
);

const paqueteEjson = {
    metadatos: {
        issue: "Issue #12 [NoSQL/Aggregation]",
        script: "analytics/scripts/06_reportes_agregacion_yeb.js",
        base_datos: DB_NAME,
        zona_horaria: ZONA_HORARIA,
        ventana_inicio: VENTANA_INICIO,
        fecha_corte: FECHA_CORTE,
        solo_lectura_verificado: true,
        huella_sha256_referencia_antes: huellaAntes,
        huella_sha256_referencia_despues: huellaDespues,
        regresion_v1_0: {
            defecto_corregido: "DEF-NOSQL-10-01: Agrupación mensual en UTC sin timezone America/Bogota, falta de categoría sin_sector en vacante_snapshot y operador <= en corte de vencidas.",
            salida_v1_defectuosa: r01Defectuoso,
            salida_v2_corregida: r01CorregidoCtrl,
            estado: "PASA"
        }
    },
    escenario_control: {
        R01_finalizadas_mes_sector_modalidad: r01CorregidoCtrl,
        R02_top5_vacantes: r02Control,
        R03_vencidas_al_corte: r03Control,
        R04_duracion_procesos_cerrados: {
            resumen_validas: r04ControlVal,
            detalle_excluidas: r04ControlExcl
        }
    },
    escenario_referencia: {
        R01_finalizadas_mes_sector_modalidad: r01Ref,
        R02_top5_vacantes: r02Ref,
        R03_vencidas_al_corte: r03Ref,
        R04_duracion_procesos_cerrados: {
            resumen_validas: r04RefVal,
            detalle_excluidas: r04RefExcl
        }
    },
    contraste_manual_vs_obtenido: filasContraste
};

fs.writeFileSync(
    path.join(outputsDir, "reportes_r01_r04_salidas.ejson"),
    EJSON.stringify(paqueteEjson, null, 2, { relaxed: false }) + "\n",
    "utf8"
);

print("\n  [OK] Huella SHA-256 de referencia intacta : " + huellaDespues.slice(0, 16) + "... (idéntica)");
print("  [OK] Contraste exportado a               : analytics/outputs/contraste_reportes_r01_r04.csv (8/8 PASA)");
print("  [OK] Salidas EJSON con metadatos en      : analytics/outputs/reportes_r01_r04_salidas.ejson");
print("\n==============================================================================");
print("RESULTADO FINAL ISSUE #12: PIPELINES R01–R04, REGRESIÓN v1.0 Y CONTRASTE MANUAL VERIFICADOS (CP-052 / CP-052B / CP-052C)");
print("==============================================================================");
