describe('Clientes CRUD', () => {
    beforeEach(() => {
        cy.login();
        cy.visit('/clientes');
    });

    it('muestra listado de clientes', () => {
        cy.get('h1, h2').should('contain', 'Clientes');
        cy.get('table').should('exist');
    });

    it('abre modal de nuevo cliente', () => {
        cy.contains('button', /nuevo cliente/i).click();
        cy.get('[data-ajax-form], form').should('be.visible');
    });

    it('valida campos requeridos en el formulario', () => {
        cy.contains('button', /nuevo cliente/i).click();
        cy.get('button[data-submit-btn]').click();
        cy.get('.is-invalid, .invalid-feedback:visible').should('have.length.greaterThan', 0);
    });

    it('crea un cliente nuevo correctamente', () => {
        cy.contains('button', /nuevo cliente/i).click();
        cy.fillForm({
            nombre:           'Cliente E2E',
            tipo_persona:     'natural',
            numero_documento: '1234567890',
            email:            'e2e@test.com',
            telefono:         '3001234567',
        });
        cy.get('button[data-submit-btn]').click();
        cy.contains('.toast, .alert', /éxito|cliente/i).should('be.visible');
    });

    it('filtra clientes con búsqueda', () => {
        cy.get('[data-table-search]').type('E2E');
        cy.get('tbody tr:visible').should('have.length.at.least', 1);
    });
});
