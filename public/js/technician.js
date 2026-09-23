'use strict';
const $ = id => document.getElementById(id);
const base = document.querySelector('meta[name="api-base"]').content;
const login = document.querySelector('meta[name="login-url"]').content;
const esc = value => String(value ?? '').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const money = n => new Intl.NumberFormat('en-NG',{style:'currency',currency:'NGN'}).format(Number(n || 0)/100);
let user, profile, jobs=[], page=1, last=1, section='overview', urls=[];
async function api(path, method='GET', body, image=false) {
    const token=sessionStorage.getItem('fixifier-token');
    if(!token) { location.replace(login); throw new Error('Please sign in.'); }
    const headers={Accept:'application/json',Authorization:`Bearer ${token}`};
    if(body && !(body instanceof FormData)) { headers['Content-Type']='application/json'; body=JSON.stringify(body); }
    const response=await fetch(base+path,{method,headers,body});
    if(response.status===401) { sessionStorage.removeItem('fixifier-token'); location.replace(login); throw new Error('Please sign in.'); }
    if(image && response.ok) return response.blob();
    const result=await response.json();
    if(!response.ok) throw new Error(result.errors?Object.values(result.errors).flat().join(' '):result.message || 'Request failed.');
    return result;
}
function toast(message) { $('toast').textContent=message; $('toast').style.display='block'; clearTimeout(window.toastTimer); window.toastTimer=setTimeout(()=>$('toast').style.display='none',6000); }
function closeModal() { $('overlay').classList.remove('open'); urls.forEach(URL.revokeObjectURL); urls=[]; }
function modal(title, html) { closeModal(); $('modalTitle').textContent=title; $('modalBody').innerHTML=html; $('overlay').classList.add('open'); $('closeModal').focus(); }
$('closeModal').onclick=closeModal;
$('overlay').onclick=e=>{if(e.target===$('overlay'))closeModal();};
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeModal();});
$('menu').onclick=()=>$('sidebar').classList.toggle('open');
$('today').textContent=new Date().toLocaleDateString();
$('logout').onclick=async()=>{try{await api('/auth/logout','POST');sessionStorage.removeItem('fixifier-token');location.replace(login);}catch(e){toast(e.message);}};
function go(value) { section=value; $('sidebar').classList.remove('open'); render(); }
document.querySelectorAll('#nav button').forEach(button=>button.onclick=()=>go(button.dataset.page));
async function load() { const result=await api(`/bookings?page=${page}`); jobs=result.data; last=result.last_page; render(); }
function button(title,action,id) { return `<button class="btn secondary" data-action="${action}" data-id="${id}">${title}</button>`; }
function card(job) {
    let actions=button('Details / evidence','details',job.id);
    if(job.status==='requested') actions+=button('Send quotation','quote',job.id);
    if(job.status==='confirmed') actions+=button('Start job','start',job.id);
    if(job.status==='in_progress') actions+=button('Upload evidence','upload',job.id)+button('Submit for approval','submit',job.id);
    return `<div class="job"><div class="jobhead"><strong>${esc(job.service_category)}</strong><span class="badge">${esc(job.status.replaceAll('_',' '))}</span></div><p>${esc(job.reference)} · ${esc(job.address)}</p><p>${esc(job.description)}</p><b>${job.quotation?money(job.quotation.amount_minor):'Quotation pending'}</b><div class="actions">${actions}</div></div>`;
}
function render() {
    const names={overview:'Overview',requests:'Service Requests',jobs:'My Jobs',evidence:'Job Evidence',earnings:'Earnings',profile:'My Profile & KYC'};
    $('pageTitle').textContent=names[section];
    document.querySelectorAll('#nav button').forEach(b=>b.classList.toggle('active',b.dataset.page===section));
    const heading=`<h1>${section==='overview'?`Welcome back, ${esc(user.name)}`:names[section]}</h1>`;
    if(section==='profile') {
        $('content').innerHTML=heading+`<div class="card"><h2>${esc(user.name)}</h2><p>${esc(user.email)} · ${esc(user.phone || 'No phone recorded')}</p><p>${esc(profile?.trade || 'No professional profile submitted')}</p><p>${esc(profile?.bio)}</p><p>Experience: ${esc(profile?.years_experience ?? '—')} years</p><span class="badge">KYC: ${esc(profile?.kyc_status || 'Not submitted')}</span><p class="notice">Profile editing and identity document submission are not available here yet.</p></div>`; return;
    }
    const paid=jobs.filter(j=>j.payment?.status==='released');
    let content='';
    if(section==='overview') {
        const stats=[['New requests',jobs.filter(j=>j.status==='requested').length],['Active jobs',jobs.filter(j=>['confirmed','in_progress','evidence_submitted'].includes(j.status)).length],['Completed jobs',jobs.filter(j=>j.status==='completed').length],['Recorded releases',money(paid.reduce((sum,j)=>sum+Number(j.payment.amount_minor),0))]];
        content=`<div class="grid">${stats.map(([title,value])=>`<div class="card metric"><small>${title}</small><b>${value}</b></div>`).join('')}</div><h2 style="margin-top:24px">Service activity</h2>`;
    }
    if(section==='earnings') {
        content=`<div class="card"><h2>Payment records</h2>${jobs.filter(j=>j.payment).map(j=>`<div class="listrow"><span>${esc(j.reference)} · ${esc(j.payment.status)}</span><b>${money(j.payment.amount_minor)}</b></div>`).join('') || '<p>No payment records on this page.</p>'}<p class="notice">These are recorded payment states. Demo-provider records represent simulated payments.</p></div>`;
    } else {
        const visible=jobs.filter(j=>section==='requests'?['requested','quoted'].includes(j.status):['jobs','evidence'].includes(section)?!['requested','quoted'].includes(j.status):true);
        content+=`<div class="card stack">${visible.map(card).join('') || '<div class="empty">No matching jobs on this page.</div>'}</div>`;
    }
    $('content').innerHTML=heading+'<p class="intro">Records and totals reflect the current booking page.</p>'+content+`<div class="actions" style="margin-top:20px">${button('Refresh','refresh',0)}<button class="btn secondary" data-action="previous" ${page<=1?'disabled':''}>Previous</button><span>Page ${page} of ${last}</span><button class="btn secondary" data-action="next" ${page>=last?'disabled':''}>Next</button></div>`;
}
function input(name,title,type,extra='') { return `<label class="field">${title}<input name="${name}" type="${type}" ${extra} required></label>`; }
function form(title,html,submit) {
    modal(title,`<form id="actionForm">${html}<p id="formError" role="alert"></p><button class="btn">Submit</button></form>`);
    $('actionForm').onsubmit=async e=>{
        e.preventDefault(); const button=e.currentTarget.querySelector('button');button.disabled=true;
        try {await submit(new FormData(e.currentTarget));closeModal();await load();toast('Saved successfully.');}
        catch(error){$('formError').textContent=error.message;}finally{button.disabled=false;}
    };
}
async function details(id) {
    const job=(await api(`/bookings/${id}`)).data;
    modal(job.reference,`<h3>${esc(job.service_category)}</h3><p>Customer: ${esc(job.customer?.name)}</p><p>${esc(job.description)}</p><p>${esc(job.address)}</p><p>Status: ${esc(job.status)}</p>${job.quotation?`<p>${money(job.quotation.amount_minor)} · ${esc(job.quotation.scope)}</p>`:''}<div class="evidence-grid">${job.evidence.map(e=>`<div class="evidence-box"><b>${esc(e.type)}</b><img data-photo="${e.id}" style="width:100%" alt="${esc(e.type)} evidence"><p>${esc(e.note)}</p></div>`).join('') || '<p>No evidence uploaded yet.</p>'}</div>`);
    await Promise.all(job.evidence.map(async e=>{
        const img=document.querySelector(`[data-photo="${e.id}"]`);
        try {const blob=await api(`/evidence/${e.id}`,'GET',undefined,true);if(!img.isConnected)return;const url=URL.createObjectURL(blob);urls.push(url);img.src=url;}catch(error){img.alt='Evidence unavailable';toast(error.message);}
    }));
}
async function act(action,id) {
    if(action==='refresh')return load();
    if(action==='previous'||action==='next'){page+=action==='next'?1:-1;return load();}
    if(action==='details')return details(id);
    if(action==='quote')return form('Submit quotation',input('amount','Amount (₦)','number','min="100" step="0.01"')+'<label class="field">Scope of work<textarea name="scope" minlength="10" maxlength="5000" required></textarea></label>'+input('expires_at','Valid until','datetime-local'),data=>api(`/bookings/${id}/quotation`,'POST',{amount_minor:Math.round(Number(data.get('amount'))*100),currency:'NGN',scope:data.get('scope'),expires_at:new Date(data.get('expires_at')).toISOString()}));
    if(action==='upload')return form('Upload job evidence','<label class="field">Stage<select name="type"><option value="before">Before repair</option><option value="after">After repair</option></select></label>'+input('photo','Photo (JPEG, PNG or WebP, max 10 MB)','file','accept="image/jpeg,image/png,image/webp"')+'<label class="field">Notes<textarea name="note" maxlength="2000"></textarea></label><p class="notice">Upload both before and after photos before submitting completion.</p>',data=>{data.set('captured_at',new Date().toISOString());return api(`/bookings/${id}/evidence`,'POST',data);});
    if(action==='start'||action==='submit'){await api(`/bookings/${id}/${action==='start'?'start':'evidence/submit'}`,'POST');await load();toast(action==='start'?'Job started. Capture before evidence before repairs.':'Submitted for customer approval.');}
}
document.addEventListener('click',async e=>{const button=e.target.closest('[data-action]');if(!button||button.disabled)return;button.disabled=true;try{await act(button.dataset.action,button.dataset.id);}catch(error){toast(error.message);}finally{button.disabled=false;}});
(async()=>{try{const result=await api('/me');user=result.data;profile=result.technician_profile;if(user.role!=='technician')throw new Error('Please sign in with a technician account.');$('topName').textContent=user.name;$('topTrade').textContent=profile?.trade || 'Technician';await load();}catch(error){$('content').innerHTML=`<div class="card"><p>${esc(error.message)}</p><a href="${esc(login)}">Sign in</a></div>`;}})();
