import { test } from 'node:test';
import assert from 'node:assert/strict';
import { spawn, spawnSync } from 'node:child_process';
import { mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { resolve, join, dirname, sep } from 'node:path';
import { fileURLToPath } from 'node:url';
import { setTimeout as delay } from 'node:timers/promises';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
class Cdp {
    constructor(socket) {
        this.socket = socket; this.sequence = 0; this.pending = new Map(); this.errors = [];
        socket.addEventListener('message', event => {
            const m = JSON.parse(event.data);
            if (m.id) { const p = this.pending.get(m.id); this.pending.delete(m.id); if (m.error) p?.reject(new Error(m.error.message)); else p?.resolve(m.result); }
            if (m.method === 'Runtime.exceptionThrown') this.errors.push(m.params.exceptionDetails.exception?.description || m.params.exceptionDetails.text);
            if (m.method === 'Page.javascriptDialogOpening') void this.send('Page.handleJavaScriptDialog', { accept: true }, m.sessionId);
        });
    }
    send(method, params = {}, sessionId) {
        const id = ++this.sequence;
        return new Promise((resolvePromise, reject) => { this.pending.set(id, { resolve: resolvePromise, reject }); this.socket.send(JSON.stringify({ id, method, params, sessionId })); });
    }
    async run(session, fn, ...args) {
        const expression = '(' + fn.toString() + ')(' + args.map(value => JSON.stringify(value)).join(',') + ')';
        const r = await this.send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true, userGesture: true }, session);
        if (r.exceptionDetails) throw new Error(r.exceptionDetails.exception?.description || r.exceptionDetails.text);
        return r.result.value;
    }
    async until(session, fn, ...args) {
        for (let i=0;i<150;i++) { try { if (await this.run(session,fn,...args)) return; } catch {} await delay(80); }
        throw new Error('Timeout: '+fn.toString()+' '+JSON.stringify(await this.run(session,()=>({url:location.href,text:document.body.innerText.slice(-1000)}))));
    }
    async page() {
        const { browserContextId } = await this.send('Target.createBrowserContext');
        const { targetId } = await this.send('Target.createTarget', { url:'about:blank', browserContextId });
        const { sessionId } = await this.send('Target.attachToTarget', { targetId, flatten:true });
        await this.send('Page.enable',{},sessionId); await this.send('Runtime.enable',{},sessionId);
        await this.send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]},sessionId);
        return sessionId;
    }
    async navigate(session,url) {
        await this.send('Page.navigate',{url},session);
        await this.until(session,()=>document.readyState==='complete' && location.href!=='about:blank');
    }
}

test('Isolated private Chrome: asynchronous forms, PICs and public examiner links', { timeout:120000 }, async () => {
    const directory = mkdtempSync(join(tmpdir(),'hc-fit-proper-browser-'));
    const database = join(directory,'browser.sqlite'); writeFileSync(database,'');
    const port=18879, debugPort=18880, base='http://127.0.0.1:'+port;
    const env={...process.env,APP_ENV:'local',APP_URL:base,DB_URL:'',DB_CONNECTION:'sqlite',DB_DATABASE:database,SESSION_DRIVER:'database',SESSION_SECURE_COOKIE:'false',CACHE_STORE:'array',MAIL_MAILER:'array',QUEUE_CONNECTION:'sync',BCRYPT_ROUNDS:'4',FP_BROWSER_ROOT:root,FP_BROWSER_NO_HOT:join(directory,'unused-hot')};
    let server,chrome,cdp;
    try {
        const migration=spawnSync('php',['artisan','migrate','--no-interaction'],{cwd:root,env,encoding:'utf8',windowsHide:true});
        assert.equal(migration.status,0,migration.stdout+migration.stderr);
        const php = [
            '<?php', "require 'vendor/autoload.php';", "$app = require 'bootstrap/app.php';",
            "$app->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();",
            "if (config('database.default') !== 'sqlite' || \\Illuminate\\Support\\Facades\\DB::connection()->getDatabaseName() !== getenv('DB_DATABASE')) { throw new RuntimeException('Unsafe fixture database'); }",
            "$admin=\\App\\Models\\User::factory()->create(['name'=>'Admin Browser Fiktif','email'=>'browser-admin@example.test','password'=>'password','role'=>'Admin','must_change_password'=>false]);",
            "$one=\\App\\Models\\User::factory()->create(['name'=>'PIC Browser Satu','email'=>'pic-one@example.test','role'=>'Staff']);",
            "$two=\\App\\Models\\User::factory()->create(['name'=>'PIC Browser Dua','email'=>'pic-two@example.test','role'=>'Staff']);",
            "$service=app(\\App\\Services\\FitProperService::class);",
            "$activity=$service->create(['name'=>'SIMULASI BROWSER','target_position'=>'Kepala Fiktif','scheduled_at'=>'2026-10-19','is_simulation'=>true],$admin);",
            "$service->assignPics($activity,[$admin->id],$admin);",
            "foreach (['Kandidat Browser Alfa','Kandidat Browser Beta'] as $name) { $service->participant($activity,['name'=>$name,'kpi_year_1'=>4,'kpi_year_2'=>4,'assessment'=>3]); }",
            "echo json_encode(['activity'=>$activity->id,'first'=>$one->id,'second'=>$two->id]);",
        ].join('\n');
        const fixture=spawnSync('php',[],{cwd:root,env,encoding:'utf8',windowsHide:true,input:php});
        assert.equal(fixture.status,0,fixture.stdout+fixture.stderr);
        const ids=JSON.parse(fixture.stdout);
        const router=join(directory,'router.php');
        writeFileSync(router,[
            '<?php',
            "$uri=urldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH) ?? '/');",
            "if ($uri !== '/' && is_file(getcwd().$uri)) { return false; }",
            "require getenv('FP_BROWSER_ROOT').'/vendor/autoload.php';",
            "$app=require getenv('FP_BROWSER_ROOT').'/bootstrap/app.php';",
            "$app->afterResolving(\\Illuminate\\Foundation\\Vite::class, function ($vite) { $vite->useHotFile(getenv('FP_BROWSER_NO_HOT')); });",
            "$app->handleRequest(\\Illuminate\\Http\\Request::capture());",
        ].join('\n'));
        server=spawn('php',['-S','127.0.0.1:'+port,router],{cwd:join(root,'public'),env,windowsHide:true,stdio:'ignore'});
        chrome=spawn(process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--disable-extensions','--remote-debugging-port='+debugPort,'--user-data-dir='+join(directory,'chrome')],{windowsHide:true,stdio:'ignore'});
        let version;
        for(let i=0;i<100;i++){try{version=await(await fetch('http://127.0.0.1:'+debugPort+'/json/version')).json();if(version.webSocketDebuggerUrl)break;}catch{}await delay(100);}
        assert.ok(version?.webSocketDebuggerUrl,'Chrome CDP unavailable');
        const socket=new WebSocket(version.webSocketDebuggerUrl);
        await new Promise((resolvePromise,reject)=>{socket.addEventListener('open',resolvePromise,{once:true});socket.addEventListener('error',reject,{once:true});});
        cdp=new Cdp(socket);
        const admin=await cdp.page();
        await cdp.navigate(admin,base+'/fit-proper/'+ids.activity);
        await cdp.until(admin,()=>!!document.querySelector('input[name=email]'));
        await cdp.run(admin,()=>{document.querySelector('input[name=email]').value='browser-admin@example.test';document.querySelector('input[name=password]').value='password';document.querySelector('form').requestSubmit();});
        await cdp.until(admin,()=>location.pathname!=='/login');
        await cdp.navigate(admin,base+'/fit-proper/'+ids.activity+'#pic');
        await cdp.until(admin,()=>!!window.Alpine && !!document.querySelector('[data-fp-root]'));
        await cdp.run(admin,async()=>{await document.fonts.ready;await Promise.all(document.getAnimations().filter(animation=>animation.effect?.getTiming().iterations!==Infinity).map(animation=>animation.finished.catch(()=>{})));});
        for (const width of [1440, 1280, 768, 390]) {
            await cdp.send('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: false }, admin);
            const layout = await cdp.run(admin, async () => {
                await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
                const root = document.querySelector('.fp-detail');
                const histories = [...root.querySelectorAll('.fp-history')];
                const first = histories[0];
                const closed = histories.every(item => !item.open);
                first.querySelector('summary').click();
                const opened = first.open && first.querySelector('.fp-history-content').getBoundingClientRect().height > 0;
                first.querySelector('summary').click();
                return {
                    overflow: document.documentElement.scrollWidth > innerWidth + 1,
                    closed, opened, reclosed: !first.open,
                    metrics: root.querySelectorAll('.fp-detail-metrics > div').length,
                    labels: /Data Simulasi|seluruh identitas dan hasil adalah fiktif|penguji tautan \/ demo/i.test(root.innerText),
                };
            });
            assert.equal(layout.overflow, false, `Page overflow at ${width}px`);
            assert.equal(layout.closed && layout.opened && layout.reclosed, true, `History accordion at ${width}px`);
            assert.equal(layout.metrics, 4);
            assert.equal(layout.labels, false);
        }
        await cdp.send('Emulation.clearDeviceMetricsOverride', {}, admin);
        await cdp.run(admin, () => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
        await cdp.run(admin,()=>{window.fpSentinel=42;});
        for(const id of [ids.first,ids.second]){
            await cdp.run(admin,id=>{const f=document.querySelector('form[action$="/pics"]');f.querySelector('input[value="'+id+'"]').checked=true;f.scrollIntoView({block:'center',behavior:'instant'});return f.getBoundingClientRect().top;},id);
            const before=await cdp.run(admin,()=>{const form=document.querySelector('form[action$="/pics"]');const top=form.getBoundingClientRect().top;form.requestSubmit();return top;});
            await cdp.until(admin,id=>!!document.querySelector('[data-fp-pic-id="'+id+'"]') && document.querySelector('[data-fp-root]').dataset.fpBusy!=='true',id);
            const state=await cdp.run(admin,()=>({sentinel:window.fpSentinel,hash:location.hash,top:document.querySelector('form[action$="/pics"]').getBoundingClientRect().top}));
            assert.equal(state.sentinel,42);assert.equal(state.hash,'#pic');
            assert.ok(Math.abs(state.top-before)<5,'PIC form scroll preserved: '+JSON.stringify({before,...state}));
        }
        assert.equal(await cdp.run(admin,()=>document.querySelectorAll('[data-fp-pic-id]').length),3);
        await cdp.run(admin,id=>document.querySelector('[data-fp-pic-id="'+id+'"] form').requestSubmit(),ids.first);
        await cdp.until(admin,()=>document.querySelector('.swal2-popup')?.classList.contains('swal2-modal'));
        await cdp.run(admin,()=>document.querySelector('.swal2-confirm').click());
        await cdp.until(admin,id=>!document.querySelector('[data-fp-pic-id="'+id+'"]') && document.querySelectorAll('[data-fp-pic-id]').length===2,ids.first);
        await cdp.run(admin,()=>{const f=document.querySelector('form[action$="/examiners"]');f.querySelector('[name=name]').value='Penguji Browser Manual';f.scrollIntoView({block:'center',behavior:'instant'});return f.getBoundingClientRect().top;});
        const before=await cdp.run(admin,()=>{const form=document.querySelector('form[action$="/examiners"]');const top=form.getBoundingClientRect().top;form.requestSubmit();return top;});
        await cdp.until(admin,()=>!!document.querySelector('[data-fp-examiner-id] [data-fp-link-panel] input') && document.querySelector('[data-fp-root]').dataset.fpBusy!=='true');
        assert.equal(await cdp.run(admin,()=>window.fpSentinel),42);
        const after=await cdp.run(admin,()=>document.querySelector('form[action$="/examiners"]').getBoundingClientRect().top);
        assert.ok(Math.abs(after-before)<5,'Examiner scroll preserved: '+JSON.stringify({before,after}));
        const link=await cdp.run(admin,()=>document.querySelector('[data-fp-examiner-id] [data-fp-link-panel] input').value);
        assert.ok(link.startsWith(base+'/fit-proper-link/'));
        await cdp.send('Page.bringToFront',{},admin);
        await cdp.run(admin,()=>document.querySelector('[data-fp-copy]').click());
        await cdp.until(admin,()=>document.body.innerText.includes('Tautan berhasil disalin'));
        const guest=await cdp.page();
        await cdp.navigate(guest,link);
        await cdp.until(guest,()=>document.body.innerText.includes('Kegiatan belum dibuka'));
        await cdp.run(admin,()=>{const d=document.querySelector('#ringkasan details');d.open=true;const f=d.querySelector('form');f.noValidate=true;f.querySelector('[name=name]').value='';f.requestSubmit();});
        await cdp.until(admin,()=>!!document.querySelector('#ringkasan [name=name][aria-invalid=true]'));
        assert.equal(await cdp.run(admin,()=>window.fpSentinel),42);
        await cdp.run(admin,()=>{document.querySelector('#ringkasan [name=name]').value='Nama Sesudah Async';document.querySelector('#ringkasan details form').requestSubmit();});
        await cdp.until(admin,()=>document.querySelector('[data-fp-fragment=heading]').innerText.includes('Nama Sesudah Async') && document.querySelector('[data-fp-root]').dataset.fpBusy!=='true');
        await cdp.send('Network.enable',{},admin);
        await cdp.send('Network.emulateNetworkConditions',{offline:true,latency:0,downloadThroughput:0,uploadThroughput:0},admin);
        await cdp.run(admin,()=>{document.querySelector('#ringkasan [name=description]').value='Input jaringan dipertahankan';document.querySelector('#ringkasan details form').requestSubmit();});
        await cdp.until(admin,()=>document.querySelector('#ringkasan details form').innerText.includes('Tidak dapat menyimpan'));
        assert.equal(await cdp.run(admin,()=>document.querySelector('#ringkasan [name=description]').value),'Input jaringan dipertahankan');
        await cdp.send('Network.emulateNetworkConditions',{offline:false,latency:0,downloadThroughput:-1,uploadThroughput:-1},admin);
        await cdp.run(admin,()=>document.querySelector('form[action$="/transition"]').requestSubmit());
        await cdp.until(admin,()=>document.querySelector('.swal2-popup')?.classList.contains('swal2-modal'));
        await cdp.run(admin,()=>document.querySelector('.swal2-confirm').click());
        await cdp.until(admin,()=>document.querySelector('[data-fp-fragment=summary]')?.innerText.includes('Berjalan') && !document.querySelector('#ringkasan details'));
        assert.equal(await cdp.run(admin,()=>window.fpSentinel),42);
        assert.equal(await cdp.run(admin,()=>document.querySelector('[data-fp-fragment=examiner-list]').innerText.includes('Penilaian sudah dibuka')),true);
        await cdp.navigate(guest,link);
        await cdp.until(guest,()=>document.body.innerText.includes('Kandidat Browser Alfa') && !!window.Alpine);
        assert.equal(await cdp.run(guest,()=>document.querySelector('[data-app-shell]')===null),true);
        assert.equal(await cdp.run(guest,()=>document.querySelectorAll('input[type=number]').length),20);
        assert.equal(await cdp.run(guest,()=>Array.from(document.querySelectorAll('input[type=number]')).every(input=>input.value==='' && input.getBoundingClientRect().height>0)),true);
        assert.equal(await cdp.run(guest,()=>document.querySelector('[data-fp-candidates]').clientWidth<=innerWidth),true);
        await cdp.run(guest,()=>{window.fpDraftSentinel=84;const fields=document.querySelectorAll('input[type=number]');fields[0].value='0';fields[0].dispatchEvent(new Event('input',{bubbles:true}));fields[1].value='2.5';fields[1].dispatchEvent(new Event('input',{bubbles:true}));document.querySelectorAll('textarea')[0].value='Rekomendasi browser';document.querySelectorAll('textarea')[1].value='Catatan draft browser';Array.from(document.querySelectorAll('button')).find(button=>button.textContent.includes('Simpan Draft')).click();});
        await cdp.until(guest,()=>document.querySelector('[data-fp-assessment-state]').innerText.includes('Draft penilaian disimpan') && document.querySelector('[data-fp-root]').dataset.fpBusy!=='true');
        assert.equal(await cdp.run(guest,()=>window.fpDraftSentinel),84);
        await cdp.send('Page.navigate',{url:'about:blank'},guest);
        await cdp.until(guest,()=>location.href==='about:blank');
        await cdp.navigate(guest,link);
        await cdp.until(guest,()=>!!document.querySelector('[data-fp-candidates]') && !!window.Alpine);
        assert.deepEqual(await cdp.run(guest,()=>Array.from(document.querySelectorAll('input[type=number]')).slice(0,3).map(input=>input.value)),['0','2.5','']);
        assert.deepEqual(await cdp.run(guest,()=>Array.from(document.querySelectorAll('textarea')).slice(0,2).map(input=>input.value)),['Rekomendasi browser','Catatan draft browser']);
        await cdp.run(guest,()=>{document.querySelectorAll('input[type=number]').forEach(input=>{input.value='4';input.dispatchEvent(new Event('input',{bubbles:true}));});Array.from(document.querySelectorAll('button')).find(b=>b.textContent.includes('Simpan Draft')).click();});
        await cdp.until(guest,()=>document.body.innerText.includes('Penilaian disimpan'));
        assert.equal(await cdp.run(guest,()=>Array.from(document.querySelectorAll('input[type=number]')).every(i=>i.value==='4')),true);
        await cdp.run(guest,()=>Array.from(document.querySelectorAll('button')).find(b=>b.textContent.includes('Selesaikan Penilaian')).click());
        await cdp.until(guest,()=>document.body.innerText.includes('Penilaian telah dikirim dan terkunci'));
        await cdp.run(admin,()=>document.querySelector('[data-fp-examiner-id] form[data-fp-confirm]').requestSubmit());
        await cdp.until(admin,()=>document.querySelector('.swal2-popup')?.classList.contains('swal2-modal'));
        await cdp.run(admin,()=>document.querySelector('.swal2-confirm').click());
        await cdp.until(admin,old=>{const input=document.querySelector('[data-fp-examiner-id] [data-fp-link-panel] input');return !!input && input.value!==old && document.querySelector('[data-fp-root]').dataset.fpBusy!=='true';},link);
        const renewed=await cdp.run(admin,()=>document.querySelector('[data-fp-examiner-id] [data-fp-link-panel] input').value);
        assert.notEqual(renewed,link);
        await cdp.navigate(guest,link);await cdp.until(guest,()=>document.body.innerText.includes('tidak valid atau telah diganti'));
        await cdp.navigate(guest,renewed);await cdp.until(guest,()=>document.body.innerText.includes('Penilaian telah dikirim dan terkunci'));
        assert.equal(await cdp.run(admin,()=>!!document.querySelector('[data-app-shell]')),true);
        await cdp.run(admin,()=>document.querySelector('form[action$="/transition"] input[value="Selesai"]').form.requestSubmit());
        await cdp.until(admin,()=>document.querySelector('.swal2-popup')?.classList.contains('swal2-modal'));
        await cdp.run(admin,()=>document.querySelector('.swal2-confirm').click());
        await cdp.until(admin,()=>document.querySelector('[data-fp-fragment=examiner-list]').innerText.includes('Seluruh penilaian terkunci'));
        await cdp.navigate(guest,renewed);
        await cdp.until(guest,()=>document.body.innerText.includes('Kegiatan sudah selesai'));
        assert.deepEqual(cdp.errors,[],'Browser JavaScript errors');
        console.log('Verified: private guest, unchanged admin session, no reload, scroll/hash, PIC add/remove, copy URL, validation/network input retention, draft/final, reissue.');
    } finally {
        if(cdp){try{await cdp.send('Browser.close');}catch{}cdp.socket.close();}
        server?.kill();chrome?.kill();await delay(500);
        const absolute=resolve(directory);
        assert.ok(absolute.startsWith(resolve(tmpdir())+sep) && absolute.includes('hc-fit-proper-browser-'));
        rmSync(absolute,{recursive:true,force:true,maxRetries:5,retryDelay:200});
    }
});

