/**
 * Comandos Cypress personalizados para LegalOPS Cloud.
 */

Cypress.Commands.add('login', (email = 'admin@legalops.test', password = 'password') => {
    cy.session([email, password], () => {
        cy.visit('/login');
        cy.get('input[name="email"]').type(email);
        cy.get('input[name="password"]').type(password);
        cy.get('button[type="submit"]').click();
        cy.url().should('not.include', '/login');
    });
});

Cypress.Commands.add('fillForm', (fields) => {
    Object.entries(fields).forEach(([name, value]) => {
        cy.get(`[name="${name}"]`).clear().type(value);
    });
});
