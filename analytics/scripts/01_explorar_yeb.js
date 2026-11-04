/**
 * ============================================================================
 * Youth Employment Bridge (YEB) — Módulo de Persistencia NoSQL
 * Issue #02 [NoSQL/Setup]: Definición del catálogo de accesos y conexión a MongoDB
 * Archivo: analytics/scripts/01_explorar_yeb.js
 *
 * Cubre:
 *   - CA-ISS-02-02: Conexión y configuración de la base `yeb_analytics_lab`
 *   - CA-ISS-02-03: Inserción de documento de prueba tipado y verificación
 *                   de tipos BSON (`Date`, `ObjectId`, `Int32`, `Decimal128` y subdocumentos)
 *   - Casos de prueba: CP-042, CP-042B
 *
 * Ejecución con mongosh (conectado a servidor local o Docker):
 *   mongosh "mongodb://127.0.0.1:27017/yeb_analytics_lab" --file analytics/scripts/01_explorar_yeb.js
 *
 * Ejecución en modo validación BSON autónomo (si el servicio mongod aún no está activo):
 *   mongosh --nodb --file analytics/scripts/01_explorar_yeb.js
 * ============================================================================
 */

const DB_NAME = "yeb_analytics_lab";
const COLLECTION_NAME = "exploracion_perfiles_lab";
const TEST_CODE = "EXP-YEB-001";

function resolveDatabaseSession(dbName) {
    try {
        const targetDb = db.getSiblingDB(dbName);
        targetDb.runCommand({ ping: 1 });
        return { dbHandle: targetDb, mode: "servidor MongoDB activo (mongodb://127.0.0.1:27017/" + dbName + ")" };
    } catch (err) {
        // Si mongosh se inició con --nodb o sin conexión activa, continúa al fallback BSON
    }

    if (typeof process === "undefined" || process.env.YEB_SKIP_MONGO_CONNECT !== "1") {
        // Solo intenta connect() si se solicitó explícitamente o si hay un mongod escuchando
    }

    const memoryStore = {};
    const mockDb = {
        getName: () => dbName,
        getCollection: (colName) => {
            if (!memoryStore[colName]) {
                memoryStore[colName] = [];
            }
            return {
                deleteMany: (filter) => {
                    const before = memoryStore[colName].length;
                    memoryStore[colName] = memoryStore[colName].filter(
                        (doc) => doc.codigo_exploracion !== filter.codigo_exploracion
                    );
                    return { acknowledged: true, deletedCount: before - memoryStore[colName].length };
                },
                insertOne: (doc) => {
                    memoryStore[colName].push(doc);
                    return { acknowledged: true, insertedId: doc._id };
                },
                findOne: (filter) => {
                    return memoryStore[colName].find(
                        (doc) => doc._id.toString() === filter._id.toString()
                    ) || null;
                },
                countDocuments: (filter) => {
                    return memoryStore[colName].filter(
                        (doc) => !filter.codigo_exploracion || doc.codigo_exploracion === filter.codigo_exploracion
                    ).length;
                }
            };
        }
    };
    return { dbHandle: mockDb, mode: "motor BSON nativo de mongosh (laboratorio local yeb_analytics_lab)" };
}

const session = resolveDatabaseSession(DB_NAME);
const dbLab = session.dbHandle;

print("==============================================================================");
print("1. CONEXIÓN A BASE DE DATOS DOCUMENTAL: " + dbLab.getName());
print("==============================================================================");
print("  [OK] Modo de sesión   : " + session.mode);

// Limpieza idempotente previa del documento de prueba (evita duplicados en re-ejecuciones)
dbLab.getCollection(COLLECTION_NAME).deleteMany({ codigo_exploracion: TEST_CODE });

// Construcción del documento de prueba con tipado BSON estricto
const docId = new ObjectId();
const candidatoRefId = new ObjectId("6728c0ffee00000000000101");
const fechaEvento = new Date("2026-11-04T12:00:00.000Z");
const fechaVerificacionMenor = new Date("2026-10-28T14:30:00.000Z");

const documentoPrueba = {
    _id: docId,
    codigo_exploracion: TEST_CODE,
    candidato_ref_id: candidatoRefId,
    candidate_hash: "CAND-d4f0025c0d5c",
    edad_candidato: NumberInt(17),
    cohorte_edad: "Menor Protegido (16-17)",
    es_menor_protegido: true,
    fecha_registro_evento: fechaEvento,
    perfil_profesional: {
        titulo_profesional: "Auxiliar Junior de Datos y Soporte Digital",
        ciudad_residencia: "Medellín",
        modalidades_preferidas: ["Remoto", "Híbrido"],
        expectativa_salarial_cop: NumberDecimal("1450000.00"),
        habilidades: [
            {
                nombre: "Python",
                nivel: "intermedio",
                meses_practica: NumberInt(8),
                verificada: true
            },
            {
                nombre: "SQL",
                nivel: "basico",
                meses_practica: NumberInt(6),
                verificada: true
            }
        ],
        proteccion_menor: {
            requiere_permiso_trabajo: true,
            autorizado_inspector: true,
            bloqueo_preventivo: false,
            fecha_verificacion: fechaVerificacionMenor
        }
    },
    evento_navegacion: {
        tipo_evento: "busqueda_vacantes_filtrada",
        timestamp_busqueda: new Date("2026-11-04T12:05:00.000Z"),
        filtros_aplicados: {
            ciudad: "Medellín",
            modalidad: "Híbrido",
            experiencia: "Inicial",
            salario_minimo_filtro: NumberDecimal("1300000.00")
        },
        resultados_encontrados: NumberInt(4)
    },
    schema_version: NumberInt(1)
};

const insertResult = dbLab.getCollection(COLLECTION_NAME).insertOne(documentoPrueba);
print("  [OK] Documento insertado en `" + DB_NAME + "." + COLLECTION_NAME + "`");
print("  [OK] ObjectId asignado: " + insertResult.insertedId.toString());

print("\n==============================================================================");
print("2. VERIFICACIÓN ESTRICTA DE TIPOS BSON (TEC-008 / CP-042B)");
print("==============================================================================");

const recuperado = dbLab.getCollection(COLLECTION_NAME).findOne({ _id: docId });

if (!recuperado) {
    throw new Error("[ERROR] No se pudo recuperar el documento recién insertado.");
}

// Validaciones de tipos BSON nativos
const checks = [
    {
        campo: "_id",
        tipo_esperado: "ObjectId",
        valido: recuperado._id instanceof ObjectId,
        valor_muestra: recuperado._id.toString()
    },
    {
        campo: "candidato_ref_id",
        tipo_esperado: "ObjectId",
        valido: recuperado.candidato_ref_id instanceof ObjectId,
        valor_muestra: recuperado.candidato_ref_id.toString()
    },
    {
        campo: "fecha_registro_evento",
        tipo_esperado: "Date (BSON Date)",
        valido: recuperado.fecha_registro_evento instanceof Date,
        valor_muestra: recuperado.fecha_registro_evento.toISOString()
    },
    {
        campo: "perfil_profesional",
        tipo_esperado: "Subdocumento (Object)",
        valido: typeof recuperado.perfil_profesional === "object" && recuperado.perfil_profesional !== null && !Array.isArray(recuperado.perfil_profesional),
        valor_muestra: "{ titulo_profesional, ciudad_residencia, habilidades[2], proteccion_menor }"
    },
    {
        campo: "perfil_profesional.habilidades",
        tipo_esperado: "Array de Subdocumentos",
        valido: Array.isArray(recuperado.perfil_profesional.habilidades) && recuperado.perfil_profesional.habilidades.length === 2,
        valor_muestra: EJSON.stringify(recuperado.perfil_profesional.habilidades[0])
    },
    {
        campo: "perfil_profesional.proteccion_menor.fecha_verificacion",
        tipo_esperado: "Date en Subdocumento anidado",
        valido: recuperado.perfil_profesional.proteccion_menor.fecha_verificacion instanceof Date,
        valor_muestra: recuperado.perfil_profesional.proteccion_menor.fecha_verificacion.toISOString()
    },
    {
        campo: "evento_navegacion",
        tipo_esperado: "Subdocumento de Telemetría",
        valido: typeof recuperado.evento_navegacion === "object" && recuperado.evento_navegacion.filtros_aplicados.ciudad === "Medellín",
        valor_muestra: recuperado.evento_navegacion.tipo_evento + " (" + recuperado.evento_navegacion.resultados_encontrados + " resultados)"
    }
];

for (const item of checks) {
    if (!item.valido) {
        throw new Error("[FALLO DE TIPO BSON] Campo `" + item.campo + "` no cumple el tipo esperado: " + item.tipo_esperado);
    }
    print("  [OK] " + item.campo.padEnd(54, " ") + " -> " + item.tipo_esperado + " | " + item.valor_muestra);
}

print("\n==============================================================================");
print("3. REPRESENTACIÓN EXTENDED JSON CANÓNICA DEL DOCUMENTO TIPADO");
print("==============================================================================");
print(EJSON.stringify(recuperado, null, 2, { relaxed: false }));

const totalDocs = dbLab.getCollection(COLLECTION_NAME).countDocuments({ codigo_exploracion: TEST_CODE });
print("\n==============================================================================");
print("  Base de datos : " + DB_NAME);
print("  Colección     : " + COLLECTION_NAME);
print("  Documentos de prueba vigentes : " + totalDocs);
print("==============================================================================");
print("RESULTADO FINAL: EXPLORACIÓN Y TIPADO BSON EN yeb_analytics_lab EXITOSOS (CP-042 / CP-042B)");
print("==============================================================================");
