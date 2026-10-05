const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { resolve } = require('node:path');
const vm = require('node:vm');

async function workspace(role = 'technician') {
    const elements = new Map(), calls = [];
    function element(id) {
        if (!elements.has(id)) {
            const classes = new Set();
            elements.set(id, {innerHTML:'',textContent:'',style:{},dataset:{},isConnected:true,
                setAttribute(){},removeAttribute(){},focus(){},querySelectorAll(){return [];},querySelector(selector){return element(id+selector);},
                classList:{add:c=>classes.add(c),remove:c=>classes.delete(c),contains:c=>classes.has(c),toggle(c){if(classes.has(c)){classes.delete(c);return false;}classes.add(c);return true;}}});
        }
        return elements.get(id);
    }
    const bookings = [
        {id:1,reference:'FX-1',status:'requested',service_category:'Plumbing',description:'<img src=x onerror=alert(1)>',address:'Test street',current_work_round:1},
        {id:2,reference:'FX-2',status:'completed',service_category:'Electrical',description:'Finished',address:'Test street',current_work_round:1,payment:{status:'held',amount_minor:400000,provider:'demo'},settlement_status:'release_pending'},
        {id:3,reference:'FX-3',status:'confirmed',service_category:'Plumbing',description:'Rework',address:'Test street',current_work_round:2},
    ];
    const document = {getElementById:element,querySelectorAll:()=>[],addEventListener(){},activeElement:element('trigger'),body:{style:{}},querySelector(selector){return {content:selector.includes('api-base')?'/api/v1':selector.includes('login-url')?'/technician/login':'/job-verification'};}};
    const context = vm.createContext({document,console,Intl,URL,FormData,Date,
        sessionStorage:{getItem:()=> 'private-test-token',removeItem(){}},
        location:{hash:'',replace(){}},window:{addEventListener(){}},setTimeout:()=>1,clearTimeout(){},
        fetch:async(url,options)=>{calls.push({url,options});return {ok:true,status:200,json:async()=>url.endsWith('/me')?{data:{id:9,name:'Test Technician',email:'test@example.test',role},technician_profile:{trade:'Plumbing',kyc_status:'approved',is_available:1}}:{data:bookings,current_page:1,last_page:2}};},
    });
    for (const name of ['technician-views.js','technician-schedule.js','technician.js']) vm.runInContext(readFileSync(resolve(__dirname,'../../public/js',name),'utf8'),context);
    await new Promise(setImmediate);
    return {context,elements,calls,run:code=>vm.runInContext(code,context)};
}

test('all ten journey sections render and customer input stays escaped',async()=>{
    const app=await workspace();
    for(const section of ['overview','requests','jobs','quotes','evidence','disputes','earnings','verification','profile','flow']) {
        app.run(`go('${section}')`);
        const html=app.elements.get('content').innerHTML;
        assert.match(html,/<h1>/);
        assert.doesNotMatch(html,/<img src=x onerror/);
        assert.doesNotMatch(html,/Simulate customer|Confirm provider|Musa Bello/);
    }
    app.run("go('requests')");
    assert.match(app.elements.get('content').innerHTML,/&lt;img src=x/);
});
test('job filters and rework use persisted states rather than prototype states',async()=>{
    const app=await workspace();
    app.run("jobFilter='completed';go('jobs')");
    assert.match(app.elements.get('content').innerHTML,/FX-2/);
    assert.doesNotMatch(app.elements.get('content').innerHTML,/FX-1/);
    app.run("go('disputes')");
    assert.match(app.elements.get('content').innerHTML,/FX-3/);
    assert.match(app.elements.get('content').innerHTML,/Round 2/);
});
test('completed held payments do not count as released earnings',async()=>{
    const app=await workspace();
    assert.match(app.elements.get('content').innerHTML,/Recorded releases<\/span><strong>₦0\.00/);
    app.run("go('earnings')");
    assert.match(app.elements.get('content').innerHTML,/release pending/);
    assert.match(app.elements.get('content').innerHTML,/>held</);
});
test('wrong-role accounts never load technician bookings',async()=>{
    const app=await workspace('customer');
    assert.equal(app.calls.length,1);
    assert.match(app.elements.get('content').innerHTML,/technician account/);
    app.run("go('jobs')");
    assert.match(app.elements.get('content').innerHTML,/technician account/);
});
test('start and completion requests retain the server work-round guard',async()=>{
    const app=await workspace();
    app.run("globalThis.requests=[];api=async(path,method,body)=>{requests.push({path,method,body});return {data:{current_work_round:3}}};load=async()=>{};");
    await app.run("act('start',7)");
    await app.run("act('submit',7)");
    const writes=JSON.parse(app.run('JSON.stringify(requests.filter(r=>r.method))'));
    assert.deepEqual(writes,[{path:'/bookings/7/start',method:'POST',body:{expected_work_round:3}},{path:'/bookings/7/evidence/submit',method:'POST',body:{expected_work_round:3}}]);
});
test('quotation form converts naira to minor units and posts to the real API',async()=>{
    const app=await workspace();
    app.run('globalThis.writes=[];form=(title,html,submit)=>{globalThis.submitAction=submit;};api=async(path,method,body)=>writes.push({path,method,body});');
    await app.run("act('quote',1)");
    await app.run("globalThis.quoteData=new FormData();for(const [key,value] of [['item_price','1250.50'],['item_quantity','2'],['item_kind','labour'],['item_description','Repair fitting'],['diagnosis','The pipe joint is cracked'],['scope','Replace the damaged fitting'],['exclusions','None'],['duration_minutes','60'],['expires_at','2030-01-01T12:00']])quoteData.append(key,value);submitAction(quoteData)");
    const write=JSON.parse(app.run('JSON.stringify(writes[0])'));
    assert.equal(write.path,'/bookings/1/quotation');
    assert.equal(write.body.items[0].unit_price_minor,125050);
    assert.equal(write.body.items[0].quantity,2);
    assert.equal(write.body.duration_minutes,60);
    assert.equal(write.body.diagnosis,'The pipe joint is cracked');
    assert.equal(write.body.currency,'NGN');
});

test('calendar month boundaries and timezone date conversion are stable',async()=>{
    const app=await workspace();
    assert.equal(app.run("JSON.stringify(calendarRange('2030-12-15','month'))"),'["2030-12-01","2031-01-01"]');
    app.run("calendarData={timezone:'Africa/Lagos'}");
    assert.equal(app.run("scheduleDate('2030-01-07T23:30:00Z')"),'2030-01-08');
});
test('failed pagination leaves the current page and records intact',async()=>{
    const app=await workspace();
    const before=app.elements.get('content').innerHTML;
    app.run("api=async()=>{throw new Error('Network unavailable')}");
    await assert.rejects(app.run("act('next')"),/Network unavailable/);
    assert.equal(app.run('page'),1);
    assert.equal(app.elements.get('content').innerHTML,before);
});

test('profile save displays server errors and retains the form for retry',async()=>{
    const app=await workspace();
    app.run(`FormData=class {constructor(){return new Map([['trade','Electrical'],['service_location','Lagos'],['bio','Short'],['skills','Repairs'],['years_experience','2'],['indicative_price_minor',''],['is_available','0'],['availability_notes','']]);}};
        api=async(path)=>{if(path==='/technicians')return {categories:['Electrical'],areas:['Lagos']};throw new Error('The bio field must be at least 20 characters.');};`);
    await app.run('openProfileEditor()');
    await app.elements.get('profileEditorForm').onsubmit({preventDefault(){}});
    assert.match(app.elements.get('profileSaveError').textContent,/at least 20 characters/);
    assert.equal(app.elements.get('profileEditorForm[type="submit"]').disabled,false);
    assert.equal(app.elements.get('overlay').classList.contains('open'),true);
});

test('profile save posts form fields and uses the persisted response',async()=>{
    const app=await workspace();
    app.run(`FormData=class {constructor(){return new Map([['trade','Electrical'],['service_location','Lagos'],['bio','Experienced electrical technician'],['skills','Repairs'],['years_experience','2'],['indicative_price_minor',''],['is_available','0'],['availability_notes','']]);}};
        globalThis.savedRequest=null;
        api=async(path,method,data)=>{if(path==='/technicians')return {categories:['Electrical'],areas:['Lagos']};savedRequest={path,method,data};return {data:{...data,user_id:9,kyc_status:'pending'}};};`);
    await app.run('openProfileEditor()');
    await app.elements.get('profileEditorForm').onsubmit({preventDefault(){}});
    const request=JSON.parse(app.run('JSON.stringify(savedRequest)'));
    assert.equal(request.path,'/technician/profile');
    assert.equal(request.method,'PUT');
    assert.equal(request.data.is_available,false);
    assert.equal(request.data.indicative_price_minor,null);
    assert.equal(request.data.availability_notes,null);
    assert.equal(app.run('profile.user_id'),9);
    assert.equal(app.elements.get('topTrade').textContent,'Electrical');
    assert.equal(app.elements.get('overlay').classList.contains('open'),false);
    assert.equal(app.elements.get('toast').textContent,'Profile saved.');
});
