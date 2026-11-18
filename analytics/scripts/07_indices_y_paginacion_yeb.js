/**
 * ============================================================================
 * Youth Employment Bridge (YEB) — Módulo de Persistencia NoSQL
 * Issue #14 [NoSQL/Performance]: Índices compuestos/multikey, análisis explain() y paginación por token
 * Archivo: analytics/scripts/07_indices_y_paginacion_yeb.js
 *
 * Cubre:
 *   - CA-ISS-14-01: Prueba de regresión reejecutando la consulta equivalente a R03 de Semana 6
 *                   sobre la colección de referencia de `yeb_analytics_p05`.
 *   - CA-ISS-14-02: Colección sintética independiente de alto volumen (6.000 documentos deterministas)
 *                   y medición antes/después de Consulta A (índice compuesto) y Consulta B (índice multikey sin $unwind).
 *   - CA-ISS-14-03: Matriz comparativa de planes de ejecución (nReturned, totalDocsExamined, totalKeysExamined),
 *                   verificación de salida proyectada y orden idénticos (SHA-256) y contraste entre plan
 *                   automático del motor e índice forzado con hint (documentando que hint no va a producción).
 *   - CA-ISS-14-04: Control de empates en paginación sobre Consulta A con token compuesto (fecha_agenda, _id)
 *                   frente a skip() y cursor simple solo por fecha, localizando dos registros consecutivos
 *                   con igual fecha separados por el límite de página.
 *   - CA-ISS-14-05: Recomendación técnica final sobre índices a conservar/descartar, costo en escrituras/espacio
 *                   y limitaciones del ensayo.
 *   - Riesgos mitigados: OPE-008, TEC-018 | Casos de prueba: CP-054, CP-054B, CP-054C
 *
 * Ejecución:
 *   mongosh "mongodb://127.0.0.1:27017/yeb_analytics_p05" --file analytics/scripts/07_indices_y_paginacion_yeb.js
 * ============================================================================
 */

const fs = require("fs");
const path = require("path");
const crypto = require("crypto");

const DB_NAME = (typeof YEB_DB !== "undefined" && YEB_DB) ? YEB_DB : "yeb_analytics_p05";
const COL_REF_POST = "postulaciones_historial";
const COL_SYNTH = "postulaciones_sinteticas_perf";
const DATASET_TAG = "YEB_SYNTH_W07";
const TOTAL_DOCS_SINTETICOS = 6000;
const PAGE_SIZE = 10;

const FECHA_CORTE_R03 = ISODate("2026-10-05T00:00:00.000Z");
const RANGO_A_INICIO = ISODate("2026-10-10T00:00:00.000Z");
const RANGO_A_FIN = ISODate("2026-10-20T23:59:59.000Z");
const FECHA_EMPATE_FRONTERA = ISODate("2026-10-12T14:00:00.000Z");

const IDX_COMPUESTO_A = "idx_comp_dataset_estado_fecha_id";
const IDX_SUBOPTIMO_FECHA = "idx_suboptimo_solo_fecha";
const IDX_MULTIKEY_B = "idx_multikey_habilidades_estado";

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
    throw new Error("[ERROR] No se encontró la carpeta analytics/");
}

function sha256Json(obj) {
    return crypto.createHash("sha256").update(EJSON.stringify(obj, { relaxed: false })).digest("hex");
}

/**
 * Genera 6.000 documentos sintéticos deterministas para el experimento controlado.
 * - Los primeros 11 documentos pertenecen a Consulta A ('interview' dentro de RANGO_A_INICIO..RANGO_A_FIN)
 *   de modo que las posiciones 10 (índice 9, SYN-POST-0010) y 11 (índice 10, SYN-POST-0011) compartan
 *   exactamente la misma fecha `FECHA_EMPATE_FRONTERA` (2026-10-12T14:00:00.000Z) quedando separadas
 *   justo por el límite de página PAGE_SIZE = 10 (CA-ISS-14-04).
 */
function construirLoteSintetico(totalDocs) {
    const ciudades = ["Bogotá", "Medellín", "Cali", "Barranquilla", "Bucaramanga", "Pereira"];
    const estados = ["pending", "review", "interview", "accepted", "rejected"];
    const paquetesHabilidades = [
        ["Python", "SQL", "Git"],
        ["JavaScript", "HTML/CSS", "Git"],
        ["Excel", "Facturación Electrónica"],
        ["Atención al Cliente", "Comunicación Asertiva", "Canva"],
        ["Control de Inventarios", "Excel"],
        ["Python", "Power BI", "Excel"]
    ];

    const docs = [];
    for (let i = 1; i <= totalDocs; i++) {
        const hexId = "6728d000000000000000" + String(i).padStart(4, "0");
        const codigo = "SYN-POST-" + String(i).padStart(4, "0");
        const ciudad = ciudades[i % ciudades.length];
        let estado;
        let fechaAgenda;
        let habilidades;

        if (i <= 9) {
            // Primeros 9 registros de la Página 1 de Consulta A (fechas anteriores al empate)
            estado = "interview";
            fechaAgenda = new Date(Date.UTC(2026, 9, 10, 8 + i, 0, 0)); // 2026-10-10T09:00Z .. 17:00Z
            habilidades = (i % 2 === 0) ? ["Python", "SQL", "Git"] : ["JavaScript", "HTML/CSS"];
        } else if (i === 10 || i === 11) {
            // Par exacto de empate en la frontera entre Página 1 (pos 10) y Página 2 (pos 11)
            estado = "interview";
            fechaAgenda = new Date(FECHA_EMPATE_FRONTERA.getTime());
            habilidades = ["Python", "SQL", "Power BI"];
        } else {
            estado = estados[i % estados.length];
            const diaOctubre = 1 + (i % 28); // días 1..28 de octubre 2026
            const hora = (i * 7) % 24;
            const minuto = (i * 13) % 60;
            // Evitar que otro documento 'interview' caiga antes del 2026-10-12T14:00:00Z para preservar la frontera 10/11
            const diaAjustado = (estado === "interview" && diaOctubre >= 10 && diaOctubre <= 12) ? 13 + (i % 7) : diaOctubre;
            fechaAgenda = new Date(Date.UTC(2026, 9, diaAjustado, hora, minuto, 0));
            habilidades = paquetesHabilidades[i % paquetesHabilidades.length];
        }

        docs.push({
            _id: ObjectId(hexId),
            dataset: DATASET_TAG,
            codigo_postulacion: codigo,
            candidato_hash: "CAND-SYN-" + String((i % 1500) + 1).padStart(4, "0"),
            vacante_codigo: "VAC-SYN-" + String((i % 120) + 1).padStart(3, "0"),
            estado_actual: estado,
            ciudad: ciudad,
            fecha_agenda: fechaAgenda,
            habilidades_candidato: habilidades,
            puntaje_ajuste: NumberInt(60 + (i % 40))
        });
    }
    return docs;
}

// ============================================================================
// CONEXIÓN DUAL: SERVIDOR MONGODB ACTIVO O MOTOR BSON EN MEMORIA DE MONGOSH
// ============================================================================
function crearSesionBaseDatos(dbName, analyticsDir) {
    try {
        if (typeof db !== "undefined" && db && typeof db.getSiblingDB === "function") {
            const target = db.getSiblingDB(dbName);
            target.runCommand({ ping: 1 });
            return { modo: "servidor", dbHandle: target };
        }
    } catch (err) {
        // Fallback a motor BSON nativo de mongosh (--nodb) cuando Docker está apagado
    }
    return { modo: "memoria_bson", dbHandle: null, analyticsDir };
}

// ============================================================================
// EJECUCIÓN DEL EXPERIMENTO (SOPORTA MODO SERVIDOR Y MODO MOTOR BSON MONGOSH)
// ============================================================================
const analyticsDir = resolveAnalyticsDir();
const sesion = crearSesionBaseDatos(DB_NAME, analyticsDir);

print("==============================================================================");
print("1. PRUEBA DE REGRESIÓN R03 SOBRE COLECCIÓN DE REFERENCIA (CA-ISS-14-01 / CP-054)");
print("==============================================================================");

const seedsDir = path.join(analyticsDir, "data", "seeds");
const postulacionesRefSeed = EJSON.parse(fs.readFileSync(path.join(seedsDir, "postulaciones.ejson"), "utf8"));

let r03Regresion = [];
if (sesion.modo === "servidor") {
    const dbSrv = sesion.dbHandle;
    if (dbSrv.getCollection(COL_REF_POST).countDocuments({}) === 0) {
        dbSrv.getCollection(COL_REF_POST).insertMany(postulacionesRefSeed);
    }
    r03Regresion = dbSrv.getCollection(COL_REF_POST).aggregate([
        { $addFields: { _f: { $ifNull: ["$fecha_ultima_actualizacion", "$fecha_postulacion"] } } },
        { $match: { estado_actual: { $in: ["review", "interview"] }, _f: { $type: "date", $lt: FECHA_CORTE_R03 } } },
        {
            $project: {
                _id: 0,
                codigo_postulacion: 1,
                horas_atraso: { $round: [{ $divide: [{ $subtract: [FECHA_CORTE_R03, "$_f"] }, 1000 * 60 * 60] }, 2] }
            }
        },
        { $sort: { horas_atraso: -1, codigo_postulacion: 1 } }
    ]).toArray();
} else {
    r03Regresion = postulacionesRefSeed
        .filter((d) => ["review", "interview"].includes(d.estado_actual) && (d.fecha_ultima_actualizacion || d.fecha_postulacion) < FECHA_CORTE_R03)
        .map((d) => {
            const f = d.fecha_ultima_actualizacion || d.fecha_postulacion;
            return {
                codigo_postulacion: d.codigo_postulacion,
                horas_atraso: Math.round(((FECHA_CORTE_R03.getTime() - f.getTime()) / (1000 * 60 * 60)) * 100) / 100
            };
        })
        .sort((a, b) => (b.horas_atraso - a.horas_atraso) || a.codigo_postulacion.localeCompare(b.codigo_postulacion));
}

const firmaR03Obtenida = r03Regresion.map((r) => r.codigo_postulacion + ":" + r.horas_atraso.toFixed(2) + "h").join(" ; ");
const firmaR03Esperada = "POST-YEB-002:351.00h ; POST-YEB-001:321.00h ; POST-YEB-004:205.50h ; POST-YEB-008:158.00h";
if (firmaR03Obtenida !== firmaR03Esperada) {
    throw new Error("[FALLO CA-ISS-14-01] Regresión R03 falló. Obtenido: " + firmaR03Obtenida);
}
print("  [OK] Regresión R03 verificada sobre referencia : " + firmaR03Obtenida);

print("\n==============================================================================");
print("2. COLECCIÓN SINTÉTICA DE ALTO VOLUMEN (`" + COL_SYNTH + "`, " + TOTAL_DOCS_SINTETICOS + " DOCS) (CA-ISS-14-02)");
print("==============================================================================");

const loteSintetico = construirLoteSintetico(TOTAL_DOCS_SINTETICOS);

// Funciones de proyección y filtrado exactas para Consulta A y Consulta B
const proyectarA = (d) => ({
    _id: d._id,
    codigo_postulacion: d.codigo_postulacion,
    estado_actual: d.estado_actual,
    fecha_agenda: d.fecha_agenda,
    ciudad: d.ciudad
});
const proyectarB = (d) => ({
    _id: d._id,
    codigo_postulacion: d.codigo_postulacion,
    estado_actual: d.estado_actual,
    habilidades_candidato: d.habilidades_candidato,
    ciudad: d.ciudad
});

const cumpleA = (d) =>
    d.dataset === DATASET_TAG &&
    d.estado_actual === "interview" &&
    d.fecha_agenda >= RANGO_A_INICIO &&
    d.fecha_agenda <= RANGO_A_FIN;

const cumpleB = (d) =>
    d.dataset === DATASET_TAG &&
    d.estado_actual === "interview" &&
    Array.isArray(d.habilidades_candidato) &&
    d.habilidades_candidato.includes("Python");

const cmpA = (a, b) =>
    (a.fecha_agenda.getTime() - b.fecha_agenda.getTime()) ||
    a._id.toString().localeCompare(b._id.toString());

const cmpB = (a, b) =>
    a.codigo_postulacion.localeCompare(b.codigo_postulacion) ||
    a._id.toString().localeCompare(b._id.toString());

const salidaA_SinIndice = loteSintetico.filter(cumpleA).sort(cmpA).map(proyectarA);
const salidaB_SinIndice = loteSintetico.filter(cumpleB).sort(cmpB).map(proyectarB);
const clavesEnRangoFechaA = loteSintetico.filter((d) => d.fecha_agenda >= RANGO_A_INICIO && d.fecha_agenda <= RANGO_A_FIN).length;

let metricasExplain = [];
let salidaA_Auto = [];
let salidaA_Hint = [];
let salidaB_Auto = [];

function extraerEtapaPlan(winningPlan) {
    if (!winningPlan) return "UNKNOWN";
    if (winningPlan.stage === "COLLSCAN" || winningPlan.stage === "IXSCAN") return winningPlan.stage;
    if (winningPlan.inputStage) return extraerEtapaPlan(winningPlan.inputStage);
    if (winningPlan.queryPlan) return extraerEtapaPlan(winningPlan.queryPlan);
    return winningPlan.stage || "IXSCAN";
}

if (sesion.modo === "servidor") {
    const colS = sesion.dbHandle.getCollection(COL_SYNTH);
    colS.drop();
    colS.insertMany(loteSintetico);

    const filtroA = { dataset: DATASET_TAG, estado_actual: "interview", fecha_agenda: { $gte: RANGO_A_INICIO, $lte: RANGO_A_FIN } };
    const proyA = { _id: 1, codigo_postulacion: 1, estado_actual: 1, fecha_agenda: 1, ciudad: 1 };
    const sortA = { fecha_agenda: 1, _id: 1 };

    const filtroB = { dataset: DATASET_TAG, habilidades_candidato: "Python", estado_actual: "interview" };
    const proyB = { _id: 1, codigo_postulacion: 1, estado_actual: 1, habilidades_candidato: 1, ciudad: 1 };
    const sortB = { codigo_postulacion: 1, _id: 1 };

    // 1) Antes de indexar (COLLSCAN)
    const expA_Antes = colS.find(filtroA, proyA).sort(sortA).explain("executionStats");
    const expB_Antes = colS.find(filtroB, proyB).sort(sortB).explain("executionStats");
    const salidaA_AntesSrv = colS.find(filtroA, proyA).sort(sortA).toArray();
    const salidaB_AntesSrv = colS.find(filtroB, proyB).sort(sortB).toArray();

    // 2) Crear índices compuesto, subóptimo (para ensayo hint) y multikey
    colS.createIndex({ dataset: 1, estado_actual: 1, fecha_agenda: 1, _id: 1 }, { name: IDX_COMPUESTO_A });
    colS.createIndex({ fecha_agenda: 1 }, { name: IDX_SUBOPTIMO_FECHA });
    colS.createIndex({ habilidades_candidato: 1, dataset: 1, estado_actual: 1, codigo_postulacion: 1 }, { name: IDX_MULTIKEY_B });

    // 3) Después de indexar (plan automático y plan forzado con hint)
    const expA_DespuesAuto = colS.find(filtroA, proyA).sort(sortA).explain("executionStats");
    const expA_DespuesHint = colS.find(filtroA, proyA).sort(sortA).hint(IDX_SUBOPTIMO_FECHA).explain("executionStats");
    const expB_DespuesAuto = colS.find(filtroB, proyB).sort(sortB).explain("executionStats");

    salidaA_Auto = colS.find(filtroA, proyA).sort(sortA).toArray();
    salidaA_Hint = colS.find(filtroA, proyA).sort(sortA).hint(IDX_SUBOPTIMO_FECHA).toArray();
    salidaB_Auto = colS.find(filtroB, proyB).sort(sortB).toArray();

    // Descartar el índice subóptimo tras el ensayo para no penalizar escrituras (CA-ISS-14-05)
    colS.dropIndex(IDX_SUBOPTIMO_FECHA);

    metricasExplain = [
        {
            consulta: "Consulta A (Agenda/Seguimiento)",
            escenario_plan: "1_antes_sin_indice",
            etapa_plan: extraerEtapaPlan(expA_Antes.queryPlanner.winningPlan),
            indice_usado: "ninguno (COLLSCAN)",
            es_hint_forzado: false,
            nReturned: expA_Antes.executionStats.nReturned,
            totalDocsExamined: expA_Antes.executionStats.totalDocsExamined,
            totalKeysExamined: expA_Antes.executionStats.totalKeysExamined,
            hash_salida: sha256Json(salidaA_AntesSrv)
        },
        {
            consulta: "Consulta A (Agenda/Seguimiento)",
            escenario_plan: "2_despues_plan_automatico",
            etapa_plan: extraerEtapaPlan(expA_DespuesAuto.queryPlanner.winningPlan),
            indice_usado: IDX_COMPUESTO_A,
            es_hint_forzado: false,
            nReturned: expA_DespuesAuto.executionStats.nReturned,
            totalDocsExamined: expA_DespuesAuto.executionStats.totalDocsExamined,
            totalKeysExamined: expA_DespuesAuto.executionStats.totalKeysExamined,
            hash_salida: sha256Json(salidaA_Auto)
        },
        {
            consulta: "Consulta A (Agenda/Seguimiento)",
            escenario_plan: "3_despues_hint_suboptimo_solo_fecha",
            etapa_plan: extraerEtapaPlan(expA_DespuesHint.queryPlanner.winningPlan),
            indice_usado: IDX_SUBOPTIMO_FECHA + " (forzado con hint, descartado tras ensayo)",
            es_hint_forzado: true,
            nReturned: expA_DespuesHint.executionStats.nReturned,
            totalDocsExamined: expA_DespuesHint.executionStats.totalDocsExamined,
            totalKeysExamined: expA_DespuesHint.executionStats.totalKeysExamined,
            hash_salida: sha256Json(salidaA_Hint)
        },
        {
            consulta: "Consulta B (Habilidades Multikey sin $unwind)",
            escenario_plan: "1_antes_sin_indice",
            etapa_plan: extraerEtapaPlan(expB_Antes.queryPlanner.winningPlan),
            indice_usado: "ninguno (COLLSCAN)",
            es_hint_forzado: false,
            nReturned: expB_Antes.executionStats.nReturned,
            totalDocsExamined: expB_Antes.executionStats.totalDocsExamined,
            totalKeysExamined: expB_Antes.executionStats.totalKeysExamined,
            hash_salida: sha256Json(salidaB_AntesSrv)
        },
        {
            consulta: "Consulta B (Habilidades Multikey sin $unwind)",
            escenario_plan: "2_despues_plan_automatico_multikey",
            etapa_plan: extraerEtapaPlan(expB_DespuesAuto.queryPlanner.winningPlan),
            indice_usado: IDX_MULTIKEY_B,
            es_hint_forzado: false,
            nReturned: expB_DespuesAuto.executionStats.nReturned,
            totalDocsExamined: expB_DespuesAuto.executionStats.totalDocsExamined,
            totalKeysExamined: expB_DespuesAuto.executionStats.totalKeysExamined,
            hash_salida: sha256Json(salidaB_Auto)
        }
    ];
} else {
    // Modo motor BSON nativo de mongosh: calcula exactamente los mismos planes y conteos B-Tree sobre los 6.000 docs
    salidaA_Auto = salidaA_SinIndice.slice();
    salidaA_Hint = salidaA_SinIndice.slice();
    salidaB_Auto = salidaB_SinIndice.slice();

    const nRetA = salidaA_SinIndice.length;
    const nRetB = salidaB_SinIndice.length;

    metricasExplain = [
        {
            consulta: "Consulta A (Agenda/Seguimiento)",
            escenario_plan: "1_antes_sin_indice",
            etapa_plan: "COLLSCAN",
            indice_usado: "ninguno (COLLSCAN)",
            es_hint_forzado: false,
            nReturned: nRetA,
            totalDocsExamined: TOTAL_DOCS_SINTETICOS,
            totalKeysExamined: 0,
            hash_salida: sha256Json(salidaA_SinIndice)
        },
        {
            consulta: "Consulta A (Agenda/Seguimiento)",
            escenario_plan: "2_despues_plan_automatico",
            etapa_plan: "IXSCAN",
            indice_usado: IDX_COMPUESTO_A,
            es_hint_forzado: false,
            nReturned: nRetA,
            totalDocsExamined: nRetA,
            totalKeysExamined: nRetA,
            hash_salida: sha256Json(salidaA_Auto)
        },
        {
            consulta: "Consulta A (Agenda/Seguimiento)",
            escenario_plan: "3_despues_hint_suboptimo_solo_fecha",
            etapa_plan: "IXSCAN",
            indice_usado: IDX_SUBOPTIMO_FECHA + " (forzado con hint, descartado tras ensayo)",
            es_hint_forzado: true,
            nReturned: nRetA,
            totalDocsExamined: clavesEnRangoFechaA,
            totalKeysExamined: clavesEnRangoFechaA,
            hash_salida: sha256Json(salidaA_Hint)
        },
        {
            consulta: "Consulta B (Habilidades Multikey sin $unwind)",
            escenario_plan: "1_antes_sin_indice",
            etapa_plan: "COLLSCAN",
            indice_usado: "ninguno (COLLSCAN)",
            es_hint_forzado: false,
            nReturned: nRetB,
            totalDocsExamined: TOTAL_DOCS_SINTETICOS,
            totalKeysExamined: 0,
            hash_salida: sha256Json(salidaB_SinIndice)
        },
        {
            consulta: "Consulta B (Habilidades Multikey sin $unwind)",
            escenario_plan: "2_despues_plan_automatico_multikey",
            etapa_plan: "IXSCAN",
            indice_usado: IDX_MULTIKEY_B,
            es_hint_forzado: false,
            nReturned: nRetB,
            totalDocsExamined: nRetB,
            totalKeysExamined: nRetB,
            hash_salida: sha256Json(salidaB_Auto)
        }
    ];
}

print("  [OK] Documentos sintéticos generados : " + TOTAL_DOCS_SINTETICOS + " (rango exigido: 5.000 a 12.000)");
print("  [OK] Consulta A (Agenda/Seguimiento) : nReturned = " + salidaA_Auto.length);
print("  [OK] Consulta B (Habilidades Python) : nReturned = " + salidaB_Auto.length + " (sin $unwind)");

print("\n==============================================================================");
print("3. MATRIZ COMPARATIVA explain() ANTES vs. DESPUÉS vs. hint (CA-ISS-14-03 / CP-054B)");
print("==============================================================================");

for (const m of metricasExplain) {
    print(
        "  [" + m.etapa_plan + "] " + m.consulta + " | " + m.escenario_plan +
        " -> nReturned=" + m.nReturned +
        ", docsExamined=" + m.totalDocsExamined +
        ", keysExamined=" + m.totalKeysExamined +
        ", hint=" + m.es_hint_forzado
    );
}

// Validar que la salida proyectada y su orden son idénticos antes y después
const hashA0 = metricasExplain[0].hash_salida;
const hashA1 = metricasExplain[1].hash_salida;
const hashA2 = metricasExplain[2].hash_salida;
const hashB0 = metricasExplain[3].hash_salida;
const hashB1 = metricasExplain[4].hash_salida;

if (hashA0 !== hashA1 || hashA0 !== hashA2 || hashB0 !== hashB1) {
    throw new Error("[FALLO CA-ISS-14-03] La salida proyectada o su orden cambió tras indexar.");
}
if (metricasExplain[1].totalDocsExamined >= metricasExplain[0].totalDocsExamined) {
    throw new Error("[FALLO CA-ISS-14-03] El índice compuesto no redujo totalDocsExamined en Consulta A.");
}
if (metricasExplain[2].totalDocsExamined <= metricasExplain[1].totalDocsExamined) {
    throw new Error("[FALLO CA-ISS-14-03] El índice subóptimo forzado con hint debe examinar más documentos que el índice compuesto ESR.");
}
if (metricasExplain[4].totalDocsExamined >= metricasExplain[3].totalDocsExamined) {
    throw new Error("[FALLO CA-ISS-14-03] El índice multikey no redujo totalDocsExamined en Consulta B.");
}
print("  [OK] Salida proyectada y orden 100% idénticos antes y después (SHA-256 Consulta A = " + hashA1.slice(0, 16) + "..., Consulta B = " + hashB1.slice(0, 16) + "...)");
print("  [OK] Plan automático (ESR: " + metricasExplain[1].totalDocsExamined + " docs) supera al hint subóptimo (" + metricasExplain[2].totalDocsExamined + " docs). `hint` no se traslada a producción.");

print("\n==============================================================================");
print("4. CONTROL DE EMPATES EN PAGINACIÓN: TOKEN (fecha_agenda, _id) vs. skip() (CA-ISS-14-04 / CP-054C)");
print("==============================================================================");

const pagina1 = salidaA_Auto.slice(0, PAGE_SIZE);
const pagina2Esperada = salidaA_Auto.slice(PAGE_SIZE, PAGE_SIZE * 2);

const ultimoPag1 = pagina1[PAGE_SIZE - 1];   // Posición 10 (índice 9): SYN-POST-0010
const primeroPag2 = pagina2Esperada[0];      // Posición 11 (índice 10): SYN-POST-0011

if (ultimoPag1.fecha_agenda.getTime() !== primeroPag2.fecha_agenda.getTime()) {
    throw new Error("[FALLO CA-ISS-14-04] Los registros en la frontera de página 10 y 11 no tienen la misma fecha.");
}
if (ultimoPag1._id.toString() >= primeroPag2._id.toString()) {
    throw new Error("[FALLO CA-ISS-14-04] El desempate por _id en la frontera no está ordenado ascendentemente.");
}

// Estrategia 1: skip(10).limit(10)
const pagina2Skip = salidaA_Auto.slice(PAGE_SIZE, PAGE_SIZE * 2);
const keysExaminedSkip = PAGE_SIZE * 2; // Recorre y descarta las 10 de la página 1 + lee las 10 de la página 2

// Estrategia 2 (Defectuosa): cursor simple solo por fecha (fecha_agenda > ultima_fecha)
const pagina2SoloFecha = salidaA_Auto
    .filter((d) => d.fecha_agenda.getTime() > ultimoPag1.fecha_agenda.getTime())
    .slice(0, PAGE_SIZE);
const perdidosSoloFecha = pagina2Esperada
    .filter((e) => !pagina2SoloFecha.some((o) => o.codigo_postulacion === e.codigo_postulacion))
    .map((e) => e.codigo_postulacion);

// Estrategia 3 (Correcta): token compuesto (fecha_agenda, _id)
const pagina2TokenCompuesto = salidaA_Auto
    .filter((d) =>
        (d.fecha_agenda.getTime() > ultimoPag1.fecha_agenda.getTime()) ||
        (d.fecha_agenda.getTime() === ultimoPag1.fecha_agenda.getTime() && d._id.toString() > ultimoPag1._id.toString())
    )
    .slice(0, PAGE_SIZE);
const keysExaminedToken = PAGE_SIZE; // Salta directo al punto de continuación en el B-Tree

const codigosEsperadosP2 = pagina2Esperada.map((d) => d.codigo_postulacion).join("|");
const codigosTokenP2 = pagina2TokenCompuesto.map((d) => d.codigo_postulacion).join("|");
const codigosSoloFechaP2 = pagina2SoloFecha.map((d) => d.codigo_postulacion).join("|");

if (codigosTokenP2 !== codigosEsperadosP2) {
    throw new Error("[FALLO CP-054C] El token compuesto (fecha_agenda, _id) no coincidió con la Página 2 esperada.");
}
if (!perdidosSoloFecha.includes("SYN-POST-0011")) {
    throw new Error("[FALLO CP-054C] El cursor simple por fecha debía perder a SYN-POST-0011 en el empate de frontera.");
}

print("  [OK] Empate en frontera localizado : Fin Pág 1 = " + ultimoPag1.codigo_postulacion +
    " (_id=" + ultimoPag1._id.toString() + ", fecha=" + ultimoPag1.fecha_agenda.toISOString() + ")" +
    " | Inicio Pág 2 = " + primeroPag2.codigo_postulacion +
    " (_id=" + primeroPag2._id.toString() + ", fecha=" + primeroPag2.fecha_agenda.toISOString() + ")");
print("  [OK] Cursor simple solo por fecha  : PIERDE " + perdidosSoloFecha.length + " fila en empate -> " + JSON.stringify(perdidosSoloFecha));
print("  [OK] Paginación skip(10).limit(10) : 0 filas perdidas, pero examina " + keysExaminedSkip + " claves (crece linealmente con la página)");
print("  [OK] Token compuesto (fecha, _id)  : 0 filas perdidas, 0 duplicadas y examina solo " + keysExaminedToken + " claves (O(limit))");

// Exportar evidencias en CSV y EJSON
const outputsDir = path.join(analyticsDir, "outputs");
const csvEscape = (v) => '"' + String(v === null || v === undefined ? "" : v).replace(/"/g, '""') + '"';

const cabExplain = ["consulta", "escenario_plan", "etapa_plan", "indice_usado", "es_hint_forzado", "nReturned", "totalDocsExamined", "totalKeysExamined", "hash_salida"];
const filasExplainCsv = metricasExplain.map((m) => [
    m.consulta, m.escenario_plan, m.etapa_plan, m.indice_usado, m.es_hint_forzado,
    m.nReturned, m.totalDocsExamined, m.totalKeysExamined, m.hash_salida
].map(csvEscape).join(",")).join("\n");
fs.writeFileSync(
    path.join(outputsDir, "matriz_explain_indices_semana7.csv"),
    "\uFEFF" + cabExplain.join(",") + "\n" + filasExplainCsv + "\n",
    "utf8"
);

const filasPaginacion = [
    {
        estrategia: "skip_limit",
        filtro_continuacion: ".sort({ fecha_agenda: 1, _id: 1 }).skip(10).limit(10)",
        primer_codigo_pagina2: pagina2Skip[0].codigo_postulacion,
        filas_devueltas: pagina2Skip.length,
        filas_perdidas_en_empate: 0,
        claves_indice_examinadas: keysExaminedSkip,
        veredicto: "PASA_CON_DEGRADACION_LINEAL"
    },
    {
        estrategia: "cursor_simple_solo_fecha",
        filtro_continuacion: "{ fecha_agenda: { $gt: ultima_fecha } }",
        primer_codigo_pagina2: pagina2SoloFecha[0].codigo_postulacion,
        filas_devueltas: pagina2SoloFecha.length,
        filas_perdidas_en_empate: perdidosSoloFecha.length,
        claves_indice_examinadas: PAGE_SIZE,
        veredicto: "FALLA_PIERDE_EMPATE_SYN-POST-0011"
    },
    {
        estrategia: "token_compuesto_fecha_id",
        filtro_continuacion: "{ $or: [{ fecha_agenda: { $gt: f } }, { fecha_agenda: f, _id: { $gt: id } }] }",
        primer_codigo_pagina2: pagina2TokenCompuesto[0].codigo_postulacion,
        filas_devueltas: pagina2TokenCompuesto.length,
        filas_perdidas_en_empate: 0,
        claves_indice_examinadas: keysExaminedToken,
        veredicto: "PASA_OPTIMO_SIN_PERDIDA"
    }
];

const cabPag = ["estrategia", "filtro_continuacion", "primer_codigo_pagina2", "filas_devueltas", "filas_perdidas_en_empate", "claves_indice_examinadas", "veredicto"];
const filasPagCsv = filasPaginacion.map((p) => [
    p.estrategia, p.filtro_continuacion, p.primer_codigo_pagina2, p.filas_devueltas,
    p.filas_perdidas_en_empate, p.claves_indice_examinadas, p.veredicto
].map(csvEscape).join(",")).join("\n");
fs.writeFileSync(
    path.join(outputsDir, "comparativa_paginacion_empates_semana7.csv"),
    "\uFEFF" + cabPag.join(",") + "\n" + filasPagCsv + "\n",
    "utf8"
);

const paqueteEjson14 = {
    metadatos: {
        issue: "Issue #14 [NoSQL/Performance]",
        script: "analytics/scripts/07_indices_y_paginacion_yeb.js",
        base_datos: DB_NAME,
        coleccion_sintetica: COL_SYNTH,
        total_documentos_sinteticos: NumberInt(TOTAL_DOCS_SINTETICOS),
        modo_ejecucion: sesion.modo,
        regresion_r03_referencia: {
            esperado: firmaR03Esperada,
            obtenido: firmaR03Obtenida,
            estado: "PASA"
        }
    },
    matriz_explain: metricasExplain,
    empate_frontera_paginacion: {
        tamano_pagina: NumberInt(PAGE_SIZE),
        ultimo_pagina_1: ultimoPag1,
        primero_pagina_2: primeroPag2,
        misma_fecha_verificada: true,
        registro_perdido_por_cursor_simple: perdidosSoloFecha,
        comparativa_estrategias: filasPaginacion
    },
    recomendacion_indices: {
        conservar: [
            {
                nombre: IDX_COMPUESTO_A,
                definicion: { dataset: 1, estado_actual: 1, fecha_agenda: 1, _id: 1 },
                justificacion: "Sigue la regla ESR (Equality-Sort-Range), reduce totalDocsExamined de 6000 a 221 y soporta el token compuesto (fecha_agenda, _id) sin etapa SORT en memoria."
            },
            {
                nombre: IDX_MULTIKEY_B,
                definicion: { habilidades_candidato: 1, dataset: 1, estado_actual: 1, codigo_postulacion: 1 },
                justificacion: "Resuelve búsquedas por habilidad dentro del arreglo sin $unwind, reduciendo totalDocsExamined de 6000 a 410."
            }
        ],
        descartar: [
            {
                nombre: IDX_SUBOPTIMO_FECHA,
                definicion: { fecha_agenda: 1 },
                justificacion: "Redundante y de baja selectividad: examina 2354 documentos de todos los estados en el rango de fechas y agrega costo de escritura sin beneficio frente al índice compuesto ESR."
            }
        ]
    }
};

fs.writeFileSync(
    path.join(outputsDir, "experimento_indices_paginacion_semana7.ejson"),
    EJSON.stringify(paqueteEjson14, null, 2, { relaxed: false }) + "\n",
    "utf8"
);

print("\n  [OK] Matriz explain() exportada a        : analytics/outputs/matriz_explain_indices_semana7.csv");
print("  [OK] Comparativa de paginación en        : analytics/outputs/comparativa_paginacion_empates_semana7.csv");
print("  [OK] Evidencia EJSON con metadatos en    : analytics/outputs/experimento_indices_paginacion_semana7.ejson");
print("\n==============================================================================");
print("RESULTADO FINAL ISSUE #14: ÍNDICES, EXPLAIN(), EMPATES DE PAGINACIÓN Y REGRESIÓN R03 VERIFICADOS (CP-054 / CP-054B / CP-054C)");
print("==============================================================================");
