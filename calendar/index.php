<?php
/**
 * DetalStudio - Calendar
 */

require_once __DIR__ . '/../config/config.php';

Auth::requireAuth();

$pageTitle = 'Календар';
include __DIR__ . '/../views/layout/header.php';
?>

<style>
    /* Custom FullCalendar Dark Theme */
    .fc {
        --fc-border-color: rgba(31, 173, 228, 0.2);
        --fc-button-bg-color: rgba(31, 173, 228, 0.1);
        --fc-button-border-color: rgba(31, 173, 228, 0.3);
        --fc-button-hover-bg-color: rgba(31, 173, 228, 0.2);
        --fc-button-hover-border-color: rgba(31, 173, 228, 0.5);
        --fc-button-active-bg-color: #1fade4;
        --fc-button-active-border-color: #1fade4;
        --fc-button-text-color: #fff;
        --fc-today-bg-color: rgba(31, 173, 228, 0.1);
    }
    
    .fc .fc-toolbar-title {
        color: #1fade4 !important;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-size: 1.3rem;
    }
    
    .fc .fc-button {
        background: rgba(31, 173, 228, 0.1) !important;
        border: 1px solid rgba(31, 173, 228, 0.3) !important;
        color: #fff !important;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 0.4em 0.8em;
        transition: all 0.3s;
    }
    
    .fc .fc-button:hover {
        background: rgba(31, 173, 228, 0.2) !important;
        border-color: rgba(31, 173, 228, 0.5) !important;
        transform: translateY(-1px);
    }
    
    .fc .fc-button-primary:not(:disabled).fc-button-active,
    .fc .fc-button-primary:not(:disabled):active {
        background: linear-gradient(135deg, #1fade4 0%, #1797c6 100%) !important;
        border-color: #1fade4 !important;
        color: #000 !important;
        transform: translateY(0);
    }
    
    /* Arrow icons for prev/next buttons */
    .fc .fc-prev-button .fc-icon {
        display: none;
    }
    
    .fc .fc-prev-button::before {
        content: "◄";
        font-size: 1.2rem;
    }
    
    .fc .fc-next-button .fc-icon {
        display: none;
    }
    
    .fc .fc-next-button::after {
        content: "►";
        font-size: 1.2rem;
    }
    
    /* Day headers (Mon, Tue, etc) */
    .fc .fc-col-header-cell {
        background: rgba(20, 20, 20, 0.95) !important;
        border-color: rgba(31, 173, 228, 0.2) !important;
    }
    
    .fc .fc-col-header-cell-cushion {
        color: #1fade4 !important;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-size: 0.85rem;
        padding: 0.85rem 0.5rem;
        display: block !important;
    }
    
    .fc-theme-standard th {
        background: rgba(20, 20, 20, 0.95) !important;
        border-color: rgba(31, 173, 228, 0.2) !important;
    }
    
    .fc .fc-scrollgrid {
        border-color: rgba(31, 173, 228, 0.2) !important;
    }
    
    .fc .fc-daygrid-day {
        background: rgba(10, 10, 10, 0.5);
    }
    
    .fc .fc-daygrid-day:hover {
        background: rgba(31, 173, 228, 0.05);
    }
    
    .fc .fc-daygrid-day-number {
        color: #aaa !important;
        padding: 0.5rem;
        font-weight: 500;
    }
    
    .fc .fc-daygrid-day.fc-day-today {
        background: rgba(31, 173, 228, 0.15) !important;
    }
    
    .fc .fc-daygrid-day.fc-day-today .fc-daygrid-day-number {
        color: #1fade4 !important;
        font-weight: 700;
    }
    
    .fc .fc-event {
        border-radius: 4px;
        border: none;
        padding: 2px 4px;
        font-weight: 500;
        font-size: 0.85rem;
        cursor: pointer;
    }
    
    .fc .fc-event:hover {
        opacity: 0.9;
        transform: translateY(-1px);
        box-shadow: 0 3px 8px rgba(0, 0, 0, 0.3);
    }
    
    .fc .fc-daygrid-event-harness {
        margin: 2px 0;
    }
    
    .fc .fc-list-event:hover td {
        background: rgba(31, 173, 228, 0.1) !important;
    }
    
    .fc .fc-list-day-cushion {
        background: rgba(20, 20, 20, 0.8) !important;
        color: #1fade4 !important;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .fc .fc-list-event {
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .fc .fc-list-event-title {
        color: #fff !important;
    }
    
    .fc .fc-list-event-time {
        color: #888 !important;
    }
    
    .fc-theme-standard td, 
    .fc-theme-standard th {
        border-color: rgba(31, 173, 228, 0.2) !important;
    }
</style>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-calendar3 me-2"></i>Календар подій
                </h5>
                <div>
                     <span class="badge bg-primary me-2"><i class="bi bi-circle-fill me-1" style="font-size: 8px;"></i>Задачі</span>
                     <span class="badge" style="background-color: #6610f2;"><i class="bi bi-circle-fill me-1" style="font-size: 8px;"></i>Замовлення</span>
                </div>
            </div>
            <div class="card-body p-0">
                <div id="calendar" style="min-height: 800px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- FullCalendar -->
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
        },
        buttonText: {
            today: 'Сьогодні',
            month: 'Місяць',
            week: 'Тиждень',
            day: 'День',
            list: 'Список'
        },
        locale: 'uk',
        firstDay: 1, // Monday
        eventSources: [
            {
                url: '/api/calendar_events.php',
                failure: function() {
                    alert('Помилка завантаження подій!');
                }
            }
        ],
        eventClick: function(info) {
            if (info.event.url) {
                // Determine if we should open in modal or navigate
                // For now, let default behavior (navigate) happen or preventDefault and open modal
                // info.jsEvent.preventDefault();
                // window.open(info.event.url);
            }
        },
        eventDidMount: function(info) {
            // Tooltip
            var tooltip = new bootstrap.Tooltip(info.el, {
                title: info.event.title + (info.event.extendedProps.description ? '\n' + info.event.extendedProps.description : ''),
                placement: 'top',
                trigger: 'hover',
                container: 'body'
            });
        },
        height: 'auto',
        themeSystem: 'bootstrap5'
    });
    calendar.render();
});
</script>

<?php include __DIR__ . '/../views/layout/footer.php'; ?>
