'use strict';

let calendarData = null, calendarLoading = false, calendarError = '', calendarView = 'week';
let calendarDate = new Date().toLocaleDateString('en-CA');
const weekdays = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

async function refreshCalendar() {
    if (calendarLoading) return;
    calendarLoading = true; calendarError = '';
    try { calendarData = (await api('/technician/schedule')).data; }
    catch (error) { calendarError = error.message; }
    finally { calendarLoading = false; if (section === 'schedule') render(); }
}
function scheduleDate(value) {
    return new Intl.DateTimeFormat('en-CA', {timeZone:calendarData.timezone, year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date(value));
}
function scheduleTime(value) {
    return new Intl.DateTimeFormat(undefined,{timeZone:calendarData.timezone,dateStyle:'medium',timeStyle:'short'}).format(new Date(value));
}
function calendarRange(date, mode) {
    const start = new Date(date+'T12:00:00Z'), end = new Date(start);
    if (mode === 'week') end.setUTCDate(end.getUTCDate()+7);
    else if (mode === 'month') {start.setUTCDate(1);end.setUTCDate(1);end.setUTCMonth(end.getUTCMonth()+1);}
    else end.setUTCDate(end.getUTCDate()+1);
    return [start.toISOString().slice(0,10),end.toISOString().slice(0,10)];
}
function weeklyRow(row={weekday:1,starts_at:'09:00',ends_at:'17:00'}) {
    return `<div class="schedule-rule formgrid" data-weekly-row><label class="field">Day<select name="weekday">${weekdays.map((name,day)=>`<option value="${day}" ${day===Number(row.weekday)?'selected':''}>${name}</option>`).join('')}</select></label><label class="field">From<input name="starts_at" type="time" value="${esc(row.starts_at.slice(0,5))}" required></label><label class="field">Until<input name="ends_at" type="time" value="${esc(row.ends_at.slice(0,5))}" required></label><button class="btn sm" type="button" data-remove-rule>Remove window</button></div>`;
}
function exceptionRow(row={available:false,reason:'',starts_at:'',ends_at:''}) {
    const local = value => value ? new Date(value.endsWith('Z') || /[+-]\d\d:\d\d$/.test(value) ? value : value.replace(' ','T')+'Z').toISOString().slice(0,16) : '';
    return `<div class="schedule-rule formgrid" data-exception-row><label class="field">Override<select name="available"><option value="0" ${!row.available?'selected':''}>Blocked / holiday</option><option value="1" ${row.available?'selected':''}>Extra working hours</option></select></label><label class="field">Reason<input name="reason" required maxlength="250" value="${esc(row.reason)}"></label><label class="field">From (UTC)<input name="starts_at" type="datetime-local" value="${local(row.starts_at)}" required></label><label class="field">Until (UTC)<input name="ends_at" type="datetime-local" value="${local(row.ends_at)}" required></label><button class="btn sm" type="button" data-remove-rule>Remove exception</button></div>`;
}
function renderSchedule() {
    if (!calendarData) {
        if (!calendarLoading && !calendarError) void refreshCalendar();
        return `<div class="card pad"><p>${esc(calendarError || 'Loading your schedule…')}</p><button class="btn" data-calendar-refresh>Retry / refresh</button></div>`;
    }
    const [from,until] = calendarRange(calendarDate,calendarView);
    const records = calendarData.bookings.filter(b=>{const day=scheduleDate(b.scheduled_at);return day>=from && day<until;});
    return `<div class="row between wrap"><div class="tabs">${['day','week','month'].map(mode=>`<button data-calendar-view="${mode}" class="${calendarView===mode?'active':''}">${mode[0].toUpperCase()+mode.slice(1)}</button>`).join('')}</div><label class="field">Starting date<input id="calendarDate" type="date" value="${calendarDate}"></label><button class="btn" data-calendar-refresh>Refresh schedule</button></div><p class="muted">Appointments shown in ${esc(calendarData.timezone)}. Travel buffers also reserve capacity.</p>${calendarError?notice(calendarError):''}<div class="grid split"><div class="stack">${panel(`${calendarView} appointments`,records.map(b=>`<div class="item"><strong>${esc(b.reference)} · ${esc(b.service_category)}</strong><p>${esc(scheduleTime(b.scheduled_at))}${b.scheduled_end_at ? ' – '+esc(scheduleTime(b.scheduled_end_at)) : ' · duration recorded on quotation'}</p><p>${esc(readable(b.status))} · ${Number(b.travel_buffer_minutes || 0)} minute travel buffer</p>${button('Open job','details',b.id)}</div>`).join('') || empty('No appointments in this period'))}${notice('Changing working hours does not cancel existing bookings. Use the job scheduling action to reschedule an appointment.')}</div><section class="card pad"><h2>Weekly hours and exceptions</h2><p class="muted">Weekly hours use your timezone. Date-specific exceptions use UTC and override weekly hours; blocked time wins.</p><form id="scheduleEditor"><label class="field">Timezone<input name="timezone" required value="${esc(calendarData.timezone)}" placeholder="Africa/Lagos"></label><div id="weeklyRows">${calendarData.weekly.map(weeklyRow).join('')}</div><button type="button" class="btn sm" data-add-weekly>Add weekly window</button><h3>Date-specific exceptions</h3><div id="exceptionRows">${calendarData.exceptions.map(exceptionRow).join('')}</div><button type="button" class="btn sm" data-add-exception>Add exception</button><p id="scheduleError" role="alert"></p><div class="actions"><button class="btn primary" type="submit">Save availability</button></div></form></section></div>`;
}
document.addEventListener('click',event=>{
    const target=event.target.closest('button'); if(!target)return;
    if(target.hasAttribute('data-calendar-refresh'))void refreshCalendar();
    if(target.dataset.calendarView){calendarView=target.dataset.calendarView;render();}
    if(target.hasAttribute('data-add-weekly'))$('weeklyRows').insertAdjacentHTML('beforeend',weeklyRow());
    if(target.hasAttribute('data-add-exception'))$('exceptionRows').insertAdjacentHTML('beforeend',exceptionRow());
    if(target.hasAttribute('data-remove-rule'))target.closest('.schedule-rule').remove();
});
document.addEventListener('change',event=>{if(event.target.id==='calendarDate' && event.target.value){calendarDate=event.target.value;render();}});
document.addEventListener('submit',async event=>{
    if(event.target.id!=='scheduleEditor')return;
    event.preventDefault();
    const form=event.target, save=form.querySelector('[type="submit"]');
    if(save.disabled)return;save.disabled=true;
    const values=row=>Object.fromEntries(Array.from(row.querySelectorAll('[name]')).map(input=>[input.name,input.value]));
    try {
        const weekly=Array.from(form.querySelectorAll('[data-weekly-row]')).map(row=>{const data=values(row);data.weekday=Number(data.weekday);return data;});
        const exceptions=Array.from(form.querySelectorAll('[data-exception-row]')).map(row=>{const data=values(row);data.available=data.available==='1';data.starts_at+=':00Z';data.ends_at+=':00Z';return data;});
        calendarData=(await api('/technician/schedule','PUT',{version:calendarData.version,timezone:form.elements.timezone.value,weekly,exceptions})).data;
        calendarError='';render();toast('Availability saved. Existing bookings were preserved.');
    } catch(error){$('scheduleError').textContent=error.message;} finally{save.disabled=false;}
});
