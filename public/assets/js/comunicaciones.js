(() => {
    'use strict';

    const state = { casoId: null, tipo: '', loaded: false, modal: null };

    function workspace() {
        return document.querySelector('[data-caso-workspace]');
    }

    async function loadTimeline(casoId, filtros = {}) {
        const container = document.querySelector('[data-comunicaciones-timeline]');
        if (!container) return;
        container.innerHTML = '<div class="text-secondary py-4">Cargando comunicaciones...</div>';
        const params = new URLSearchParams();
        if (filtros.tipo) params.set('tipo', filtros.tipo);
        const payload = await window.LegalOPS.request(`/casos/${casoId}/comunicaciones?${params.toString()}`);
        container.innerHTML = payload.data.html;
        const emailBadge = document.querySelector('[data-comunicaciones-email]');
        if (emailBadge) emailBadge.textContent = payload.data.email_address;
        state.loaded = true;
    }

    function filterByTipo(tipo) {
        state.tipo = tipo;
        document.querySelectorAll('[data-comunicaciones-filter]').forEach((button) => {
            button.classList.toggle('active', button.dataset.comunicacionesFilter === tipo);
        });
        if (state.casoId) loadTimeline(state.casoId, { tipo }).catch(showError);
    }

    function expandCuerpo(id) {
        document.querySelector(`[data-cuerpo-short="${id}"]`)?.setAttribute('hidden', 'hidden');
        document.querySelector(`[data-cuerpo-full="${id}"]`)?.removeAttribute('hidden');
        document.querySelector(`[data-expand-cuerpo="${id}"]`)?.setAttribute('hidden', 'hidden');
    }

    async function copyEmail(email) {
        if (!email || email.includes('Cargando')) return;
        await navigator.clipboard.writeText(email);
        const button = document.querySelector('[data-copy-comunicaciones-email]');
        if (!button) return;
        const original = button.innerHTML;
        button.innerHTML = '<i class="bi bi-check2 me-1"></i>¡Copiado!';
        setTimeout(() => { button.innerHTML = original; }, 1600);
    }

    async function submitForm(casoId) {
        const form = document.getElementById('comunicacion-form');
        if (!form) return;
        const data = Object.fromEntries(new FormData(form).entries());
        const participants = String(data.participantes ?? '').split(',').map((item) => item.trim()).filter(Boolean);
        data.participantes = participants;
        await window.LegalOPS.request(`/casos/${casoId}/comunicaciones`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data),
        });
        state.modal?.hide();
        form.reset();
        setDefaultDate();
        await loadTimeline(casoId, { tipo: state.tipo });
    }

    async function deleteEntry(id, casoId) {
        if (!window.confirm('¿Eliminar esta comunicacion?')) return;
        await window.LegalOPS.request(`/casos/${casoId}/comunicaciones/${id}`, { method: 'DELETE' });
        await loadTimeline(casoId, { tipo: state.tipo });
    }

    function applyTipo(tipo) {
        const hidden = document.getElementById('comunicacion-tipo');
        if (hidden) hidden.value = tipo;
        document.querySelectorAll('[data-comunicacion-tipo]').forEach((button) => {
            button.classList.toggle('active', button.dataset.comunicacionTipo === tipo);
        });
        const subject = document.querySelector('[data-field="asunto"]');
        const duration = document.querySelector('[data-field="duracion"]');
        const body = document.querySelector('[data-field="cuerpo"]');
        if (subject) subject.hidden = tipo === 'llamada' || tipo === 'mensaje';
        if (duration) duration.hidden = !(tipo === 'llamada' || tipo === 'reunion');
        if (body) body.querySelector('label').textContent = tipo === 'reunion' ? 'Agenda / notas' : 'Cuerpo / notas';
    }

    function setDefaultDate() {
        const input = document.getElementById('comunicacion-fecha');
        if (!input) return;
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        input.value = now.toISOString().slice(0, 16);
    }

    function showError(error) {
        const box = document.getElementById('comunicacion-error') ?? document.querySelector('[data-feedback]');
        if (!box) return;
        box.hidden = false;
        box.textContent = error.message || 'No fue posible completar la accion.';
    }

    document.addEventListener('DOMContentLoaded', () => {
        const root = workspace();
        if (!root) return;
        state.casoId = root.dataset.casoId;
        const modalEl = document.getElementById('comunicacionModal');
        if (modalEl && window.bootstrap) state.modal = new window.bootstrap.Modal(modalEl);
        setDefaultDate();
    });

    document.addEventListener('shown.bs.tab', (event) => {
        if (event.target?.id === 'caso-comunicaciones-tab' && state.casoId && !state.loaded) {
            loadTimeline(state.casoId, { tipo: state.tipo }).catch(showError);
        }
    });

    document.addEventListener('click', (event) => {
        const filter = event.target.closest('[data-comunicaciones-filter]');
        if (filter) filterByTipo(filter.dataset.comunicacionesFilter ?? '');
        const expand = event.target.closest('[data-expand-cuerpo]');
        if (expand) expandCuerpo(expand.dataset.expandCuerpo);
        const copy = event.target.closest('[data-copy-comunicaciones-email]');
        if (copy) copyEmail(document.querySelector('[data-comunicaciones-email]')?.textContent ?? '').catch(showError);
        const submit = event.target.closest('[data-submit-comunicacion]');
        if (submit && state.casoId) submitForm(state.casoId).catch(showError);
        const remove = event.target.closest('[data-delete-comunicacion]');
        if (remove && state.casoId) deleteEntry(remove.dataset.deleteComunicacion, state.casoId).catch(showError);
        const tipo = event.target.closest('[data-comunicacion-tipo]');
        if (tipo) applyTipo(tipo.dataset.comunicacionTipo);
    });

    window.Comunicaciones = { loadTimeline, filterByTipo, expandCuerpo, copyEmail, submitForm, deleteEntry };
})();
