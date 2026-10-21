export const DB_NAME = 'trackeat-menu';
export const DB_VERSION = 1;
export const normalize = (value) =>
    value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim()
        .toLowerCase();

function open() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);
        request.onupgradeneeded = () => {
            const db = request.result;
            const products = db.createObjectStore('productos', { keyPath: 'id' });
            products.createIndex('porCategoria', 'id_categoria');
            products.createIndex('porNombre', 'nombreBusqueda');
            db.createObjectStore('categorias', { keyPath: 'id' });
            db.createObjectStore('carrito', { keyPath: 'id' });
            db.createObjectStore('metadatos', { keyPath: 'id' });
        };
        request.onerror = () => reject(request.error);
        request.onblocked = () => reject(new Error('Cierra las otras pestañas del menú para actualizar la base.'));
        request.onsuccess = () => {
            request.result.onversionchange = () => request.result.close();
            resolve(request.result);
        };
    });
}

async function transaction(stores, mode, operation) {
    const db = await open();
    try {
        return await new Promise((resolve, reject) => {
            const tx = db.transaction(stores, mode);
            const result = operation(tx);
            tx.oncomplete = () => resolve(result?.result);
            tx.onabort = () => reject(tx.error || new Error('No se pudo guardar la base local.'));
            tx.onerror = () => reject(tx.error);
        });
    } finally {
        db.close();
    }
}

export function saveCatalog(data) {
    return transaction(['productos', 'categorias', 'metadatos'], 'readwrite', (tx) => {
        const products = tx.objectStore('productos');
        const categories = tx.objectStore('categorias');
        products.clear();
        categories.clear();
        for (const product of data.productos) products.put({ ...product, nombreBusqueda: normalize(product.nombre) });
        for (const category of data.categorias) categories.put(category);
        tx.objectStore('metadatos').put({ id: 'actualizado', value: data.actualizado });
    });
}

export function products({ nombre = '', categoria = '' } = {}) {
    return transaction(['productos'], 'readonly', (tx) => {
        const store = tx.objectStore('productos');
        const prefix = normalize(nombre);
        if (prefix) return store.index('porNombre').getAll(IDBKeyRange.bound(prefix, `${prefix}\uffff`));
        if (categoria) return store.index('porCategoria').getAll(Number(categoria));
        return store.getAll();
    }).then((rows) => rows.filter((row) => !categoria || row.id_categoria === Number(categoria)));
}

export const categories = () => transaction(['categorias'], 'readonly', (tx) => tx.objectStore('categorias').getAll());
export const updated = () => transaction(['metadatos'], 'readonly', (tx) => tx.objectStore('metadatos').get('actualizado'));
export const loadCart = () => transaction(['carrito'], 'readonly', (tx) => tx.objectStore('carrito').get('actual'));
export const saveCart = (cart) => transaction(['carrito'], 'readwrite', (tx) => tx.objectStore('carrito').put({ ...cart, id: 'actual' }));
