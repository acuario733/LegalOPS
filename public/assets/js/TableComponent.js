'use strict';

if (typeof Component === 'undefined' && typeof require !== 'undefined') {
    global.Component = require('./Component.js').Component;
}

/**
 * TableComponent — tablas con búsqueda client-side y sorting visual.
 *
 * HTML:
 *   <table data-table>
 *     <thead><tr><th data-sortable="nombre">Nombre</th></tr></thead>
 *     <tbody data-table-body>…</tbody>
 *   </table>
 *   <input data-table-search placeholder="Buscar…">
 */
class TableComponent extends Component {
    constructor(selectorOrElement) {
        super(selectorOrElement);
        this.sortColumn    = null;
        this.sortDirection = 'asc';
    }

    mount() {
        if (!super.mount()) return false;
        this._setupSorting();
        this._setupSearch();
        return true;
    }

    _setupSorting() {
        this.selectAll('th[data-sortable]').forEach(th => {
            th.style.cursor = 'pointer';
            th.setAttribute('title', 'Clic para ordenar');
            this.on(th, 'click', () => this._handleSort(th));
        });
    }

    _setupSearch() {
        const input = document.querySelector('[data-table-search]');
        if (input) this.on(input, 'input', e => this.filterRows(e.target.value));
    }

    _handleSort(th) {
        const col = th.dataset.sortable;
        this.sortDirection = this.sortColumn === col && this.sortDirection === 'asc' ? 'desc' : 'asc';
        this.sortColumn = col;

        this.selectAll('th[data-sortable]').forEach(h => h.classList.remove('sorted-asc', 'sorted-desc'));
        th.classList.add(`sorted-${this.sortDirection}`);

        this.emit('table:sort', { column: col, direction: this.sortDirection });
    }

    filterRows(term) {
        const lower = term.toLowerCase();
        this.selectAll('tbody tr').forEach(row => {
            row.hidden = !row.textContent.toLowerCase().includes(lower);
        });
    }

    clear() {
        const tbody = this.select('[data-table-body]');
        if (tbody) tbody.innerHTML = '';
    }
}

if (typeof module !== 'undefined') module.exports = { TableComponent };
