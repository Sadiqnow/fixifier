'use strict';
const $ = id => document.getElementById(id);
const base = document.querySelector('meta[name="api-base"]').content;
const loginUrl = document.querySelector('meta[name="login-url"]').content;
const esc = value => String(value ?? '—').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const sections = {overview:'Operations Overview',technicians:'Technician KYC',bookings:'Bookings',evidence:'Job Evidence',disputes:'Disputes',customers:'Customers',finance:'Finance',audit:'Audit Log'};
let section = 'overview', page = 1, generation = 0, imageUrls = [];
async function api(path, options = {}) {
    const token = sessionStorage.getItem('fixifier-token');
    if (!token) { location.replace(loginUrl); throw new Error('Please sign in.'); }
    const response = await fetch(base + path, {...options, headers:{Accept:'application/json',Authorization:`Bearer ${token}`, ...(options.body ? {'Content-Type':'application/json'} : {})}});
    if (response.status === 401) { sessionStorage.removeItem('fixifier-token'); location.replace(loginUrl); throw new Error('Please sign in.'); }
    if (response.status === 403) throw new Error('Administrator access is required.');
    if (options.image && response.ok) return response.blob();
    const data = await response.json();
    if (!response.ok) throw new Error(data.errors ? Object.values(data.errors).flat().join(' ') : data.message || 'Request failed.');
    return data;
}
function toast(message) { $('toast').textContent = message; $('toast').style.display = 'block'; clearTimeout(window.toastTimer); window.toastTimer = setTimeout(() => $('toast').style.display = 'none', 6000); }
function closeModal() { $('modalback').classList.remove('open'); imageUrls.forEach(URL.revokeObjectURL); imageUrls = []; }
function modal(title, html) { closeModal(); $('modalTitle').textContent = title; $('modalContent').innerHTML = html; $('modalback').classList.add('open'); $('modalback').querySelector('button').focus(); }
document.addEventListener('keydown', e => { if(e.key === 'Escape') closeModal(); });
function money(amount, currency = 'NGN') { return new Intl.NumberFormat('en-NG',{style:'currency',currency}).format(Number(amount || 0)/100); }
function table(headers, rows) { return `<div class="tablewrap"><table class="table"><thead><tr>${headers.map(h=>`<th>${esc(h)}</th>`).join('')}</tr></thead><tbody>${rows.map(row=>`<tr>${row.map(cell=>`<td>${cell}</td>`).join('')}</tr>`).join('') || `<tr><td colspan="${headers.length}" class="empty">No records found.</td></tr>`}</tbody></table></div>`; }
function go(name) { section = name; page = 1; load(); }
async function load() {
    const version = ++generation, current = section;
    $('nav').innerHTML = Object.entries(sections).map(([key,title])=>`<button class="navbtn ${key===section?'active':''}" onclick="go('${key}')">${title}</button>`).join('');
    document.querySelectorAll('.section').forEach(el=>el.classList.toggle('active',el.id===section));
    $('title').textContent = sections[section]; $(current).innerHTML = '<div class="card">Loading records…</div>';
    try {
        const result = await api(`/admin/dashboard?section=${current}&page=${page}`);
        if (version !== generation) return;
        const data = result.records, rows = data.data; let headers, cells;
        if (['bookings','evidence','disputes'].includes(current)) {
            headers = ['Reference','Customer / technician','Service','Quotation','Status','Action'];
            cells = rows.map(j=>[esc(j.reference),`${esc(j.customer?.name)}<br>${esc(j.technician?.name)}`,esc(j.service_category),j.quotation?money(j.quotation.amount_minor):'—',esc(j.status),`<button class="btn secondary" onclick="reviewJob(${j.id})">Review</button> <a class="btn secondary" href="${esc(document.querySelector('meta[name="verification-url"]').content)}?booking=${j.id}">Verification</a>`]);
        } else if (current === 'technicians') {
            headers = ['ID','Name','Email','Trade','KYC status']; cells = rows.map(t=>[t.id,esc(t.name),esc(t.email),esc(t.trade),esc(t.kyc_status || 'No profile submitted')]);
        } else if (current === 'customers') {
            headers = ['ID','Name','Email','Phone','Registered']; cells = rows.map(c=>[c.id,esc(c.name),esc(c.email),esc(c.phone),esc(c.created_at)]);
        } else if (current === 'finance') {
            headers = ['Payment','Booking','Provider','Amount','Status']; cells = rows.map(p=>[p.id,p.booking_id,esc(p.provider),money(p.amount_minor,p.currency),esc(p.status)]);
        } else {
            headers = ['Time','Actor ID','Action','Record type','Record ID']; cells = rows.map(a=>[esc(a.created_at),esc(a.actor_id),esc(a.action),esc(a.subject_type),a.subject_id]);
        }
        const stats = current === 'overview' ? `<div class="stats">${Object.entries(result.summary).map(([title,count])=>`<div class="card stat"><div class="muted">${title}</div><strong>${count}</strong></div>`).join('')}</div>` : '';
        const note = current === 'technicians' ? '<p class="notice">Recorded verification status. Document review and KYC decisions are not available here yet.</p>' : current === 'finance' ? '<p class="notice">Recorded payment states. Payment provider transfers are not initiated here.</p>' : '';
        $(current).innerHTML = stats + `<div class="card">${note}${table(headers,cells)}<div class="actions" style="margin-top:18px"><button class="btn ghost" onclick="page--;load()" ${page<=1?'disabled':''}>Previous</button><span class="muted">Page ${data.current_page} of ${data.last_page} · ${data.total} records</span><button class="btn ghost" onclick="page++;load()" ${page>=data.last_page?'disabled':''}>Next</button></div></div>`;
    } catch(error) { if(version===generation) $(current).innerHTML=`<div class="card"><p role="alert">${esc(error.message)}</p><a href="${esc(loginUrl)}">Sign in with another account</a></div>`; }
}
async function reviewJob(id) {
    try {
        const job = (await api(`/bookings/${id}`)).data;
        modal(`Booking · ${job.reference}`,`<div class="detail"><h3>${esc(job.service_category)}</h3><p>${esc(job.customer?.name)} → ${esc(job.technician?.name)}</p><p>${esc(job.description)}</p><p>${esc(job.address)}</p><p>Status: ${esc(job.status)} · Payment: ${esc(job.payment?.status || 'Not recorded')}</p><div class="photos">${job.evidence.map(e=>`<div class="photo"><div><b>${esc(e.type)}</b><img data-evidence="${e.id}" alt="${esc(e.type)} evidence"><p>${esc(e.note)}</p></div></div>`).join('') || '<p>No evidence uploaded.</p>'}</div>${job.dispute ? `<div class="notice"><b>${esc(job.dispute.reason)}</b><p>${esc(job.dispute.details)}</p><p>${esc(job.dispute.resolution || '')}</p></div>` : ''}${job.dispute?.status === 'open' ? `<form id="resolutionForm" class="detail"><label>Decision<select class="field" name="decision"><option value="rework">Request rework</option><option value="refund">Record refund decision</option><option value="release">Approve release</option></select></label><label>Resolution notes<textarea class="field" name="resolution" minlength="20" maxlength="5000" required></textarea></label><p class="notice">This updates the dispute, booking and recorded payment state.</p><p id="resolutionError" role="alert"></p><button class="btn">Save resolution</button></form>` : ''}</div>`);
        if ($('resolutionForm')) $('resolutionForm').onsubmit = async event => {
            event.preventDefault(); const form = event.currentTarget, button = form.querySelector('button'); button.disabled = true;
            try { await api(`/disputes/${job.dispute.id}/resolve`,{method:'POST',body:JSON.stringify(Object.fromEntries(new FormData(form)))}); closeModal(); await load(); toast('Dispute resolution saved.'); }
            catch(error) { $('resolutionError').textContent = error.message; } finally { button.disabled = false; }
        };
        await Promise.all(job.evidence.map(async e=>{
            const image = document.querySelector(`[data-evidence="${e.id}"]`);
            try { const blob = await api(`/evidence/${e.id}`,{image:true}); if(!image.isConnected) return; const url=URL.createObjectURL(blob); imageUrls.push(url); image.src=url; }
            catch(error) { image.alt='Evidence unavailable'; toast(error.message); }
        }));
    } catch(error) { toast(error.message); }
}
async function logout() { try { await api('/auth/logout',{method:'POST'}); sessionStorage.removeItem('fixifier-token'); location.replace(loginUrl); } catch(error) { toast(error.message); } }
load();
