/**
 * ============================================================================
 * Youth Employment Bridge (YEB) — Módulo de Persistencia NoSQL
 * Issue #07 [NoSQL/Schema]: Contratos estrictos con $jsonSchema, índices únicos y migración idempotente
 * Archivo: analytics/scripts/04_contratos_schema_migracion.js
 *
 * Cubre:
 *   - CA-ISS-07-01: Validador $jsonSchema (validationLevel strict, validationAction error) sobre `vacantes`
 *                   con $expr (salario mínimo <= máximo), enum de modalidades y longitud mínima del título
 *   - CA-ISS-07-02: Índices únicos (postulación candidato + vacante, código de vacante y de candidato)
 *   - CA-ISS-07-03: Flujo staging -> cuarentena -> carga de registros limpios
 *   - CA-ISS-07-04: Matriz de 8 pruebas de validación (3 válidas aceptadas, 5 inválidas rechazadas)
 *   - CA-ISS-07-05: Migración idempotente M04_001 (schema_version 1 -> 2, canal_publicacion = 'portal_web')
 *   - Riesgos: NEG-002, NEG-003, TEC-012 | Casos de prueba: CP-047, CP-047B, CP-047C
 *
 * Ejecución (requiere servidor MongoDB activo):
 *   mongosh "mongodb://127.0.0.1:27017/yeb_analytics_lab" --file analytics/scripts/04_contratos_schema_migracion.js
 * ============================================================================
 */

const fs = require("fs");
const path = require("path");

const DB_NAME = (typeof YEB_DB !== "undefined" && YEB_DB) ? YEB_DB : "yeb_analytics_lab";
const MODALIDADES = ["Remoto", "Híbrido", "Presencial"];
const TITULO_MIN = 10;

// Diccionario cerrado de equivalencias de modalidad (mismo criterio del Issue #05).
// Solo se corrige lo que tiene soporte en este diccionario; lo demás va a cuarentena.
const MAPA_MODALIDADES = {
    "presencial": "Presencial", "in-office": "Presencial", "on-site": "Presencial",
    "hibrido": "Híbrido", "híbrido": "Híbrido", "hybrid": "Híbrido", "mixto": "Híbrido",
    "remoto": "Remoto", "remote": "Remoto", "teletrabajo": "Remoto"
};

function resolveAnalyticsDir() {
    const candidates = [path.resolve(process.cwd(), "analytics"), path.resolve(process.cwd()), path.resolve(__dirname, "..")];
    for (const dir of candidates) {
        if (fs.existsSync(path.join(dir, "data", "seeds", "candidatos.ejson"))) {
            return dir;
        }
    }
    throw new Error("[ERROR] No se encontró la carpeta analytics/");
}

function connectOrFail(dbName) {
    const target = db.getSiblingDB(dbName);
    try {
        target.runCommand({ ping: 1 });
    } catch (err) {
        throw new Error("[ERROR] Este script necesita un servidor MongoDB activo (docker compose up -d mongodb). Detalle: " + err.message);
    }
    return target;
}

function ensureSeedsLoaded(dbHandle, seedsDir) {
    const pares = [["candidatos", "candidatos.ejson"], ["vacantes", "vacantes.ejson"], ["postulaciones_historial", "postulaciones.ejson"]];
    for (const [coleccion, archivo] of pares) {
        const col = dbHandle.getCollection(coleccion);
        if (col.countDocuments({}) === 0) {
            col.insertMany(EJSON.parse(fs.readFileSync(path.join(seedsDir, archivo), "utf8")));
            print("  [i] Semilla cargada en `" + coleccion + "`");
        }
    }
}

/** Resume el errInfo que devuelve MongoDB (código 121) en una frase corta y legible. */
function resumirErrorValidacion(err) {
    const reglas = [];
    const recorrer = (nodo, propiedad) => {
        if (!nodo || typeof nodo !== "object") return;
        if (Array.isArray(nodo)) { nodo.forEach((n) => recorrer(n, propiedad)); return; }
        const prop = nodo.propertyName || propiedad;
        if (nodo.operatorName === "$expr") {
            reglas.push("$expr rango_salarial.minimo <= rango_salarial.maximo");
        } else if (nodo.operatorName && !["$and", "$jsonSchema", "properties"].includes(nodo.operatorName)) {
            const faltantes = nodo.missingProperties ? " " + nodo.missingProperties.join(",") : "";
            reglas.push((prop ? prop + ": " : "") + nodo.operatorName + faltantes);
        }
        for (const v of Object.values(nodo)) recorrer(v, prop);
    };
    recorrer(err.errInfo, null);
    const unicas = [...new Set(reglas)];
    return unicas.length ? unicas.join("; ") : err.message;
}

// ----------------------------------------------------------------------------
// Contrato de vacantes (CA-ISS-07-01)
// ----------------------------------------------------------------------------
const CONTRATO_VACANTES = {
    $and: [
        {
            $jsonSchema: {
                bsonType: "object",
                title: "Contrato de vacantes YEB v2 (acepta schema_version 1 y 2)",
                required: ["codigo_vacante", "titulo", "ciudad", "modalidad", "estado", "empresa", "rango_salarial", "fecha_publicacion", "schema_version"],
                properties: {
                    codigo_vacante: { bsonType: "string", pattern: "^(VAC-YEB|TMP-VAC|TMP-VAL|STG-VAC)-[0-9]{2,4}$", description: "Código único con prefijo controlado" },
                    titulo: { bsonType: "string", minLength: TITULO_MIN, maxLength: 120, description: "Título descriptivo de al menos 10 caracteres" },
                    ciudad: { bsonType: "string", minLength: 3 },
                    modalidad: { enum: MODALIDADES, description: "Catálogo cerrado de modalidades" },
                    estado: { enum: ["draft", "published", "closed"] },
                    empresa: {
                        bsonType: "object",
                        required: ["nombre", "sector"],
                        properties: { nombre: { bsonType: "string", minLength: 2 }, sector: { bsonType: "string" } }
                    },
                    rango_salarial: {
                        bsonType: "object",
                        required: ["minimo", "maximo", "moneda"],
                        properties: {
                            minimo: { bsonType: "decimal", minimum: 0 },
                            maximo: { bsonType: "decimal", minimum: 0 },
                            moneda: { enum: ["COP"] }
                        }
                    },
                    fecha_publicacion: { bsonType: "date" },
                    schema_version: { bsonType: "int", enum: [1, 2] },
                    canal_publicacion: { enum: ["portal_web"] }
                }
            }
        },
        // $jsonSchema no compara dos campos entre sí: la regla de negocio salarial se expresa con $expr.
        { $expr: { $lte: ["$rango_salarial.minimo", "$rango_salarial.maximo"] } }
    ]
};

function aplicarContrato(dbHandle, coleccion, validator) {
    const opciones = { validator, validationLevel: "strict", validationAction: "error" };
    if (dbHandle.getCollectionNames().includes(coleccion)) {
        dbHandle.runCommand({ collMod: coleccion, ...opciones });
    } else {
        dbHandle.createCollection(coleccion, opciones);
    }
    return dbHandle.getCollectionInfos({ name: coleccion })[0].options;
}

function vacantePrueba(n, cambios) {
    const base = {
        codigo_vacante: "TMP-VAL-" + String(n).padStart(2, "0"),
        titulo: "Auxiliar de pruebas de validación",
        ciudad: "Medellín",
        modalidad: "Híbrido",
        estado: "published",
        empresa: { nombre: "Empresa de Validación S.A.S.", sector: "Tecnología" },
        rango_salarial: { minimo: NumberDecimal("1400000.00"), maximo: NumberDecimal("1800000.00"), moneda: "COP" },
        fecha_publicacion: ISODate("2026-10-20T08:00:00Z"),
        schema_version: NumberInt(1)
    };
    const doc = { ...base, ...cambios };
    for (const k of Object.keys(doc)) if (doc[k] === undefined) delete doc[k];
    return doc;
}

// ============================================================================
// EJECUCIÓN PRINCIPAL
// ============================================================================
const analyticsDir = resolveAnalyticsDir();
const dbLab = connectOrFail(DB_NAME);
ensureSeedsLoaded(dbLab, path.join(analyticsDir, "data", "seeds"));

print("==============================================================================");
print("1. CONTRATO $jsonSchema ESTRICTO SOBRE `vacantes` (CA-ISS-07-01 / NEG-002)");
print("==============================================================================");

// Antes de endurecer el motor se confirma que los datos existentes cumplen el contrato.
const incumplenAntes = dbLab.getCollection("vacantes").countDocuments({ $nor: [CONTRATO_VACANTES] });
if (incumplenAntes !== 0) {
    throw new Error("[ERROR] Hay " + incumplenAntes + " vacantes existentes que no cumplen el contrato; corregirlas antes de aplicarlo.");
}
const opcionesVacantes = aplicarContrato(dbLab, "vacantes", CONTRATO_VACANTES);
print("  [OK] Vacantes existentes que incumplen el contrato antes de aplicarlo : 0");
print("  [OK] validationLevel = '" + opcionesVacantes.validationLevel + "' | validationAction = '" + opcionesVacantes.validationAction + "'");
print("  [OK] Reglas: required(9), enum modalidad " + JSON.stringify(MODALIDADES) + ", titulo minLength " + TITULO_MIN + ", $expr minimo <= maximo");

print("\n==============================================================================");
print("2. ÍNDICES ÚNICOS (CA-ISS-07-02 / NEG-003 / CP-047B)");
print("==============================================================================");

dbLab.getCollection("postulaciones_historial").createIndex(
    { candidato_id: 1, vacante_id: 1 },
    { unique: true, name: "uq_postulacion_candidato_vacante" }
);
dbLab.getCollection("postulaciones_historial").createIndex({ codigo_postulacion: 1 }, { unique: true, name: "uq_codigo_postulacion" });
dbLab.getCollection("vacantes").createIndex({ codigo_vacante: 1 }, { unique: true, name: "uq_codigo_vacante" });
dbLab.getCollection("candidatos").createIndex({ codigo_candidato: 1 }, { unique: true, name: "uq_codigo_candidato" });
print("  [OK] postulaciones_historial : uq_postulacion_candidato_vacante { candidato_id: 1, vacante_id: 1 } (equivale a user_id + job_id del portal)");
print("  [OK] postulaciones_historial : uq_codigo_postulacion | vacantes : uq_codigo_vacante | candidatos : uq_codigo_candidato");

const original = dbLab.getCollection("postulaciones_historial").findOne({ codigo_postulacion: "POST-YEB-001" });
let duplicadaRechazada = false;
let codigoDuplicado = null;
try {
    dbLab.getCollection("postulaciones_historial").insertOne({
        codigo_postulacion: "TMP-DUP-01",
        candidato_id: original.candidato_id,
        vacante_id: original.vacante_id,
        estado_actual: "pending",
        intentos_contacto: NumberInt(0),
        fecha_postulacion: new Date(),
        snapshot_vacante: null,
        schema_version: NumberInt(1)
    });
} catch (err) {
    duplicadaRechazada = err.code === 11000;
    codigoDuplicado = err.code;
}
if (!duplicadaRechazada || dbLab.getCollection("postulaciones_historial").countDocuments({ codigo_postulacion: "TMP-DUP-01" }) !== 0) {
    throw new Error("[FALLO CP-047B] El índice único no rechazó la postulación duplicada.");
}
print("  [OK] CP-047B verificado: segunda postulación del mismo candidato a VAC-YEB-001 rechazada (E" + codigoDuplicado + " duplicate key)");

print("\n==============================================================================");
print("3. MATRIZ DE 8 PRUEBAS DE VALIDACIÓN EN EL MOTOR (CA-ISS-07-04 / CP-047)");
print("==============================================================================");

const matriz = [
    { caso: "V1", descripcion: "Vacante completa y coherente (Híbrido, 1,4 a 1,8 millones)", esperado: "ACEPTADO", doc: vacantePrueba(1, {}) },
    { caso: "V2", descripcion: "Salario mínimo igual al máximo (caso límite permitido)", esperado: "ACEPTADO", doc: vacantePrueba(2, { modalidad: "Remoto", rango_salarial: { minimo: NumberDecimal("1500000.00"), maximo: NumberDecimal("1500000.00"), moneda: "COP" } }) },
    { caso: "V3", descripcion: "Documento ya en versión 2 con canal_publicacion", esperado: "ACEPTADO", doc: vacantePrueba(3, { modalidad: "Presencial", schema_version: NumberInt(2), canal_publicacion: "portal_web" }) },
    { caso: "I1", descripcion: "Salario invertido (mínimo 2.000.000 > máximo 1.500.000)", esperado: "RECHAZADO", doc: vacantePrueba(4, { rango_salarial: { minimo: NumberDecimal("2000000.00"), maximo: NumberDecimal("1500000.00"), moneda: "COP" } }) },
    { caso: "I2", descripcion: "Modalidad fuera del catálogo ('Teletrabajo')", esperado: "RECHAZADO", doc: vacantePrueba(5, { modalidad: "Teletrabajo" }) },
    { caso: "I3", descripcion: "Título demasiado corto ('Aux', 3 caracteres)", esperado: "RECHAZADO", doc: vacantePrueba(6, { titulo: "Aux" }) },
    { caso: "I4", descripcion: "Falta el subdocumento obligatorio empresa", esperado: "RECHAZADO", doc: vacantePrueba(7, { empresa: undefined }) },
    { caso: "I5", descripcion: "Salario guardado como texto en lugar de Decimal128", esperado: "RECHAZADO", doc: vacantePrueba(8, { rango_salarial: { minimo: "1400000", maximo: "1800000", moneda: "COP" } }) }
];

const filasMatriz = [];
for (const prueba of matriz) {
    let obtenido = "ACEPTADO";
    let detalle = "Documento insertado";
    let codigo = "";
    try {
        dbLab.getCollection("vacantes").insertOne(prueba.doc);
    } catch (err) {
        obtenido = "RECHAZADO";
        codigo = String(err.code);
        detalle = err.code === 121 ? resumirErrorValidacion(err) : err.message;
    }
    const pasa = obtenido === prueba.esperado;
    filasMatriz.push({ ...prueba, obtenido, codigo, detalle, resultado: pasa ? "PASA" : "FALLA" });
    print("  [" + (pasa ? "OK" : "FALLA") + "] " + prueba.caso + " " + prueba.descripcion);
    print("        esperado=" + prueba.esperado + " | obtenido=" + obtenido + (codigo ? " (código " + codigo + ": " + detalle + ")" : ""));
}
dbLab.getCollection("vacantes").deleteMany({ codigo_vacante: /^TMP-VAL-/ });

const aceptadas = filasMatriz.filter((f) => f.obtenido === "ACEPTADO").length;
const rechazadas = filasMatriz.filter((f) => f.obtenido === "RECHAZADO").length;
if (filasMatriz.some((f) => f.resultado !== "PASA") || aceptadas !== 3 || rechazadas !== 5) {
    throw new Error("[FALLO CP-047] La matriz no dio 3 aceptadas y 5 rechazadas.");
}
print("  [OK] CP-047 verificado: 3 válidas aceptadas y 5 inválidas rechazadas por el motor (código 121). Documentos TMP-VAL-* retirados.");

print("\n==============================================================================");
print("4. FLUJO STAGING -> CUARENTENA -> CARGA DE LIMPIOS (CA-ISS-07-03)");
print("==============================================================================");

const colStaging = dbLab.getCollection("vacantes_staging");
const colCuarentena = dbLab.getCollection("vacantes_cuarentena");
// Idempotencia del flujo: se rehace desde cero en cada corrida.
colStaging.deleteMany({});
colCuarentena.deleteMany({});
dbLab.getCollection("vacantes").deleteMany({ codigo_vacante: /^STG-VAC-/ });

const loteStaging = EJSON.parse(fs.readFileSync(path.join(analyticsDir, "data", "staging", "vacantes_staging.ejson"), "utf8"));
colStaging.insertMany(loteStaging);
print("  [i] Lote recibido en `vacantes_staging` (sin contrato): " + colStaging.countDocuments({}) + " registros");

const codigosExistentes = new Set(
    dbLab.getCollection("vacantes").find({}, { codigo_vacante: 1 }).toArray().map((v) => v.codigo_vacante)
);
const enviarACuarentena = (registro, etapa, motivo) => {
    colCuarentena.insertOne({
        codigo_vacante: registro.codigo_vacante,
        fila_origen: registro._staging ? registro._staging.fila_origen : null,
        etapa,
        motivo,
        documento_original: registro,
        fecha_cuarentena: new Date()
    });
};

const cargadas = [];
for (const registro of colStaging.find({}).sort({ "_staging.fila_origen": 1 }).toArray()) {
    const limpio = JSON.parse(EJSON.stringify(registro, { relaxed: false }));
    const doc = EJSON.parse(JSON.stringify(limpio));
    delete doc._id;
    delete doc._staging;
    const correcciones = [];
    let motivo = null;

    // a) Correcciones con soporte documentado
    if (typeof doc.titulo === "string" && doc.titulo !== doc.titulo.trim()) {
        doc.titulo = doc.titulo.trim();
        correcciones.push("titulo sin espacios sobrantes");
    }
    if (!MODALIDADES.includes(doc.modalidad)) {
        const canonica = MAPA_MODALIDADES[String(doc.modalidad).trim().toLowerCase()];
        if (canonica) {
            correcciones.push("modalidad '" + doc.modalidad + "' -> '" + canonica + "' (diccionario cerrado)");
            doc.modalidad = canonica;
        } else {
            motivo = "Modalidad '" + doc.modalidad + "' sin equivalencia en el catálogo; no se adivina";
        }
    }
    if (!motivo && doc.rango_salarial) {
        for (const campo of ["minimo", "maximo"]) {
            const valor = doc.rango_salarial[campo];
            if (typeof valor === "string") {
                if (/^\d+(\.\d{1,2})?$/.test(valor.trim())) {
                    doc.rango_salarial[campo] = NumberDecimal(valor.trim());
                    correcciones.push(campo + " texto -> Decimal128");
                } else {
                    motivo = "Salario " + campo + " no numérico ('" + valor + "')";
                }
            }
        }
    }

    // b) Errores sin soporte para corregir: se aíslan, no se inventan datos
    if (!motivo && !doc.empresa) {
        motivo = "Sin subdocumento empresa: no hay soporte para saber quién publica la oferta";
    }
    if (!motivo && doc.rango_salarial && Number(doc.rango_salarial.minimo.toString()) > Number(doc.rango_salarial.maximo.toString())) {
        motivo = "Salario mínimo mayor que el máximo: no hay soporte para decidir cuál valor es el correcto";
    }
    if (!motivo && codigosExistentes.has(doc.codigo_vacante)) {
        motivo = "Código " + doc.codigo_vacante + " ya pertenece a una vacante publicada (conflicto de identificador)";
    }
    if (motivo) {
        enviarACuarentena(registro, "precarga", motivo);
        continue;
    }

    // c) El contrato del motor es la última barrera: lo que no cumpla también va a cuarentena
    doc.trazabilidad_carga = { lote: registro._staging.lote, fila_origen: registro._staging.fila_origen, correcciones };
    try {
        dbLab.getCollection("vacantes").insertOne(doc);
        cargadas.push(doc.codigo_vacante + (correcciones.length ? " (" + correcciones.join(", ") + ")" : ""));
    } catch (err) {
        enviarACuarentena(registro, "motor", "Rechazado por el contrato $jsonSchema: " + (err.code === 121 ? resumirErrorValidacion(err) : err.message));
    }
}

for (const c of cargadas) print("  [CARGADA]     " + c);
const cuarentenados = colCuarentena.find({}).sort({ fila_origen: 1 }).toArray();
for (const q of cuarentenados) print("  [CUARENTENA]  " + q.codigo_vacante + " fila " + q.fila_origen + " [" + q.etapa + "] " + q.motivo);

if (cargadas.length + cuarentenados.length !== loteStaging.length) {
    throw new Error("[FALLO CA-ISS-07-03] Se perdieron registros: cargadas + cuarentena != lote.");
}
if (dbLab.getCollection("vacantes").countDocuments({ $nor: [CONTRATO_VACANTES] }) !== 0) {
    throw new Error("[FALLO CA-ISS-07-03] Quedó en `vacantes` un documento que incumple el contrato.");
}
print("  [OK] Lote conciliado: " + loteStaging.length + " recibidos = " + cargadas.length + " cargados + " + cuarentenados.length + " en cuarentena (0 perdidos)");

print("\n==============================================================================");
print("5. MIGRACIÓN IDEMPOTENTE M04_001 SOBRE COPIA DE VACANTES (CA-ISS-07-05 / CP-047C / TEC-012)");
print("==============================================================================");

dbLab.getCollection("vacantes_v2_m04").drop();
dbLab.getCollection("migraciones_esquema").deleteOne({ _id: "M04_001" });
const rutaMigracion = path.join(__dirname, "migraciones", "M04_001_canal_publicacion.js");

print("  Ejecución 1:");
load(rutaMigracion);
const ejecucion1 = globalThis.M04_001_RESULTADO;
print("  Ejecución 2 (inmediata):");
load(rutaMigracion);
const ejecucion2 = globalThis.M04_001_RESULTADO;

const vacantesOrigenV1 = dbLab.getCollection("vacantes").countDocuments({ schema_version: 1 });
const bitacora = dbLab.getCollection("migraciones_esquema").findOne({ _id: "M04_001" });
if (ejecucion1.modificados !== ejecucion1.total || ejecucion1.pendientes !== 0) {
    throw new Error("[FALLO CP-047C] La primera ejecución no migró todos los documentos de la copia.");
}
if (ejecucion2.modificados !== 0 || ejecucion2.en_v2 !== ejecucion1.en_v2) {
    throw new Error("[FALLO CP-047C] La segunda ejecución modificó documentos: la migración no es idempotente.");
}
if (vacantesOrigenV1 === 0) {
    throw new Error("[FALLO CP-047C] La migración alteró la colección original `vacantes`.");
}
print("  [OK] Ejecución 1 migró " + ejecucion1.modificados + "/" + ejecucion1.total + " documentos a schema_version 2 con canal_publicacion = 'portal_web'");
print("  [OK] Ejecución 2 modificó 0 documentos (idempotente) | bitácora migraciones_esquema: " + bitacora.ejecuciones + " ejecuciones");
print("  [OK] La colección original `vacantes` conserva sus " + vacantesOrigenV1 + " documentos en schema_version 1");

// Evidencias en CSV
const outputsDir = path.join(analyticsDir, "outputs");
const csv = (v) => '"' + String(v === null || v === undefined ? "" : v).replace(/"/g, '""') + '"';
fs.writeFileSync(
    path.join(outputsDir, "matriz_validacion_jsonschema.csv"),
    "﻿" + ["caso", "descripcion", "resultado_esperado", "resultado_obtenido", "codigo_error", "regla_incumplida", "veredicto"].join(",") + "\n" +
    filasMatriz.map((f) => [f.caso, f.descripcion, f.esperado, f.obtenido, f.codigo, f.obtenido === "RECHAZADO" ? f.detalle : "", f.resultado].map(csv).join(",")).join("\n") + "\n",
    "utf8"
);
fs.writeFileSync(
    path.join(outputsDir, "cuarentena_vacantes_staging.csv"),
    "﻿" + ["codigo_vacante", "fila_origen", "etapa", "motivo", "titulo_original", "modalidad_original"].join(",") + "\n" +
    cuarentenados.map((q) => [q.codigo_vacante, q.fila_origen, q.etapa, q.motivo, q.documento_original.titulo, q.documento_original.modalidad].map(csv).join(",")).join("\n") + "\n",
    "utf8"
);
print("\n  [OK] Evidencias: outputs/matriz_validacion_jsonschema.csv y outputs/cuarentena_vacantes_staging.csv");

print("\n==============================================================================");
print("RESULTADO FINAL ISSUE #07: CONTRATOS, ÍNDICES ÚNICOS, CUARENTENA Y M04_001 VERIFICADOS (CP-047 / CP-047B / CP-047C)");
print("==============================================================================");
