'use strict';
(() => {
    const $ = selector => document.querySelector(selector);
    const base = $('meta[name="api-base"]').content;
    const query = new URLSearchParams(location.search);
    const bookingIntent = $('meta[name="booking-intent"]').content === 'true' || query.has('service');
    const escape = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const money = value => new Intl.NumberFormat('en-NG', {style:'currency', currency:'NGN'}).format(Number(value || 0) / 100);
    const label = value => String(value).replaceAll('_', ' ');
    let token = sessionStorage.getItem('fixifier-token'), user, jobs = [], view = 'overview', register = false, page = 1, lastPage = 1;
    let urls = [], toastTimer;

    async function api(path, {method = 'GET', body, blob = false} = {}) {
        const headers = {Accept:'application/json'};
        if (token) headers.Authorization = `Bearer ${token}`;
        if (body && !(body instanceof FormData)) { headers['Content-Type'] = 'application/json'; body = JSON.stringify(body); }
        const response = await fetch(base + path, {method, headers, body});
        if (response.status === 401) { clearSession(); throw new Error('Please sign in to continue.'); }
        if (blob && response.ok) return response.blob();
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.errors ? Object.values(data.errors).flat().join(' ') : data.message || `Request failed (${response.status}). Please try again.`);
        return data;
    }
    function toast(message) { clearTimeout(toastTimer); $('#toast').textContent = message; $('#toast').classList.remove('hidden'); toastTimer = setTimeout(() => $('#toast').classList.add('hidden'), 6000); }
    function clearSession() { token = null; user = null; jobs = []; sessionStorage.removeItem('fixifier-token'); closeModal(); $('#app').classList.add('hidden'); $('#login').classList.remove('hidden'); }
    function closeModal() { $('#modal').close(); urls.forEach(URL.revokeObjectURL); urls = []; }
    function modal(title, body) { closeModal(); $('#modalTitle').textContent = title; $('#modalBody').innerHTML = body; $('#modal').showModal(); }
    function heading(title, description) { return `<div class="heading"><div><h1>${escape(title)}</h1><p>${escape(description)}</p></div><button class="ghost" data-action="refresh">Refresh</button></div>`; }
    function field(name, title, type = 'text', extra = '') { return `<div class="field"><label for="f-${name}">${escape(title)}</label><input id="f-${name}" name="${name}" type="${type}" ${extra} required></div>`; }
    function area(name, title, min = 20) { return `<div class="field span2"><label for="f-${name}">${escape(title)}</label><textarea id="f-${name}" name="${name}" minlength="${min}" maxlength="5000" required></textarea></div>`; }
    function action(title, type, id) { return `<button class="btn secondary small" data-action="${type}" data-id="${id}">${title}</button>`; }
    function form(title, body, onSubmit) {
        modal(title, `<form id="actionForm" class="form">${body}<p id="formError" role="alert" class="notice hidden"></p><button class="btn" type="submit">Submit</button></form>`);
        $('#actionForm').onsubmit = async event => {
            event.preventDefault(); const el = event.currentTarget, button = el.querySelector('[type="submit"]'); button.disabled = true;
            try { await onSubmit(new FormData(el)); closeModal(); await refresh(); toast('Saved successfully.'); }
            catch (error) { $('#formError').textContent = error.message; $('#formError').classList.remove('hidden'); }
            finally { button.disabled = false; }
        };
    }
    async function refresh() {
        const data = await api(`/bookings?page=${page}`); jobs = data.data; lastPage = data.last_page; render();
    }
    async function enter() {
        user = (await api('/me')).data;
        const verificationBooking = query.get('verification_booking');
        if (query.get('verification') === '1' || (verificationBooking && /^[1-9]\d*$/.test(verificationBooking))) {
            const destination = new URL($('meta[name="verification-url"]').content);
            if (verificationBooking && /^[1-9]\d*$/.test(verificationBooking)) destination.searchParams.set('booking', verificationBooking);
            location.replace(destination.href); return;
        }
        if (user.role === 'admin') { location.replace($('meta[name="admin-url"]').content); return; }
        if (user.role === 'technician') { location.replace($('meta[name="technician-url"]').content); return; }
        $('#rolePill').textContent = `${label(user.role)} portal`;
        $('#login').classList.add('hidden'); $('#app').classList.remove('hidden'); view = user.role === 'customer' && bookingIntent ? 'book' : 'overview'; page = 1;
        $('#main').innerHTML = '<div class="card">Loading your workspace…</div>';
        try { await refresh(); } catch (error) { $('#main').innerHTML = heading('Unable to load bookings', error.message); }
    }
    $('#toggleAuth').onclick = () => {
        register = !register; $('.register-only input').required = register;
        $('#password_confirmation').required = register;
        document.querySelectorAll('.register-only').forEach(el => el.classList.toggle('hidden', !register));
        $('#authTitle').textContent = register ? 'Create your account' : 'Welcome back';
        $('#authSubmit').textContent = register ? 'Create account' : 'Sign in';
        $('#toggleAuth').textContent = register ? 'Already registered? Sign in' : 'Create an account';
        $('#password').autocomplete = register ? 'new-password' : 'current-password';
        $('#authError').classList.add('hidden');
    };
    $('#authForm').onsubmit = async event => {
        event.preventDefault(); $('#authSubmit').disabled = true; $('#authError').classList.add('hidden');
        const data = Object.fromEntries(new FormData(event.currentTarget));
        try {
            const result = await api(`/auth/${register ? 'register' : 'login'}`, {method:'POST', body:data});
            token = result.token; sessionStorage.setItem('fixifier-token', token); event.target.reset(); await enter();
        } catch (error) { $('#authError').textContent = error.message; $('#authError').classList.remove('hidden'); }
        finally { $('#authSubmit').disabled = false; }
    };
    $('#logout').onclick = async () => { try { await api('/auth/logout', {method:'POST'}); clearSession(); } catch (error) { toast(error.message); } };
    $('#closeModal').onclick = closeModal;
    $('#modal').addEventListener('cancel', event => { event.preventDefault(); closeModal(); });

    function render() {
        const nav = [['overview','Overview'],['jobs',user.role === 'admin' ? 'Booking oversight' : 'My bookings']];
        if (user.role === 'customer') nav.push(['book','Book a service']);
        nav.push(['payments',user.role === 'technician' ? 'Earnings' : 'Payments'],['profile','My profile']);
        $('#nav').innerHTML = nav.map(([id,title]) => `<button data-view="${id}" class="${id === view ? 'active' : ''}">${title}</button>`).join('');
        if (view === 'book') { renderBooking(); return; }
        if (view === 'profile') {
            $('#main').innerHTML = heading('My profile', 'Your account information.') + `<div class="card"><h3>${escape(user.name)}</h3><p>${escape(user.email)}</p><p>${escape(user.phone || '')}</p><span class="role-pill">${escape(label(user.role))}</span></div>`; return;
        }
        const pager = `<div class="job-actions"><button class="ghost" data-action="previous" ${page <= 1 ? 'disabled' : ''}>Previous</button><span>Page ${page} of ${lastPage}</span><button class="ghost" data-action="next" ${page >= lastPage ? 'disabled' : ''}>Next</button></div>`;
        if (view === 'payments') {
            $('#main').innerHTML = heading('Payment records', 'Recorded payments for bookings on this page.') + `<div class="card table-wrap"><table class="table"><thead><tr><th>Booking</th><th>Amount</th><th>Status</th></tr></thead><tbody>${jobs.filter(j => j.payment).map(j => `<tr><td>${escape(j.reference)}</td><td>${money(j.payment.amount_minor)}</td><td>${escape(label(j.payment.status))}</td></tr>`).join('') || '<tr><td colspan="3">No recorded payments on this page.</td></tr>'}</tbody></table>${pager}</div>`; return;
        }
        const stat = (title, value) => `<div class="card stat"><label>${title}</label><strong>${value}</strong><small>Current page</small></div>`;
        const stats = view === 'overview' ? `<div class="grid stats">${stat('Bookings',jobs.length)}${stat('Active',jobs.filter(j => !['completed','cancelled'].includes(j.status)).length)}${stat('Awaiting approval',jobs.filter(j => j.status === 'evidence_submitted').length)}${stat('Completed',jobs.filter(j => j.status === 'completed').length)}</div>` : '';
        $('#main').innerHTML = heading(view === 'overview' ? `Welcome, ${user.name}` : 'Bookings', 'Manage service requests, quotations and completion evidence.') + stats + `<div class="card bookings"><h3>Service activity</h3>${jobs.map(card).join('') || '<div class="empty">No bookings yet.</div>'}${pager}</div>`;
    }
    function card(job) {
        let buttons = action('View details','details',job.id);
        buttons += `<a class="btn secondary small" href="${escape($('meta[name="verification-url"]').content)}?booking=${job.id}">Job verification</a>`;
        if (user.role === 'customer' && job.status === 'quoted') buttons += action('Accept quotation','accept',job.id);
        if (user.role === 'technician' && job.status === 'requested') buttons += action('Send quotation','quote',job.id);
        if (user.role === 'technician' && job.status === 'confirmed') buttons += action('Start job','start',job.id);
        if (user.role === 'technician' && job.status === 'in_progress') buttons += action('Add evidence','upload',job.id) + action('Submit for approval','submit',job.id);
        const color = job.status === 'completed' ? 'green' : job.status === 'disputed' ? 'red' : 'blue';
        return `<div class="job"><div class="job-head"><div><h4>${escape(job.service_category)}</h4><div class="meta">${escape(job.reference)} · ${escape(job.address)}</div></div><span class="status ${color}">${escape(label(job.status))}</span></div><p>${escape(job.description)}</p>${job.quotation ? `<b>${money(job.quotation.amount_minor)}</b>` : ''}<div class="job-actions">${buttons}</div></div>`;
    }
    async function renderBooking() {
        $('#main').innerHTML = heading('Book a service', 'Loading technicians…');
        try {
            const technicians = (await api('/technicians')).data;
            if (view !== 'book') return;
            $('#main').innerHTML = heading('Book a service','Describe the issue and request a quotation.') + `<div class="card"><form id="bookingForm" class="form two-col">${field('service_category','Service category','text','maxlength="100" placeholder="e.g. Air conditioner repair"')}<div class="field"><label for="f-technician">Technician</label><select id="f-technician" name="technician_id" required><option value="">Select a technician</option>${technicians.map(t => `<option value="${t.id}">${escape(t.name)}</option>`).join('')}</select></div>${area('description','What needs repair?')}${field('address','Service address','text','maxlength="1000"')}${field('scheduled_at','Preferred date and time','datetime-local')}<p class="meta span2">${technicians.length ? 'Your technician will respond with a quotation.' : 'No technicians are registered yet. Please check again later.'}</p><p id="bookingError" class="notice hidden span2" role="alert"></p><button class="btn" ${technicians.length ? '' : 'disabled'}>Request quotation</button></form></div>`;
            $('#bookingForm').onsubmit = async event => {
                event.preventDefault(); const button = event.currentTarget.querySelector('button'); button.disabled = true;
                const data = Object.fromEntries(new FormData(event.currentTarget)); data.scheduled_at = new Date(data.scheduled_at).toISOString();
                try { await api('/bookings',{method:'POST',body:data}); view = 'jobs'; page = 1; await refresh(); toast('Quotation requested.'); }
                catch (error) { $('#bookingError').textContent = error.message; $('#bookingError').classList.remove('hidden'); button.disabled = false; }
            };
            $('#f-service_category').value = query.get('service') || '';
        } catch (error) { toast(error.message); }
    }
    async function details(id) {
        const job = (await api(`/bookings/${id}`)).data;
        modal(job.service_category, `<p class="meta">${escape(job.reference)} · ${escape(label(job.status))}</p><p>${escape(job.description)}</p><p><b>Customer:</b> ${escape(job.customer?.name)}</p><p><b>Technician:</b> ${escape(job.technician?.name || 'Unassigned')}</p><p>${escape(job.address)}</p><p>${job.scheduled_at ? escape(new Date(job.scheduled_at).toLocaleString()) : 'No appointment date'}</p>${job.quotation ? `<div class="job"><b>${money(job.quotation.amount_minor)}</b><p>${escape(job.quotation.scope)}</p></div>` : ''}<h3>Job evidence</h3><div class="evidence">${job.evidence.map(e => `<div><b>${escape(label(e.type))}</b><img class="preview" data-evidence="${e.id}" alt="${escape(e.type)} repair evidence"><p>${escape(e.note)}</p></div>`).join('') || '<p>No evidence uploaded.</p>'}</div>${job.dispute ? `<div class="notice"><b>${escape(job.dispute.reason)}</b><p>${escape(job.dispute.details)}</p><p>${escape(job.dispute.resolution || '')}</p></div>` : ''}<div class="job-actions">${user.role === 'customer' && job.status === 'evidence_submitted' ? action('Approve completion','approve',id) + action('Raise dispute','dispute',id) : ''}${user.role === 'admin' && job.dispute?.status === 'open' ? action('Resolve dispute','resolve',job.dispute.id) : ''}</div>`);
        await Promise.all(job.evidence.map(async evidence => {
            const img = $(`[data-evidence="${evidence.id}"]`);
            try { const blob = await api(`/evidence/${evidence.id}`,{blob:true}); if (!img.isConnected) return; const url = URL.createObjectURL(blob); urls.push(url); img.src = url; }
            catch (error) { if (img.isConnected) { img.alt = 'Unable to load evidence'; toast(error.message); } }
        }));
    }
    async function handleAction(type, id) {
        if (type === 'refresh') return refresh();
        if (type === 'previous' || type === 'next') { page += type === 'next' ? 1 : -1; return refresh(); }
        if (type === 'details') return details(id);
        if (type === 'quote') return form('Submit quotation', field('amount','Amount (₦)','number','min="100" step="0.01"') + area('scope','Scope of work',10) + field('expires_at','Valid until','datetime-local'), data => api(`/bookings/${id}/quotation`,{method:'POST',body:{amount_minor:Math.round(Number(data.get('amount')) * 100),currency:'NGN',scope:data.get('scope'),expires_at:new Date(data.get('expires_at')).toISOString()}}));
        if (type === 'upload') return form('Upload job evidence','<div class="field"><label for="evidenceType">Evidence type</label><select name="type" id="evidenceType"><option value="before">Before repair</option><option value="after">After repair</option></select></div>' + field('photo','Photo','file','accept="image/jpeg,image/png,image/webp"') + '<div class="field"><label for="note">Notes (optional)</label><textarea id="note" name="note" maxlength="2000"></textarea></div><p class="meta">Upload both before and after photos, then submit the job for approval. Maximum 10 MB per photo.</p>', data => { data.set('captured_at',new Date().toISOString()); return api(`/bookings/${id}/evidence`,{method:'POST',body:data}); });
        if (type === 'dispute') return form('Raise a dispute',field('reason','Reason','text','maxlength="120"') + area('details','Describe the issue'), data => api(`/bookings/${id}/dispute`,{method:'POST',body:Object.fromEntries(data)}));
        if (type === 'resolve') return form('Resolve dispute','<div class="field"><label for="decision">Decision</label><select id="decision" name="decision"><option value="rework">Return for rework</option><option value="release">Approve release</option><option value="refund">Refund</option></select></div>' + area('resolution','Resolution details'), data => api(`/disputes/${id}/resolve`,{method:'POST',body:Object.fromEntries(data)}));
        const endpoints = {accept:'quotation/accept',start:'start',submit:'evidence/submit',approve:'approve'};
        if (endpoints[type]) { await api(`/bookings/${id}/${endpoints[type]}`,{method:'POST'}); closeModal(); await refresh(); toast('Booking updated.'); }
    }
    document.addEventListener('click', async event => {
        const nav = event.target.closest('[data-view]');
        if (nav) { view = nav.dataset.view; render(); return; }
        const button = event.target.closest('[data-action]'); if (!button || button.disabled) return;
        button.disabled = true;
        try { await handleAction(button.dataset.action, button.dataset.id); } catch (error) { toast(error.message); } finally { button.disabled = false; }
    });
    if (location.pathname.endsWith('/register') || query.get('mode') === 'register') $('#toggleAuth').click();
    if (location.pathname.includes('/technician/') || query.get('role') === 'technician') $('#role').value = 'technician';
    if (token) enter().catch(error => { clearSession(); toast(error.message); });
})();
