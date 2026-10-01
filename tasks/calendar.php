<?php
/**
 * DetalStudio - Tasks Calendar
 * Календар подій
 */

require_once __DIR__ . '/../config/config.php';

Auth::requireAuth();

$pageTitle = 'Календар';
$additionalScripts = '<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>';
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
    .fc .fc-prev-button::before {
        content: "◄ ";
        font-size: 1.1rem;
        margin-right: 4px;
    }
    
    .fc .fc-next-button::after {
        content: " ►";
        font-size: 1.1rem;
        margin-left: 4px;
    }
    
    /* Day headers (Mon, Tue, etc) */
    .fc .fc-col-header-cell {
        background: rgba(20, 20, 20, 0.9) !important;
        border-color: rgba(31, 173, 228, 0.2) !important;
    }
    
    .fc .fc-col-header-cell-cushion {
        color: #1fade4 !important;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 0.8rem;
        padding: 0.75rem 0.5rem;
        display: block !important;
    }
    
    .fc-theme-standard th {
        background: rgba(20, 20, 20, 0.9) !important;
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
        color: #aaa;
        padding: 0.5rem;
        font-weight: 500;
    }
    
    .fc .fc-daygrid-day.fc-day-today {
        background: rgba(31, 173, 228, 0.15) !important;
    }
    
    .fc .fc-daygrid-day.fc-day-today .fc-daygrid-day-number {
        color: #1fade4;
        font-weight: 700;
    }
    
    .fc .fc-event {
        border-radius: 4px;
        border: none;
        padding: 2px 4px;
        font-weight: 500;
        font-size: 0.85rem;
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
        background: rgba(31, 173, 228, 0.1);
    }
    
    .fc .fc-list-day-cushion {
        background: rgba(20, 20, 20, 0.8);
        color: #1fade4;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .fc .fc-list-event {
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .fc .fc-list-event-title {
        color: #fff;
    }
    
    .fc .fc-list-event-time {
        color: #888;
    }
</style>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-calendar-event me-2"></i>Календар подій
                </h5>
            </div>
            <div class="card-body">
                <div id="calendar"></div>
            </div>
        </div>
    </div>
</div>

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
        firstDay: 1,
        height: 'auto',
        eventTimeFormat: {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        },
        events: function(info, successCallback, failureCallback) {
            fetch('/api/calendar_events.php?start=' + info.startStr + '&end=' + info.endStr)
                .then(response => response.json())
                .then(data => successCallback(data))
                .catch(error => {
                    console.error('Error loading calendar events:', error);
                    failureCallback(error);
                });
        },
        eventClick: function(info) {
            if (info.event.url) {
                window.location.href = info.event.url;
                info.jsEvent.preventDefault();
            }
        },
        eventDidMount: function(info) {
            // Add custom styling based on event type
            if (info.event.extendedProps.type === 'task') {
                info.el.style.borderLeft = '3px solid ' + info.event.backgroundColor;
            } else if (info.event.extendedProps.type === 'order') {
                info.el.style.borderLeft = '3px solid #6610f2';
            }
        }
    });
    calendar.render();
});
</script>

<?php include __DIR__ . '/../views/layout/footer.php'; ?>