export const DB_NAME = 'trackeat-personal';
export const DB_VERSION = 2;
export const AREAS = ['Cocina', 'Servicio', 'Caja', 'Administración'];
export const TURNOS = ['Matutino', 'Vespertino', 'Mixto'];
export const PHOTO_LIMIT = 5 * 1024 * 1024;

export function normalizeName(value) {
    return value
        .trim()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();
}

export function today() {
    const date = new Date();
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

// Ambas versiones se aplican dentro de la transacción de actualización de IndexedDB.
function upgradeDatabase(event) {
    const db = event.target.result;
    const transaction = event.target.transaction;
    if (event.oldVersion < 1) {
        const store = db.createObjectStore('personal', { keyPath: 'id', autoIncrement: true });
        store.createIndex('porArea', 'area');
    }
    if (event.oldVersion < 2 && event.newVersion >= 2) {
        const store = transaction.objectStore('personal');
        store.createIndex('porNombre', 'nombreBusqueda');
        const metadata = db.createObjectStore('metadatos', { keyPath: 'clave' });
        let migrated = 0;
        const cursorRequest = store.openCursor();
        cursorRequest.onsuccess = () => {
            const cursor = cursorRequest.result;
            if (cursor) {
                cursor.update({
                    ...cursor.value,
                    turno: cursor.value.turno || 'Matutino',
                    nombreBusqueda: normalizeName(cursor.value.nombre),
                });
                migrated += 1;
                cursor.continue();
            } else {
                metadata.put({
                    clave: 'migracion',
                    desde: event.oldVersion,
                    hasta: 2,
                    registros: migrated,
                    fecha: new Date().toISOString(),
                });
            }
        };
    }
}

export function openDatabase(name = DB_NAME, version = DB_VERSION) {
    return new Promise((resolve, reject) => {
        if (!globalThis.indexedDB) {
            reject(new Error('Este navegador no ofrece IndexedDB. Usa un navegador actualizado.'));
            return;
        }
        let blocked = false;
        const request = indexedDB.open(name, version);
        request.onupgradeneeded = upgradeDatabase;
        request.onblocked = () => {
            blocked = true;
            reject(new Error('Cierra las otras pestañas del catálogo y vuelve a intentar para actualizar la base.'));
        };
        request.onerror = () => reject(request.error);
        request.onsuccess = () => {
            const db = request.result;
            db.onversionchange = () => db.close();
            if (blocked) db.close();
            else resolve(db);
        };
    });
}

// Se confirma el resultado al completar la transacción, no solo al completar la petición.
async function readRequest(storeName, operation, name = DB_NAME) {
    const db = await openDatabase(name);
    try {
        return await new Promise((resolve, reject) => {
            const transaction = db.transaction(storeName, 'readonly');
            const request = operation(transaction.objectStore(storeName));
            transaction.oncomplete = () => resolve(request.result);
            transaction.onabort = () => reject(transaction.error || request.error || new Error('La consulta fue interrumpida.'));
            transaction.onerror = () => reject(transaction.error || request.error);
        });
    } finally {
        db.close();
    }
}

export function listPeople({ nombre = '', area = '' } = {}, name = DB_NAME) {
    return readRequest(
        'personal',
        (store) => {
            const prefix = normalizeName(nombre);
            if (prefix) return store.index('porNombre').getAll(IDBKeyRange.bound(prefix, `${prefix}\uffff`));
            if (area) return store.index('porArea').getAll(area);
            return store.getAll();
        },
        name,
    ).then((records) => records.filter((record) => !area || record.area === area).sort((a, b) => a.nombre.localeCompare(b.nombre, 'es')));
}

export function getPerson(id) {
    return readRequest('personal', (store) => store.get(id));
}

export function getMigration(name = DB_NAME) {
    return readRequest('metadatos', (store) => store.get('migracion'), name);
}

function validatePerson(person) {
    for (const key of ['nombre', 'email', 'telefono', 'area', 'puesto', 'fechaIngreso', 'turno']) {
        if (typeof person[key] !== 'string' || !person[key].trim()) throw new Error('Completa todos los campos del registro.');
    }
    if (person.nombre.trim().length < 2 || person.nombre.length > 100 || person.puesto.length > 70) {
        throw new Error('Revisa la longitud del nombre y del puesto.');
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(person.email) || person.email.length > 150) throw new Error('Escribe un correo válido.');
    if (!/^[+\d\s().-]{7,25}$/.test(person.telefono)) throw new Error('Escribe un teléfono válido de 7 a 25 caracteres.');
    if (!AREAS.includes(person.area) || !TURNOS.includes(person.turno)) throw new Error('Selecciona un área y un turno válidos.');
    const date = new Date(`${person.fechaIngreso}T12:00:00Z`);
    if (
        !/^\d{4}-\d{2}-\d{2}$/.test(person.fechaIngreso) ||
        Number.isNaN(date.getTime()) ||
        date.toISOString().slice(0, 10) !== person.fechaIngreso ||
        person.fechaIngreso > today()
    ) {
        throw new Error('La fecha de ingreso debe ser una fecha válida, hasta el día de hoy.');
    }
    if (
        !(person.foto instanceof Blob) ||
        !['image/png', 'image/jpeg', 'image/webp'].includes(person.foto.type) ||
        !person.foto.size ||
        person.foto.size > PHOTO_LIMIT
    ) {
        throw new Error('Selecciona una fotografía PNG, JPEG o WebP de hasta 5 MiB.');
    }
}

export async function savePerson(person) {
    const record = { ...person };
    for (const key of ['nombre', 'email', 'telefono', 'puesto']) record[key] = record[key].trim();
    validatePerson(record);
    record.nombreBusqueda = normalizeName(record.nombre);
    const db = await openDatabase();
    try {
        return await new Promise((resolve, reject) => {
            const transaction = db.transaction('personal', 'readwrite');
            const store = transaction.objectStore('personal');
            let id;
            let error;
            function write() {
                const request = record.id ? store.put(record) : store.add(record);
                request.onsuccess = () => {
                    id = request.result;
                };
            }
            if (record.id) {
                const existing = store.get(record.id);
                existing.onsuccess = () => {
                    if (!existing.result) {
                        error = new Error('Este registro fue eliminado en otra pestaña. Actualiza el catálogo.');
                        transaction.abort();
                    } else write();
                };
            } else write();
            transaction.oncomplete = () => resolve(id);
            transaction.onabort = () => reject(error || transaction.error || new Error('No se pudo guardar el registro.'));
            transaction.onerror = () => reject(transaction.error);
        });
    } finally {
        db.close();
    }
}

export async function deletePerson(id) {
    const db = await openDatabase();
    try {
        await new Promise((resolve, reject) => {
            const transaction = db.transaction('personal', 'readwrite');
            transaction.objectStore('personal').delete(id);
            transaction.oncomplete = resolve;
            transaction.onabort = () => reject(transaction.error || new Error('No se pudo eliminar el registro.'));
            transaction.onerror = () => reject(transaction.error);
        });
    } finally {
        db.close();
    }
}

export async function demonstrateMigration(person) {
    // Solo esta base temporal se crea y elimina; nunca se degrada la base real.
    const name = `${DB_NAME}-demo-${crypto.randomUUID()}`;
    let db;
    try {
        db = await openDatabase(name, 1);
        const legacy = Object.fromEntries(['nombre', 'email', 'telefono', 'area', 'puesto', 'fechaIngreso', 'foto'].map((key) => [key, person[key]]));
        const id = await new Promise((resolve, reject) => {
            const transaction = db.transaction('personal', 'readwrite');
            const request = transaction.objectStore('personal').add(legacy);
            transaction.oncomplete = () => resolve(request.result);
            transaction.onabort = () => reject(transaction.error);
        });
        const beforeVersion = db.version;
        db.close();
        db = await openDatabase(name, 2);
        const afterVersion = db.version;
        db.close();
        const [records, found, audit] = await Promise.all([listPeople({}, name), listPeople({ nombre: legacy.nombre }, name), getMigration(name)]);
        const migrated = records[0];
        const beforeBytes = new Uint8Array(await legacy.foto.arrayBuffer());
        const afterBytes = new Uint8Array(await migrated.foto.arrayBuffer());
        const fieldsPreserved = ['nombre', 'email', 'telefono', 'area', 'puesto', 'fechaIngreso'].every((key) => legacy[key] === migrated[key]);
        const photoPreserved =
            legacy.foto.type === migrated.foto.type &&
            beforeBytes.length === afterBytes.length &&
            beforeBytes.every((value, index) => value === afterBytes[index]);
        if (
            records.length !== 1 ||
            migrated.id !== id ||
            !fieldsPreserved ||
            !photoPreserved ||
            found.length !== 1 ||
            migrated.turno !== 'Matutino' ||
            audit.desde !== 1 ||
            audit.registros !== 1
        ) {
            throw new Error('La verificación de migración no se completó correctamente.');
        }
        return { nombre: migrated.nombre, antes: beforeVersion, despues: afterVersion, registros: records.length, bytes: migrated.foto.size };
    } finally {
        db?.close();
        await new Promise((resolve, reject) => {
            const request = indexedDB.deleteDatabase(name);
            request.onsuccess = resolve;
            request.onerror = () => reject(request.error);
        });
    }
}
