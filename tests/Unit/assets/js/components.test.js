'use strict';

// Component must be on global before subclasses are required
const { Component } = require('../../../../public/assets/js/Component.js');
global.Component = Component;

const { TableComponent } = require('../../../../public/assets/js/TableComponent.js');
global.TableComponent = TableComponent;

const { ModalComponent } = require('../../../../public/assets/js/ModalComponent.js');
global.ModalComponent = ModalComponent;

// ── helpers ──────────────────────────────────────────────────────────────────

function makeEl(tag = 'div', attrs = {}) {
    const el = document.createElement(tag);
    Object.entries(attrs).forEach(([k, v]) => el.setAttribute(k, v));
    document.body.appendChild(el);
    return el;
}

afterEach(() => { document.body.innerHTML = ''; });

// ── Component (base) ─────────────────────────────────────────────────────────

describe('Component (base)', () => {
    test('mount() returns false when element not found', () => {
        const c = new Component('#does-not-exist');
        expect(c.mount()).toBe(false);
        expect(c.mounted).toBe(false);
    });

    test('mount() returns true for existing element', () => {
        const el = makeEl('div');
        const c  = new Component(el);
        expect(c.mount()).toBe(true);
        expect(c.mounted).toBe(true);
    });

    test('on() registers listener and destroy() removes it', () => {
        const el = makeEl('div');
        const c  = new Component(el);
        c.mount();
        let count = 0;
        c.on(el, 'click', () => count++);
        el.click();
        expect(count).toBe(1);
        c.destroy();
        el.click();
        expect(count).toBe(1); // no second increment
    });

    test('destroy() sets mounted to false', () => {
        const el = makeEl('div');
        const c  = new Component(el);
        c.mount();
        c.destroy();
        expect(c.mounted).toBe(false);
    });

    test('select() returns child element', () => {
        const el  = makeEl('div');
        const span = document.createElement('span');
        el.appendChild(span);
        const c = new Component(el);
        c.mount();
        expect(c.select('span')).toBe(span);
    });
});

// ── TableComponent ────────────────────────────────────────────────────────────

describe('TableComponent', () => {
    function makeTable() {
        const table = document.createElement('table');
        table.setAttribute('data-table', '');
        table.innerHTML = `
            <thead>
                <tr>
                    <th data-sortable="nombre">Nombre</th>
                </tr>
            </thead>
            <tbody data-table-body>
                <tr><td>Ana García</td></tr>
                <tr><td>Carlos López</td></tr>
                <tr><td>Beatriz Mora</td></tr>
            </tbody>`;
        document.body.appendChild(table);
        return table;
    }

    test('mount() returns true on valid table', () => {
        const t = new TableComponent(makeTable());
        expect(t.mount()).toBe(true);
    });

    test('filterRows() hides non-matching rows', () => {
        const table = makeTable();
        const t     = new TableComponent(table);
        t.mount();
        t.filterRows('ana');
        const rows = table.querySelectorAll('tbody tr');
        expect(rows[0].hidden).toBe(false);
        expect(rows[1].hidden).toBe(true);
        expect(rows[2].hidden).toBe(true);
    });

    test('filterRows() is case-insensitive', () => {
        const table = makeTable();
        const t     = new TableComponent(table);
        t.mount();
        t.filterRows('ANA');
        expect(table.querySelectorAll('tbody tr')[0].hidden).toBe(false);
    });

    test('clear() empties tbody', () => {
        const table = makeTable();
        const t     = new TableComponent(table);
        t.mount();
        t.clear();
        expect(table.querySelector('[data-table-body]').innerHTML).toBe('');
    });

    test('sortable th gets cursor:pointer style', () => {
        const table = makeTable();
        const t     = new TableComponent(table);
        t.mount();
        const th = table.querySelector('th[data-sortable]');
        expect(th.style.cursor).toBe('pointer');
    });
});

// ── ModalComponent ────────────────────────────────────────────────────────────

describe('ModalComponent', () => {
    function makeModal() {
        const div = document.createElement('div');
        div.id = 'testModal';
        div.setAttribute('data-modal', '');
        div.innerHTML = `
            <div class="modal-body">
                <span data-modal-nombre></span>
            </div>
            <button data-confirm-btn>Confirmar</button>`;
        document.body.appendChild(div);
        return div;
    }

    test('mount() returns true', () => {
        const m = new ModalComponent(makeModal());
        expect(m.mount()).toBe(true);
    });

    test('onConfirm() sets callback and confirm btn calls it', () => {
        const el = makeModal();
        const m  = new ModalComponent(el);
        m.mount();
        let called = false;
        m.onConfirm(() => { called = true; });
        el.querySelector('[data-confirm-btn]').click();
        expect(called).toBe(true);
    });

    test('show() injects data into [data-modal-*] elements', () => {
        const el = makeModal();
        // bootstrap stub
        global.bootstrap = { Modal: { getOrCreateInstance: () => ({ show: jest.fn(), hide: jest.fn(), dispose: jest.fn() }) } };
        const m = new ModalComponent(el);
        m.mount();
        m.show({ nombre: 'Pedro' });
        expect(el.querySelector('[data-modal-nombre]').textContent).toBe('Pedro');
        delete global.bootstrap;
    });
});
