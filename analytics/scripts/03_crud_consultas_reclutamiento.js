/**
 * ============================================================================
 * Youth Employment Bridge (YEB) — Módulo de Persistencia NoSQL
 * Issue #06 [NoSQL/CRUD]: Operaciones CRUD aisladas y catálogo de 10 consultas para reclutamiento
 * Archivo: analytics/scripts/03_crud_consultas_reclutamiento.js
 *
 * Cubre:
 *   - CA-ISS-06-01: Operaciones atómicas con `$set` e `$inc` sobre datos temporales `TMP-*`
 *   - CA-ISS-06-02: Catálogo C01–C10 con contraste entre valor esperado y valor obtenido
 *   - Riesgos: SEC-013 (alteración de datos productivos), OPE-006 (paginación skip/limit)
 *   - Casos de prueba: CP-046, CP-046B
 *
 * Ejecución (requiere un servidor MongoDB activo, local o el contenedor `yeb_mongo`):
 *   mongosh "mongodb://127.0.0.1:27017/yeb_analytics_lab" --file analytics/scripts/03_crud_consultas_reclutamiento.js
 *
 * Variables opcionales (definirlas con --eval antes de --file):
 *   YEB_DB       -> base de datos destino (por defecto yeb_analytics_lab)
 *   YEB_KEEP_TMP -> true para conservar los documentos TMP-* al terminar (inspección manual)
 * ============================================================================
 */

const fs = require("fs");
const path = require("path");
const crypto = require("crypto");

const DB_NAME = (typeof YEB_DB !== "undefined" && YEB_DB) ? YEB_DB : "yeb_analytics_lab";
const KEEP_TMP = (typeof YEB_KEEP_TMP !== "undefined") && YEB_KEEP_TMP === true;
const TMP_REGEX = /^TMP-/;

// ----------------------------------------------------------------------------
// Utilidades de entorno
// ----------------------------------------------------------------------------
function resolveAnalyticsDir() {
    const candidates = [
        path.resolve(process.cwd(), "analytics"),
        path.resolve(process.cwd()),
        path.resolve(__dirname, "..")
    ];
    for (const dir of candidates) {
        if (fs.existsSync(path.join(dir, "data", "seeds", "candidatos.ejson"))) {
            return dir;
        }
    }
    throw new Error("[ERROR] No se encontró la carpeta analytics/ (falta data/seeds/candidatos.ejson)");
}

function connectOrFail(dbName) {
    const target = db.getSiblingDB(dbName);
    try {
        target.runCommand({ ping: 1 });
    } catch (err) {
        throw new Error(
            "[ERROR] Este script necesita un servidor MongoDB activo (docker compose up -d mongodb). Detalle: " + err.message
        );
    }
    return target;
}

function ensureSeedsLoaded(dbHandle, seedsDir) {
    // Si la base está vacía (entorno recién creado) se cargan las semillas del Issue #03.
    const pares = [
        ["candidatos", "candidatos.ejson"],
        ["vacantes", "vacantes.ejson"],
        ["postulaciones_historial", "postulaciones.ejson"]
    ];
    for (const [coleccion, archivo] of pares) {
        const col = dbHandle.getCollection(coleccion);
        if (col.countDocuments({}) === 0) {
            const docs = EJSON.parse(fs.readFileSync(path.join(seedsDir, archivo), "utf8"));
            col.insertMany(docs);
            print("  [i] Semilla cargada en `" + coleccion + "` (" + docs.length + " documentos)");
        }
    }
}

/**
 * Barrera de seguridad (SEC-013): toda escritura de este script pasa por aquí.
 * Si el filtro no restringe explícitamente el código al prefijo TMP-, se aborta.
 */
function assertFiltroTemporal(filtro, campoCodigo) {
    const valor = filtro[campoCodigo];
    const esTemporal =
        (typeof valor === "string" && TMP_REGEX.test(valor)) ||
        (valor instanceof RegExp && valor.source.startsWith("^TMP-"));
    if (!esTemporal) {
        throw new Error("[BLOQUEADO SEC-013] Escritura rechazada: el filtro no está limitado a documentos TMP-* -> " + EJSON.stringify(filtro));
    }
}

function huellaProductiva(dbHandle) {
    // Huella SHA-256 de todos los documentos NO temporales. Si cambia, el CRUD tocó producción.
    const hash = crypto.createHash("sha256");
    const colecciones = [
        ["candidatos", "codigo_candidato"],
        ["vacantes", "codigo_vacante"],
        ["postulaciones_historial", "codigo_postulacion"]
    ];
    const conteos = {};
    for (const [nombre, campo] of colecciones) {
        const docs = dbHandle.getCollection(nombre)
            .find({ [campo]: { $not: TMP_REGEX } })
            .sort({ _id: 1 })
            .toArray();
        conteos[nombre] = docs.length;
        for (const d of docs) {
            hash.update(EJSON.stringify(d, { relaxed: false }));
        }
    }
    return { sha256: hash.digest("hex"), conteos };
}

function codigos(docs, campo) {
    return docs.map((d) => d[campo]);
}

// ----------------------------------------------------------------------------
// Datos temporales TMP-* (identificadores fijos para que el resultado sea reproducible)
// ----------------------------------------------------------------------------
function oid(sufijo) {
    return ObjectId("6728c0ffee0000000000" + sufijo);
}

function habilidad(nombre, nivel, meses) {
    return { nombre, nivel, meses_practica: NumberInt(meses) };
}

function construirCandidatosTmp() {
    const base = (n, edad, ciudad, salario, modalidades, habilidades, experiencia, contacto) => ({
        _id: oid("91" + String(n).padStart(2, "0")),
        codigo_candidato: "TMP-CAND-" + String(n).padStart(2, "0"),
        candidate_hash: "TMP-HASH-" + String(n).padStart(4, "0"),
        edad: NumberInt(edad),
        cohorte_edad: edad < 18 ? "Menor Protegido (16-17)" : "Joven Adulto (18-24)",
        es_menor: edad < 18,
        estado_cuenta: "active",
        fecha_registro: ISODate("2026-10-01T09:00:00Z"),
        perfil_profesional: {
            titulo_profesional: "Perfil temporal de prueba " + n,
            ciudad_residencia: ciudad,
            modalidades_preferidas: modalidades,
            expectativa_salarial_cop: NumberDecimal(salario),
            habilidades,
            experiencia_laboral: experiencia
        },
        contacto,
        schema_version: NumberInt(1)
    });

    return [
        // Menor de 17 años: teléfono fijo reportado explícitamente como null
        base(1, 17, "Medellín", "1450000.00", ["Remoto", "Híbrido"],
            [habilidad("Python", "intermedio", 8), habilidad("SQL", "basico", 6)],
            [],
            { telefono_fijo: null, canal_preferido: "correo" }),
        // Menor de 16 años: el campo telefono_fijo NO existe en el documento
        base(2, 16, "Envigado", "1350000.00", ["Presencial"],
            [habilidad("Atención al Cliente", "avanzado", 14), habilidad("Excel", "basico", 4)],
            [{ cargo: "Auxiliar de ventas", sector: "Comercio", meses: NumberInt(6) }],
            { canal_preferido: "correo" }),
        base(3, 20, "Medellín", "1900000.00", ["Remoto"],
            [habilidad("Python", "avanzado", 24), habilidad("SQL", "intermedio", 18), habilidad("Power BI", "intermedio", 10)],
            [
                { cargo: "Practicante de datos", sector: "Tecnología", meses: NumberInt(6) },
                { cargo: "Monitor académico", sector: "Educación", meses: NumberInt(12) }
            ],
            { telefono_fijo: "604-555-0103", canal_preferido: "telefono" }),
        base(4, 22, "Itagüí", "1650000.00", ["Presencial", "Híbrido"],
            [habilidad("Excel", "avanzado", 30), habilidad("SQL", "basico", 5)],
            [{ cargo: "Auxiliar contable", sector: "Servicios Financieros", meses: NumberInt(18) }],
            { telefono_fijo: null, canal_preferido: "correo" }),
        base(5, 19, "Bello", "1750000.00", ["Remoto", "Híbrido"],
            [habilidad("JavaScript", "intermedio", 12), habilidad("Git", "intermedio", 12)],
            [{ cargo: "Desarrollador freelance", sector: "Tecnología", meses: NumberInt(4) }],
            { canal_preferido: "chat" }),
        // Caso trampa para $elemMatch: Python básico + Excel avanzado; Tecnología 3 meses + Comercio 24 meses
        base(6, 24, "Medellín", "2200000.00", ["Híbrido"],
            [habilidad("Python", "basico", 3), habilidad("Excel", "avanzado", 20)],
            [
                { cargo: "Analista de datos", sector: "Tecnología", meses: NumberInt(3) },
                { cargo: "Cajero", sector: "Comercio", meses: NumberInt(24) }
            ],
            { telefono_fijo: "604-555-0106", canal_preferido: "telefono" })
    ];
}

function construirVacantesTmp() {
    const base = (n, titulo, modalidad, min, max) => ({
        _id: oid("92" + String(n).padStart(2, "0")),
        codigo_vacante: "TMP-VAC-" + String(n).padStart(2, "0"),
        titulo,
        descripcion: "Vacante temporal para pruebas CRUD del Issue #06.",
        ciudad: "Medellín",
        modalidad,
        experiencia_requerida: "Inicial",
        estado: "published",
        admite_menores_autorizados: true,
        empresa: { empresa_id: NumberInt(90), nombre: "Empresa Temporal de Pruebas S.A.S.", sector: "Tecnología", verificada: true },
        rango_salarial: { minimo: NumberDecimal(min), maximo: NumberDecimal(max), moneda: "COP" },
        habilidades_requeridas: ["Python", "SQL"],
        fecha_publicacion: ISODate("2026-09-30T08:00:00Z"),
        metricas: { postulaciones_recibidas: NumberInt(0) },
        schema_version: NumberInt(1)
    });
    return [
        base(1, "Auxiliar temporal de analítica de datos", "Híbrido", "1400000.00", "1800000.00"),
        base(2, "Asistente temporal de soporte digital", "Remoto", "1300000.00", "1600000.00")
    ];
}

// ----------------------------------------------------------------------------
// Operaciones CRUD atómicas (CA-ISS-06-01)
// ----------------------------------------------------------------------------

/** Crea una postulación TMP y, en la misma operación lógica, incrementa el contador de la vacante. */
function registrarPostulacionTmp(dbHandle, codigo, idSufijo, candidatoCodigo, vacanteCodigo, fecha) {
    const colCand = dbHandle.getCollection("candidatos");
    const colVac = dbHandle.getCollection("vacantes");
    const colPost = dbHandle.getCollection("postulaciones_historial");

    assertFiltroTemporal({ codigo_postulacion: codigo }, "codigo_postulacion");
    const candidato = colCand.findOne({ codigo_candidato: candidatoCodigo });
    const vacante = colVac.findOne({ codigo_vacante: vacanteCodigo });
    if (!candidato || !vacante) {
        throw new Error("Referencia inexistente al crear " + codigo);
    }

    colPost.insertOne({
        _id: oid("93" + idSufijo),
        codigo_postulacion: codigo,
        candidato_id: candidato._id,
        vacante_id: vacante._id,
        estado_actual: "pending",
        intentos_contacto: NumberInt(0),
        fecha_postulacion: fecha,
        fecha_ultima_actualizacion: fecha,
        snapshot_vacante: null,
        schema_version: NumberInt(1)
    });

    const filtroVacante = { codigo_vacante: vacanteCodigo };
    assertFiltroTemporal(filtroVacante, "codigo_vacante");
    colVac.updateOne(filtroVacante, {
        $inc: { "metricas.postulaciones_recibidas": NumberInt(1) },
        $set: { "metricas.ultima_postulacion": fecha }
    });
}

/** Un reclutador intenta contactar al candidato: $inc del contador y $set del estado, solo si sigue activa. */
function registrarContactoReclutador(dbHandle, codigo) {
    const filtro = { codigo_postulacion: codigo, estado_actual: { $in: ["pending", "review"] } };
    assertFiltroTemporal(filtro, "codigo_postulacion");
    return dbHandle.getCollection("postulaciones_historial").findOneAndUpdate(
        filtro,
        {
            $inc: { intentos_contacto: NumberInt(1) },
            $set: { estado_actual: "review", fecha_ultima_actualizacion: new Date() }
        },
        { returnDocument: "after" }
    );
}

function limpiarTemporales(dbHandle) {
    const resultado = {};
    const objetivos = [
        ["candidatos", "codigo_candidato"],
        ["vacantes", "codigo_vacante"],
        ["postulaciones_historial", "codigo_postulacion"]
    ];
    for (const [nombre, campo] of objetivos) {
        const filtro = { [campo]: TMP_REGEX };
        assertFiltroTemporal(filtro, campo);
        resultado[nombre] = dbHandle.getCollection(nombre).deleteMany(filtro).deletedCount;
    }
    return resultado;
}

// ============================================================================
// EJECUCIÓN PRINCIPAL
// ============================================================================
const analyticsDir = resolveAnalyticsDir();
const seedsDir = path.join(analyticsDir, "data", "seeds");
const outputsDir = path.join(analyticsDir, "outputs");
const dbLab = connectOrFail(DB_NAME);

print("==============================================================================");
print("1. PREPARACIÓN DEL ENTORNO AISLADO TMP-* EN `" + dbLab.getName() + "` (SEC-013)");
print("==============================================================================");
ensureSeedsLoaded(dbLab, seedsDir);
const residuos = limpiarTemporales(dbLab); // por si una ejecución anterior se interrumpió
print("  [OK] Residuos TMP-* de ejecuciones previas eliminados : " + JSON.stringify(residuos));

const huellaAntes = huellaProductiva(dbLab);
print("  [OK] Huella SHA-256 de datos productivos (antes)    : " + huellaAntes.sha256.substring(0, 16) + "...");
print("  [OK] Conteos productivos                            : " + JSON.stringify(huellaAntes.conteos));

// Prueba de la barrera: una escritura sin prefijo TMP- debe ser bloqueada antes de llegar al motor
let barreraFunciona = false;
try {
    assertFiltroTemporal({ codigo_postulacion: "POST-YEB-001" }, "codigo_postulacion");
} catch (err) {
    barreraFunciona = err.message.includes("BLOQUEADO SEC-013");
}
if (!barreraFunciona) {
    throw new Error("[FALLO SEC-013] La barrera de escritura no bloqueó un filtro productivo.");
}
print("  [OK] Barrera SEC-013 verificada: un filtro sobre POST-YEB-001 es rechazado antes de escribir.");

print("\n==============================================================================");
print("2. OPERACIONES CRUD ATÓMICAS CON $set E $inc (CA-ISS-06-01 / CP-046)");
print("==============================================================================");

// CREATE
dbLab.getCollection("candidatos").insertMany(construirCandidatosTmp());
dbLab.getCollection("vacantes").insertMany(construirVacantesTmp());
print("  [C] Insertados 6 candidatos TMP-CAND-* y 2 vacantes TMP-VAC-*");

const postulacionesPlan = [
    ["TMP-POST-01", "01", "TMP-CAND-01", "TMP-VAC-01", ISODate("2026-10-01T10:00:00Z")],
    ["TMP-POST-02", "02", "TMP-CAND-02", "TMP-VAC-01", ISODate("2026-10-02T10:00:00Z")],
    ["TMP-POST-03", "03", "TMP-CAND-03", "TMP-VAC-01", ISODate("2026-10-03T10:00:00Z")],
    ["TMP-POST-04", "04", "TMP-CAND-04", "TMP-VAC-01", ISODate("2026-10-04T10:00:00Z")],
    // TMP-POST-05 comparte fecha con TMP-POST-04: el desempate de la paginación lo hace _id
    ["TMP-POST-05", "05", "TMP-CAND-05", "TMP-VAC-02", ISODate("2026-10-04T10:00:00Z")],
    ["TMP-POST-06", "06", "TMP-CAND-06", "TMP-VAC-02", ISODate("2026-10-05T10:00:00Z")],
    ["TMP-POST-07", "07", "TMP-CAND-03", "TMP-VAC-02", ISODate("2026-10-06T10:00:00Z")]
];
for (const p of postulacionesPlan) {
    registrarPostulacionTmp(dbLab, p[0], p[1], p[2], p[3], p[4]);
}
print("  [C] Registradas 7 postulaciones TMP-POST-* con $inc del contador de la vacante");

// READ + verificación de $inc
const vac1 = dbLab.getCollection("vacantes").findOne({ codigo_vacante: "TMP-VAC-01" });
const vac2 = dbLab.getCollection("vacantes").findOne({ codigo_vacante: "TMP-VAC-02" });
if (vac1.metricas.postulaciones_recibidas !== 4 || vac2.metricas.postulaciones_recibidas !== 3) {
    throw new Error("[FALLO CP-046] Contadores $inc incorrectos: VAC-01=" + vac1.metricas.postulaciones_recibidas + ", VAC-02=" + vac2.metricas.postulaciones_recibidas);
}
print("  [R] $inc verificado -> TMP-VAC-01.postulaciones_recibidas = 4 | TMP-VAC-02 = 3 (esperado 4 y 3)");

// UPDATE: tres contactos del reclutador sobre TMP-POST-03
let ultimo = null;
for (let i = 0; i < 3; i++) {
    ultimo = registrarContactoReclutador(dbLab, "TMP-POST-03");
}
if (ultimo.intentos_contacto !== 3 || ultimo.estado_actual !== "review") {
    throw new Error("[FALLO CP-046] TMP-POST-03 debía quedar con 3 intentos y estado review.");
}
print("  [U] $inc + $set verificado -> TMP-POST-03: intentos_contacto = 3, estado_actual = 'review'");

// UPDATE condicional: una postulación cerrada no debe aceptar más contactos
dbLab.getCollection("postulaciones_historial").updateOne(
    { codigo_postulacion: "TMP-POST-06" },
    { $set: { estado_actual: "rejected", fecha_ultima_actualizacion: ISODate("2026-10-08T12:00:00Z") } }
);
const intentoCerrada = registrarContactoReclutador(dbLab, "TMP-POST-06");
if (intentoCerrada !== null) {
    throw new Error("[FALLO CP-046] Se incrementó el contador de una postulación ya rechazada.");
}
print("  [U] Guardia de estado verificada -> TMP-POST-06 (rejected) no admite $inc de contacto (0 coincidencias)");

print("\n==============================================================================");
print("3. CATÁLOGO DE CONSULTAS C01–C10 CON CONTRASTE ESPERADO vs. OBTENIDO (CA-ISS-06-02 / CP-046B)");
print("==============================================================================");

const colCand = dbLab.getCollection("candidatos");
const colPost = dbLab.getCollection("postulaciones_historial");
const soloTmpCand = { codigo_candidato: TMP_REGEX };
const soloTmpPost = { codigo_postulacion: TMP_REGEX };
const ordenPaginacion = { fecha_postulacion: -1, _id: -1 };
colPost.createIndex(ordenPaginacion, { name: "idx_paginacion_fecha_id" });

const catalogo = [];
function registrarConsulta(id, descripcion, operadores, esperado, obtenido, conservaOrden, nota) {
    const a = conservaOrden ? esperado.slice() : esperado.slice().sort();
    const b = conservaOrden ? obtenido.slice() : obtenido.slice().sort();
    const pasa = JSON.stringify(a) === JSON.stringify(b);
    catalogo.push({ id, descripcion, operadores, esperado: esperado.join(" | "), obtenido: obtenido.join(" | "), resultado: pasa ? "PASA" : "FALLA", nota: nota || "" });
    print("  [" + (pasa ? "OK" : "FALLA") + "] " + id + " " + descripcion);
    print("        esperado = [" + esperado.join(", ") + "]");
    print("        obtenido = [" + obtenido.join(", ") + "]");
}

// C01 — Filtro anidado (dot notation) sobre habilidades embebidas
registrarConsulta(
    "C01", "Candidatos con la habilidad Python (filtro anidado)", "dot notation",
    ["TMP-CAND-01", "TMP-CAND-03", "TMP-CAND-06"],
    codigos(colCand.find({ ...soloTmpCand, "perfil_profesional.habilidades.nombre": "Python" }).toArray(), "codigo_candidato"),
    false
);

// C02 — Pertenencia con $in sobre un campo anidado
registrarConsulta(
    "C02", "Candidatos con Power BI o Git", "$in",
    ["TMP-CAND-03", "TMP-CAND-05"],
    codigos(colCand.find({ ...soloTmpCand, "perfil_profesional.habilidades.nombre": { $in: ["Power BI", "Git"] } }).toArray(), "codigo_candidato"),
    false
);

// C03 — Rango salarial con $gte/$lte sobre Decimal128
registrarConsulta(
    "C03", "Expectativa salarial entre 1.400.000 y 1.800.000 COP", "$gte, $lte",
    ["TMP-CAND-01", "TMP-CAND-04", "TMP-CAND-05"],
    codigos(colCand.find({
        ...soloTmpCand,
        "perfil_profesional.expectativa_salarial_cop": { $gte: NumberDecimal("1400000.00"), $lte: NumberDecimal("1800000.00") }
    }).toArray(), "codigo_candidato"),
    false
);

// C04 — Rango combinado con pertenencia por ciudad
registrarConsulta(
    "C04", "Expectativa <= 1.800.000 COP y residencia en Medellín o Itagüí", "$lte, $in",
    ["TMP-CAND-01", "TMP-CAND-04"],
    codigos(colCand.find({
        ...soloTmpCand,
        "perfil_profesional.expectativa_salarial_cop": { $lte: NumberDecimal("1800000.00") },
        "perfil_profesional.ciudad_residencia": { $in: ["Medellín", "Itagüí"] }
    }).toArray(), "codigo_candidato"),
    false
);

// C05 — $elemMatch sobre el diagnóstico de habilidades (misma habilidad debe cumplir las 3 condiciones)
const c05ElemMatch = codigos(colCand.find({
    ...soloTmpCand,
    "perfil_profesional.habilidades": { $elemMatch: { nombre: "Python", nivel: { $in: ["intermedio", "avanzado"] }, meses_practica: { $gte: 6 } } }
}).toArray(), "codigo_candidato");
const c05SinElemMatch = codigos(colCand.find({
    ...soloTmpCand,
    "perfil_profesional.habilidades.nombre": "Python",
    "perfil_profesional.habilidades.nivel": { $in: ["intermedio", "avanzado"] },
    "perfil_profesional.habilidades.meses_practica": { $gte: 6 }
}).toArray(), "codigo_candidato");
registrarConsulta(
    "C05", "Python de nivel intermedio/avanzado con >= 6 meses de práctica", "$elemMatch, $in, $gte",
    ["TMP-CAND-01", "TMP-CAND-03"],
    c05ElemMatch,
    false,
    "Sin $elemMatch el motor devuelve [" + c05SinElemMatch.sort().join(", ") + "]: TMP-CAND-06 entra por error porque combina Python básico con Excel avanzado de 20 meses."
);

// C06 — $elemMatch sobre la experiencia laboral previa
const c06SinElemMatch = codigos(colCand.find({
    ...soloTmpCand,
    "perfil_profesional.experiencia_laboral.sector": "Tecnología",
    "perfil_profesional.experiencia_laboral.meses": { $gte: 6 }
}).toArray(), "codigo_candidato");
registrarConsulta(
    "C06", "Al menos 6 meses de experiencia en una misma empresa del sector Tecnología", "$elemMatch, $gte",
    ["TMP-CAND-03"],
    codigos(colCand.find({
        ...soloTmpCand,
        "perfil_profesional.experiencia_laboral": { $elemMatch: { sector: "Tecnología", meses: { $gte: 6 } } }
    }).toArray(), "codigo_candidato"),
    false,
    "Sin $elemMatch el motor devuelve [" + c06SinElemMatch.sort().join(", ") + "]: TMP-CAND-06 suma 3 meses en Tecnología con 24 meses en Comercio."
);

// C07 — null explícito (se distingue con $type porque { campo: null } también trae ausentes)
const c07Ambiguo = codigos(colCand.find({ ...soloTmpCand, "contacto.telefono_fijo": null }).toArray(), "codigo_candidato");
registrarConsulta(
    "C07", "Teléfono fijo reportado explícitamente como null", "$type: 'null'",
    ["TMP-CAND-01", "TMP-CAND-04"],
    codigos(colCand.find({ ...soloTmpCand, "contacto.telefono_fijo": { $type: "null" } }).toArray(), "codigo_candidato"),
    false,
    "El filtro { telefono_fijo: null } devuelve [" + c07Ambiguo.sort().join(", ") + "] porque mezcla null explícito con campo ausente."
);

// C08 — Campo ausente
registrarConsulta(
    "C08", "Candidatos sin el campo teléfono fijo (nunca reportado)", "$exists: false",
    ["TMP-CAND-02", "TMP-CAND-05"],
    codigos(colCand.find({ ...soloTmpCand, "contacto.telefono_fijo": { $exists: false } }).toArray(), "codigo_candidato"),
    false,
    "TMP-CAND-02 es menor de edad (16 años) y nunca reportó teléfono fijo: el campo no se inventa, se deja ausente."
);

// C09 / C10 — Paginación ordenada: skip/limit frente a cursor de continuación (fecha, _id)
const TAM_PAGINA = 3;
const pagina1 = colPost.find(soloTmpPost).sort(ordenPaginacion).limit(TAM_PAGINA).toArray();
const ultimoP1 = pagina1[pagina1.length - 1];
const filtroContinuacion = {
    ...soloTmpPost,
    $or: [
        { fecha_postulacion: { $lt: ultimoP1.fecha_postulacion } },
        { fecha_postulacion: ultimoP1.fecha_postulacion, _id: { $lt: ultimoP1._id } }
    ]
};

const skipAntes = codigos(colPost.find(soloTmpPost).sort(ordenPaginacion).skip(TAM_PAGINA).limit(TAM_PAGINA).toArray(), "codigo_postulacion");

// Mientras el reclutador lee la página 1 llega una postulación nueva (más reciente que todas)
registrarPostulacionTmp(dbLab, "TMP-POST-08", "08", "TMP-CAND-04", "TMP-VAC-02", ISODate("2026-10-07T10:00:00Z"));

const skipDespues = codigos(colPost.find(soloTmpPost).sort(ordenPaginacion).skip(TAM_PAGINA).limit(TAM_PAGINA).toArray(), "codigo_postulacion");
const cursorDespues = codigos(colPost.find(filtroContinuacion).sort(ordenPaginacion).limit(TAM_PAGINA).toArray(), "codigo_postulacion");

const planSkip = colPost.find({}).sort(ordenPaginacion).skip(TAM_PAGINA).limit(TAM_PAGINA).explain("executionStats").executionStats;
const planCursor = colPost.find({
    $or: [
        { fecha_postulacion: { $lt: ultimoP1.fecha_postulacion } },
        { fecha_postulacion: ultimoP1.fecha_postulacion, _id: { $lt: ultimoP1._id } }
    ]
}).sort(ordenPaginacion).limit(TAM_PAGINA).explain("executionStats").executionStats;

print("  [i] Página 1 (ambas estrategias) = [" + codigos(pagina1, "codigo_postulacion").join(", ") + "]");
registrarConsulta(
    "C09", "Página 2 con skip/limit (orden fecha_postulacion desc, _id desc)", "sort, skip, limit",
    ["TMP-POST-04", "TMP-POST-03", "TMP-POST-02"],
    skipAntes,
    true,
    "Tras llegar TMP-POST-08 la misma página 2 con skip devuelve [" + skipDespues.join(", ") +
    "]: TMP-POST-05 se repite. keysExamined skip=" + planSkip.totalKeysExamined + "."
);
registrarConsulta(
    "C10", "Página 2 con cursor de continuación (fecha, _id) tras llegar una postulación nueva", "$or, $lt, sort, limit",
    ["TMP-POST-04", "TMP-POST-03", "TMP-POST-02"],
    cursorDespues,
    true,
    "El cursor no repite ni salta registros aunque cambie el total. keysExamined cursor=" + planCursor.totalKeysExamined +
    " frente a " + planSkip.totalKeysExamined + " con skip (índice idx_paginacion_fecha_id)."
);

if (skipDespues.join() === skipAntes.join()) {
    throw new Error("[FALLO OPE-006] La demostración de deriva de skip/limit no se reprodujo.");
}

const fallidas = catalogo.filter((c) => c.resultado !== "PASA");
if (fallidas.length > 0) {
    throw new Error("[FALLO CP-046B] Consultas con diferencia entre esperado y obtenido: " + fallidas.map((c) => c.id).join(", "));
}

// Exportar la evidencia del contraste
const csvEscape = (v) => '"' + String(v).replace(/"/g, '""') + '"';
const encabezado = ["consulta_id", "descripcion", "operadores", "valor_esperado", "valor_obtenido", "resultado", "observacion"];
const filas = catalogo.map((c) => [c.id, c.descripcion, c.operadores, c.esperado, c.obtenido, c.resultado, c.nota].map(csvEscape).join(","));
const rutaCsv = path.join(outputsDir, "contraste_consultas_c01_c10.csv");
fs.writeFileSync(rutaCsv, "﻿" + encabezado.join(",") + "\n" + filas.join("\n") + "\n", "utf8");
print("\n  [OK] Contraste exportado a analytics/outputs/contraste_consultas_c01_c10.csv (" + catalogo.length + " consultas, 0 fallas)");

print("\n==============================================================================");
print("4. LIMPIEZA DE TEMPORALES Y CONTROL DE NO AFECTACIÓN PRODUCTIVA (DELETE / SEC-013)");
print("==============================================================================");
if (KEEP_TMP) {
    print("  [i] YEB_KEEP_TMP = true -> los documentos TMP-* se conservan para inspección manual.");
} else {
    const borrados = limpiarTemporales(dbLab);
    print("  [D] Documentos TMP-* eliminados : " + JSON.stringify(borrados));
}

const huellaDespues = huellaProductiva(dbLab);
if (huellaDespues.sha256 !== huellaAntes.sha256) {
    throw new Error("[FALLO SEC-013] La huella de los datos productivos cambió durante el CRUD.");
}
print("  [OK] Huella SHA-256 de datos productivos (después) : " + huellaDespues.sha256.substring(0, 16) + "... (idéntica)");
print("  [OK] CP-046 verificado: $set/$inc aplicados solo sobre TMP-* y 0 documentos productivos alterados.");

print("\n==============================================================================");
print("RESULTADO FINAL ISSUE #06: CRUD AISLADO Y CATÁLOGO C01–C10 VERIFICADOS (CP-046 / CP-046B)");
print("==============================================================================");
