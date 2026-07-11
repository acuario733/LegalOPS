'use strict';

// ── Cargar el módulo aislando las dependencias del browser ───────────────────

// Stub bootstrap y window.LegalOPS antes de require
global.bootstrap = { Toast: jest.fn().mockImplementation(() => ({ show: jest.fn() })) };
global.console.log = jest.fn(); // silenciar el console.log del módulo

// El módulo usa `module.exports` cuando existe (ver condición en improvements.js)
const { EventBus, validators } = require('../../../../public/assets/js/improvements.js');

// ── EventBus ─────────────────────────────────────────────────────────────────

describe('EventBus', () => {
    let bus;
    beforeEach(() => { bus = new EventBus(); });

    test('registra y ejecuta listeners', () => {
        const cb = jest.fn();
        bus.on('test', cb);
        bus.emit('test', { x: 1 });
        expect(cb).toHaveBeenCalledWith({ x: 1 });
    });

    test('retorna función de unsubscribe', () => {
        const cb = jest.fn();
        const off = bus.on('test', cb);
        off();
        bus.emit('test', {});
        expect(cb).not.toHaveBeenCalled();
    });

    test('off() elimina sólo el listener correcto', () => {
        const cb1 = jest.fn();
        const cb2 = jest.fn();
        bus.on('test', cb1);
        bus.on('test', cb2);
        bus.off('test', cb1);
        bus.emit('test', {});
        expect(cb1).not.toHaveBeenCalled();
        expect(cb2).toHaveBeenCalledTimes(1);
    });

    test('maneja errores en listeners sin romper los demás', () => {
        const err   = () => { throw new Error('boom'); };
        const ok    = jest.fn();
        const spy   = jest.spyOn(console, 'error').mockImplementation(() => {});
        bus.on('test', err);
        bus.on('test', ok);
        bus.emit('test', {});
        expect(ok).toHaveBeenCalledTimes(1);
        expect(spy).toHaveBeenCalled();
        spy.mockRestore();
    });

    test('emit no falla si el evento no tiene listeners', () => {
        expect(() => bus.emit('noop', {})).not.toThrow();
    });
});

// ── Validators ───────────────────────────────────────────────────────────────

describe('validators.required', () => {
    test('true con valor', ()  => expect(validators.required('hola')).toBe(true));
    test('false con vacío', () => expect(validators.required('')).toBe(false));
    test('false con null',  () => expect(validators.required(null)).toBe(false));
});

describe('validators.email', () => {
    test('válido',   () => expect(validators.email('test@example.com')).toBe(true));
    test('inválido', () => expect(validators.email('invalid')).toBe(false));
    test('sin @',    () => expect(validators.email('nodomain')).toBe(false));
});

describe('validators.minLength', () => {
    test('cumple mínimo',  () => expect(validators.minLength('test', 3)).toBe(true));
    test('no cumple',      () => expect(validators.minLength('ab', 3)).toBe(false));
    test('vacío → skip',   () => expect(validators.minLength('', 3)).toBe(true));
});

describe('validators.documento', () => {
    test('válido 6 dígitos',   () => expect(validators.documento('123456')).toBe(true));
    test('inválido 5 dígitos', () => expect(validators.documento('12345')).toBe(false));
    test('con letras',         () => expect(validators.documento('12345A')).toBe(false));
});

describe('validators.phone', () => {
    test('válido con +',   () => expect(validators.phone('+1234567890')).toBe(true));
    test('válido sin +',   () => expect(validators.phone('3001234567')).toBe(true));
    test('muy corto',      () => expect(validators.phone('123')).toBe(false));
});
