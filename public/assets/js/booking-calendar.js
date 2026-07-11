(() => {
    'use strict';

    class BookingCalendar {
        constructor(root) {
            this.root = root;
            this.slug = root.dataset.slug;
            this.activeDays = JSON.parse(root.dataset.days || '[]').map(Number);
            this.minDays = Number(root.dataset.minDays || 0);
            this.maxDays = Number(root.dataset.maxDays || 30);
            this.today = this.atMidnight(new Date());
            this.minimum = this.addDays(this.today, this.minDays);
            this.maximum = this.addDays(this.today, this.maxDays);
            this.current = new Date(this.minimum.getFullYear(), this.minimum.getMonth(), 1);
            this.selectedDate = null;
            this.selectedTime = null;
            this.bind();
            this.render(this.current.getFullYear(), this.current.getMonth());
        }

        render(year, month) {
            this.current = new Date(year, month, 1);
            const grid = document.getElementById('booking-calendar-grid');
            grid.replaceChildren();
            ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'].forEach(day => {
                const label = document.createElement('div');
                label.className = 'calendar-label';
                label.textContent = day;
                grid.append(label);
            });
            const firstWeekday = this.isoDay(new Date(year, month, 1));
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            for (let spacer = 1; spacer < firstWeekday; spacer += 1) grid.append(document.createElement('span'));
            for (let day = 1; day <= daysInMonth; day += 1) {
                const date = new Date(year, month, day);
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'calendar-day';
                button.textContent = String(day);
                button.disabled = !this.isAvailableDay(date);
                const value = this.formatDate(date);
                button.dataset.date = value;
                if (this.selectedDate === value) button.classList.add('is-selected');
                button.addEventListener('click', () => this.selectDay(value));
                grid.append(button);
            }
            document.getElementById('booking-month-label').textContent = new Intl.DateTimeFormat('es-CO', {month: 'long', year: 'numeric'}).format(this.current);
            document.getElementById('booking-prev-month').disabled = new Date(year, month + 1, 0) < this.minimum;
            document.getElementById('booking-next-month').disabled = new Date(year, month + 1, 1) > this.maximum;
        }

        async selectDay(fecha) {
            this.selectedDate = fecha;
            this.selectedTime = null;
            this.render(this.current.getFullYear(), this.current.getMonth());
            const panel = document.getElementById('booking-slots-panel');
            const container = document.getElementById('booking-slots');
            const empty = document.getElementById('booking-no-slots');
            panel.hidden = false;
            container.replaceChildren();
            document.getElementById('booking-data-panel').hidden = true;
            try {
                const response = await fetch(`/booking/${encodeURIComponent(this.slug)}/slots?fecha=${encodeURIComponent(fecha)}`, {
                    headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                    credentials: 'same-origin',
                });
                const payload = await response.json();
                if (!response.ok) throw new Error(payload.message || 'No fue posible cargar los horarios.');
                const slots = payload.data.slots || [];
                empty.hidden = slots.length > 0;
                slots.forEach(slot => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'btn btn-outline-success slot-button';
                    button.textContent = slot;
                    button.addEventListener('click', () => this.selectSlot(slot));
                    container.append(button);
                });
            } catch (error) {
                empty.textContent = error.message;
                empty.hidden = false;
            }
        }

        selectSlot(hora) {
            this.selectedTime = hora;
            document.querySelectorAll('.slot-button').forEach(button => button.classList.toggle('is-selected', button.textContent === hora));
            document.getElementById('booking-selected-date').value = this.selectedDate;
            document.getElementById('booking-selected-time').value = hora;
            document.getElementById('booking-data-panel').hidden = false;
            document.getElementById('booking-client-name').focus();
        }

        async submitForm() {
            const form = document.getElementById('booking-public-form');
            const button = form.querySelector('button[type="submit"]');
            button.disabled = true;
            const data = Object.fromEntries(new FormData(form).entries());
            try {
                const response = await fetch(`/booking/${encodeURIComponent(this.slug)}`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(data),
                });
                const payload = await response.json();
                if (!response.ok) throw new Error(payload.message || 'No fue posible confirmar la cita.');
                document.getElementById('booking-data-panel').hidden = true;
                const confirmation = document.getElementById('booking-confirmation');
                confirmation.hidden = false;
                confirmation.querySelector('#booking-confirmation-summary').textContent = `${data.fecha} a las ${data.hora_inicio}`;
                confirmation.querySelector('#booking-google-link').href = payload.data.google_calendar_url;
                confirmation.querySelector('#booking-ics-link').href = payload.data.ics_url;
            } catch (error) {
                const alert = document.getElementById('booking-error');
                alert.textContent = error.message;
                alert.hidden = false;
                button.disabled = false;
            }
        }

        prevMonth() {
            this.render(this.current.getFullYear(), this.current.getMonth() - 1);
        }

        nextMonth() {
            this.render(this.current.getFullYear(), this.current.getMonth() + 1);
        }

        bind() {
            document.getElementById('booking-prev-month').addEventListener('click', () => this.prevMonth());
            document.getElementById('booking-next-month').addEventListener('click', () => this.nextMonth());
            document.getElementById('booking-public-form').addEventListener('submit', event => {
                event.preventDefault();
                this.submitForm();
            });
        }

        isAvailableDay(date) {
            return date >= this.minimum && date <= this.maximum && this.activeDays.includes(this.isoDay(date));
        }

        isoDay(date) {
            return date.getDay() === 0 ? 7 : date.getDay();
        }

        atMidnight(date) {
            return new Date(date.getFullYear(), date.getMonth(), date.getDate());
        }

        addDays(date, days) {
            return new Date(date.getFullYear(), date.getMonth(), date.getDate() + days);
        }

        formatDate(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }
    }

    window.BookingCalendar = BookingCalendar;
    document.addEventListener('DOMContentLoaded', () => {
        const root = document.getElementById('booking-calendar-app');
        if (root) new BookingCalendar(root);
    });
})();
