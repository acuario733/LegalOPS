const viewports = [
    { name: 'mobile',  width: 375,  height: 812 },
    { name: 'tablet',  width: 768,  height: 1024 },
    { name: 'desktop', width: 1280, height: 800 },
];

describe('Responsive layout', () => {
    beforeEach(() => { cy.login(); });

    viewports.forEach(({ name, width, height }) => {
        context(`Vista ${name} (${width}×${height})`, () => {
            beforeEach(() => { cy.viewport(width, height); });

            it('muestra clientes sin desbordamiento horizontal', () => {
                cy.visit('/clientes');
                cy.get('body').should('not.have.css', 'overflow-x', 'scroll');
                cy.get('table').should('be.visible');
            });

            if (width < 576) {
                it('las celdas muestran data-label en móvil', () => {
                    cy.visit('/clientes');
                    cy.get('table.table-responsive-stack tbody td[data-label]')
                        .should('have.length.greaterThan', 0);
                });
            }

            it('los botones tienen altura mínima de 44px en táctil', () => {
                cy.visit('/clientes');
                if (width < 768) {
                    cy.get('.btn').first().invoke('outerHeight').should('be.gte', 44);
                }
            });
        });
    });
});
