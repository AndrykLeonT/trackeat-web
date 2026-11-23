import { test } from 'node:test';
import assert from 'node:assert/strict';
import { normalize } from '../menu/db.js';

test('normalización pública encuentra fragmentos sin distinguir acentos ni mayúsculas', () => {
    assert(normalize(' Hamburguesa de CAFÉ ').includes(normalize('guesa de cafe')));
    assert.equal(normalize('Limón'), normalize('LIMON'));
});
