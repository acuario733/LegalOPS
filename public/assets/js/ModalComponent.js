'use strict';

if (typeof Component === 'undefined' && typeof require !== 'undefined') {
    global.Component = require('./Component.js').Component;
}

/**
 * ModalComponent — wraps a Bootstrap 5 modal with confirm helpers.
 *
 * HTML:
 *   <div id="deleteModal" class="modal fade" data-modal>
 *     <div class="modal-dialog">…
 *       <button data-confirm-btn>Eliminar</button>
 *     </div>
 *   </div>
 */
class ModalComponent extends Component {
    constructor(selectorOrElement) {
        super(selectorOrElement);
        this._bsModal = null;
        this._onConfirm = null;
    }

    mount() {
        if (!super.mount()) return false;
        if (typeof bootstrap !== 'undefined') {
            this._bsModal = bootstrap.Modal.getOrCreateInstance(this.el);
        }
        const confirmBtn = this.select('[data-confirm-btn]');
        if (confirmBtn) {
            this.on(confirmBtn, 'click', () => {
                if (typeof this._onConfirm === 'function') this._onConfirm();
                this.hide();
            });
        }
        return true;
    }

    show(data = {}) {
        Object.entries(data).forEach(([key, value]) => {
            const el = this.select(`[data-modal-${key}]`);
            if (el) el.textContent = value;
        });
        this._bsModal?.show();
        this.emit('modal:show', { id: this.el.id, data });
    }

    hide() {
        this._bsModal?.hide();
        this._onConfirm = null;
        this.emit('modal:hide', { id: this.el.id });
    }

    onConfirm(callback) {
        this._onConfirm = callback;
        return this;
    }

    destroy() {
        this._bsModal?.dispose();
        super.destroy();
    }
}

if (typeof module !== 'undefined') module.exports = { ModalComponent };
