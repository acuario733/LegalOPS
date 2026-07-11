'use strict';

/**
 * Clase base para componentes UI reutilizables.
 * Gestiona lifecycle (mount/destroy) y listeners con cleanup automático.
 */
class Component {
    constructor(selectorOrElement) {
        this.el = typeof selectorOrElement === 'string'
            ? document.querySelector(selectorOrElement)
            : selectorOrElement;
        this.eventBus = window.LegalOPS?.eventBus ?? null;
        this._listeners = [];
        this.mounted = false;
    }

    mount() {
        if (!this.el) {
            console.warn(`${this.constructor.name}: elemento no encontrado`);
            return false;
        }
        this.mounted = true;
        return true;
    }

    destroy() {
        this._listeners.forEach(({ target, event, handler, capture }) => {
            if (target && typeof target.removeEventListener === 'function') {
                target.removeEventListener(event, handler, capture);
            }
        });
        this._listeners = [];
        this.mounted = false;
    }

    /** Registra un listener con cleanup automático en destroy(). */
    on(target, event, handler, capture = false) {
        target.addEventListener(event, handler, capture);
        this._listeners.push({ target, event, handler, capture });
    }

    emit(event, data) {
        this.eventBus?.emit(event, data);
    }

    select(selector) {
        return this.el?.querySelector(selector) ?? null;
    }

    selectAll(selector) {
        return this.el?.querySelectorAll(selector) ?? [];
    }
}

if (typeof module !== 'undefined') module.exports = { Component };
