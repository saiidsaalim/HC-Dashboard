import { test } from 'node:test';
import assert from 'node:assert/strict';
import { spawn, spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
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
        const marker = 'fit-proper-navigation-' + this.sequence;
        await this.run(session, value => { window.fpBrowserNavigationMarker = value; }, marker);
        const navigation = await this.send('Page.navigate',{url},session);
        if (navigation.errorText) throw new Error(navigation.errorText);
        await this.until(session, (previous, requested, newDocument) => document.readyState === 'complete'
            && location.href !== 'about:blank'
            && (newDocument ? window.fpBrowserNavigationMarker !== previous : location.href === requested),
        marker, url, !!navigation.loaderId);
    }
}

test('Isolated private Chrome: viewport layout, asynchronous forms, PICs and public examiner links', { timeout:240000 }, async () => {
    const directory = mkdtempSync(join(tmpdir(),'hc-fit-proper-browser-'));
    const database = join(directory,'browser.sqlite'); writeFileSync(database,'');
    const compiledViews = join(directory, 'views'); mkdirSync(compiledViews);
    const port=18879, debugPort=18880, base='http://127.0.0.1:'+port;
    const env={...process.env,APP_ENV:'testing',APP_DEBUG:'false',APP_URL:base,APP_CONFIG_CACHE:join(directory,'config.php'),APP_ROUTES_CACHE:join(directory,'routes.php'),VIEW_COMPILED_PATH:compiledViews,DB_URL:'',DB_CONNECTION:'sqlite',DB_DATABASE:database,SESSION_DRIVER:'database',SESSION_SECURE_COOKIE:'false',CACHE_STORE:'array',MAIL_MAILER:'array',QUEUE_CONNECTION:'sync',BCRYPT_ROUNDS:'4',FP_BROWSER_ROOT:root,FP_BROWSER_NO_HOT:join(directory,'unused-hot')};
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
            "$activity=$service->create(['name'=>'UJI BROWSER','target_position'=>'Kepala Fiktif','scheduled_at'=>'2026-10-19'],$admin);",
            "$service->assignPics($activity,[$admin->id],$admin);",
            "foreach (['Kandidat Browser Alfa','Kandidat Browser Beta'] as $name) { $service->participant($activity,['name'=>$name,'kpi_year_1'=>4,'kpi_year_2'=>4,'assessment'=>3]); }",
            "$activity->forceFill(['created_at'=>'2026-11-07 09:00:00'])->save();",
            '$layoutActivities = [];',
            "$longName = 'Kegiatan Tampilan 21 dengan nama panjang untuk memastikan ringkasan tetap terbaca dan tombol pengelolaan tetap dapat digunakan pada layar desktop yang lebih pendek';",
            'foreach (range(1, 21) as $number) {',
            "    $name = $number === 21 ? $longName : sprintf('Kegiatan Tampilan %02d', $number);",
            "    $layoutActivity = $service->create(['name'=>$name,'target_position'=>'Posisi Tampilan '.$number,'scheduled_at'=>'2026-10-19'],$admin);",
            "    $layoutActivity->forceFill(['created_at'=>sprintf('2026-11-06 08:%02d:00', $number)])->save();",
            '    $layoutActivities[$number] = $layoutActivity->id;',
            '}',
            "$running = $service->create(['name'=>'Kegiatan Berjalan Tampilan','target_position'=>'Posisi Berjalan','scheduled_at'=>'2026-10-19'],$admin);",
            '$service->assignPics($running, [$admin->id], $admin);',
            "$service->participant($running, ['name'=>'Kandidat Tampilan Berjalan']);",
            "$service->examiner($running, ['name'=>'Penguji Tampilan Berjalan']);",
            "$service->transition($running, 'Berjalan', $admin);",
            "$running->forceFill(['created_at'=>'2026-11-06 09:00:00'])->save();",
            "echo json_encode(['activity'=>$activity->id,'first'=>$one->id,'second'=>$two->id,'layout'=>$layoutActivities,'running'=>$running->id,'long_name'=>$longName]);",
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
        const screenshotDirectory = join(root, 'storage/framework/testing/fit-proper-ui');
        mkdirSync(screenshotDirectory, { recursive: true });
        async function screenshot(session, name) {
            const { data } = await cdp.send('Page.captureScreenshot', { format:'png' }, session);
            writeFileSync(join(screenshotDirectory, name+'.png'), Buffer.from(data, 'base64'));
        }
        const admin=await cdp.page();
        await cdp.navigate(admin,base+'/fit-proper/'+ids.activity);
        await cdp.until(admin,()=>!!document.querySelector('input[name=email]'));
        await cdp.run(admin,()=>{document.querySelector('input[name=email]').value='browser-admin@example.test';document.querySelector('input[name=password]').value='password';document.querySelector('form').requestSubmit();});
        await cdp.until(admin,()=>location.pathname!=='/login');

        async function visitIndex(parameters = {}) {
            const query = new URLSearchParams(parameters).toString();
            const requestedUrl = base + '/fit-proper' + (query ? '?' + query : '');
            await cdp.navigate(admin, requestedUrl);
            await cdp.until(admin, requested => location.href === requested && document.readyState === 'complete'
                && !!window.Alpine && !!document.querySelector('[data-fp-index]')
                && !document.querySelector('[data-app-shell] aside[x-cloak]')
                && document.querySelectorAll('[data-fp-index-summary] [x-text="stage.label"]').length === 4, requestedUrl);
        }

        async function indexTheme(preference) {
            await cdp.run(admin, () => document.querySelector('button[aria-label="Pilih tema tampilan"]').click());
            await cdp.until(admin, selected => document.querySelector(`[data-theme-option="${selected}"]`)?.getBoundingClientRect().height > 0, preference);
            await cdp.run(admin, selected => document.querySelector(`[data-theme-option="${selected}"]`).click(), preference);
            await cdp.until(admin, selected => document.documentElement.dataset.theme === selected
                && document.documentElement.classList.contains('dark') === (selected === 'dark')
                && document.querySelector(`[data-theme-option="${selected}"]`)?.getBoundingClientRect().height === 0, preference);
        }

        async function assertIndexViewport(width, height, expectedRows = 20, includeStages = true, name = 'index-viewport') {
            await cdp.send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: false }, admin);
            await cdp.until(admin, (expectedWidth, expectedHeight) => innerWidth === expectedWidth && innerHeight === expectedHeight
                && document.readyState === 'complete' && !!window.Alpine
                && !document.querySelector('[data-app-shell] aside[x-cloak]')
                && document.querySelectorAll('[data-fp-index-summary] [x-text="stage.label"]').length === 4, width, height);
            await cdp.run(admin, async () => {
                await document.fonts.ready;
                await Promise.all(document.getAnimations()
                    .filter(animation => animation.effect?.getTiming().iterations !== Infinity)
                    .map(animation => animation.finished.catch(() => {})));
            });
            await cdp.send('Page.getLayoutMetrics', {}, admin);
            await screenshot(admin, `${name}-${width}x${height}`);
            let previousGeometry;
            let stableFrames = 0;
            for (let frame = 0; frame < 30 && stableFrames < 3; frame++) {
                await cdp.send('Page.getLayoutMetrics', {}, admin);
                const geometry = await cdp.run(admin, async () => {
                    await new Promise(resolvePromise => requestAnimationFrame(resolvePromise));
                    return JSON.stringify([
                        innerWidth, innerHeight, document.documentElement.scrollHeight, document.body.scrollHeight,
                        ...['#main-content', '[data-fp-index]', '[data-fp-index-stats]', '[data-fp-index-footer]', '[data-fp-index-summary]'].map(selector => {
                            const rectangle = document.querySelector(selector).getBoundingClientRect();
                            return [rectangle.x, rectangle.y, rectangle.width, rectangle.height].map(value => Math.round(value * 100) / 100);
                        }),
                    ]);
                });
                stableFrames = geometry === previousGeometry ? stableFrames + 1 : 0;
                previousGeometry = geometry;
            }
            assert.equal(stableFrames, 3, 'The browser must finish laying out the requested viewport before measurements.');
            const layout = await cdp.run(admin, async showStages => {
                await document.fonts.ready;
                window.scrollTo({ top: 0, behavior: 'instant' });
                const root = document.querySelector('[data-fp-index]');
                const list = root.querySelector('[aria-label="Daftar kegiatan Fit & Proper"]');
                list.scrollTop = 0;
                await new Promise(resolvePromise => requestAnimationFrame(() => requestAnimationFrame(resolvePromise)));
                const summary = root.querySelector('[data-fp-index-summary]');
                const visible = element => element && element.getBoundingClientRect().height > 0;
                const bounds = (label, element) => {
                    if (!element) return { label, missing: true };
                    const rectangle = element.getBoundingClientRect();
                    return { label, top: rectangle.top, bottom: rectangle.bottom, left: rectangle.left, right: rectangle.right, width: rectangle.width, height: rectangle.height };
                };
                const action = [...root.querySelectorAll('[data-fp-index-stats] button')].find(button => button.textContent.includes('Tambah Kegiatan'));
                const controls = [
                    bounds('Tambah Kegiatan', action),
                    ...[...root.querySelectorAll('[data-fp-index-stats] article')].map((card, index) => bounds(`stat ${index + 1}`, card)),
                    bounds('search', root.querySelector('input[name="q"]')),
                    ...[...root.querySelectorAll('nav[aria-label="Filter status kegiatan"] a')].map(link => bounds(`status ${link.textContent.trim()}`, link)),
                    bounds('pagination footer', root.querySelector('[data-fp-index-footer]')),
                    bounds('per page', root.querySelector('#fit-proper-per-page')),
                    bounds('pagination', root.querySelector('[data-fp-index-footer] nav[role="navigation"]')),
                    bounds('summary', summary),
                    bounds('summary heading', summary.querySelector('#activity-summary-heading')),
                    ...[...summary.querySelectorAll('button, a')].filter(visible).map(element => bounds(`summary action ${element.textContent.trim()}`, element)),
                ];
                if (showStages) {
                    controls.push(...[...summary.querySelectorAll('[x-text="stage.label"]')].map(element => bounds(`stage ${element.textContent.trim()}`, element)));
                }
                const cards = [...list.querySelectorAll('[data-fp-activity-id]')];
                const initial = {
                    height: innerHeight, width: innerWidth,
                    documentHeight: document.documentElement.scrollHeight,
                    bodyHeight: document.body.scrollHeight,
                    documentWidth: document.documentElement.scrollWidth,
                    stats: root.querySelectorAll('[data-fp-index-stats] article').length,
                    rows: cards.length,
                    cardsHaveLayout: cards.every(card => card.getBoundingClientRect().height > 0),
                    controls,
                };
                list.scrollTop = list.scrollHeight;
                await new Promise(resolvePromise => requestAnimationFrame(() => requestAnimationFrame(resolvePromise)));
                const listBounds = list.getBoundingClientRect();
                const lastBounds = cards.at(-1).getBoundingClientRect();
                const scrolling = {
                    clientHeight: list.clientHeight, scrollHeight: list.scrollHeight, scrollTop: list.scrollTop,
                    lastVisible: lastBounds.bottom <= listBounds.bottom + 1 && lastBounds.bottom > listBounds.top,
                    documentScroll: window.scrollY,
                };
                list.scrollTop = 0;
                return { ...initial, scrolling };
            }, includeStages);
            await screenshot(admin, `${name}-${width}x${height}`);
            assert.ok(layout.documentHeight <= height + 1 && layout.bodyHeight <= height + 1,
                `Fit & Proper must fit vertically at ${width}x${height}: ${JSON.stringify(layout)}`);
            assert.ok(layout.documentWidth <= width + 1, `Fit & Proper horizontal overflow at ${width}x${height}`);
            assert.equal(layout.stats, 3);
            assert.equal(layout.rows, expectedRows, 'Viewport styling must retain every paginated activity.');
            assert.equal(layout.cardsHaveLayout, true, 'Activities must remain rendered inside their scroll container.');
            for (const control of layout.controls) {
                assert.ok(!control.missing && control.width > 0 && control.height > 0
                    && control.top >= -1 && control.bottom <= height + 1
                    && control.left >= -1 && control.right <= width + 1,
                `Control must remain visible at ${width}x${height}: ${JSON.stringify(control)}`);
            }
            assert.ok(layout.scrolling.scrollHeight > layout.scrolling.clientHeight && layout.scrolling.scrollTop > 0,
                `The activity list must keep its own scrolling at ${width}x${height}: ${JSON.stringify(layout.scrolling)}`);
            assert.equal(layout.scrolling.lastVisible, true, 'The last rendered activity must remain reachable.');
            assert.equal(layout.scrolling.documentScroll, 0, 'Scrolling activities must leave the page fixed.');
        }

        async function assertIndexDialog(kind) {
            await cdp.run(admin, selected => {
                const root = document.querySelector('[data-fp-index]');
                const button = selected === 'create'
                    ? [...root.querySelectorAll('[data-fp-index-stats] button')].find(element => element.textContent.includes('Tambah Kegiatan'))
                    : [...root.querySelectorAll('[data-fp-index-summary] button')].find(element => element.textContent.includes('Lihat Standar Penilaian'));
                button.click();
            }, kind);
            const dialogId = kind === 'create' ? 'fit-proper-create-title' : 'fit-proper-settings-title';
            await cdp.until(admin, label => document.querySelector(`[aria-labelledby="${label}"]`)?.getBoundingClientRect().height > 0, dialogId);
            const dialog = await cdp.run(admin, async label => {
                const element = document.querySelector(`[aria-labelledby="${label}"]`);
                const form = element.querySelector('form');
                form.scrollTop = form.scrollHeight;
                await new Promise(resolvePromise => requestAnimationFrame(() => requestAnimationFrame(resolvePromise)));
                const save = [...form.querySelectorAll('button')].find(button => button.textContent.includes('Simpan'));
                const rectangle = element.getBoundingClientRect();
                const saveBounds = save.getBoundingClientRect();
                return {
                    top: rectangle.top, bottom: rectangle.bottom, height: innerHeight,
                    saveTop: saveBounds.top, saveBottom: saveBounds.bottom,
                    fields: form.querySelectorAll('input:not([type="hidden"]), textarea').length,
                };
            }, dialogId);
            assert.ok(dialog.top >= -1 && dialog.bottom <= dialog.height + 1,
                `${kind} dialog must fit its viewport: ${JSON.stringify(dialog)}`);
            assert.ok(dialog.saveTop >= -1 && dialog.saveBottom <= dialog.height + 1,
                `${kind} dialog save action must remain reachable: ${JSON.stringify(dialog)}`);
            assert.ok(dialog.fields >= 5, `${kind} dialog fields must remain available.`);
            await cdp.run(admin, selected => document.querySelector(`button[aria-label="${selected === 'create' ? 'Tutup form tambah kegiatan' : 'Tutup pengaturan standar'}"]`).click(), kind);
            await cdp.until(admin, label => document.querySelector(`[aria-labelledby="${label}"]`)?.getBoundingClientRect().height === 0, dialogId);
        }

        await visitIndex();
        assert.equal(await cdp.run(admin, id => document.querySelector(`[data-fp-activity-id="${id}"]`)?.getAttribute('aria-selected'), ids.activity), 'true');
        assert.equal(await cdp.run(admin, () => document.querySelector('[data-fp-index-summary] [x-text="selected.name"]').textContent.trim()), 'UJI BROWSER');
        for (const [width, height] of [[1366, 768], [1440, 900], [1920, 1080]]) {
            await assertIndexViewport(width, height);
        }
        await indexTheme('dark');
        await assertIndexViewport(1366, 768, 20, true, 'index-viewport-dark');
        await indexTheme('light');
        await cdp.send('Emulation.setDeviceMetricsOverride', { width: 1366, height: 768, deviceScaleFactor: 1, mobile: false }, admin);
        await cdp.run(admin, id => {
            window.fpIndexSentinel = 99;
            document.querySelector(`[data-fp-activity-id="${id}"]`).click();
        }, ids.running);
        await cdp.until(admin, id => document.querySelector(`[data-fp-activity-id="${id}"]`)?.getAttribute('aria-selected') === 'true'
            && document.querySelector('[data-fp-index-summary] [x-text="selected.name"]')?.textContent.trim() === 'Kegiatan Berjalan Tampilan', ids.running);
        const selected = await cdp.run(admin, () => ({
            sentinel: window.fpIndexSentinel,
            selected: document.querySelectorAll('[data-fp-activity-id][aria-selected="true"]').length,
            manage: [...document.querySelectorAll('[data-fp-index-summary] a')].find(link => link.getBoundingClientRect().height > 0)?.href,
        }));
        assert.equal(selected.sentinel, 99);
        assert.equal(selected.selected, 1);
        assert.equal(selected.manage, base + '/fit-proper/' + ids.running);
        await cdp.run(admin, id => {
            const card = document.querySelector(`[data-fp-activity-id="${id}"]`);
            card.focus({ preventScroll: true });
            card.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
        }, ids.layout['21']);
        await cdp.until(admin, name => document.querySelector('[data-fp-index-summary] [x-text="selected.name"]')?.textContent.trim() === name, ids.long_name);
        await assertIndexViewport(1366, 768, 20, false, 'index-long-title');
        await assertIndexDialog('create');
        await assertIndexDialog('settings');

        await cdp.run(admin, () => {
            const input = document.querySelector('[data-fp-index] input[name="q"]');
            input.value = 'Kegiatan Tampilan 21';
            input.form.requestSubmit();
        });
        await cdp.until(admin, id => location.pathname === '/fit-proper' && document.readyState === 'complete'
            && new URLSearchParams(location.search).get('q') === 'Kegiatan Tampilan 21'
            && !!document.querySelector(`[data-fp-activity-id="${id}"]`), ids.layout['21']);
        assert.equal(await cdp.run(admin, () => document.querySelectorAll('[data-fp-activity-id]').length), 1);
        await cdp.run(admin, () => [...document.querySelectorAll('nav[aria-label="Filter status kegiatan"] a')].find(link => link.textContent.trim() === 'Terjadwal').click());
        await cdp.until(admin, () => document.readyState === 'complete' && new URLSearchParams(location.search).get('status') === 'Draft');
        assert.equal(await cdp.run(admin, () => new URLSearchParams(location.search).get('q')), 'Kegiatan Tampilan 21');
        assert.equal(await cdp.run(admin, () => document.querySelectorAll('[data-fp-activity-id]').length), 1);
        await visitIndex();
        await cdp.run(admin, () => [...document.querySelectorAll('nav[aria-label="Filter status kegiatan"] a')].find(link => link.textContent.trim() === 'Berjalan').click());
        await cdp.until(admin, () => document.readyState === 'complete' && new URLSearchParams(location.search).get('status') === 'Berjalan');
        assert.deepEqual(await cdp.run(admin, () => [...document.querySelectorAll('[data-fp-activity-id]')].map(card => Number(card.dataset.fpActivityId))), [ids.running]);
        await visitIndex();
        await cdp.run(admin, () => [...document.querySelectorAll('[data-fp-index-footer] nav[role="navigation"] a')].find(link => new URL(link.href).searchParams.get('page') === '2').click());
        await cdp.until(admin, () => document.readyState === 'complete' && new URLSearchParams(location.search).get('page') === '2');
        assert.deepEqual(await cdp.run(admin, () => [...document.querySelectorAll('[data-fp-activity-id]')].map(card => Number(card.dataset.fpActivityId))), [ids.layout['3'], ids.layout['2'], ids.layout['1']]);
        assert.equal(await cdp.run(admin, () => document.querySelector('[data-fp-activity-id]')?.getAttribute('aria-selected')), 'true');
        for (const perPage of [40, 60]) {
            await cdp.run(admin, value => {
                const input = document.querySelector('#fit-proper-per-page');
                input.value = String(value);
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }, perPage);
            await cdp.until(admin, value => document.readyState === 'complete' && new URLSearchParams(location.search).get('per_page') === String(value)
                && document.querySelectorAll('[data-fp-activity-id]').length === 23, perPage);
            assert.equal(await cdp.run(admin, () => new URLSearchParams(location.search).has('page')), false);
        }
        await visitIndex();
        await cdp.send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: false }, admin);
        await cdp.run(admin, () => new Promise(resolvePromise => requestAnimationFrame(() => requestAnimationFrame(resolvePromise))));
        assert.equal(await cdp.run(admin, () => document.documentElement.scrollWidth <= innerWidth + 1), true, 'Mobile Fit & Proper must stay horizontally contained.');
        for (const selector of ['input[name="q"]', '#fit-proper-per-page', '[data-fp-index-summary] button', '[data-fp-index-summary] a:not([style*="display: none"])']) {
            const reachable = await cdp.run(admin, selected => {
                const element = [...document.querySelectorAll(`[data-fp-index] ${selected}`)].find(candidate => candidate.getBoundingClientRect().height > 0);
                element.scrollIntoView({ block: 'center', behavior: 'instant' });
                const rectangle = element.getBoundingClientRect();
                return rectangle.top >= -1 && rectangle.bottom <= innerHeight + 1 && rectangle.height > 0;
            }, selector);
            assert.equal(reachable, true, `Mobile control must remain reachable: ${selector}`);
        }
        await cdp.run(admin, () => window.scrollTo({ top: 0, behavior: 'instant' }));
        await screenshot(admin, 'index-mobile-390x844');
        await assertIndexDialog('create');
        await assertIndexDialog('settings');
        await cdp.run(admin, () => window.scrollTo({ top: 0, behavior: 'instant' }));
        await indexTheme('dark');
        await screenshot(admin, 'index-mobile-dark-390x844');
        await indexTheme('light');
        await cdp.send('Emulation.clearDeviceMetricsOverride', {}, admin);

        // Production pages must confine wide content to their own scroll containers.
        for (const path of ['/fit-proper', '/fit-proper/'+ids.activity, '/fit-proper/'+ids.activity+'/participants/create', '/fit-proper/'+ids.activity+'/print']) {
            await cdp.navigate(admin, base+path);
            await cdp.until(admin, () => !!document.querySelector('[data-fp-root]'));
            for (const width of [1440, 1280, 768, 390]) {
                await cdp.send('Emulation.setDeviceMetricsOverride', {width, height:900, deviceScaleFactor:1, mobile:false}, admin);
                const overflow = await cdp.run(admin, async () => {
                    document.querySelectorAll('[data-fp-root] details').forEach(item => item.open = true);
                    await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
                    return document.documentElement.scrollWidth > innerWidth + 1;
                });
                assert.equal(overflow, false, `Page overflow: ${path} at ${width}px`);
                if (width === 390 || width === 1440) await screenshot(admin, path.split('/').filter(Boolean).join('-')+'-'+width);
            }
        }
        await cdp.send('Emulation.clearDeviceMetricsOverride', {}, admin);

        async function detailTab(tab) {
            await cdp.run(admin, selected => document.querySelector(`[data-fp-tabs] [role="tab"][href="#${selected}"]`).click(), tab);
            await cdp.until(admin, selected => location.hash === '#' + selected
                && document.querySelector(`[data-fp-tabs] [href="#${selected}"]`)?.getAttribute('aria-selected') === 'true'
                && document.getElementById(selected)?.getBoundingClientRect().height > 0, tab);
        }

        async function openActivityEdit() {
            await detailTab('ringkasan');
            await cdp.run(admin, () => {
                const dialog = document.querySelector('[aria-labelledby="fit-proper-edit-title"]');
                if (!dialog || dialog.getBoundingClientRect().height === 0) {
                    [...document.querySelectorAll('#ringkasan button')].find(button => button.textContent.trim() === 'Edit Kegiatan').click();
                }
            });
            await cdp.until(admin, () => document.querySelector('[aria-labelledby="fit-proper-edit-title"]')?.getBoundingClientRect().height > 0);
        }

        await cdp.navigate(admin,base+'/fit-proper/'+ids.activity+'#pic');
        await cdp.until(admin,()=>!!window.Alpine && !!document.querySelector('[data-fp-root]'));
        await detailTab('riwayat');
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
        await detailTab('pic');
        await cdp.send('Page.getLayoutMetrics', {}, admin);
        await cdp.send('Page.captureScreenshot', { format: 'png' }, admin);
        await cdp.run(admin, () => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
        await cdp.run(admin,()=>{window.fpSentinel=42;});
        for(const id of [ids.first,ids.second]){
            await cdp.run(admin,id=>{const f=document.querySelector('form[action$="/pics"]');f.querySelector('input[value="'+id+'"]').checked=true;f.scrollIntoView({block:'center',behavior:'instant'});return f.getBoundingClientRect().top;},id);
            const before=await cdp.run(admin,()=>{const form=document.querySelector('form[action$="/pics"]');const top=form.getBoundingClientRect().top;form.requestSubmit(form.querySelector('button[type="submit"], button:not([type])'));return top;});
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
        await detailTab('penguji');
        await cdp.run(admin,()=>{const f=document.querySelector('form[action$="/examiners"]');f.querySelector('[name=name]').value='Penguji Browser Manual';f.scrollIntoView({block:'center',behavior:'instant'});return f.getBoundingClientRect().top;});
        const before=await cdp.run(admin,()=>{const form=document.querySelector('form[action$="/examiners"]');const top=form.getBoundingClientRect().top;form.requestSubmit(form.querySelector('button[type="submit"], button:not([type])'));return top;});
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
        await openActivityEdit();
        await cdp.run(admin,()=>{const f=document.querySelector('[aria-labelledby="fit-proper-edit-title"] form');f.noValidate=true;f.querySelector('[name=name]').value='';f.requestSubmit();});
        await cdp.until(admin,()=>!!document.querySelector('[aria-labelledby="fit-proper-edit-title"] [name=name][aria-invalid=true]'));
        assert.equal(await cdp.run(admin,()=>window.fpSentinel),42);
        await cdp.run(admin,()=>{document.querySelector('[aria-labelledby="fit-proper-edit-title"] [name=name]').value='Nama Sesudah Async';document.querySelector('[aria-labelledby="fit-proper-edit-title"] form').requestSubmit();});
        await cdp.until(admin,()=>document.querySelector('[data-fp-fragment=heading]').innerText.includes('Nama Sesudah Async') && document.querySelector('[data-fp-root]').dataset.fpBusy!=='true');
        await openActivityEdit();
        await cdp.send('Network.enable',{},admin);
        await cdp.send('Network.emulateNetworkConditions',{offline:true,latency:0,downloadThroughput:0,uploadThroughput:0},admin);
        await cdp.run(admin,()=>{document.querySelector('[aria-labelledby="fit-proper-edit-title"] [name=description]').value='Input jaringan dipertahankan';document.querySelector('[aria-labelledby="fit-proper-edit-title"] form').requestSubmit();});
        await cdp.until(admin,()=>document.querySelector('[aria-labelledby="fit-proper-edit-title"] form').innerText.includes('Tidak dapat menyimpan'));
        assert.equal(await cdp.run(admin,()=>document.querySelector('[aria-labelledby="fit-proper-edit-title"] [name=description]').value),'Input jaringan dipertahankan');
        await cdp.send('Network.emulateNetworkConditions',{offline:false,latency:0,downloadThroughput:-1,uploadThroughput:-1},admin);
        await cdp.run(admin,()=>document.querySelector('button[aria-label="Tutup form edit kegiatan"]').click());
        await cdp.until(admin,()=>document.querySelector('[aria-labelledby="fit-proper-edit-title"]')?.getBoundingClientRect().height===0);
        await cdp.run(admin,()=>document.querySelector('form[action$="/transition"]').requestSubmit());
        await cdp.until(admin,()=>document.querySelector('.swal2-popup')?.classList.contains('swal2-modal'));
        await cdp.run(admin,()=>document.querySelector('.swal2-confirm').click());
        await cdp.until(admin,()=>document.querySelector('[data-fp-fragment=summary]')?.innerText.includes('Berjalan') && !document.querySelector('[aria-labelledby="fit-proper-edit-title"]'));
        assert.equal(await cdp.run(admin,()=>window.fpSentinel),42);
        await detailTab('penguji');
        assert.equal(await cdp.run(admin,()=>document.querySelector('[data-fp-fragment=examiner-panel]').innerText.includes('Penilaian sudah dibuka')),true);
        await cdp.navigate(guest,link);
        await cdp.until(guest,()=>document.body.innerText.includes('Kandidat Browser Alfa') && !!window.Alpine);
        assert.equal(await cdp.run(guest,()=>document.querySelector('[data-app-shell]')===null),true);
        for (const width of [1440, 1280, 768, 390]) {
            await cdp.send('Emulation.setDeviceMetricsOverride', {width, height:900, deviceScaleFactor:1, mobile:false}, guest);
            const fits = await cdp.run(guest, async () => {
                await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
                return document.documentElement.scrollWidth <= innerWidth + 1;
            });
            assert.equal(fits, true, `Examiner page overflow at ${width}px`);
            if (width === 390 || width === 1440) await screenshot(guest, 'assessment-'+width);
        }
        await cdp.send('Emulation.clearDeviceMetricsOverride', {}, guest);
        assert.equal(await cdp.run(guest,()=>document.querySelectorAll('input[type=number]').length),20);
        assert.equal(await cdp.run(guest,()=>Array.from(document.querySelectorAll('input[type=number]')).every(input=>input.value==='' && input.getBoundingClientRect().height>0)),true);
        assert.equal(await cdp.run(guest,()=>document.querySelector('[data-fp-candidates]').clientWidth<=innerWidth),true);
        await cdp.run(guest,()=>{window.fpDraftSentinel=84;const fields=document.querySelectorAll('input[type=number]');fields[0].value='1';fields[0].dispatchEvent(new Event('input',{bubbles:true}));fields[1].value='2.5';fields[1].dispatchEvent(new Event('input',{bubbles:true}));document.querySelectorAll('textarea')[0].value='Rekomendasi browser';document.querySelectorAll('textarea')[1].value='Catatan draft browser';Array.from(document.querySelectorAll('button')).find(button=>button.textContent.includes('Simpan Draft')).click();});
        await cdp.until(guest,()=>document.querySelector('[data-fp-assessment-state]').innerText.includes('Draft penilaian disimpan') && document.querySelector('[data-fp-root]').dataset.fpBusy!=='true');
        assert.equal(await cdp.run(guest,()=>window.fpDraftSentinel),84);
        assert.equal(await cdp.run(guest,()=>document.querySelector('[data-fp-result=core]').value),'1,8');
        await cdp.send('Page.navigate',{url:'about:blank'},guest);
        await cdp.until(guest,()=>location.href==='about:blank');
        await cdp.navigate(guest,link);
        await cdp.until(guest,()=>!!document.querySelector('[data-fp-candidates]') && !!window.Alpine);
        assert.deepEqual(await cdp.run(guest,()=>Array.from(document.querySelectorAll('input[type=number]')).slice(0,3).map(input=>input.value)),['1','2.5','']);
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
        await detailTab('ringkasan');
        await cdp.run(admin,()=>document.querySelector('form[action$="/finalize"]').requestSubmit());
        await cdp.until(admin,()=>document.querySelector('.swal2-popup')?.classList.contains('swal2-modal'));
        await cdp.run(admin,()=>document.querySelector('.swal2-confirm').click());
        await cdp.until(admin,()=>document.querySelector('[data-fp-fragment=summary]').innerText.includes('Selesai') && document.querySelector('[data-fp-root]').dataset.fpBusy!=='true');
        await detailTab('penguji');
        assert.equal(await cdp.run(admin,()=>document.querySelector('[data-fp-fragment=examiner-panel]').innerText.includes('Seluruh penilaian terkunci')),true);
        await detailTab('rekap');
        for (const width of [1440, 1280, 768, 390]) {
            await cdp.send('Emulation.setDeviceMetricsOverride', {width, height:900, deviceScaleFactor:1, mobile:false}, admin);
            const overflow = await cdp.run(admin, async () => {
                document.querySelectorAll('#rekap details').forEach(item => item.open = true);
                await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
                return document.documentElement.scrollWidth > innerWidth + 1;
            });
            assert.equal(overflow, false, `Final recap overflow at ${width}px`);
            if (width === 390 || width === 1440) await screenshot(admin, 'final-recap-'+width);
        }
        await cdp.navigate(guest,renewed);
        await cdp.until(guest,()=>document.body.innerText.includes('Kegiatan sudah selesai'));
        assert.deepEqual(cdp.errors,[],'Browser JavaScript errors');
        console.log('Verified: desktop index viewport fit, internal list scrolling, selection/search/status/pagination, dialogs and light/dark mobile layout, private guest, unchanged admin session, no reload, scroll/hash, PIC add/remove, copy URL, validation/network input retention, draft/final, reissue.');
    } finally {
        if(cdp){try{await cdp.send('Browser.close');}catch{}cdp.socket.close();}
        server?.kill();chrome?.kill();await delay(500);
        const absolute=resolve(directory);
        assert.ok(absolute.startsWith(resolve(tmpdir())+sep) && absolute.includes('hc-fit-proper-browser-'));
        rmSync(absolute,{recursive:true,force:true,maxRetries:5,retryDelay:200});
    }
});

