'use strict';
const $ = id => document.getElementById(id);
const base = document.querySelector('meta[name="api-base"]').content;
const login = document.querySelector('meta[name="login-url"]').content;
const esc = value => String(value ?? '').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const money = n => new Intl.NumberFormat('en-NG',{style:'currency',currency:'NGN'}).format(Number(n || 0)/100);
let user, profile, jobs=[], page=1, last=1, section='overview', urls=[], returnFocus=null, loadVersion=0;
async function api(path, method='GET', body, image=false) {
    const token=sessionStorage.getItem('fixifier-token');
    if(!token) { location.replace(login); throw new Error('Please sign in.'); }
    const headers={Accept:'application/json',Authorization:`Bearer ${token}`};
    if(body && !(body instanceof FormData)) { headers['Content-Type']='application/json'; body=JSON.stringify(body); }
    const response=await fetch(base+path,{method,headers,body});
    if(response.status===401) { sessionStorage.removeItem('fixifier-token'); location.replace(login); throw new Error('Please sign in.'); }
    if(image && response.ok) return response.blob();
    let result;
    try { result=await response.json(); } catch (_) { throw new Error('The server could not return this request. Please try again.'); }
    if(!response.ok) throw new Error(result.errors?Object.values(result.errors).flat().join(' '):result.message || 'Request failed.');
    return result;
}
function toast(message) { $('toast').textContent=message; $('toast').style.display='block'; clearTimeout(window.toastTimer); window.toastTimer=setTimeout(()=>$('toast').style.display='none',6000); }
function closeModal() { $('overlay').classList.remove('open'); document.body.style.overflow=''; urls.forEach(URL.revokeObjectURL); urls=[]; if(returnFocus?.isConnected) returnFocus.focus(); }
function modal(title, html) { closeModal(); returnFocus=document.activeElement; $('modalTitle').textContent=title; $('modalBody').innerHTML=html; $('overlay').classList.add('open'); document.body.style.overflow='hidden'; $('closeModal').focus(); }
$('closeModal').onclick=closeModal;
$('overlay').onclick=e=>{if(e.target===$('overlay'))closeModal();};
document.addEventListener('keydown',e=>{
    if(e.key==='Escape') { closeModal(); $('sidebar').classList.remove('open'); $('menu').setAttribute('aria-expanded','false'); }
    if(e.key==='Tab' && $('overlay').classList.contains('open')) {
        const items=Array.from($('overlay').querySelectorAll('button:not(:disabled),a[href],input:not(:disabled),select:not(:disabled),textarea:not(:disabled),[tabindex="0"]'));
        const first=items[0], last=items[items.length-1];
        if(e.shiftKey && document.activeElement===first){e.preventDefault();last.focus();}
        else if(!e.shiftKey && document.activeElement===last){e.preventDefault();first.focus();}
    }
});
$('menu').onclick=()=>{const open=$('sidebar').classList.toggle('open');$('menu').setAttribute('aria-expanded',String(open));};
$('today').textContent=new Date().toLocaleDateString();
$('logout').onclick=async()=>{try{await api('/auth/logout','POST');sessionStorage.removeItem('fixifier-token');location.replace(login);}catch(e){toast(e.message);}};
function go(value) { if(!journeySections[value])return; section=value; location.hash=value; $('sidebar').classList.remove('open'); $('menu').setAttribute('aria-expanded','false'); render(); }
window.addEventListener('hashchange',()=>{const value=location.hash.slice(1);if(journeySections[value]){section=value;render();}});
document.querySelectorAll('#nav button').forEach(button=>button.onclick=()=>go(button.dataset.page));
async function load(targetPage=page) {
    const version=++loadVersion;
    $('content').setAttribute('aria-busy','true');
    try {
        const result=await api(`/bookings?page=${targetPage}`);
        if(version!==loadVersion)return;
        jobs=result.data; last=result.last_page; page=result.current_page || targetPage; render();
    } finally { if(version===loadVersion)$('content').setAttribute('aria-busy','false'); }
}
function button(title,action,id) { return `<button class="btn ${['quote','upload','submit'].includes(action) ? 'primary' : 'secondary'}" data-action="${action}" data-id="${id}">${title}</button>`; }
function render() { renderJourney(); }
function input(name,title,type,extra='') { return `<label class="field">${title}<input name="${name}" type="${type}" ${extra} required></label>`; }
function form(title,html,submit) {
    modal(title,`<form id="actionForm">${html}<p id="formError" role="alert"></p><button class="btn">Submit</button></form>`);
    $('actionForm').onsubmit=async e=>{
        e.preventDefault(); const button=e.currentTarget.querySelector('button');button.disabled=true;
        try {await submit(new FormData(e.currentTarget));closeModal();toast('Saved successfully.');try{await load();}catch(error){toast('Saved, but refresh failed. '+error.message);}}
        catch(error){$('formError').textContent=error.message;}finally{button.disabled=false;}
    };
}
async function details(id) {
    const job=(await api(`/bookings/${id}`)).data;
    modal(job.reference,`<h3>${esc(job.service_category)}</h3><p>Customer: ${esc(job.customer?.name)}</p><p>${esc(job.description)}</p><p>${esc(job.address)}</p><p>Status: ${esc(readable(job.status))} · Round ${Number(job.current_work_round || 1)}</p>${job.quotation?`<p>${money(job.quotation.amount_minor)} · ${esc(job.quotation.scope)}</p>`:''}<h3>Dispute history</h3>${(job.disputes || []).map(d=>`<div class="item"><strong>Round ${Number(d.work_round)} · ${esc(readable(d.status))}</strong><p>${esc(d.reason)} · ${esc(d.details)}</p><p>${esc(d.resolution || 'Awaiting administrator decision')}</p></div>`).join('') || '<p>No disputes recorded.</p>'}<h3>Work evidence</h3><div class="evidence-grid">${job.evidence.map(e=>`<div class="evidence-box"><b>${esc(e.type)} · Round ${Number(e.work_round)}</b><img data-photo="${e.id}" style="width:100%" alt="${esc(e.type)} evidence"><p>${esc(e.note)}</p></div>`).join('') || '<p>No evidence uploaded yet.</p>'}</div>`);
    await Promise.all(job.evidence.map(async e=>{
        const img=document.querySelector(`[data-photo="${e.id}"]`);
        try {const blob=await api(`/evidence/${e.id}`,'GET',undefined,true);if(!img?.isConnected)return;const url=URL.createObjectURL(blob);urls.push(url);img.src=url;}catch(error){if(img)img.alt='Evidence unavailable';toast(error.message);}
    }));
}
async function act(action,id) {
    if(action==='refresh'){await loadIdentity();return load();}
    if(action==='previous'||action==='next')return load(Math.max(1,Math.min(last,page+(action==='next'?1:-1))));
    if(action==='details')return details(id);
    if(action==='quote')return form('Submit quotation',input('amount','Amount (₦)','number','min="100" step="0.01"')+'<label class="field">Scope of work<textarea name="scope" minlength="10" maxlength="5000" required></textarea></label>'+input('expires_at','Valid until','datetime-local'),data=>api(`/bookings/${id}/quotation`,'POST',{amount_minor:Math.round(Number(data.get('amount'))*100),currency:'NGN',scope:data.get('scope'),expires_at:new Date(data.get('expires_at')).toISOString()}));
    if(action==='upload') {
        const job=(await api(`/bookings/${id}`)).data, stage=job.status==='confirmed' || !job.evidence.some(e=>e.type==='before' && e.work_round===job.current_work_round)?'before':'after';
        return form(`Upload ${stage} evidence · Round ${job.current_work_round}`,`<input type="hidden" name="type" value="${stage}">`+input('photo','Photo (JPEG, PNG or WebP, max 10 MB)','file','accept="image/jpeg,image/png,image/webp"')+'<label class="field">Notes<textarea name="note" maxlength="2000"></textarea></label><p class="notice">Before evidence is required before starting work. After evidence is captured during work.</p>',data=>{data.set('captured_at',new Date().toISOString());data.set('expected_work_round',job.current_work_round);return api(`/bookings/${id}/evidence`,'POST',data);});
    }
    if(action==='start'||action==='submit'){const job=(await api(`/bookings/${id}`)).data;await api(`/bookings/${id}/${action==='start'?'start':'evidence/submit'}`,'POST',{expected_work_round:job.current_work_round});await load();toast(action==='start'?'Job started.':'Submitted for customer approval.');}
}
document.addEventListener('click',async e=>{const button=e.target.closest('[data-action]');if(!button||button.disabled)return;button.disabled=true;try{await act(button.dataset.action,button.dataset.id);}catch(error){toast(error.message);}finally{button.disabled=false;}});
async function loadIdentity(){
    const result=await api('/me');
    if(result.data.role!=='technician'){user=null;throw new Error('Please sign in with a technician account.');}
    user=result.data; profile=result.technician_profile;
    $('topName').textContent=user.name; $('topTrade').textContent=profile?.trade || 'Technician';
    $('avatar').textContent=user.name.split(/\s+/).map(word=>word[0]).slice(0,2).join('').toUpperCase();
}
(async()=>{try{await loadIdentity();if(journeySections[location.hash.slice(1)])section=location.hash.slice(1);await load();}catch(error){$('content').innerHTML=`<div class="card pad"><p>${esc(error.message)}</p><a class="btn" href="${esc(login)}">Sign in</a></div>`;}finally{$('content').setAttribute('aria-busy','false');}})();
