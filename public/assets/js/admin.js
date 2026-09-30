'use strict';
(() => {
    const overlay=document.getElementById('overlay'), dialog=document.getElementById('dialog');
    let previousFocus=null;
    function close() {
        if (!overlay) return;
        overlay.classList.remove('show'); dialog.replaceChildren(); document.body.style.overflow=''; previousFocus?.focus();
    }
    function open(id) {
        const template=document.getElementById(id); if (!template || !dialog) return;
        previousFocus=document.activeElement; dialog.replaceChildren(template.content.cloneNode(true));
        dialog.querySelectorAll('form').forEach(form=>{
            const field=document.createElement('input'); field.type='hidden'; field.name='_modal'; field.value=id; form.appendChild(field);
        });
        overlay.classList.add('show'); document.body.style.overflow='hidden'; dialog.querySelector('button,input,select,textarea')?.focus();
    }
    document.addEventListener('click',event=>{
        const opener=event.target.closest('[data-dialog]'); if(opener) open(opener.dataset.dialog);
        if(event.target.closest('[data-close]') || event.target===overlay) close();
        const menu=event.target.closest('[data-menu]'); if(menu) { const expanded=document.getElementById('side').classList.toggle('open'); menu.setAttribute('aria-expanded',String(expanded)); }
    });
    document.addEventListener('keydown',event=>{
        if(!overlay?.classList.contains('show')) return;
        if(event.key==='Escape') close();
        if(event.key==='Tab') {
            const focusable=[...dialog.querySelectorAll('button,a[href],input:not([type=hidden]),select,textarea')].filter(e=>!e.disabled);
            const first=focusable[0],last=focusable.at(-1);
            if(event.shiftKey && document.activeElement===first) { event.preventDefault(); last?.focus(); }
            else if(!event.shiftKey && document.activeElement===last) { event.preventDefault(); first?.focus(); }
        }
    });
    const amount=document.getElementById('exampleAmount'); let timer,controller;
    amount?.addEventListener('input',()=>{
        clearTimeout(timer); controller?.abort();
        timer=setTimeout(async()=>{
            controller=new AbortController();
            try {
                const url=new URL(amount.dataset.url,location.href); url.searchParams.set('amount',amount.value || '0');
                const response=await fetch(url,{headers:{'Accept':'text/html'},signal:controller.signal});
                if(!response.ok) throw new Error('Enter a valid amount with up to two decimal places.');
                document.getElementById('calculation').innerHTML=await response.text();
            } catch(error) { if(error.name!=='AbortError') document.getElementById('calculation').textContent=error.message; }
        },180);
    });
    if(document.body.dataset.reopen) open(document.body.dataset.reopen);
})();
