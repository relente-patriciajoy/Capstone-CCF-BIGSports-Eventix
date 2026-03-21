/**
 * Event Calendar JavaScript
 * Handles calendar rendering, event display, and interactions
 */

class EventCalendar {
  constructor() {
    this.currentDate = new Date();
    this.events = [];
    this.init();
  }

  init() {
    this.fetchEvents();
    this.renderCalendar();
    this.attachEventListeners();
  }

  async fetchEvents() {
    try {
      const response = await fetch('get_events.php');
      const data = await response.json();
      if (data.success) {
        this.events = data.events.map(event => ({
          ...event,
          start_time: new Date(event.start_time),
          end_time: new Date(event.end_time)
        }));
        this.renderCalendar();
      }
    } catch (error) {
      console.error('Error fetching events:', error);
    }
  }

  renderCalendar() {
    const year  = this.currentDate.getFullYear();
    const month = this.currentDate.getMonth();

    document.getElementById('calendar-month-year').textContent =
      this.currentDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });

    const firstDay       = new Date(year, month, 1);
    const lastDay        = new Date(year, month + 1, 0);
    const firstDayOfWeek = firstDay.getDay();
    const daysInMonth    = lastDay.getDate();
    const prevMonthLast  = new Date(year, month, 0).getDate();

    const calendarGrid = document.getElementById('calendar-grid');
    calendarGrid.innerHTML = '';

    ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].forEach(day => {
      const h = document.createElement('div');
      h.className = 'calendar-day-header';
      h.textContent = day;
      calendarGrid.appendChild(h);
    });

    for (let i = firstDayOfWeek - 1; i >= 0; i--) {
      this.createDayCell(calendarGrid, prevMonthLast - i, true, new Date(year, month - 1, prevMonthLast - i));
    }
    for (let day = 1; day <= daysInMonth; day++) {
      this.createDayCell(calendarGrid, day, false, new Date(year, month, day));
    }
    const remaining = 42 - (firstDayOfWeek + daysInMonth);
    for (let day = 1; day <= remaining; day++) {
      this.createDayCell(calendarGrid, day, true, new Date(year, month + 1, day));
    }
  }

  createDayCell(container, dayNumber, isOtherMonth, date) {
    const dayCell = document.createElement('div');
    dayCell.className = 'calendar-day';
    if (isOtherMonth) dayCell.classList.add('other-month');

    const today = new Date();
    if (date.toDateString() === today.toDateString()) dayCell.classList.add('today');

    const dayNumberDiv = document.createElement('div');
    dayNumberDiv.className = 'calendar-day-number';
    dayNumberDiv.textContent = dayNumber;
    dayCell.appendChild(dayNumberDiv);

    const dayEvents  = this.getEventsForDate(date);
    const maxVisible = 3;

    dayEvents.slice(0, maxVisible).forEach(event => {
      const eventDiv = document.createElement('div');
      eventDiv.className = 'calendar-event';
      eventDiv.textContent = event.title;
      eventDiv.onclick = (e) => { e.stopPropagation(); this.showEventModal(event); };
      dayCell.appendChild(eventDiv);
    });

    if (dayEvents.length > maxVisible) {
      const moreDiv = document.createElement('div');
      moreDiv.className = 'calendar-event-more';
      moreDiv.textContent = `+${dayEvents.length - maxVisible} more`;
      moreDiv.onclick = (e) => { e.stopPropagation(); this.showEventModal(dayEvents[0]); };
      dayCell.appendChild(moreDiv);
    }

    dayCell.onclick = () => { if (dayEvents.length > 0) this.showEventModal(dayEvents[0]); };
    container.appendChild(dayCell);
  }

  getEventsForDate(date) {
    return this.events.filter(event =>
      new Date(event.start_time).toDateString() === date.toDateString()
    );
  }

  showEventModal(event) {
    const overlay = document.getElementById('event-modal-overlay');

    document.getElementById('modal-event-title').textContent = event.title;
    document.getElementById('modal-event-date').querySelector('span').textContent =
      event.start_time.toLocaleDateString('en-US', { weekday:'long', year:'numeric', month:'long', day:'numeric' });

    document.getElementById('modal-event-time').textContent =
      `${event.start_time.toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit'})} - ${event.end_time.toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit'})}`;

    document.getElementById('modal-event-venue').textContent    = event.venue || 'TBA';
    document.getElementById('modal-event-capacity').textContent = `${event.capacity} people`;
    document.getElementById('modal-event-description').textContent = event.description || 'No description available.';

    document.getElementById('modal-register-btn').onclick = () => {
      window.location.href = `../event/event_register.php?event_id=${event.event_id}`;
    };

    overlay.classList.add('active');
  }

  closeModal() {
    document.getElementById('event-modal-overlay').classList.remove('active');
  }

  previousMonth() { this.currentDate.setMonth(this.currentDate.getMonth() - 1); this.renderCalendar(); }
  nextMonth()     { this.currentDate.setMonth(this.currentDate.getMonth() + 1); this.renderCalendar(); }
  goToToday()     { this.currentDate = new Date(); this.renderCalendar(); }

  attachEventListeners() {
    document.getElementById('prev-month').onclick  = () => this.previousMonth();
    document.getElementById('next-month').onclick  = () => this.nextMonth();
    document.getElementById('today-btn').onclick   = () => this.goToToday();

    // Close button (optional — may be commented out in HTML)
    const closeBtn = document.getElementById('modal-close-btn');
    if (closeBtn) closeBtn.onclick = () => this.closeModal();

    document.getElementById('event-modal-overlay').onclick = (e) => {
      if (e.target.id === 'event-modal-overlay') this.closeModal();
    };

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') this.closeModal();
    });
  }
}

document.addEventListener('DOMContentLoaded', () => {
  new EventCalendar();
  lucide.createIcons();
});