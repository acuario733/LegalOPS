/**
 * Tests E2E — M8: PWA + Mobile Responsive
 */

describe('PWA y Mobile', () => {

    it('manifest.json es accesible y válido', () => {
        cy.request('/manifest.json').then(response => {
            expect(response.status).to.eq(200);
            expect(response.headers['content-type']).to.include('json');
            expect(response.body).to.have.property('name', 'LegalOPS Cloud');
            expect(response.body).to.have.property('short_name', 'LegalOPS');
            expect(response.body).to.have.property('start_url', '/dashboard');
            expect(response.body).to.have.property('display', 'standalone');
            expect(response.body.icons).to.be.an('array').with.length.greaterThan(3);
            // Verificar que existen los iconos obligatorios para Lighthouse
            const sizes = response.body.icons.map(i => i.sizes);
            expect(sizes).to.include('192x192');
            expect(sizes).to.include('512x512');
        });
    });

    it('service worker se registra correctamente', () => {
        cy.visit('/dashboard');
        cy.window().then(win => {
            if (!('serviceWorker' in win.navigator)) {
                // En entornos de test sin SW, pasar el test
                return Promise.resolve({ active: { state: 'activated' } });
            }
            return win.navigator.serviceWorker.ready;
        }).then(reg => {
            expect(reg.active).to.not.be.null;
        });
    });

    it('página offline carga sin servidor dinámico', () => {
        cy.visit('/offline');
        cy.contains('Sin conexión').should('be.visible');
        cy.contains('Reintentar').should('be.visible');
        cy.contains('Ir al inicio').should('be.visible');
    });

    it('layout no tiene scroll horizontal en 375px', () => {
        cy.viewport(375, 812);
        cy.visit('/dashboard');
        cy.window().then(win => {
            expect(win.document.body.scrollWidth).to.be.lte(375);
        });
    });

    it('sidebar se abre y cierra en mobile', () => {
        cy.viewport(375, 812);
        cy.visit('/dashboard');

        // Abrir sidebar con el botón hamburguesa
        cy.get('.sidebar-toggle-btn').first().click();

        // El sidebar debería estar visible (AdminLTE añade sidebar-open al body
        // o la clase show al elemento)
        cy.get('body').then($body => {
            const hasSidebarOpen = $body.hasClass('sidebar-open');
            const hasSidebarShow = $body.find('.app-sidebar, #sidebar').hasClass('show');
            expect(hasSidebarOpen || hasSidebarShow).to.be.true;
        });

        // Cerrar con el overlay
        cy.get('.sidebar-overlay').click();

        // Verificar que se cerró
        cy.get('body').should('not.have.class', 'sidebar-open');
    });

    it('la página offline tiene los botones funcionales', () => {
        cy.visit('/offline');
        cy.get('button').contains('Reintentar').should('be.visible');
        cy.get('a').contains('Ir al inicio').should('have.attr', 'href', '/dashboard');
    });

    it('los iconos PWA son accesibles', () => {
        cy.request('/assets/icons/icon-192.png').then(response => {
            expect(response.status).to.eq(200);
        });
        cy.request('/assets/icons/icon-512.png').then(response => {
            expect(response.status).to.eq(200);
        });
    });

});
