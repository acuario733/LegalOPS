describe('Prospectos CRUD', () => {
    beforeEach(() => {
        cy.login();
        cy.visit('/prospectos');
    });

    it('muestra listado de prospectos', () => {
        cy.get('h1, h2').should('contain', 'Prospectos');
        cy.get('table').should('exist');
    });

    it('valida email inválido', () => {
        cy.contains('button', /nuevo prospecto/i).click();
        cy.get('[name="email"]').type('no-es-email');
        cy.get('[name="email"]').blur();
        cy.get('#email-error, .invalid-feedback').should('be.visible');
    });

    it('crea prospecto válido', () => {
        cy.contains('button', /nuevo prospecto/i).click();
        cy.fillForm({
            nombre:   'Prospecto E2E',
            email:    'prospecto@e2e.com',
            telefono: '3009876543',
        });
        cy.get('button[data-submit-btn]').click();
        cy.contains('.toast, .alert', /éxito|prospecto/i).should('be.visible');
    });
});
