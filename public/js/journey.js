'use strict';
(() => {
    const script = document.querySelector('script[data-journey-base]'), base = script.dataset.journeyBase;
    const dialog = document.getElementById('journey-dialog'), body = document.getElementById('journey-body');
    const error = document.getElementById('journey-error'), status = document.getElementById('journey-status');
    const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const money = v => v == null ? 'Not calculated' : new Intl.NumberFormat('en-NG', {style:'currency',currency:'NGN'}).format(Number(v)/100);
    const field = (name, label, type='text', value='', extra='required') => `<label>${esc(label)}<input name="${name}" type="${type}" value="${esc(value)}" ${extra}></label>`;
    const area = (name,label,value='') => `<label>${esc(label)}<textarea name="${name}" required maxlength="5000">${esc(value)}</textarea></label>`;
    const select = (name,label,values) => `<label>${label}<select name="${name}">${values.map(v=>`<option value="${esc(v)}">${esc(v.replaceAll('_',' '))}</option>`).join('')}</select></label>`;
    const form = (action,title,fields) => `<section><h3>${title}</h3><form data-jform="${action}">${fields}<button type="submit">${title}</button></form></section>`;
    const button = (action,title) => `<button type="button" data-jaction="${action}">${title}</button>`;
    let job, identity, objectURLs=[], previousFocus;
    function headers(json=false) {
        const h = {Accept:'application/json'};
        if (base.includes('/admin/')) h['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').content;
        else h.Authorization = 'Bearer '+sessionStorage.getItem('fixifier-token');
        if(json) h['Content-Type']='application/json';
        return h;
    }
    async function api(path, method='GET', data, blob=false) {
        const isJson = data && !(data instanceof FormData);
        const r=await fetch(base+path,{method,headers:headers(isJson),body:isJson?JSON.stringify(data):data});
        if(blob && r.ok) return r.blob();
        const result=await r.json().catch(()=>({message:'The server did not return a valid response.'}));
        if(!r.ok) throw Error(result.errors?Object.values(result.errors).flat().join(' '):result.message||'Request failed.');
        return result;
    }
    function clearURLs(){objectURLs.forEach(URL.revokeObjectURL);objectURLs=[];}
    function show(title) {
        clearURLs(); error.textContent='';status.textContent='';document.getElementById('journey-title').textContent=title;
        if(!dialog.open){previousFocus=document.activeElement;dialog.showModal();}body.innerHTML='<p>Loading…</p>';
    }
    dialog.addEventListener('close',()=>{clearURLs();previousFocus?.focus();window.dispatchEvent(new Event('journey-updated'));});
    dialog.querySelector('[data-journey-close]').onclick=()=>dialog.close();
    async function open(id) {
        show('Service journey');
        [identity,job]=await Promise.all([api('/me').then(r=>r.data),api('/bookings/'+id).then(r=>r.data)]);
        render();
        await Promise.all(job.evidence.map(async e=>{
            const img=body.querySelector(`[data-jphoto="${e.id}"]`);if(!img)return;
            try {const file=await api('/evidence/'+e.id,'GET',undefined,true);if(!img.isConnected)return;const url=URL.createObjectURL(file);objectURLs.push(url);img.src=url;}
            catch(e){img.alt='Evidence unavailable: '+e.message;}
        }));
    }
    function quote(q){return `<section><h3>Quotation version ${Number(q.version)} · ${money(q.amount_minor)}</h3><p>${esc(q.diagnosis)}</p><p><b>Scope:</b> ${esc(q.scope)}</p><p><b>Exclusions:</b> ${esc(q.exclusions)}</p><p>Duration: ${Number(q.duration_minutes||0)} minutes · Expires ${esc(q.expires_at)}</p><table><thead><tr><th>Item</th><th>Quantity</th><th>Unit price</th><th>Total</th></tr></thead><tbody>${(q.items||[]).map(i=>`<tr><td>${esc(i.kind)}: ${esc(i.description)}</td><td>${Number(i.quantity)}</td><td>${money(i.unit_price_minor)}</td><td>${money(i.quantity*i.unit_price_minor)}</td></tr>`).join('')}</tbody></table><p>${q.accepted_at?'Accepted '+esc(q.accepted_at):esc(q.decision||'Awaiting decision')} ${esc(q.decision_reason)}</p></section>`;}
    function render(){
        clearURLs(); const tech=identity.role==='technician',customer=identity.role==='customer',admin=identity.role==='admin';
        let html=`<section><h3>${esc(job.reference)} · ${esc(job.service_category)}</h3><p>${esc(job.status)} · Work round ${job.current_work_round}</p><p>${esc(job.description)}</p><p>${esc(job.address)}</p><p>Appointment: ${esc(job.scheduled_at||'Not confirmed')} · ${esc(job.visit_status)}</p></section>`;
        html+=(job.quote_versions||[]).map(quote).join('');
        if(tech && job.status==='requested' && !job.request_accepted_at) html+=`<section>${button('accept-request','Accept request')}</section>`+form('decline-request','Decline request',area('reason','Reason'));
        if(tech && job.status==='requested' && job.request_accepted_at) html+=form('quote','Send quotation',area('diagnosis','Diagnosis and recommended repair')+area('scope','Scope')+area('exclusions','Exclusions (write None if none)')+field('duration_minutes','Estimated duration (minutes)','number','', 'required min="1" max="43200"')+field('expires_at','Expires','datetime-local')+'<div id="quote-items">'+item()+'</div>'+button('add-item','Add line item')+'<p>Totals are calculated by the server. Prices below are in naira.</p>');
        if(customer && job.status==='quoted') html+=`<section>${button('accept-quote','Accept this quotation')}</section>`+form('revise-quote','Request revision',area('reason','Reason'))+form('decline-quote','Decline quotation',area('reason','Reason'));
        if(tech && job.request_accepted_at && ['requested','quoted','confirmed'].includes(job.status)) {
            html+=form('schedule','Confirm or reschedule appointment',field('scheduled_at','Appointment','datetime-local')+area('reason','Appointment details / rescheduling reason'));
            const next={scheduled:'en_route',en_route:'arrived',arrived:'inspection'}[job.visit_status];
            if(next) html+=form('visit','Record '+next.replaceAll('_',' '),`<input type="hidden" name="status" value="${next}">`+area('note','Visit update'));
        }
        if(job.payment) html+=`<section><h3>Payment</h3><p>${esc(job.payment.status)} · Gross ${money(job.payment.amount_minor)} · Platform fee ${money(job.payment.platform_fee_minor)} · Technician net ${money(job.payment.technician_net_minor)} · Refund ${money(job.payment.refund_minor)}</p><p>Reference: ${esc(job.payment.provider_reference)} · Settlement: ${esc(job.settlement_status||'Not requested')}</p>${button('reconcile-payment','Check funding with provider')}${job.payment.settlement_reference?button('reconcile-settlement','Check settlement with provider'):''}</section>`;
        if(customer && job.status==='confirmed' && (!job.payment || ['pending','failed'].includes(job.payment.status))) html+=`<section>${button('fund','Open test funding checkout')}<small>Funding is confirmed only after server verification. Live payments remain disabled.</small></section>`;
        if(admin && ['release_pending','refund_pending'].includes(job.settlement_status)) html+=`<section>${button('settle','Request test settlement')}</section>`;
        for(const round of job.work_rounds||[]){
            html+=`<section><h3>Work round ${round.number}</h3><p>${esc(round.corrective_work||'Original agreed scope')}</p><p>${esc(round.completion_notes||'Completion not submitted')}</p><p>Review: ${esc(round.review||'Pending')} · ${esc(round.reviewed_at)}</p><div class="journey-grid">${job.evidence.filter(e=>e.work_round===round.number).map(e=>`<div><b>${esc(e.type)}</b><img data-jphoto="${e.id}" alt="${esc(e.type)} evidence"><p>${esc(e.note)}</p><small>Uploaded ${esc(e.created_at)}</small></div>`).join('')}</div></section>`;
        }
        if(tech && ['confirmed','in_progress'].includes(job.status)){
            html+=form('evidence','Upload work evidence',select('type','Stage',job.status==='confirmed'?['before']:['before','during','after'])+fileField('photo')+area('note','Evidence note'));
            if(job.status==='confirmed')html+=`<section>${button('start','Start work')}</section>`;
            else html+=form('progress','Add progress update',area('body','Progress'))+form('submit','Submit completion',area('completion_notes','Completion notes'));
        }
        if(customer && job.status==='evidence_submitted') html+=`<section><p>Review the agreed scope, current round evidence and completion notes above.</p>${button('approve','Approve completion')}</section>`+form('dispute','Open dispute',field('reason','Reason')+area('details','Describe the issue'));
        html+=`<section><h3>Dispute history</h3>${(job.disputes||[]).map(d=>`<article><h4>Round ${d.work_round}: ${esc(d.reason)}</h4><p>${esc(d.details)}</p><p>${esc(d.decision)} ${esc(d.resolution)}</p><small>${esc(d.resolved_at)}</small></article>`).join('')||'<p>No disputes.</p>'}</section>`;
        if(tech && job.status==='disputed')html+=form('dispute-response','Respond to dispute',area('body','Response'));
        if(admin && job.status==='disputed')html+=form('resolve','Record dispute decision',select('decision','Decision',['rework','refund','release'])+area('resolution','Reason and required corrective work'));
        if((customer && job.status==='requested') || job.status==='disputed')html+=form('attachment','Upload supporting attachment',`<input type="hidden" name="type" value="${job.status==='disputed'?'dispute':'fault'}">`+fileField('file'));
        html+=`<section><h3>Attachments</h3>${(job.booking_attachments||[]).map(a=>`<button type="button" data-jattachment="${a.id}">${esc(a.type)} · round ${a.work_round} · ${esc(a.created_at)}</button>`).join('')||'<p>No attachments.</p>'}</section>`;
        if(customer && job.status==='completed' && !job.review)html+=form('rating','Submit review',field('stars','Stars','number','', 'required min="1" max="5"')+area('comment','Written review'));
        html+=`<section><h3>Conversation and history</h3>${(job.booking_updates||[]).map(u=>`<article><b>${esc(u.type)} · Round ${u.work_round}</b><p>${esc(u.body)}</p><small>${esc(u.created_at)}</small></article>`).join('')||'<p>No messages.</p>'}</section>`+form('message','Send message',area('body','Booking message'));
        body.innerHTML=html;
    }
    function item(){return `<div class="quote-item">${select('kind','Kind',['labour','materials','charge'])}${field('description','Description')}${field('quantity','Quantity','number','1','required min="1" max="1000"')}${field('unit_price','Unit price (NGN)','number','','required min="0" step="0.01"')}${button('remove-item','Remove line')}</div>`;}
    function fileField(name){return `<label>Photo or file (maximum 10 MB)<input name="${name}" type="file" ${name==='photo'?'accept="image/jpeg,image/png,image/webp" capture="environment"':'accept="image/jpeg,image/png,image/webp,application/pdf"'} required></label><div data-preview></div><progress max="100" value="0" hidden></progress>`;}
    async function upload(path,data,formEl){
        return new Promise((resolve,reject)=>{
            const xhr=new XMLHttpRequest();xhr.open('POST',base+path);Object.entries(headers()).forEach(([k,v])=>xhr.setRequestHeader(k,v));
            const progress=formEl.querySelector('progress');progress.hidden=false;
            xhr.upload.onprogress=e=>{if(e.lengthComputable)progress.value=e.loaded/e.total*100;};
            xhr.onerror=()=>reject(Error('Upload failed. Check your connection and retry.'));
            xhr.onload=()=>{let r;try{r=JSON.parse(xhr.responseText);}catch(_){reject(Error('Upload failed.'));return;}if(xhr.status>=200&&xhr.status<300)resolve(r);else reject(Error(r.errors?Object.values(r.errors).flat().join(' '):r.message||'Upload rejected.'));};xhr.send(data);
        });
    }
    async function perform(action,data={},formEl){
        const root='/bookings/'+job?.id;
        if(action==='quote'){
            data.items=Array.from(formEl.querySelectorAll('.quote-item')).map(row=>({kind:row.querySelector('[name=kind]').value,description:row.querySelector('[name=description]').value,quantity:Number(row.querySelector('[name=quantity]').value),unit_price_minor:Math.round(Number(row.querySelector('[name=unit_price]').value)*100)}));
            data.currency='NGN';data.expires_at=new Date(data.expires_at).toISOString();await api(root+'/quotation','POST',data);
        }else if(action==='accept-quote')await api(root+'/quotation/accept','POST',{quotation_id:job.quotation.id});
        else if(action==='evidence') {const f=new FormData(formEl);f.set('captured_at',new Date().toISOString());f.set('expected_work_round',job.current_work_round);await upload(root+'/evidence',f,formEl);}
        else if(action==='attachment')await upload(root+'/attachments',new FormData(formEl),formEl);
        else if(['start','submit','approve','dispute','rating'].includes(action))await api(root+'/'+({submit:'evidence/submit'}[action]||action),'POST',{...data,expected_work_round:job.current_work_round});
        else if(action==='resolve')await api('/disputes/'+job.dispute.id+'/resolve','POST',data);
        else if(action==='fund') {const r=await api(root+'/payments','POST',{});if(r.data.checkout_url){body.innerHTML=`<section><p>${esc(r.data.message)}</p><a href="${esc(r.data.checkout_url)}" target="_blank" rel="noopener">Continue to Paystack test checkout</a><p>Return here and check funding after checkout.</p>${button('reload','Refresh booking')}</section>`;return;}throw Error(r.data.message);}
        else if(['reconcile-payment','settle','reconcile-settlement'].includes(action))await api(root+'/'+({'reconcile-payment':'payments/reconcile',settle:'settlement','reconcile-settlement':'settlement/reconcile'}[action]),'POST',{});
        else if(action==='profile'){data.is_available=data.is_available==='1';await api('/technician/profile','PUT',data);await tool('profile');status.textContent='Profile saved.';return;}
        else if(action==='document'){await upload('/technician/documents',new FormData(formEl),formEl);await tool('documents');status.textContent='Document submitted.';return;}
        else if(action==='destination'){await api('/technician/payout-destination','PUT',data);await tool('earnings');return;}
        else if(action!=='reload'){if(action==='schedule')data.scheduled_at=new Date(data.scheduled_at).toISOString();if(action.includes('quote'))data.quotation_id=job.quotation.id;await api(root+'/journey/'+action,'POST',data);}
        await open(job.id);status.textContent='Saved. Current records loaded from the server.';
    }
    async function tool(name){
        show(name==='notifications'?'Notifications':'Technician '+name);job=null;
        if(name==='notifications'){const rows=(await api('/notifications')).data;body.innerHTML=rows.map(n=>`<section><b>${esc(n.type)}</b><p>${esc(n.body)}</p>${n.booking_id?`<button data-journey-id="${n.booking_id}">Open booking</button>`:''}${!n.read_at?`<button data-jread="${n.id}">Mark read</button>`:'Read'}<small>${esc(n.created_at)}</small></section>`).join('')||'<p>No notifications.</p>';return;}
        if(name==='profile'){
            const [me,directory]=await Promise.all([api('/me'),api('/technicians')]);const p=me.technician_profile||{};
            body.innerHTML=form('profile','Save professional profile',select('trade','Trade',directory.categories)+select('service_location','Service area',directory.areas)+area('bio','Professional description',p.bio)+area('skills','Skills',p.skills)+field('years_experience','Years of experience','number',p.years_experience||0,'required min="0" max="80"')+field('indicative_price_minor','Indicative price (kobo)','number',p.indicative_price_minor||'','min="0"')+`<label>Availability<select name="is_available"><option value="1">Available</option><option value="0">Unavailable</option></select></label>`+area('availability_notes','Availability details',p.availability_notes||'Contact me to agree a suitable time.'));
            ['trade','service_location'].forEach(k=>{if(p[k])body.querySelector(`[name=${k}]`).value=p[k];});body.querySelector('[name=is_available]').value=p.is_available?'1':'0';return;
        }
        if(name==='documents'){
            const r=await api('/technician/documents');body.innerHTML=form('document','Submit verification document',field('type','Document type')+fileField('document'))+`<section><h3>Submission history</h3>${r.data.map(d=>`<p>${esc(d.type)} · ${esc(d.status)} · ${esc(d.created_at)}</p>`).join('')||'<p>No documents.</p>'}<h3>Administrator decisions</h3>${(r.decisions||[]).map(d=>`<p>${esc(d.to_status)}: ${esc(d.reason)} · ${esc(d.created_at)}</p>`).join('')||'<p>No decisions.</p>'}</section>`;return;
        }
        if(name==='earnings'){
            const r=await api('/technician/earnings');body.innerHTML=`<section><h3>Account earnings</h3><p>Pending net: ${money(r.pending_minor)} · Eligible for settlement: ${money(r.available_minor)} · Provider-confirmed paid net: ${money(r.paid_minor)}</p><p>Eligibility is not a transfer confirmation.</p>${r.data.map(p=>`<p>Booking ${p.booking_id}: ${money(p.amount_minor)} gross / ${money(p.technician_net_minor)} net · ${esc(p.status)}</p>`).join('')||'<p>No earnings.</p>'}<h3>Settlement history</h3>${r.operations.map(o=>`<p>${esc(o.kind)} · ${esc(o.reference)} · ${esc(o.status)} · ${money(o.amount_minor)}</p>`).join('')||'<p>No settlements.</p>'}<p>Destination: ${esc(r.destination?.account_name||'Not verified')} ${esc(r.destination?.last_four||'')}</p></section>`+form('destination','Verify test payout destination',field('bank_code','Paystack bank code')+field('account_number','Account number','text','','required pattern="[0-9]{10}"'));return;
        }
    }
    dialog.addEventListener('submit',async e=>{const f=e.target.closest('[data-jform]');if(!f)return;e.preventDefault();const btn=f.querySelector('[type=submit]');btn.disabled=true;error.textContent='';try{await perform(f.dataset.jform,Object.fromEntries(new FormData(f)),f);}catch(err){error.textContent=err.message;}finally{btn.disabled=false;}});
    dialog.addEventListener('change',e=>{if(e.target.type!=='file')return;const file=e.target.files[0],f=e.target.form,preview=f.querySelector('[data-preview]');preview.innerHTML='';if(!file)return;if(file.size>10*1024*1024){error.textContent='Maximum file size is 10 MB.';e.target.value='';return;}if(file.type.startsWith('image/')){const url=URL.createObjectURL(file);objectURLs.push(url);preview.innerHTML=`<img src="${url}" alt="Upload preview">`;}});
    document.addEventListener('click',async e=>{
        const b=e.target.closest('[data-journey-id],[data-journey-tool],[data-jaction],[data-jattachment],[data-jread]');if(!b||b.disabled)return;
        e.preventDefault();b.disabled=true;error.textContent='';
        try{
            if(b.dataset.journeyId)await open(b.dataset.journeyId);
            else if(b.dataset.journeyTool)await tool(b.dataset.journeyTool);
            else if(b.dataset.jread){await api('/notifications/'+b.dataset.jread+'/read','POST',{});await tool('notifications');}
            else if(b.dataset.jattachment){const file=await api('/attachments/'+b.dataset.jattachment,'GET',undefined,true);const url=URL.createObjectURL(file);objectURLs.push(url);const a=document.createElement('a');a.href=url;a.download='attachment-'+b.dataset.jattachment;a.click();}
            else if(b.dataset.jaction==='add-item')body.querySelector('#quote-items').insertAdjacentHTML('beforeend',item());
            else if(b.dataset.jaction==='remove-item')b.closest('.quote-item').remove();
            else await perform(b.dataset.jaction);
        }catch(err){error.textContent=err.message;}finally{b.disabled=false;}
    });
    window.JourneyUI={open,tool};
})();
