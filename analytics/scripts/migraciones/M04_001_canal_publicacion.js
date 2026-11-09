/**
 * ============================================================================
 * Youth Employment Bridge (YEB) — Migración de esquema documental
 * M04_001: vacantes schema_version 1 -> 2 agregando canal_publicacion = 'portal_web'
 * Issue #07 [NoSQL/Schema] — CA-ISS-07-05 | Riesgo TEC-012 | Caso CP-047C
 *
 * Reglas de la migración:
 *   1. Trabaja sobre una COPIA (`vacantes_v2_m04`), nunca sobre `vacantes`.
 *   2. Es idempotente: solo toca documentos con schema_version = 1, así que
 *      ejecutarla dos, tres o diez veces deja exactamente el mismo resultado.
 *   3. No hay ventana de caída: cada documento pasa de v1 a v2 en una sola
 *      escritura atómica y el contrato acepta ambas versiones durante la transición.
 *   4. Cada ejecución queda registrada en la colección `migraciones_esquema`.
 *
 * Uso directo:
 *   mongosh "mongodb://127.0.0.1:27017/yeb_analytics_lab" --file analytics/scripts/migraciones/M04_001_canal_publicacion.js
 * Uso desde otro script (deja el resultado en la variable global M04_001_RESULTADO):
 *   load(path.join(__dirname, "migraciones", "M04_001_canal_publicacion.js"));
 * ============================================================================
 */

(function () {
    const MIGRACION_ID = "M04_001";
    const ORIGEN = "vacantes";
    const COPIA = "vacantes_v2_m04";
    const dbName = (typeof YEB_DB !== "undefined" && YEB_DB) ? YEB_DB : "yeb_analytics_lab";
    const dbm = db.getSiblingDB(dbName);

    // Paso 1: copia idempotente. Los documentos que ya existen en la copia no se sobrescriben,
    // así una re-ejecución no devuelve a v1 lo que ya se migró.
    if (!dbm.getCollectionNames().includes(COPIA)) {
        const infoOrigen = dbm.getCollectionInfos({ name: ORIGEN })[0];
        const validator = infoOrigen && infoOrigen.options ? infoOrigen.options.validator : undefined;
        dbm.createCollection(COPIA, validator ? { validator, validationLevel: "strict", validationAction: "error" } : {});
    }
    dbm.getCollection(ORIGEN).aggregate([
        { $match: { codigo_vacante: { $not: /^TMP-/ } } },
        { $merge: { into: COPIA, on: "_id", whenMatched: "keepExisting", whenNotMatched: "insert" } }
    ]);

    // Paso 2: migración propiamente dicha (filtro por versión = idempotencia).
    const resultado = dbm.getCollection(COPIA).updateMany(
        { schema_version: 1 },
        { $set: { schema_version: NumberInt(2), canal_publicacion: "portal_web", migrado_por: MIGRACION_ID } }
    );

    const total = dbm.getCollection(COPIA).countDocuments({});
    const enV2 = dbm.getCollection(COPIA).countDocuments({ schema_version: 2, canal_publicacion: "portal_web" });
    const pendientes = dbm.getCollection(COPIA).countDocuments({ schema_version: { $ne: 2 } });

    // Paso 3: bitácora de ejecuciones.
    dbm.getCollection("migraciones_esquema").updateOne(
        { _id: MIGRACION_ID },
        {
            $setOnInsert: {
                descripcion: "vacantes schema_version 1 -> 2 con canal_publicacion = 'portal_web'",
                coleccion_origen: ORIGEN,
                coleccion_destino: COPIA,
                primera_ejecucion: new Date()
            },
            $set: { ultima_ejecucion: new Date(), ultimo_resultado: { modificados: NumberInt(resultado.modifiedCount), total_v2: NumberInt(enV2) } },
            $inc: { ejecuciones: NumberInt(1) }
        },
        { upsert: true }
    );

    globalThis.M04_001_RESULTADO = {
        coincidencias: resultado.matchedCount,
        modificados: resultado.modifiedCount,
        total,
        en_v2: enV2,
        pendientes
    };

    print("  [M04_001] coincidencias=" + resultado.matchedCount + " | modificados=" + resultado.modifiedCount +
        " | documentos en v2=" + enV2 + "/" + total + " | pendientes=" + pendientes);
})();
