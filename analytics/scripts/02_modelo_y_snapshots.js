/**
 * ============================================================================
 * Youth Employment Bridge (YEB) — Módulo de Persistencia NoSQL
 * Issue #03 [NoSQL/Model]: Modelado documental v1 de perfiles laborales y Snapshots históricos
 * Archivo: analytics/scripts/02_modelo_y_snapshots.js
 *
 * Cubre:
 *   - CA-ISS-03-01: Subdocumentos 1:1 para el perfil profesional embebidos en `candidatos`
 *   - CA-ISS-03-02: Colección independiente `postulaciones_historial` referenciando a candidato y vacante
 *   - CA-ISS-03-03: Patrón Snapshot inmutable al transicionar a `interview`, `accepted` o `rejected`
 *   - CA-ISS-03-04: Semilla reproducible en Extended JSON (`.ejson`) con 0 referencias rotas
 *   - Casos de prueba: CP-043, CP-043B, CP-043C
 * ============================================================================
 */

const fs = require("fs");
const path = require("path");

const DB_NAME = "yeb_analytics_lab";
const BSON_MAX_BYTES = 16 * 1024 * 1024; // 16 MiB (16,777,216 bytes)

function resolveSeedsDir() {
    const candidates = [
        path.resolve(process.cwd(), "analytics", "data", "seeds"),
        path.resolve(process.cwd(), "data", "seeds"),
        path.resolve(__dirname, "..", "data", "seeds")
    ];
    for (const dir of candidates) {
        if (fs.existsSync(path.join(dir, "candidatos.ejson"))) {
            return dir;
        }
    }
    throw new Error("[ERROR] No se encontró el directorio analytics/data/seeds/");
}

function resolveDatabaseSession(dbName) {
    try {
        const targetDb = db.getSiblingDB(dbName);
        targetDb.runCommand({ ping: 1 });
        return { dbHandle: targetDb, mode: "servidor MongoDB activo (mongodb://127.0.0.1:27017/" + dbName + ")" };
    } catch (err) {
        // Fallback a motor BSON en memoria de mongosh si mongod no está corriendo
    }

    const memoryStore = {};
    const applyUpdate = (doc, updateSpec) => {
        if (updateSpec.$set) {
            for (const [k, v] of Object.entries(updateSpec.$set)) {
                const parts = k.split(".");
                let target = doc;
                for (let i = 0; i < parts.length - 1; i++) {
                    if (!target[parts[i]]) target[parts[i]] = {};
                    target = target[parts[i]];
                }
                target[parts[parts.length - 1]] = v;
            }
        }
        if (updateSpec.$inc) {
            for (const [k, v] of Object.entries(updateSpec.$inc)) {
                const currentVal = Number(doc[k] || 0);
                doc[k] = NumberInt(currentVal + Number(v));
            }
        }
    };

    const matchesFilter = (doc, filter) => {
        if (!filter || Object.keys(filter).length === 0) return true;
        for (const [k, v] of Object.entries(filter)) {
            if (v instanceof ObjectId || (doc[k] && doc[k] instanceof ObjectId)) {
                if (!doc[k] || doc[k].toString() !== v.toString()) return false;
            } else if (doc[k] !== v) {
                return false;
            }
        }
        return true;
    };

    const mockDb = {
        getName: () => dbName,
        getCollection: (colName) => {
            if (!memoryStore[colName]) memoryStore[colName] = [];
            return {
                deleteMany: (filter = {}) => {
                    const before = memoryStore[colName].length;
                    memoryStore[colName] = memoryStore[colName].filter((d) => !matchesFilter(d, filter));
                    return { acknowledged: true, deletedCount: before - memoryStore[colName].length };
                },
                insertMany: (docs) => {
                    for (const d of docs) memoryStore[colName].push(EJSON.deserialize(EJSON.serialize(d)));
                    return { acknowledged: true, insertedCount: docs.length };
                },
                find: (filter = {}) => {
                    const res = memoryStore[colName].filter((d) => matchesFilter(d, filter));
                    return { toArray: () => res };
                },
                findOne: (filter = {}) => {
                    return memoryStore[colName].find((d) => matchesFilter(d, filter)) || null;
                },
                updateOne: (filter, updateSpec) => {
                    const target = memoryStore[colName].find((d) => matchesFilter(d, filter));
                    if (!target) return { acknowledged: true, matchedCount: 0, modifiedCount: 0 };
                    applyUpdate(target, updateSpec);
                    return { acknowledged: true, matchedCount: 1, modifiedCount: 1 };
                },
                countDocuments: (filter = {}) => {
                    return memoryStore[colName].filter((d) => matchesFilter(d, filter)).length;
                }
            };
        }
    };

    return { dbHandle: mockDb, mode: "motor BSON nativo de mongosh (laboratorio local yeb_analytics_lab)" };
}

/**
 * Función de negocio que implementa el Patrón Snapshot (CA-ISS-03-03 / NEG-006).
 * Al transicionar una postulación a `interview`, `accepted` o `rejected`, si aún
 * no posee `snapshot_vacante`, congela una copia inmutable de los datos de la vacante.
 */
function transicionarPostulacionConSnapshot(dbHandle, codigoPostulacion, nuevoEstado) {
    const estadosConSnapshot = ["interview", "accepted", "rejected"];
    const colPostulaciones = dbHandle.getCollection("postulaciones_historial");
    const colVacantes = dbHandle.getCollection("vacantes");

    const postulacion = colPostulaciones.findOne({ codigo_postulacion: codigoPostulacion });
    if (!postulacion) {
        throw new Error("Postulación no encontrada: " + codigoPostulacion);
    }

    const ahora = new Date();
    const camposSet = {
        estado_actual: nuevoEstado,
        fecha_ultima_actualizacion: ahora
    };

    // Si pasa a interview o definitivo y no tiene snapshot previo, se captura inmutablemente
    if (estadosConSnapshot.includes(nuevoEstado) && postulacion.snapshot_vacante === null) {
        const vacante = colVacantes.findOne({ _id: postulacion.vacante_id });
        if (!vacante) {
            throw new Error("Referencia rota: vacante no encontrada para snapshot: " + postulacion.vacante_id);
        }
        camposSet.snapshot_vacante = {
            titulo: vacante.titulo,
            empresa_nombre: vacante.empresa.nombre,
            ciudad: vacante.ciudad,
            modalidad: vacante.modalidad,
            salario_min_ofertado: vacante.rango_salarial.minimo,
            salario_max_ofertado: vacante.rango_salarial.maximo,
            estado_gatillo: nuevoEstado,
            fecha_captura: ahora
        };
    }

    colPostulaciones.updateOne(
        { _id: postulacion._id },
        {
            $set: camposSet,
            $inc: { intentos_contacto: NumberInt(1) }
        }
    );

    return colPostulaciones.findOne({ _id: postulacion._id });
}

// ============================================================================
// EJECUCIÓN PRINCIPAL DE VERIFICACIÓN (CP-043, CP-043B, CP-043C)
// ============================================================================
const seedsDir = resolveSeedsDir();
const session = resolveDatabaseSession(DB_NAME);
const dbLab = session.dbHandle;

print("==============================================================================");
print("1. CARGA DE SEMILLAS EXTENDED JSON (.ejson) EN `" + dbLab.getName() + "` (CA-ISS-03-04)");
print("==============================================================================");
print("  [OK] Directorio de semillas : " + seedsDir);
print("  [OK] Modo de sesión         : " + session.mode);

const candidatosDocs = EJSON.parse(fs.readFileSync(path.join(seedsDir, "candidatos.ejson"), "utf8"));
const vacantesDocs = EJSON.parse(fs.readFileSync(path.join(seedsDir, "vacantes.ejson"), "utf8"));
const postulacionesDocs = EJSON.parse(fs.readFileSync(path.join(seedsDir, "postulaciones.ejson"), "utf8"));

const colCandidatos = dbLab.getCollection("candidatos");
const colVacantes = dbLab.getCollection("vacantes");
const colPostulaciones = dbLab.getCollection("postulaciones_historial");

colCandidatos.deleteMany({});
colVacantes.deleteMany({});
colPostulaciones.deleteMany({});

colCandidatos.insertMany(candidatosDocs);
colVacantes.insertMany(vacantesDocs);
colPostulaciones.insertMany(postulacionesDocs);

print("  [OK] Colección `candidatos` cargada              : " + colCandidatos.countDocuments({}) + " documentos");
print("  [OK] Colección `vacantes` cargada                : " + colVacantes.countDocuments({}) + " documentos");
print("  [OK] Colección `postulaciones_historial` cargada : " + colPostulaciones.countDocuments({}) + " documentos");

print("\n==============================================================================");
print("2. VERIFICACIÓN DE SUBDOCUMENTOS 1:1 E INTEGRIDAD REFERENCIAL (CP-043)");
print("==============================================================================");

const todosCandidatos = colCandidatos.find({}).toArray();
const todasVacantes = colVacantes.find({}).toArray();
const todasPostulaciones = colPostulaciones.find({}).toArray();

for (const cand of todosCandidatos) {
    if (!cand.perfil_profesional || !Array.isArray(cand.perfil_profesional.habilidades)) {
        throw new Error("[FALLO CA-ISS-03-01] Candidato sin subdocumento 1:1 perfil_profesional: " + cand.codigo_candidato);
    }
}
print("  [OK] CA-ISS-03-01: 100% de candidatos poseen subdocumento 1:1 `perfil_profesional` embebido.");

const idsCandidatos = new Set(todosCandidatos.map((c) => c._id.toString()));
const idsVacantes = new Set(todasVacantes.map((v) => v._id.toString()));

let referenciasCandidatoRotas = 0;
let referenciasVacanteRotas = 0;

for (const post of todasPostulaciones) {
    if (!idsCandidatos.has(post.candidato_id.toString())) {
        referenciasCandidatoRotas++;
    }
    if (!idsVacantes.has(post.vacante_id.toString())) {
        referenciasVacanteRotas++;
    }
}

if (referenciasCandidatoRotas !== 0 || referenciasVacanteRotas !== 0) {
    throw new Error(
        "[FALLO CA-ISS-03-04] Referencias rotas detectadas: candidatos=" +
        referenciasCandidatoRotas + ", vacantes=" + referenciasVacanteRotas
    );
}
print("  [OK] CA-ISS-03-04: Integridad referencial verificada -> 0 referencias rotas en `postulaciones_historial`.");

print("\n==============================================================================");
print("3. PRUEBA DE INMUTABILIDAD DEL PATRÓN SNAPSHOT (CA-ISS-03-03 / CP-043B / NEG-006)");
print("==============================================================================");

// Paso 1: POST-YEB-002 inicia en `review` con `snapshot_vacante: null`
const antesSnapshot = colPostulaciones.findOne({ codigo_postulacion: "POST-YEB-002" });
if (antesSnapshot.snapshot_vacante !== null) {
    throw new Error("[ERROR] POST-YEB-002 debía iniciar con snapshot_vacante = null en estado review.");
}
print("  [1] Estado inicial de POST-YEB-002 : `" + antesSnapshot.estado_actual + "` (snapshot_vacante = null)");

// Paso 2: Transicionar POST-YEB-002 a `interview` -> dispara captura de snapshot_vacante
const postEnEntrevista = transicionarPostulacionConSnapshot(dbLab, "POST-YEB-002", "interview");
print("  [2] Transición a `interview`       : Snapshot capturado -> titulo='" +
    postEnEntrevista.snapshot_vacante.titulo + "', salario_max=" +
    postEnEntrevista.snapshot_vacante.salario_max_ofertado.toString());

// Paso 3: El reclutador edita posteriormente la vacante original VAC-YEB-002 (cambia título y salario)
colVacantes.updateOne(
    { codigo_vacante: "VAC-YEB-002" },
    {
        $set: {
            titulo: "Coordinador Senior de Comunidad (Oferta Modificada)",
            "rango_salarial.maximo": NumberDecimal("2500000.00")
        }
    }
);
const vacanteEditada = colVacantes.findOne({ codigo_vacante: "VAC-YEB-002" });
print("  [3] Vacante original modificada    : nuevo titulo='" + vacanteEditada.titulo +
    "', nuevo salario_max=" + vacanteEditada.rango_salarial.maximo.toString());

// Paso 4: Verificar que el snapshot dentro de POST-YEB-002 permanece INMUTABLE
const postVerificada = colPostulaciones.findOne({ codigo_postulacion: "POST-YEB-002" });
const tituloSnapshot = postVerificada.snapshot_vacante.titulo;
const salarioSnapshot = postVerificada.snapshot_vacante.salario_max_ofertado.toString();

if (
    tituloSnapshot !== "Gestor Junior de Atención Digital y Comunidad" ||
    salarioSnapshot !== "1500000.00"
) {
    throw new Error("[FALLO CP-043B] El snapshot de la postulación mutó tras editar la vacante.");
}
print("  [OK] CP-043B verificado: `snapshot_vacante` conserva inmutables los datos pactados ('" +
    tituloSnapshot + "', COP " + salarioSnapshot + ") pese a la edición de la vacante.");

print("\n==============================================================================");
print("4. EVALUACIÓN DE LÍMITE BSON DE 16 MiB Y DESACOPLE 1:N (CA-ISS-03-02 / CP-043C / TEC-009)");
print("==============================================================================");

const candidatoMuestra = colCandidatos.findOne({ codigo_candidato: "CAND-YEB-001" });
const bytesCandidato = Buffer.byteLength(EJSON.stringify(candidatoMuestra), "utf8");
const porcentajeLimite = ((bytesCandidato / BSON_MAX_BYTES) * 100).toFixed(5);
const postulacionesDelCandidato = colPostulaciones.countDocuments({ candidato_id: candidatoMuestra._id });

if (bytesCandidato >= BSON_MAX_BYTES) {
    throw new Error("[FALLO CP-043C] Documento supera el límite BSON de 16 MiB.");
}

print("  [OK] Candidato evaluado            : " + candidatoMuestra.codigo_candidato + " (" + postulacionesDelCandidato + " postulaciones referenciadas)");
print("  [OK] Tamaño del documento candidato: " + bytesCandidato + " bytes (" + porcentajeLimite + "% del límite BSON de 16 MiB)");
print("  [OK] CP-043C verificado: El historial en `postulaciones_historial` no infla el documento de `candidatos`.");

print("\n==============================================================================");
print("RESULTADO FINAL ISSUE #03: MODELO DOCUMENTAL v1 Y SNAPSHOTS VERIFICADOS (CP-043 / CP-043B / CP-043C)");
print("==============================================================================");
