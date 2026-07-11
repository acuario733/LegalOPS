describe('Casos CRUD', () => {
    beforeEach(() => {
        cy.login();
        cy.visit('/casos');
    });

    it('muestra listado de casos', () => {
        cy.get('h1, h2').should('contain', 'Casos');
        cy.get('table').should('exist');
    });

    it('valida campos requeridos', () => {
        cy.contains('button', /nuevo caso/i).click();
        cy.get('button[data-submit-btn]').click();
        cy.get('.is-invalid, .invalid-feedback:visible').should('have.length.greaterThan', 0);
    });

    it('crea un caso nuevo', () => {
        cy.contains('button', /nuevo caso/i).click();
        cy.fillForm({
            titulo:      'Caso E2E',
            prioridad:   'media',
            estado:      'activo',
        });
        cy.get('button[data-submit-btn]').click();
        cy.contains('.toast, .alert', /éxito|caso/i).should('be.visible');
    });
});
