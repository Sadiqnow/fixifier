'use strict';

const journeySections = {
    overview: ['Professional workspace', 'Overview', 'Review new requests and move assigned work forward.'],
    requests: ['Step 01 · Assignment', 'Incoming requests', 'Review the problem, location and preferred time before sending a quotation.'],
    jobs: ['Service work', 'My jobs', 'Open an assignment to review its details and available actions.'],
    quotes: ['Step 02 · Pricing', 'Quotes', 'Set a clear scope, amount and expiry for the assigned customer.'],
    evidence: ['Step 03 · Execution', 'Work evidence', 'Record before evidence, start work, then submit after evidence for the current round.'],
    disputes: ['Step 04 · Resolution', 'Disputes & rework', 'Review customer concerns and record fresh evidence for each rework round.'],
    earnings: ['Payment records', 'Earnings & payouts', 'Track recorded payment and settlement states for your assigned jobs.'],
    verification: ['Professional identity', 'Verification', 'Review the verification status held on your technician profile.'],
    profile: ['Your professional details', 'Profile & availability', 'Your current trade, service area and availability.'],
    flow: ['Technician process map', 'Journey and transaction flow', 'Follow each stage from assignment through customer review.'],
};
const journeyLabels = {requested:'New request',quoted:'Quote sent',confirmed:'Confirmed',in_progress:'In progress',evidence_submitted:'Customer review',completed:'Completed',disputed:'Disputed',cancelled:'Cancelled'};
let jobFilter = 'all';
const readable = value => String(value ?? 'Not recorded').replaceAll('_', ' ');
const when = value => value && !Number.isNaN(Date.parse(value)) ? new Date(value).toLocaleString() : 'Not scheduled';
const empty = message => `<div class="empty"><strong>${esc(message)}</strong>Use Refresh to check for updates.</div>`;
const panel = (title, content) => `<section class="card pad"><div class="sectionhead"><h2>${esc(title)}</h2></div>${content}</section>`;
const notice = message => `<div class="notice blue">${esc(message)}</div>`;
const detailRow = (label, value) => `<div class="detail"><span>${esc(label)}</span><strong>${esc(value)}</strong></div>`;
function journeyBadge(job) {
    const color = job.status === 'completed' ? 'green' : ['disputed','cancelled'].includes(job.status) ? 'red' : ['requested','quoted'].includes(job.status) ? 'orange' : '';
    return `<span class="badge ${color}">${esc(journeyLabels[job.status] || readable(job.status))}</span>`;
}
function journeyCard(job) {
    let actions = button('Open job', 'details', job.id);
    if (job.status === 'requested') actions += button('Send quotation', 'quote', job.id);
    if (job.status === 'confirmed') actions += button('Record before evidence', 'upload', job.id) + button('Start work', 'start', job.id);
    if (job.status === 'in_progress') actions += button('Upload evidence', 'upload', job.id) + button('Submit completion', 'submit', job.id);
    const round = Number(job.current_work_round || 1);
    return `<article class="job"><div class="jobhead"><div><strong>${esc(job.reference)} · ${esc(job.service_category)}</strong><p>${esc(job.address)} · ${esc(when(job.scheduled_at))}</p></div>${journeyBadge(job)}</div><p>${esc(job.description)}</p><div class="row between wrap"><strong>${job.quotation ? money(job.quotation.amount_minor) : 'Quotation pending'}</strong><span class="muted small">Round ${round}${round > 1 ? ' · Rework' : ''}</span></div>${job.quotation ? `<p>Scope: ${esc(job.quotation.scope)}<br>Expires: ${esc(when(job.quotation.expires_at))}</p>` : ''}<div class="actions">${actions}<a class="btn sm" href="${esc(document.querySelector('meta[name="verification-url"]').content)}?booking=${encodeURIComponent(job.id)}">Job verification</a></div></article>`;
}
function journeyList(title, records) { return panel(title, records.length ? records.map(journeyCard).join('') : empty('No matching jobs on this page')); }
function journeyOverview() {
    const active = jobs.filter(j => !['completed','cancelled'].includes(j.status));
    const releases = jobs.filter(j => j.payment?.status === 'released');
    const stats = [
        ['New requests', jobs.filter(j => j.status === 'requested').length, 'Awaiting quotation'],
        ['Active jobs', active.length, 'Across current stages'],
        ['Ready for evidence', jobs.filter(j => ['confirmed','in_progress'].includes(j.status)).length, 'Work to record'],
        ['Recorded releases', money(releases.reduce((sum,j) => sum + Number(j.payment.amount_minor),0)), 'Recorded gross amount'],
    ];
    return `<div class="grid stats">${stats.map(([title,value,note]) => `<div class="card metric"><span>${title}</span><strong>${value}</strong><small>${note}</small></div>`).join('')}</div><div class="grid split">${journeyList('Jobs needing attention',active.slice(0,4))}<aside class="stack">${panel('Profile status',detailRow('Verification',readable(profile?.kyc_status || 'not_submitted')) + detailRow('Availability',profile?.is_available ? 'Available' : 'Unavailable') + '<button class="btn" data-section="profile">View profile</button>')}${panel('Recent bookings',jobs.slice(0,4).map(j => `<div class="item"><strong>${esc(j.reference)}</strong><p>${esc(journeyLabels[j.status])} · ${esc(when(j.updated_at))}</p></div>`).join('') || empty('No activity yet'))}${notice('Customer approval and payment release are separate events. Demo-provider payment records do not represent real transfers.')}</aside></div>`;
}
function journeyProfile(verificationOnly) {
    const status = profile?.kyc_status || 'not_submitted';
    const summary = panel(verificationOnly ? 'Identity verification' : user.name,
        `<span class="badge ${status === 'approved' ? 'green' : 'orange'}">${esc(readable(status))}</span>` +
        detailRow('Email',user.email) + detailRow('Phone',user.phone || 'Not recorded') +
        detailRow('Trade',profile?.trade || 'Not submitted') + detailRow('Service area',profile?.service_location || 'Not recorded') +
        (verificationOnly ? detailRow('Verified at',profile?.verified_at ? when(profile.verified_at) : 'Not verified') :
            detailRow('Experience',`${profile?.years_experience ?? 0} years`) + detailRow('Availability',profile?.is_available ? 'Available' : 'Unavailable') + `<p class="muted">${esc(profile?.bio || 'No biography recorded.')}</p>`));
    return `<div class="grid split">${summary}<aside class="stack">${panel(verificationOnly ? 'Private documents' : 'Profile updates',
        notice(verificationOnly ? 'Document upload is not available in this portal yet. Your current review status is shown here; contact the administrator for assistance.' : 'Profile and availability editing are not available in this portal yet. Contact the administrator to update your details.') +
        `<div class="actions"><button class="btn" disabled>${verificationOnly ? 'Upload documents' : 'Save profile'}</button></div>`)}${notice('New assignments require an approved, active and available technician profile.')}</aside></div>`;
}
function journeyEarnings() {
    const payments = jobs.filter(j => j.payment);
    const rows = payments.map(j => `<tr><td>${esc(j.reference)}</td><td>${esc(j.payment.provider || 'Not recorded')}</td><td>${money(j.payment.amount_minor)}</td><td>${esc(readable(j.payment.status))}</td><td>${esc(readable(j.settlement_status || 'not_requested'))}</td><td>${button('Open job','details',j.id)}</td></tr>`).join('');
    return panel('Payment and settlement records',payments.length ? `<div class="tablewrap"><table class="table"><thead><tr><th>Booking</th><th>Provider</th><th>Gross amount</th><th>Payment</th><th>Settlement</th><th>Details</th></tr></thead><tbody>${rows}</tbody></table></div>` : empty('No payment records on this page')) + `<div class="status-summary"></div>` + notice('Amounts are recorded gross payments, not a withdrawable balance. A completed job does not confirm a payout. Demo-provider records are simulations; technicians cannot confirm releases or refunds.');
}
function journeyFlow() {
    const stages = [
        ['Onboarding','Register as a technician. Profile editing and document submission in this portal are not available yet.'],
        ['Approval','Admin reviews eligibility. Only approved, active and available technicians can receive new assignments.'],
        ['Assignment','Review an assigned request. Separate accept and decline actions are not available in this portal.'],
        ['Quote','Send an amount, scope and future expiry. The customer accepts the quotation.'],
        ['Confirmation','Wait for the booking to be confirmed. Payment records may use the demo provider.'],
        ['Before evidence','Upload private before-work evidence for the current work round.'],
        ['Execution','Start work, upload after evidence and submit for customer review.'],
        ['Customer review','The customer approves or disputes the work. Technicians cannot approve their own work.'],
        ['Resolution','Admin reviews disputes and may request rework. Each new round requires fresh evidence.'],
        ['Settlement','Release or refund requests are separate from confirmed transfers. No technician payout controls are available.'],
    ];
    const rows = [['requested','Technician sends quotation','quoted'],['quoted','Customer accepts current quotation','confirmed'],['confirmed','Before evidence exists; technician starts','in_progress'],['in_progress','Before and after evidence submitted','evidence_submitted'],['evidence_submitted','Customer approves or disputes','completed / disputed'],['disputed','Admin decides rework or resolution','confirmed / completed / cancelled']];
    return `<div class="flow">${stages.map(([title,description],i) => `<article><b>${String(i+1).padStart(2,'0')}</b><h3>${title}</h3><p>${description}</p></article>`).join('')}</div><div class="status-summary"></div>` + panel('Booking state transitions',`<div class="tablewrap"><table class="table"><thead><tr><th>Current state</th><th>Required event</th><th>Next state</th></tr></thead><tbody>${rows.map(row => `<tr>${row.map(value=>`<td>${value}</td>`).join('')}</tr>`).join('')}</tbody></table></div>`);
}
function renderJourney() {
    if (!user) return;
    const [eyebrow,title,description] = journeySections[section] || journeySections.overview;
    $('pageTitle').textContent = title;
    document.querySelectorAll('#nav button').forEach(b => {
        b.classList.toggle('active', b.dataset.page === section);
        if (b.dataset.page === section) b.setAttribute('aria-current','page'); else b.removeAttribute('aria-current');
    });
    let body;
    if (section === 'overview') body = journeyOverview();
    else if (section === 'profile' || section === 'verification') body = journeyProfile(section === 'verification');
    else if (section === 'earnings') body = journeyEarnings();
    else if (section === 'flow') body = journeyFlow();
    else {
        let records = jobs;
        if (section === 'requests') records = jobs.filter(j => j.status === 'requested');
        if (section === 'quotes') records = jobs.filter(j => ['requested','quoted'].includes(j.status));
        if (section === 'evidence') records = jobs.filter(j => !['requested','quoted','cancelled'].includes(j.status));
        if (section === 'disputes') records = jobs.filter(j => j.status === 'disputed' || Number(j.current_work_round) > 1);
        let filters = '';
        if (section === 'jobs') {
            if (jobFilter !== 'all') records = records.filter(j => j.status === jobFilter);
            filters = `<div class="tabs" aria-label="Filter jobs">${[['all','All'],...Object.entries(journeyLabels)].map(([value,label]) => `<button data-filter="${value}" class="${jobFilter === value ? 'active' : ''}" aria-pressed="${jobFilter === value}">${label}</button>`).join('')}</div>`;
        }
        body = filters + journeyList(title, records);
        if (section === 'requests') body += `<div class="status-summary"></div>` + notice('Send a quotation to respond to an assigned request. For reassignment, contact the administrator; accept and decline controls are not available yet.');
        if (section === 'disputes') body += `<div class="status-summary"></div>` + notice('Open a job to read dispute details. Only the administrator can decide rework, release or refund.');
    }
    const recordsPage = !['profile','verification','flow'].includes(section);
    $('content').innerHTML = `<div class="head"><div><div class="eyebrow">${eyebrow}</div><h1>${section === 'overview' ? `Welcome, ${esc(user.name)}` : title}</h1><p>${description}</p></div>${button('Refresh','refresh',0)}</div>${recordsPage ? '<p class="muted small">Records, filters and totals reflect the current booking page.</p>' : ''}${body}${recordsPage ? `<div class="actions pager"><button class="btn" data-action="previous" ${page <= 1 ? 'disabled' : ''}>Previous</button><span>Page ${page} of ${last}</span><button class="btn" data-action="next" ${page >= last ? 'disabled' : ''}>Next</button></div>` : ''}`;
}
document.addEventListener('click', event => {
    const sectionButton = event.target.closest('[data-section]');
    if (sectionButton) go(sectionButton.dataset.section);
    const filterButton = event.target.closest('[data-filter]');
    if (filterButton) { jobFilter = filterButton.dataset.filter; render(); }
});
