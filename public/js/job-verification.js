'use strict';
(() => {
    const $ = id => document.getElementById(id);
    const meta = name => document.querySelector(`meta[name="${name}"]`).content;
    const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const label = value => String(value || 'Not recorded').replaceAll('_', ' ');
    const date = value => value ? new Date(value).toLocaleString() : 'Not recorded';
    const money = value => new Intl.NumberFormat('en-NG', {style:'currency',currency:'NGN'}).format(Number(value)/100);
    const id = new URLSearchParams(location.search).get('booking');
    let user, booking, urls = [], busy = false, opener, page = 1;
    function message(text, error = false) { $('pageMessage').textContent=text; $('pageMessage').className=`notice${error?' error':''}`; }
    function signIn() { const target = new URL(meta('login-url')); if (id && /^\d+$/.test(id)) target.searchParams.set('verification_booking',id); else target.searchParams.set('verification','1'); location.replace(target.href); }
    async function api(path, options = {}) {
        const token=sessionStorage.getItem('fixifier-token');
        if(!token) { signIn(); throw new Error('Sign in to continue.'); }
        const response=await fetch(meta('api-base')+path,{method:options.body?'POST':'GET',headers:{Accept:'application/json',Authorization:`Bearer ${token}`,...(options.body?{'Content-Type':'application/json'}:{})},body:options.body?JSON.stringify(options.body):undefined});
        if(response.status===401) { sessionStorage.removeItem('fixifier-token'); signIn(); }
        if(options.image && response.ok) return response.blob();
        const data=await response.json().catch(()=>({}));
        if(!response.ok) { const error=new Error(response.status===403?'You do not have permission to view or change this booking.':response.status===404?'Booking or evidence not found.':data.errors?Object.values(data.errors).flat().join(' '):data.message || 'Unable to complete the request. Please try again.'); error.status=response.status; throw error; }
        return data;
    }
    function eligible() { return booking && user.role==='customer' && Number(booking.customer_id)===Number(user.id) && booking.status==='evidence_submitted'; }
    function close() { if(busy)return; $('modal').classList.add('hidden'); opener?.focus(); }
    function modal(type) { if(!eligible())return; opener=document.activeElement; $('actionError').classList.add('hidden'); $('modalTitle').textContent=type==='approve'?'Approve completed work':'Raise a dispute'; $('approveContent').classList.toggle('hidden',type!=='approve'); $('disputeForm').classList.toggle('hidden',type!=='dispute'); $('modal').classList.remove('hidden'); (type==='approve'?$('confirmApprove'):$('reason')).focus(); }
    document.querySelectorAll('[data-close]').forEach(b=>b.onclick=close);
    $('modal').onclick=e=>{if(e.target===$('modal'))close();};
    document.addEventListener('keydown',e=>{
        if($('modal').classList.contains('hidden'))return;
        if(e.key==='Escape')close();
        if(e.key==='Tab') {const nodes=[...$('modal').querySelectorAll('button:not(:disabled),select,textarea')].filter(el=>el.getClientRects().length);if(!nodes.length)return;const first=nodes[0],last=nodes.at(-1);if(e.shiftKey && document.activeElement===first){e.preventDefault();last.focus();}else if(!e.shiftKey && document.activeElement===last){e.preventDefault();first.focus();}}
    });
    document.querySelectorAll('[data-tab]').forEach(b=>b.onclick=()=>{document.querySelectorAll('[data-tab]').forEach(n=>n.classList.toggle('active',n===b));document.querySelectorAll('.section').forEach(s=>s.classList.toggle('active',s.id===b.dataset.tab));});
    $('approveBtn').onclick=()=>modal('approve'); $('disputeBtn').onclick=()=>modal('dispute'); $('disputePageBtn').onclick=()=>modal('dispute');
    async function mutate(action, body) {
        if(busy || !eligible())return;
        busy=true; $('modal').querySelectorAll('button').forEach(b=>b.disabled=true);
        let succeeded=false;
        try { await api(`/bookings/${id}/${action}`,{body}); succeeded=true; busy=false; close(); booking=null; $('reviewActions').classList.add('hidden'); $('disputePageBtn').classList.add('hidden'); await load(); message(action==='approve'?'Approval saved.':'Dispute saved for administrator review.'); }
        catch(error) {
            if(succeeded) { message('Your action was saved, but the updated booking could not be loaded. Refresh this page before taking further action.',true); }
            else if(error.status===409) { busy=false; close(); try {await load();}catch{} message(error.message+' The page has requested the latest booking state.',true); }
            else { $('actionError').textContent=error.message; $('actionError').classList.remove('hidden'); if(!error.status || error.status>=500){booking=null; $('reviewActions').classList.add('hidden'); $('disputePageBtn').classList.add('hidden'); $('actionError').textContent+=' The outcome may be uncertain. Close this dialog and refresh before retrying.';} }
        } finally {busy=false; $('modal').querySelectorAll('button').forEach(b=>b.disabled=false);}
    }
    $('confirmApprove').onclick=()=>mutate('approve',{});
    $('disputeForm').onsubmit=e=>{e.preventDefault();mutate('dispute',{reason:$('reason').value,details:$('details').value});};
    async function photoStage(type) {
        const records=booking.evidence.filter(e=>e.type===type), container=$(type+'Photos');
        container.innerHTML=records.map(e=>`<div class="photo ${type==='after'?'after':''}" style="margin-bottom:10px"><span>Loading evidence…</span><img data-photo="${e.id}" alt="${type} evidence" hidden></div><small class="muted">Captured: ${esc(date(e.captured_at))}</small>`).join('') || '<div class="photo"><div class="fallback">No evidence submitted.</div></div>';
        await Promise.all(records.map(async e=>{const img=container.querySelector(`[data-photo="${e.id}"]`);try{const blob=await api(`/evidence/${e.id}`,{image:true});if(!img.isConnected)return;const url=URL.createObjectURL(blob);urls.push(url);img.src=url;img.hidden=false;img.previousElementSibling.hidden=true;}catch{if(img.isConnected)img.previousElementSibling.textContent='Evidence unavailable. Refresh to retry.';}}));
    }
    async function load() {
        booking=null; $('bookingLayout').classList.add('hidden'); urls.forEach(URL.revokeObjectURL); urls=[];
        const result=await api(`/bookings/${id}`); booking=result.data;
        $('headerStatus').textContent=label(booking.status); $('jobTag').textContent=label(booking.status);
        $('reference').textContent='JOB '+booking.reference; $('service').textContent=booking.service_category;
        $('technician').textContent=`Technician: ${booking.technician?.name || 'Unassigned'} · ${booking.address}`;
        $('charge').textContent=booking.quotation?money(booking.quotation.amount_minor):'Not quoted';
        $('stageCount').textContent=new Set(booking.evidence.map(e=>e.type)).size+' stages'; $('jobDate').textContent=booking.scheduled_at?new Date(booking.scheduled_at).toLocaleDateString():'Not scheduled';
        $('description').textContent=booking.description;
        $('completionNote').textContent=booking.evidence.filter(e=>e.type==='after' && e.note).map(e=>e.note).join('\n') || 'No completion note recorded.';
        $('reviewActions').classList.toggle('hidden',!eligible()); $('disputePageBtn').classList.toggle('hidden',!eligible());
        $('resultMessage').textContent=eligible()?'Review the evidence before approving or raising a dispute.':user.role!=='customer'?'Read-only customer review. Only this booking’s customer can approve or dispute completion.':`Current booking state: ${label(booking.status)}. Approval is available only when evidence is submitted.`;
        const dispute=booking.dispute;
        $('disputeDetails').innerHTML=dispute?`<h3>Case #${dispute.id}</h3><p>${esc(dispute.reason)}</p><p>${esc(dispute.details)}</p><div class="notice">Status: ${esc(label(dispute.status))}</div><p>${esc(dispute.resolution || '')}</p>`:'<div class="notice">No dispute has been submitted for this job.</div>';
        const events=[['Service requested',booking.created_at],['Quotation accepted',booking.quotation?.accepted_at],...booking.evidence.map(e=>[`${label(e.type)} evidence uploaded`,e.created_at]),['Dispute opened',dispute?.created_at],['Dispute resolved',dispute?.resolved_at]].filter(e=>e[1]).sort((a,b)=>new Date(a[1])-new Date(b[1]));
        $('timeline').innerHTML=events.map(([title,time])=>`<li><strong>${esc(title)}</strong><small>${esc(date(time))}</small></li>`).join('');
        $('paymentTotal').textContent=booking.payment?money(booking.payment.amount_minor):booking.quotation?money(booking.quotation.amount_minor):'Not recorded'; $('provider').textContent=booking.payment?.provider || 'Not recorded'; $('settlement').textContent=label(booking.payment?.status);
        $('paymentNotice').textContent=booking.payment?.provider==='demo'?'Simulated payment record. No money is collected, held or transferred.':'This screen shows recorded payment information. Approval does not initiate or prove a payment-provider transfer.';
        $('bookingLayout').classList.remove('hidden'); $('pageMessage').classList.add('hidden');
        await Promise.all([photoStage('before'),photoStage('after')]);
    }
    async function pick() {
        const result=await api(`/bookings?page=${page}`); $('headerStatus').textContent='Select a booking'; $('picker').classList.remove('hidden');
        $('picker').innerHTML='<h2>Select a booking to verify</h2>'+result.data.map(b=>`<p><a href="${esc(meta('verification-url'))}?booking=${b.id}">${esc(b.reference)} · ${esc(b.service_category)}</a> — ${esc(label(b.status))}</p>`).join('')+(result.data.length?'':'<p>No bookings found.</p>')+`<div class="actions"><button class="btn secondary" id="prev" ${page<=1?'disabled':''}>Previous</button><span>Page ${page} of ${result.last_page}</span><button class="btn secondary" id="next" ${page>=result.last_page?'disabled':''}>Next</button></div>`;
        $('prev').onclick=()=>{page--;pick().catch(e=>message(e.message,true));}; $('next').onclick=()=>{page++;pick().catch(e=>message(e.message,true));}; $('pageMessage').classList.add('hidden');
    }
    (async()=>{try{user=(await api('/me')).data; $('portalLabel').textContent=user.role.toUpperCase()+' PORTAL'; if(id && !/^[1-9]\d*$/.test(id))throw new Error('Invalid booking link. Choose a booking from your dashboard.');if(id)await load();else await pick();}catch(e){message(e.message,true);$('headerStatus').textContent='Unable to load';}})();
    window.addEventListener('pagehide',()=>urls.forEach(URL.revokeObjectURL));
})();
