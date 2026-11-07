import { test } from 'node:test';
import assert from 'node:assert/strict';
import { spawn, spawnSync } from 'node:child_process';
import { existsSync, mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { createServer } from 'node:net';
import { tmpdir } from 'node:os';
import { basename, dirname, isAbsolute, join, relative, resolve, sep } from 'node:path';
import { setTimeout as delay } from 'node:timers/promises';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');

class Cdp {
    constructor(socket) {
        this.socket = socket;
        this.sequence = 0;
        this.pending = new Map();
        this.errors = [];
        this.drags = [];
        socket.addEventListener('message', event => {
            const message = JSON.parse(event.data);
            if (message.id) {
                const pending = this.pending.get(message.id);
                this.pending.delete(message.id);
                if (pending) {
                    clearTimeout(pending.timeout);
                    if (message.error) pending.reject(new Error(message.error.message));
                    else pending.resolve(message.result);
                }
            }
            if (message.method === 'Runtime.exceptionThrown') {
                const details = message.params.exceptionDetails;
                this.errors.push(details.exception?.description || details.text);
            }
            if (message.method === 'Input.dragIntercepted') {
                this.drags.push({ ...message.params, sessionId: message.sessionId });
            }
        });
    }

    send(method, params = {}, sessionId) {
        const id = ++this.sequence;
        return new Promise((resolvePromise, reject) => {
            const timeout = setTimeout(() => {
                this.pending.delete(id);
                reject(new Error(`CDP timeout: ${method}`));
            }, 15000);
            this.pending.set(id, { resolve: resolvePromise, reject, timeout });
            this.socket.send(JSON.stringify({ id, method, params, sessionId }));
        });
    }

    async run(sessionId, callback, ...args) {
        const result = await this.send('Runtime.evaluate', {
            expression: `(${callback.toString()})(${args.map(value => JSON.stringify(value)).join(',')})`,
            returnByValue: true, awaitPromise: true, userGesture: true,
        }, sessionId);
        if (result.exceptionDetails) {
            throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text);
        }
        return result.result.value;
    }

    async until(sessionId, callback, ...args) {
        for (let attempt = 0; attempt < 200; attempt++) {
            try {
                if (await this.run(sessionId, callback, ...args)) return;
            } catch (error) {
                if (!/context|navigat|Cannot find/i.test(error.message)) throw error;
            }
            await delay(80);
        }
        const state = await this.run(sessionId, () => ({ url: location.href, text: document.body?.innerText.slice(-1600) }));
        throw new Error(`Browser condition timed out: ${callback.toString()}\n${JSON.stringify(state)}`);
    }

    async page() {
        const { browserContextId } = await this.send('Target.createBrowserContext');
        const { targetId } = await this.send('Target.createTarget', { url: 'about:blank', browserContextId });
        const { sessionId } = await this.send('Target.attachToTarget', { targetId, flatten: true });
        await this.send('Page.enable', {}, sessionId);
        await this.send('Runtime.enable', {}, sessionId);
        await this.send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'reduce' }] }, sessionId);
        return sessionId;
    }

    async navigate(sessionId, url) {
        const marker = 'dashboard-navigation-' + this.sequence;
        await this.run(sessionId, value => { window.dashboardBrowserNavigation = value; }, marker);
        const navigation = await this.send('Page.navigate', { url }, sessionId);
        if (navigation.errorText) throw new Error(navigation.errorText);
        await this.until(sessionId, (previous, requested, newDocument) => document.readyState === 'complete'
            && location.href !== 'about:blank'
            && (newDocument ? window.dashboardBrowserNavigation !== previous : location.href === requested),
        marker, url, !!navigation.loaderId);
    }
}

async function availablePort() {
    const listener = createServer();
    await new Promise((resolvePromise, reject) => {
        listener.once('error', reject);
        listener.listen(0, '127.0.0.1', resolvePromise);
    });
    const port = listener.address().port;
    await new Promise((resolvePromise, reject) => listener.close(error => error ? reject(error) : resolvePromise()));
    return port;
}

async function stopProcess(processHandle) {
    if (!processHandle || processHandle.exitCode !== null) return;
    const exited = new Promise(resolvePromise => processHandle.once('exit', resolvePromise));
    processHandle.kill();
    await Promise.race([exited, delay(2500)]);
}

test('Dashboard widgets keep per-user layouts, draft edits and scoped calendar events coherent', { timeout: 240000 }, async () => {
    const edgeExecutable = process.env.EDGE_PATH || [
        'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
        'C:/Program Files/Microsoft/Edge/Application/msedge.exe',
    ].find(path => existsSync(path));
    assert.ok(edgeExecutable && existsSync(edgeExecutable), 'Install Edge or set EDGE_PATH before running this test.');
    assert.ok(existsSync(join(root, 'public/build/manifest.json')), 'Run npm run build before the browser test.');

    const temporaryDirectory = mkdtempSync(join(tmpdir(), 'hc-dashboard-personalization-'));
    const database = join(temporaryDirectory, 'dashboard.sqlite');
    const compiledViews = join(temporaryDirectory, 'views');
    writeFileSync(database, '');
    mkdirSync(compiledViews);
    const port = await availablePort();
    let debugPort = await availablePort();
    while (debugPort === port) debugPort = await availablePort();
    const base = `http://127.0.0.1:${port}`;
    const env = {
        ...process.env,
        APP_ENV: 'testing', APP_DEBUG: 'false', APP_URL: base,
        APP_CONFIG_CACHE: join(temporaryDirectory, 'config.php'),
        APP_ROUTES_CACHE: join(temporaryDirectory, 'routes.php'),
        VIEW_COMPILED_PATH: compiledViews,
        DB_URL: '', DB_CONNECTION: 'sqlite', DB_DATABASE: database,
        SESSION_DRIVER: 'database', SESSION_CONNECTION: 'sqlite', SESSION_DOMAIN: '', SESSION_SECURE_COOKIE: 'false',
        CACHE_STORE: 'array', MAIL_MAILER: 'array', QUEUE_CONNECTION: 'sync', BCRYPT_ROUNDS: '4',
        DASHBOARD_BROWSER_ROOT: root, DASHBOARD_BROWSER_NO_HOT: join(temporaryDirectory, 'unused-hot'),
    };
    let server;
    let edge;
    let cdp;
    let serverError;
    let edgeError;

    try {
        const migration = spawnSync('php', ['artisan', 'migrate', '--no-interaction'], {
            cwd: root, env, encoding: 'utf8', windowsHide: true,
        });
        assert.equal(migration.status, 0, migration.error?.message || migration.stdout + migration.stderr);
        const fixture = spawnSync('php', [], {
            cwd: root, env, encoding: 'utf8', windowsHide: true,
            input: [
                '<?php',
                "require 'vendor/autoload.php';",
                "$app = require 'bootstrap/app.php';",
                '$app->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();',
                "if (config('database.default') !== 'sqlite' || realpath(\\Illuminate\\Support\\Facades\\DB::connection()->getDatabaseName()) !== realpath(getenv('DB_DATABASE'))) { throw new RuntimeException('Unsafe dashboard fixture database'); }",
                "$admin = \\App\\Models\\User::factory()->create(['name'=>'Dashboard Browser Admin','email'=>'dashboard-browser-admin@example.test','password'=>'password','role'=>'Super Admin','must_change_password'=>false]);",
                "$other = \\App\\Models\\User::factory()->create(['name'=>'Dashboard Browser Other','email'=>'dashboard-browser-other@example.test','password'=>'password','role'=>'Super Admin','must_change_password'=>false]);",
                "$legacy = \\App\\Models\\User::factory()->create(['name'=>'Dashboard Browser Legacy','email'=>'dashboard-browser-legacy@example.test','password'=>'password','role'=>'Super Admin','must_change_password'=>false]);",
                "$staff = \\App\\Models\\User::factory()->create(['name'=>'Dashboard Browser Staff','email'=>'dashboard-browser-staff@example.test','password'=>'password','role'=>'Staff','must_change_password'=>false]);",
                "\\App\\Models\\DashboardLayout::query()->create(['user_id'=>$legacy->id,'workspace'=>'hc-od-career-management','slots'=>['compact-1'=>'wla-draft','compact-2'=>null,'main-1'=>'fit-proper-status','main-2'=>'career-movements']]);",
                "foreach (range(1, 3) as $number) { \\App\\Models\\Employee::query()->create(['sap'=>'DASHBOARD-BROWSER-'.$number,'name'=>'Pegawai Dashboard '.$number]); }",
                '$service = app(\\App\\Services\\FitProperService::class);',
                '$today = \\Carbon\\CarbonImmutable::today();',
                "$movementEmployee = \\App\\Models\\Employee::query()->where('sap', 'DASHBOARD-BROWSER-1')->firstOrFail();",
                "foreach ([\\App\\Models\\EmployeeMutation::class, \\App\\Models\\EmployeePromotion::class, \\App\\Models\\EmployeeDemotion::class] as $modelClass) { foreach (range(1, 4) as $number) { $modelClass::query()->create(['employee_id'=>$movementEmployee->id,'sap'=>$movementEmployee->sap,'nama'=>$movementEmployee->name,'departemen_lama'=>'Department Browser A','departemen_baru'=>'Department Browser B','jabatan_lama'=>'Analyst A','jabatan_baru'=>'Analyst B','tmt'=>$today->startOfMonth()->toDateString(),'pg'=>'10','level_lama'=>'Staff','level_baru'=>'Staff','jg_lama'=>'10','jg_baru'=>'10','verification_status'=>'Final']); } }",
                "$main = $service->create(['name'=>'Agenda Dashboard Utama','target_position'=>'Posisi Agenda','scheduled_at'=>$today->toDateString()], $admin);",
                "$private = $service->create(['name'=>'Agenda Tertutup Admin','target_position'=>'Posisi Privat','scheduled_at'=>$today->addDays(2)->toDateString()], $admin);",
                "$own = $service->create(['name'=>'Agenda Khusus Staff','target_position'=>'Posisi Staff','scheduled_at'=>$today->addDay()->toDateString()], $staff);",
                "$assigned = $service->create(['name'=>'Agenda PIC Staff','target_position'=>'Posisi PIC','scheduled_at'=>$today->addDays(3)->toDateString()], $other);",
                '$service->assignPics($assigned, [$staff->id], $other);',
                "echo json_encode(['today'=>$main->scheduled_at->toDateString(),'staff_date'=>$own->scheduled_at->toDateString(),'private_date'=>$private->scheduled_at->toDateString(),'assigned_date'=>$assigned->scheduled_at->toDateString(),'event_id'=>'fit-proper-'.$main->id]);",
            ].join('\n'),
        });
        assert.equal(fixture.status, 0, fixture.error?.message || fixture.stdout + fixture.stderr);
        const ids = JSON.parse(fixture.stdout);

        const router = join(temporaryDirectory, 'router.php');
        writeFileSync(router, [
            '<?php',
            "$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');",
            "if ($uri !== '/' && is_file(getcwd().$uri)) { return false; }",
            "require getenv('DASHBOARD_BROWSER_ROOT').'/vendor/autoload.php';",
            "$app = require getenv('DASHBOARD_BROWSER_ROOT').'/bootstrap/app.php';",
            "$app->afterResolving(\\Illuminate\\Foundation\\Vite::class, function ($vite) { $vite->useHotFile(getenv('DASHBOARD_BROWSER_NO_HOT')); });",
            '$app->handleRequest(\\Illuminate\\Http\\Request::capture());',
        ].join('\n'));
        server = spawn('php', ['-S', `127.0.0.1:${port}`, router], {
            cwd: join(root, 'public'), env, windowsHide: true, stdio: 'ignore',
        });
        server.once('error', error => { serverError = error; });
        edge = spawn(edgeExecutable, [
            '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--disable-extensions',
            `--remote-debugging-port=${debugPort}`, `--user-data-dir=${join(temporaryDirectory, 'edge-profile')}`,
        ], { windowsHide: true, stdio: 'ignore' });
        edge.once('error', error => { edgeError = error; });

        let version;
        for (let attempt = 0; attempt < 120; attempt++) {
            assert.ifError(serverError);
            assert.ifError(edgeError);
            try {
                version = await (await fetch(`http://127.0.0.1:${debugPort}/json/version`)).json();
                if (version.webSocketDebuggerUrl) break;
            } catch {}
            await delay(100);
        }
        assert.ok(version?.webSocketDebuggerUrl, 'Edge CDP unavailable.');
        const socket = new WebSocket(version.webSocketDebuggerUrl);
        await new Promise((resolvePromise, reject) => {
            socket.addEventListener('open', resolvePromise, { once: true });
            socket.addEventListener('error', reject, { once: true });
        });
        cdp = new Cdp(socket);

        const screenshots = join(root, 'storage/framework/testing/dashboard-personalization');
        mkdirSync(screenshots, { recursive: true });
        const initialLayout = {
            'compact-1': 'total-employees', 'compact-2': 'fit-proper-participants',
            'main-1': 'career-movements', 'main-2': 'follow-up',
            'right-1': 'fit-proper-status', 'right-2': 'wla-summary',
        };
        const page = await cdp.page();

        async function login(sessionId, email) {
            await cdp.navigate(sessionId, base + '/dashboard');
            await cdp.until(sessionId, () => !!document.querySelector('input[name="email"]'));
            await cdp.run(sessionId, address => {
                document.querySelector('input[name="email"]').value = address;
                document.querySelector('input[name="password"]').value = 'password';
                document.querySelector('form').requestSubmit();
            }, email);
            await ready(sessionId);
        }

        async function ready(sessionId = page) {
            await cdp.until(sessionId, () => location.pathname === '/dashboard' && document.readyState === 'complete'
                && !!window.Alpine && !!document.querySelector('[data-dashboard-config]')
                && document.querySelectorAll('[data-dashboard-slot]').length === 6
                && document.querySelector('[data-hc-od-dashboard]')?.dataset.editing === 'false');
            await cdp.until(sessionId, () => !!document.querySelector('[data-calendar-date]'));
        }

        async function layout(sessionId = page) {
            return cdp.run(sessionId, () => Object.fromEntries([...document.querySelectorAll('[data-dashboard-slot]')]
                .map(slot => [slot.dataset.dashboardSlot, slot.querySelector('[data-dashboard-slot-items] [data-dashboard-widget]')?.dataset.dashboardWidget ?? null])));
        }

        async function expectLayout(expected, sessionId = page) {
            try {
                await cdp.until(sessionId, wanted => {
                    const current = Object.fromEntries([...document.querySelectorAll('[data-dashboard-slot]')]
                        .map(slot => [slot.dataset.dashboardSlot, slot.querySelector('[data-dashboard-slot-items] [data-dashboard-widget]')?.dataset.dashboardWidget ?? null]));
                    return Object.keys(wanted).every(key => current[key] === wanted[key]);
                }, expected);
            } catch (error) {
                const diagnostics = await cdp.run(sessionId, () => {
                    const root = document.querySelector('[data-hc-od-dashboard]');
                    const state = window.Alpine?.$data(root);
                    return {
                        slots: Object.fromEntries([...document.querySelectorAll('[data-dashboard-slot]')]
                            .map(slot => [slot.dataset.dashboardSlot, [...slot.querySelectorAll('[data-dashboard-slot-items] [data-dashboard-widget]')].map(widget => widget.dataset.dashboardWidget)])),
                        templates: [...document.querySelectorAll('[data-dashboard-widget-template]')].map(template => ({
                            id: template.dataset.dashboardWidgetTemplate,
                            cards: [...template.content.querySelectorAll('[data-dashboard-widget]')].map(widget => widget.dataset.dashboardWidget),
                        })),
                        draft: state?.draft,
                        saved: state?.saved,
                        editing: state?.editing,
                    };
                });
                throw new Error(`${error.message}\nDashboard layout diagnostics: ${JSON.stringify(diagnostics)}`);
            }
            assert.deepEqual(await layout(sessionId), expected);
        }

        async function click(selector, sessionId = page) {
            await cdp.run(sessionId, selected => {
                const element = document.querySelector(selected);
                if (!element || element.disabled) throw new Error(`Unavailable control: ${selected}`);
                element.click();
            }, selector);
        }

        async function paint(sessionId = page) {
            await cdp.run(sessionId, async () => {
                await document.fonts.ready;
                await Promise.all(document.getAnimations().filter(animation => animation.effect?.getTiming().iterations !== Infinity)
                    .map(animation => animation.finished.catch(() => {})));
                await new Promise(resolvePromise => requestAnimationFrame(() => requestAnimationFrame(resolvePromise)));
            });
            await cdp.send('Page.getLayoutMetrics', {}, sessionId);
            await cdp.send('Page.captureScreenshot', { format: 'png' }, sessionId);
        }

        async function screenshot(name, sessionId = page) {
            const { data } = await cdp.send('Page.captureScreenshot', { format: 'png' }, sessionId);
            writeFileSync(join(screenshots, name + '.png'), Buffer.from(data, 'base64'));
        }

        async function editing(sessionId = page) {
            await click('[data-dashboard-edit]', sessionId);
            await cdp.until(sessionId, () => document.querySelector('[data-hc-od-dashboard]')?.dataset.editing === 'true');
            assert.ok(await cdp.run(sessionId, () => document.querySelector('[data-dashboard-gallery-toggle]')?.textContent.includes('Tambah Widget')),
                'Edit mode must expose the Add Widget gallery control on desktop and mobile.');
        }

        async function gallery(sessionId = page) {
            if (!await cdp.run(sessionId, () => document.querySelector('[data-hc-od-dashboard]')?.dataset.galleryOpen === 'true')) {
                await click('[data-dashboard-gallery-toggle]', sessionId);
            }
            await cdp.until(sessionId, () => {
                const element = document.querySelector('[data-dashboard-gallery]');
                const rectangle = element?.getBoundingClientRect();
                return document.querySelector('[data-hc-od-dashboard]')?.dataset.galleryOpen === 'true'
                    && rectangle?.height > 0 && rectangle.left < innerWidth && rectangle.right > 0
                    && getComputedStyle(element).visibility !== 'hidden';
            });
            await paint(sessionId);
        }

        async function place(widget, slot, move = false, sessionId = page) {
            const before = await layout(sessionId);
            if (!move) await gallery(sessionId);
            await click(`[data-widget-${move ? 'move' : 'add'}="${widget}"]`, sessionId);
            await cdp.until(sessionId, () => document.querySelector('[data-hc-od-dashboard]')?.dataset.galleryOpen === 'true'
                && [...document.querySelectorAll('[data-dashboard-gallery] [data-dashboard-place]')]
                    .some(input => !input.disabled && input.getBoundingClientRect().height > 0));
            const enabledSlots = await cdp.run(sessionId, () => [...document.querySelectorAll('[data-dashboard-gallery] [data-dashboard-place]')]
                .filter(button => !button.disabled && button.getBoundingClientRect().height > 0).map(button => button.dataset.dashboardPlace));
            assert.ok(enabledSlots.includes(slot));
            assert.ok(enabledSlots.every(key => slot.startsWith('compact-') ? key.startsWith('compact-') : /^(main|right)-/.test(key)),
                'The gallery must enforce compact and main widget capacity.');
            await click(`[data-dashboard-gallery] [data-dashboard-place="${slot}"]`, sessionId);
            await cdp.until(sessionId, selected => document.querySelector(`[data-dashboard-gallery] [data-dashboard-place="${selected}"]`)?.getAttribute('aria-checked') === 'true', slot);
            assert.deepEqual(await layout(sessionId), before, 'Selecting a slot must wait for the explicit Apply action.');
            const action = await cdp.run(sessionId, () => document.querySelector('[data-dashboard-apply]')?.textContent.trim());
            assert.ok(action?.includes(before[slot] ? 'Ganti Widget' : 'Tambah ke Slot'), `The action must explain the selected slot: ${action}`);
            if (before[slot]) {
                const replacedTitle = await cdp.run(sessionId, id => JSON.parse(document.querySelector('[data-dashboard-config]').textContent).widgets
                    .find(item => item.id === id)?.title, before[slot]);
                assert.ok(replacedTitle && await cdp.run(sessionId, title => document.querySelector('[data-dashboard-replacement]')?.innerText.includes(title), replacedTitle),
                    'The gallery must identify the widget that will be replaced.');
            }
            await click('[data-dashboard-apply]', sessionId);
            await cdp.until(sessionId, (id, selected) => document.querySelector(`[data-dashboard-slot="${selected}"] [data-dashboard-slot-items] [data-dashboard-widget]`)?.dataset.dashboardWidget === id, widget, slot);
        }

        async function drag(widget, destination) {
            await cdp.until(page, () => document.querySelector('[data-hc-od-dashboard]')?.dataset.dragReady === 'true');
            await paint();
            const coordinates = await cdp.run(page, (id, slot) => {
                const handle = document.querySelector(`[data-dashboard-widget="${id}"] [data-widget-drag]`);
                const target = document.querySelector(`[data-dashboard-slot="${slot}"] [data-dashboard-slot-items]`);
                const from = handle.getBoundingClientRect();
                const to = target.getBoundingClientRect();
                return { from: { x: from.left + from.width / 2, y: from.top + from.height / 2 }, to: { x: to.left + to.width / 2, y: to.top + Math.min(24, to.height / 4) }, target: { width: to.width, height: to.height } };
            }, widget, destination);
            assert.ok(coordinates.target.width > 80 && coordinates.target.height > 60,
                `The drop target must expose a usable hit area before dragging: ${JSON.stringify({ destination, coordinates })}`);
            const previousDrags = cdp.drags.length;
            await cdp.send('Input.setInterceptDrags', { enabled: true }, page);
            await cdp.send('Input.dispatchMouseEvent', { type: 'mouseMoved', ...coordinates.from }, page);
            await cdp.send('Input.dispatchMouseEvent', { type: 'mousePressed', button: 'left', buttons: 1, clickCount: 1, ...coordinates.from }, page);
            for (let step = 1; step <= 6; step++) {
                await cdp.send('Input.dispatchMouseEvent', {
                    type: 'mouseMoved', button: 'left', buttons: 1,
                    x: coordinates.from.x + (coordinates.to.x - coordinates.from.x) * step / 6,
                    y: coordinates.from.y + (coordinates.to.y - coordinates.from.y) * step / 6,
                }, page);
                await delay(25);
            }
            let intercepted;
            for (let attempt = 0; attempt < 40 && !intercepted; attempt++) {
                intercepted = cdp.drags.slice(previousDrags).find(event => event.sessionId === page);
                if (!intercepted) await delay(25);
            }
            assert.ok(intercepted, `The browser must start a native widget drag: ${JSON.stringify(coordinates)}`);
            if (destination.startsWith('right-')) {
                try {
                    await cdp.until(page, (point, target) => document.elementFromPoint(point.x, point.y)?.closest('[data-dashboard-slot]')?.dataset.dashboardSlot === target,
                        coordinates.to, destination);
                } catch (error) {
                    const diagnostics = await cdp.run(page, (point, target) => {
                        const rectangle = element => {
                            const value = element?.getBoundingClientRect();
                            return value ? { left: value.left, top: value.top, right: value.right, bottom: value.bottom, width: value.width, height: value.height } : null;
                        };
                        const hit = document.elementFromPoint(point.x, point.y);
                        const gallery = document.querySelector('[data-dashboard-gallery]');
                        return {
                            target: rectangle(document.querySelector(`[data-dashboard-slot="${target}"] [data-dashboard-slot-items]`)),
                            hit: hit ? { tag: hit.tagName, classes: hit.getAttribute('class'), slot: hit.closest('[data-dashboard-slot]')?.dataset.dashboardSlot ?? null } : null,
                            draggingType: document.querySelector('[data-hc-od-dashboard]')?.dataset.draggingType ?? null,
                            gallery: { rectangle: rectangle(gallery), pointerEvents: gallery ? getComputedStyle(gallery).pointerEvents : null },
                        };
                    }, coordinates.to, destination);
                    throw new Error(`${error.message}\nNative right-slot drag diagnostics: ${JSON.stringify({ widget, destination, coordinates, ...diagnostics })}`);
                }
            }
            for (const type of ['dragEnter', 'dragOver', 'drop']) {
                await cdp.send('Input.dispatchDragEvent', { type, data: intercepted.data, ...coordinates.to }, page);
            }
            await cdp.send('Input.dispatchMouseEvent', { type: 'mouseReleased', button: 'left', buttons: 0, clickCount: 1, ...coordinates.to }, page);
            await cdp.send('Input.setInterceptDrags', { enabled: false }, page);
            await cdp.run(page, () => new Promise(resolvePromise => requestAnimationFrame(() => requestAnimationFrame(resolvePromise))));
        }

        async function selectDate(date, sessionId = page) {
            if (!await cdp.run(sessionId, day => !!document.querySelector(`[data-calendar-date="${day}"]`), date)) {
                const direction = await cdp.run(sessionId, day => day < [...document.querySelectorAll('[data-calendar-date]')].map(button => button.dataset.calendarDate).sort()[0] ? 'prev' : 'next', date);
                await click(`[data-dashboard-month] .fc-${direction}-button`, sessionId);
                await cdp.until(sessionId, day => !!document.querySelector(`[data-calendar-date="${day}"]`), date);
            }
            await click(`[data-calendar-date="${date}"]`, sessionId);
        }

        await login(page, 'dashboard-browser-admin@example.test');
        await expectLayout(initialLayout);
        assert.equal(await cdp.run(page, () => document.querySelector('[data-dashboard-gallery]').getBoundingClientRect().height), 0);
        await cdp.run(page, () => { window.dashboardCalendarReference = document.querySelector('[data-dashboard-calendar]'); });
        await selectDate(ids.today);
        await cdp.until(page, () => document.querySelector('[data-dashboard-agenda]')?.innerText.includes('Agenda Dashboard Utama'));
        await cdp.until(page, day => document.querySelector(`[data-dashboard-month] td[data-date="${day}"]`)?.classList.contains('has-events'), ids.today);
        const emptyDate = await cdp.run(page, today => [...document.querySelectorAll('[data-calendar-date]')]
            .find(button => button.dataset.calendarDate.startsWith(today.slice(0, 7)) && !button.classList.contains('has-events'))?.dataset.calendarDate, ids.today);
        assert.ok(emptyDate, 'The isolated month must include an empty date.');
        await selectDate(emptyDate);
        await cdp.until(page, () => document.querySelector('[data-dashboard-agenda]')?.innerText.includes('Tidak ada kegiatan'));
        assert.equal(await cdp.run(page, () => document.querySelectorAll('[data-dashboard-agenda] a').length), 0);
        await selectDate(ids.today);
        await cdp.until(page, () => document.querySelector('[data-dashboard-agenda]')?.innerText.includes('Agenda Dashboard Utama'));
        const monthTitle = await cdp.run(page, () => document.querySelector('[data-dashboard-month] .fc-toolbar-title').textContent);
        await click('[data-dashboard-month] .fc-next-button');
        await cdp.until(page, title => document.querySelector('[data-dashboard-month] .fc-toolbar-title')?.textContent !== title, monthTitle);
        await click('[data-dashboard-month] .fc-prev-button');
        await cdp.until(page, title => document.querySelector('[data-dashboard-month] .fc-toolbar-title')?.textContent === title, monthTitle);
        await selectDate(ids.today);
        await cdp.until(page, () => document.querySelector('[data-dashboard-agenda]')?.innerText.includes('Agenda Dashboard Utama'));

        for (const [width, height] of [[1366, 768], [1440, 900], [390, 844]]) {
            await cdp.send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: false }, page);
            await paint();
            const dimensions = await cdp.run(page, () => ({ width: innerWidth, height: innerHeight, scrollWidth: document.documentElement.scrollWidth, scrollHeight: document.documentElement.scrollHeight,
                overflow: [...document.querySelectorAll('body *')].map(element => ({ element: element.tagName, class: element.getAttribute('class'), right: element.getBoundingClientRect().right })).filter(element => element.right > innerWidth + 1).slice(0, 8),
            }));
            await screenshot(`dashboard-${width}x${height}`);
            assert.ok(dimensions.scrollWidth <= width + 1, `Dashboard horizontal overflow: ${JSON.stringify(dimensions)}`);
            if (width >= 1280) {
                assert.ok(dimensions.scrollHeight <= height + 1, `Dashboard vertical overflow: ${JSON.stringify(dimensions)}`);
                const chart = await cdp.run(page, () => {
                    const rectangle = element => {
                        const value = element.getBoundingClientRect();
                        return { left: value.left, top: value.top, right: value.right, bottom: value.bottom, width: value.width, height: value.height };
                    };
                    const svg = document.querySelector('[data-dashboard-widget="career-movements"] [data-career-chart]');
                    return {
                        svg: rectangle(svg),
                        bars: [...svg.querySelectorAll('.hc-od-chart-bar')].filter(bar => Number(bar.getAttribute('height')) > 0).map(rectangle),
                        values: [...svg.querySelectorAll('.hc-od-chart-value')].map(label => ({ text: label.textContent.trim(), ...rectangle(label) })),
                        legend: rectangle(svg.closest('[data-dashboard-widget]').querySelector('.hc-od-chart-legend')),
                    };
                });
                assert.equal(chart.bars.length, 3, `The synthetic Final movements must render all three nonzero series: ${JSON.stringify(chart)}`);
                assert.deepEqual(chart.values.map(value => value.text), ['4', '4', '4']);
                for (const element of [...chart.bars, ...chart.values]) {
                    assert.ok(element.width > 0 && element.height > 0 && element.left >= chart.svg.left - 1
                        && element.right <= chart.svg.right + 1 && element.top >= chart.svg.top - 1 && element.bottom <= chart.svg.bottom + 1,
                    `Nonzero bars and their value labels must fit within the SVG: ${JSON.stringify({ width, element, chart })}`);
                }
                assert.ok(chart.legend.top >= chart.svg.bottom - 1,
                    `The chart legend must remain below the SVG and month labels: ${JSON.stringify({ width, chart })}`);
            }
        }
        await editing();
        await gallery();
        const mobileGallery = await cdp.run(page, () => {
            const rectangle = document.querySelector('[data-dashboard-gallery]').getBoundingClientRect();
            return { top: rectangle.top, bottom: rectangle.bottom, left: rectangle.left, right: rectangle.right, width: innerWidth, height: innerHeight };
        });
        assert.ok(mobileGallery.top >= -1 && mobileGallery.bottom <= mobileGallery.height + 1
            && mobileGallery.left >= -1 && mobileGallery.right <= mobileGallery.width + 1,
        `Mobile widget gallery must remain reachable: ${JSON.stringify(mobileGallery)}`);
        await screenshot('dashboard-mobile-gallery');
        await click('button[aria-label="Tutup galeri"]');
        await cdp.until(page, () => document.querySelector('[data-hc-od-dashboard]')?.dataset.galleryOpen === 'false');
        await click('[data-widget-remove="total-employees"]');
        await expectLayout({ ...initialLayout, 'compact-1': null });
        await click('[data-dashboard-cancel]');
        await expectLayout(initialLayout);
        await cdp.send('Emulation.setDeviceMetricsOverride', { width: 1366, height: 768, deviceScaleFactor: 1, mobile: false }, page);
        await paint();

        await editing();
        await gallery();
        await paint();
        const editGeometry = await cdp.run(page, () => {
            const rectangle = element => {
                const value = element.getBoundingClientRect();
                return { top: value.top, bottom: value.bottom, left: value.left, right: value.right, width: value.width, height: value.height };
            };
            const mainCards = [...document.querySelectorAll('[data-dashboard-slot^="main-"] [data-dashboard-widget], [data-dashboard-slot^="right-"] [data-dashboard-widget]')];
            return {
                viewport: { width: innerWidth, height: innerHeight },
                documentHeight: Math.max(document.documentElement.scrollHeight, document.body.scrollHeight),
                documentWidth: document.documentElement.scrollWidth,
                gallery: rectangle(document.querySelector('[data-dashboard-gallery]')),
                calendar: rectangle(document.querySelector('[data-dashboard-calendar]')),
                cards: mainCards.map(card => ({ id: card.dataset.dashboardWidget, ...rectangle(card) })),
                labels: mainCards.flatMap(card => [...card.querySelectorAll('.hc-od-widget-title, h2, .hc-od-chart text')])
                    .filter(element => element.getBoundingClientRect().height > 0)
                    .map(element => ({ text: element.textContent.trim(), ...rectangle(element) })),
                columns: ['.hc-od-left-column', '.hc-od-main-column', '.hc-od-right-column'].map(selector => rectangle(document.querySelector(selector))),
            };
        });
        await screenshot('dashboard-edit-1366x768');
        assert.equal(editGeometry.viewport.width, 1366);
        assert.equal(editGeometry.viewport.height, 768);
        assert.ok(editGeometry.documentHeight <= 769 && editGeometry.documentWidth <= 1367,
            `Desktop edit mode must fit its viewport: ${JSON.stringify(editGeometry)}`);
        assert.ok(editGeometry.gallery.width >= 280 && editGeometry.gallery.width <= 320,
            `Desktop widget gallery must remain compact: ${JSON.stringify(editGeometry.gallery)}`);
        assert.ok(editGeometry.columns.every(column => column.width > 0)
            && editGeometry.columns[0].right <= editGeometry.columns[1].left + 1
            && editGeometry.columns[1].right <= editGeometry.columns[2].left + 1
            && editGeometry.columns[2].right <= editGeometry.gallery.left + 1,
        `All three columns must remain ordered and available beside the open desktop gallery: ${JSON.stringify(editGeometry)}`);
        assert.equal(editGeometry.cards.length, 4);
        assert.ok(editGeometry.labels.length >= 4);
        for (const element of [editGeometry.gallery, editGeometry.calendar, ...editGeometry.cards, ...editGeometry.labels]) {
            assert.ok(element.width > 0 && element.height > 0 && element.left >= -1 && element.right <= 1367
                && element.top >= -1 && element.bottom <= 769,
            `Edit-mode content must remain visible: ${JSON.stringify(element)}`);
        }
        assert.equal(await cdp.run(page, () => document.querySelector('[data-dashboard-calendar]') === window.dashboardCalendarReference
            && !document.querySelector('[data-dashboard-calendar]').closest('[data-dashboard-slot]')
            && !document.querySelector('[data-dashboard-calendar] [data-widget-drag]')), true);
        await click('[data-widget-remove="total-employees"]');
        await place('wla-draft', 'compact-2');
        await expectLayout({ ...initialLayout, 'compact-1': null, 'compact-2': 'wla-draft' });
        await place('pending-requests', 'compact-2');
        await expectLayout({ ...initialLayout, 'compact-1': null, 'compact-2': 'pending-requests' });
        await place('pending-requests', 'compact-1', true);
        await place('career-movements', 'right-1', true);
        await expectLayout({ ...initialLayout, 'compact-1': 'pending-requests', 'compact-2': null,
            'main-1': 'fit-proper-status', 'right-1': 'career-movements' });
        await click('[data-widget-remove="wla-summary"]');
        await place('wla-summary', 'main-1');
        await expectLayout({ ...initialLayout, 'compact-1': 'pending-requests', 'compact-2': null,
            'main-1': 'wla-summary', 'right-1': 'career-movements', 'right-2': null });
        await click('[data-dashboard-cancel]');
        await expectLayout(initialLayout);
        await cdp.until(page, () => document.querySelector('[data-hc-od-dashboard]')?.dataset.editing === 'false');
        await cdp.navigate(page, base + '/dashboard');
        await ready();
        await expectLayout(initialLayout);

        await editing();
        await click('[data-widget-remove="fit-proper-participants"]');
        await drag('total-employees', 'compact-2');
        await expectLayout({ ...initialLayout, 'compact-1': null, 'compact-2': 'total-employees' });
        await place('wla-draft', 'compact-1');
        await click('[data-widget-remove="wla-summary"]');
        await gallery();
        await drag('career-movements', 'right-2');
        await expectLayout({ ...initialLayout, 'compact-1': 'wla-draft', 'compact-2': 'total-employees',
            'main-1': null, 'right-2': 'career-movements' });
        await place('wla-summary', 'main-1');
        const savedLayout = { 'compact-1': 'wla-draft', 'compact-2': 'total-employees',
            'main-1': 'wla-summary', 'main-2': 'follow-up', 'right-1': 'fit-proper-status', 'right-2': 'career-movements' };
        await expectLayout(savedLayout);
        await cdp.run(page, () => {
            const original = window.fetch.bind(window);
            window.dashboardSaveRequests = [];
            window.dashboardFailSave = true;
            const saveUrl = new URL(JSON.parse(document.querySelector('[data-dashboard-config]').textContent).saveUrl, location.href);
            window.fetch = (input, options = {}) => {
                const url = new URL(input instanceof Request ? input.url : String(input), location.href);
                if (url.pathname === saveUrl.pathname && (options.method || 'GET').toUpperCase() === 'PUT') {
                    window.dashboardSaveRequests.push(JSON.parse(options.body));
                    if (window.dashboardFailSave) {
                        window.dashboardFailSave = false;
                        return Promise.resolve(new Response(JSON.stringify({ message: 'Simulasi jaringan browser. Coba lagi.' }), { status: 503, headers: { 'Content-Type': 'application/json' } }));
                    }
                }
                return original(input, options);
            };
        });
        await click('[data-dashboard-save]');
        await cdp.until(page, () => document.querySelector('[data-dashboard-error]')?.getBoundingClientRect().height > 0);
        assert.equal(await cdp.run(page, () => document.querySelector('[data-hc-od-dashboard]').dataset.editing), 'true');
        await expectLayout(savedLayout);
        await screenshot('dashboard-save-failure');
        await click('[data-dashboard-save]');
        await cdp.until(page, () => document.querySelector('[data-hc-od-dashboard]')?.dataset.editing === 'false');
        assert.deepEqual(await cdp.run(page, () => window.dashboardSaveRequests.map(request => request.slots)), [savedLayout, savedLayout]);
        await cdp.navigate(page, base + '/dashboard');
        await ready();
        await expectLayout(savedLayout);

        const legacy = await cdp.page();
        await login(legacy, 'dashboard-browser-legacy@example.test');
        const legacyLayout = { 'compact-1': 'wla-draft', 'compact-2': null,
            'main-1': 'fit-proper-status', 'main-2': 'career-movements', 'right-1': null, 'right-2': null };
        await expectLayout(legacyLayout, legacy);
        await cdp.navigate(legacy, base + '/dashboard');
        await ready(legacy);
        await expectLayout(legacyLayout, legacy);
        const other = await cdp.page();
        await login(other, 'dashboard-browser-other@example.test');
        await expectLayout(initialLayout, other);
        const staff = await cdp.page();
        await login(staff, 'dashboard-browser-staff@example.test');
        await editing(staff);
        await gallery(staff);
        assert.equal(await cdp.run(staff, () => !!document.querySelector('[data-gallery-widget="wla-draft"], [data-gallery-widget="wla-summary"]')), false);
        await click('[data-dashboard-cancel]', staff);
        const scoped = await cdp.run(staff, async today => {
            const config = JSON.parse(document.querySelector('[data-dashboard-config]').textContent);
            const start = new Date(today + 'T00:00:00Z');
            start.setUTCDate(start.getUTCDate() - 1);
            const end = new Date(today + 'T00:00:00Z');
            end.setUTCDate(end.getUTCDate() + 8);
            const url = new URL(config.calendarUrl, location.href);
            url.search = new URLSearchParams({ start: start.toISOString().slice(0, 10), end: end.toISOString().slice(0, 10) });
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            return { status: response.status, titles: (await response.json()).map(event => event.title).sort() };
        }, ids.today);
        assert.equal(scoped.status, 200);
        assert.deepEqual(scoped.titles, ['Agenda Khusus Staff', 'Agenda PIC Staff']);
        await selectDate(ids.staff_date, staff);
        await cdp.until(staff, () => document.querySelector('[data-dashboard-agenda]')?.innerText.includes('Agenda Khusus Staff'));

        const guest = await cdp.page();
        await cdp.navigate(guest, base + '/dashboard');
        await cdp.until(guest, () => !!document.querySelector('input[name="email"]'));
        const calendarStatus = await cdp.run(guest, async today => {
            const end = new Date(today + 'T00:00:00Z');
            end.setUTCDate(end.getUTCDate() + 1);
            return (await fetch('/dashboard/calendar?' + new URLSearchParams({ start: today, end: end.toISOString().slice(0, 10) }), { headers: { Accept: 'application/json' } })).status;
        }, ids.today);
        assert.equal(calendarStatus, 401);
        assert.equal(await cdp.run(guest, () => !!document.querySelector('[data-dashboard-config]')), false);
        assert.deepEqual(cdp.errors, [], 'Browser JavaScript errors');
        console.log('Verified isolated six-slot dashboard: viewport layout, stable columns and fixed calendar, gallery radio/apply choices, center/right swaps and native right-slot drag, cancellation, failed-save draft retention, per-user and legacy-four persistence, scoped agenda, restricted widgets and guest authorization.');
    } finally {
        if (cdp) {
            try { await cdp.send('Browser.close'); } catch {}
            cdp.socket.close();
        }
        await Promise.all([stopProcess(server), stopProcess(edge)]);
        const absoluteTemporaryDirectory = resolve(temporaryDirectory);
        const relativeTemporaryDirectory = relative(resolve(tmpdir()), absoluteTemporaryDirectory);
        assert.ok(relativeTemporaryDirectory && !isAbsolute(relativeTemporaryDirectory)
            && !relativeTemporaryDirectory.startsWith('..' + sep) && relativeTemporaryDirectory !== '..'
            && basename(absoluteTemporaryDirectory).startsWith('hc-dashboard-personalization-'), 'Unsafe dashboard browser cleanup path.');
        rmSync(absoluteTemporaryDirectory, { recursive: true, force: true, maxRetries: 10, retryDelay: 200 });
    }
});
