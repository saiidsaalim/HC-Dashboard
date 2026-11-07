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
            if (message.method === 'Runtime.consoleAPICalled' && message.params.type === 'error') {
                this.errors.push(message.params.args.map(argument => argument.value ?? argument.description ?? argument.type).join(' '));
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
        const expression = `(${callback.toString()})(${args.map(value => JSON.stringify(value)).join(',')})`;
        const result = await this.send('Runtime.evaluate', {
            expression, returnByValue: true, awaitPromise: true, userGesture: true,
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
        const state = await this.run(sessionId, () => ({ url: location.href, text: document.body?.innerText.slice(-1800) }));
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
        await this.send('Page.navigate', { url }, sessionId);
        await this.until(sessionId, requested => document.readyState === 'complete' && location.href === requested, url);
        await this.until(sessionId, () => !!window.Alpine);
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

test('Formasi browser journeys keep filters, explicit needs and master navigation coherent', { timeout: 240000 }, async () => {
    const edgeExecutable = process.env.EDGE_PATH || [
        'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
        'C:/Program Files/Microsoft/Edge/Application/msedge.exe',
    ].find(path => existsSync(path));
    assert.ok(edgeExecutable && existsSync(edgeExecutable), 'Install Edge or set EDGE_PATH before running this test.');
    assert.ok(existsSync(join(root, 'public/build/manifest.json')), 'Run npm run build before the browser test.');

    const temporaryDirectory = mkdtempSync(join(tmpdir(), 'hc-formasi-browser-'));
    const database = join(temporaryDirectory, 'formasi.sqlite');
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
        DB_URL: '', DB_CONNECTION: 'sqlite', DB_DATABASE: database,
        SESSION_DRIVER: 'database', SESSION_CONNECTION: 'sqlite', SESSION_DOMAIN: '',
        SESSION_SECURE_COOKIE: 'false', CACHE_STORE: 'array', MAIL_MAILER: 'array',
        QUEUE_CONNECTION: 'sync', BCRYPT_ROUNDS: '4', VIEW_COMPILED_PATH: compiledViews,
        FORMASI_BROWSER_ROOT: root, FORMASI_BROWSER_NO_HOT: join(temporaryDirectory, 'unused-hot'),
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
                "if (config('database.default') !== 'sqlite' || realpath(\\Illuminate\\Support\\Facades\\DB::connection()->getDatabaseName()) !== realpath(getenv('DB_DATABASE'))) { throw new RuntimeException('Unsafe browser fixture database'); }",
                "$user = \\App\\Models\\User::factory()->create(['name' => 'Formasi Browser Admin', 'email' => 'formasi-browser@example.test', 'password' => 'password', 'role' => 'Super Admin', 'must_change_password' => false]);",
                '$branches = [];',
                '$levelPositions = [];',
                "foreach ([1, 2] as $branch) {",
                "    $directorate = \\App\\Models\\Directorate::create(['code' => 'FB-DIR-'.$branch, 'name' => 'Directorate Browser '.$branch, 'active' => true]);",
                "    $department = $directorate->departments()->create(['code' => 'FB-DEP-'.$branch, 'name' => 'Department Browser '.$branch, 'active' => true]);",
                "    $unit = $department->units()->create(['code' => 'FB-UNIT-'.$branch, 'name' => 'Unit Browser '.$branch, 'active' => true]);",
                "    $section = $unit->sections()->create(['code' => 'FB-SECT-'.$branch, 'name' => 'Section Browser '.$branch, 'active' => true]);",
                "    $branches[] = ['directorate' => $directorate->id, 'department' => $department->id, 'unit' => $unit->id, 'section' => $section->id];",
                '    foreach (range(1, $branch === 1 ? 21 : 1) as $number) {',
                "        $position = $unit->positions()->create(['section_id' => $section->id, 'code' => sprintf('FB-P-%d-%02d', $branch, $number), 'name' => $branch === 1 ? sprintf('Formasi Browser %02d', $number) : 'Formasi Browser Lain', 'job_level' => \\App\\Models\\Position::JOB_LEVEL_STAFF, 'active' => true]);",
                '        if ($branch === 1 && $number === 21) {',
                "            \\App\\Models\\Employee::query()->create(['sap' => 'FORMASI-BROWSER-LINKED', 'name' => 'Pegawai Browser Terhubung', 'position_id' => $position->id]);",
                '            $target = $position->id;',
                '        }',
                '    }',
                '    if ($branch === 2) {',
                '        $parent = null;',
                '        foreach ([',
                "            ['level' => \\App\\Models\\Position::JOB_LEVEL_GENERAL_MANAGER, 'need' => 3, 'actual' => 1, 'status' => 'shortage'],",
                "            ['level' => \\App\\Models\\Position::JOB_LEVEL_SENIOR_MANAGER, 'need' => 1, 'actual' => 1, 'status' => 'fulfilled'],",
                "            ['level' => \\App\\Models\\Position::JOB_LEVEL_MANAGER, 'need' => 1, 'actual' => 2, 'status' => 'excess'],",
                "            ['level' => \\App\\Models\\Position::JOB_LEVEL_STAFF, 'need' => 2, 'actual' => 0, 'status' => 'vacant'],",
                '        ] as $index => $case) {',
                "            $levelPosition = $unit->positions()->create(['section_id' => $section->id, 'parent_position_id' => $parent?->id, 'code' => 'FB-LEVEL-'.$index, 'name' => 'Formasi Browser '.$case['level'].' '.$case['status'], 'job_level' => $case['level'], 'formation_count' => $case['need'], 'formation_is_confirmed' => true, 'active' => true]);",
                "            $levelPositions[$case['status']] = ['id' => $levelPosition->id, 'level' => $case['level']];",
                "            for ($employee = 1; $employee <= $case['actual']; $employee++) {",
                "                $levelPosition->employees()->create(['sap' => 'FB-LEVEL-'.$index.'-'.$employee, 'name' => 'Pegawai Level Browser '.$index.' '.$employee]);",
                '            }',
                '            $parent = $levelPosition;',
                '        }',
                '    }',
                '}',
                "\\App\\Models\\Employee::query()->create(['sap' => 'FORMASI-BROWSER-UNMAPPED', 'name' => 'Pegawai Browser Belum Terpetakan']);",
                "echo json_encode(['branches' => $branches, 'target' => $target, 'level_positions' => $levelPositions]);",
            ].join('\n'),
        });
        assert.equal(fixture.status, 0, fixture.error?.message || fixture.stdout + fixture.stderr);
        const ids = JSON.parse(fixture.stdout);
        const first = ids.branches[0];
        const second = ids.branches[1];

        const router = join(temporaryDirectory, 'router.php');
        writeFileSync(router, [
            '<?php',
            "$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');",
            "if ($uri !== '/' && is_file(getcwd().$uri)) { return false; }",
            "require getenv('FORMASI_BROWSER_ROOT').'/vendor/autoload.php';",
            "$app = require getenv('FORMASI_BROWSER_ROOT').'/bootstrap/app.php';",
            "$app->afterResolving(\\Illuminate\\Foundation\\Vite::class, function ($vite) { $vite->useHotFile(getenv('FORMASI_BROWSER_NO_HOT')); });",
            '$app->handleRequest(\\Illuminate\\Http\\Request::capture());',
        ].join('\n'));
        server = spawn('php', ['-S', `127.0.0.1:${port}`, router], {
            cwd: join(root, 'public'), env, windowsHide: true, stdio: 'ignore',
        });
        server.once('error', error => { serverError = error; });
        edge = spawn(edgeExecutable, [
            '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
            '--disable-extensions', `--remote-debugging-port=${debugPort}`,
            `--user-data-dir=${join(temporaryDirectory, 'edge-profile')}`,
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
        const page = await cdp.page();
        const screenshotDirectory = join(root, 'storage/framework/cache/formasi-browser');
        mkdirSync(screenshotDirectory, { recursive: true });

        async function visit(path) {
            await cdp.navigate(page, base + path);
            await cdp.until(page, () => !!document.querySelector('[data-app-content]'));
        }

        async function waitForPath(path) {
            await cdp.until(page, expected => location.pathname === expected && document.readyState === 'complete', path);
            await cdp.until(page, () => !!window.Alpine && !!document.querySelector('[data-app-content]'));
        }

        async function select(name, value) {
            await cdp.run(page, (fieldName, fieldValue) => {
                const input = document.querySelector(`#formation-filters select[name="${fieldName}"]`);
                if (!input || input.disabled) throw new Error(`Filter cannot be selected: ${fieldName}`);
                input.value = fieldValue;
                input.dispatchEvent(new Event('change', { bubbles: true }));
                return new Promise(resolvePromise => requestAnimationFrame(() => requestAnimationFrame(resolvePromise)));
            }, name, String(value));
        }

        async function filterState() {
            return cdp.run(page, () => Object.fromEntries([...document.querySelectorAll('#formation-filters select')].map(select => [select.name, {
                value: select.value, disabled: select.disabled,
                options: [...select.options].map(option => option.value).filter(Boolean),
            }])));
        }

        async function masterTabs() {
            const state = await cdp.run(page, () => ({
                count: document.querySelectorAll('nav[aria-label="Navigasi Formasi"]').length,
                active: [...document.querySelectorAll('nav[aria-label="Navigasi Formasi"] [aria-current="page"]')].map(link => link.textContent.trim()),
                duplicatedBack: document.querySelector('[data-app-content]').innerText.includes('Kembali ke Formasi'),
            }));
            assert.equal(state.count, 1, 'Master screens must contain one Formasi navigation.');
            assert.deepEqual(state.active, ['Master Organisasi']);
            assert.equal(state.duplicatedBack, false, 'The duplicate global back link must stay removed.');
        }

        async function responsive(name) {
            for (const width of [1440, 390]) {
                await cdp.send('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: false }, page);
                const dimensions = await cdp.run(page, async () => {
                    await new Promise(resolvePromise => requestAnimationFrame(() => requestAnimationFrame(resolvePromise)));
                    return { width: innerWidth, scroll: document.documentElement.scrollWidth };
                });
                assert.ok(dimensions.scroll <= dimensions.width + 1, `${name} overflows at ${width}px: ${JSON.stringify(dimensions)}`);
                const { data } = await cdp.send('Page.captureScreenshot', { format: 'png' }, page);
                writeFileSync(join(screenshotDirectory, `${name}-${width}.png`), Buffer.from(data, 'base64'));
            }
            await cdp.send('Emulation.clearDeviceMetricsOverride', {}, page);
        }

        async function theme(preference) {
            await cdp.run(page, () => document.querySelector('button[aria-label="Pilih tema tampilan"]').click());
            await cdp.until(page, selected => document.querySelector(`[data-theme-option="${selected}"]`)?.getBoundingClientRect().height > 0, preference);
            await cdp.run(page, selected => document.querySelector(`[data-theme-option="${selected}"]`).click(), preference);
            await cdp.until(page, selected => document.documentElement.dataset.theme === selected
                && document.documentElement.classList.contains('dark') === (selected === 'dark'), preference);
            await cdp.until(page, selected => document.querySelector(`[data-theme-option="${selected}"]`)?.getBoundingClientRect().height === 0, preference);
        }

        async function quickStatus(status, expectedPositionId) {
            await cdp.run(page, selected => {
                const link = document.querySelector(`[data-quick-status="${selected}"]`);
                if (!link) throw new Error(`Quick status is unavailable: ${selected}`);
                link.click();
            }, status);
            await cdp.until(page, selected => {
                const parameters = new URLSearchParams(location.search);
                return location.pathname === '/formasi' && document.readyState === 'complete'
                    && (selected === 'all' ? !parameters.get('gap_status') && !parameters.get('confirmation')
                        : selected === 'unconfirmed' ? parameters.get('confirmation') === 'unconfirmed' && !parameters.get('gap_status')
                            : parameters.get('gap_status') === selected && parameters.get('confirmation') === 'confirmed');
            }, status);
            if (expectedPositionId !== undefined) {
                assert.deepEqual(await cdp.run(page, () => [...document.querySelectorAll('[data-position-id]')].map(row => Number(row.dataset.positionId))), [expectedPositionId]);
            }
        }

        const context = {
            search: 'Formasi Browser', directorate_id: String(first.directorate),
            department_id: String(first.department), unit_id: String(first.unit), section_id: String(first.section),
            job_level: 'Staff', confirmation: 'unconfirmed', per_page: '20', page: '2',
        };
        const analysisPath = '/formasi?' + new URLSearchParams(context);
        await cdp.send('Page.navigate', { url: base + analysisPath }, page);
        await cdp.until(page, () => !!document.querySelector('input[name="email"]'));
        await cdp.run(page, () => {
            document.querySelector('input[name="email"]').value = 'formasi-browser@example.test';
            document.querySelector('input[name="password"]').value = 'password';
            document.querySelector('form').requestSubmit();
        });
        await waitForPath('/formasi');
        await cdp.until(page, target => !!document.querySelector(`[data-position-id="${target}"]`), ids.target);
        assert.deepEqual(await cdp.run(page, () => Object.fromEntries(new URLSearchParams(location.search))), context);
        await responsive('analysis');
        await theme('dark');
        await responsive('analysis-dark');
        await theme('light');

        await visit('/formasi?' + new URLSearchParams({ search: 'Formasi Browser', per_page: '20' }));
        const levelControl = await cdp.run(page, () => {
            const input = document.querySelector('[data-formation-level]');
            return {
                name: input?.name,
                visible: input?.getBoundingClientRect().height > 0,
                inDrawer: !!input?.closest('#formation-filters'),
                options: [...(input?.options ?? [])].map(option => option.value).filter(Boolean),
            };
        });
        assert.equal(levelControl.name, 'job_level');
        assert.equal(levelControl.visible, true, 'Level must be available without opening the filter drawer.');
        assert.equal(levelControl.inDrawer, false);
        assert.deepEqual(levelControl.options, ['General Manager', 'Senior Manager', 'Manager', 'Staff']);
        await cdp.run(page, () => {
            const input = document.querySelector('[data-formation-level]');
            input.value = 'Manager';
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
        await cdp.until(page, expectedId => location.pathname === '/formasi'
            && new URLSearchParams(location.search).get('job_level') === 'Manager'
            && document.readyState === 'complete' && !!document.querySelector(`[data-position-id="${expectedId}"]`), ids.level_positions.excess.id);
        assert.deepEqual(await cdp.run(page, () => [...document.querySelectorAll('[data-position-id]')].map(row => row.children[1].textContent.trim())), ['Manager']);
        assert.equal(await cdp.run(page, () => new URLSearchParams(location.search).get('search')), 'Formasi Browser');

        await visit('/formasi?' + new URLSearchParams({ ...context, page: '1' }));
        const pageTwo = await cdp.run(page, () => [...document.querySelectorAll('nav[role="navigation"] a[href]')]
            .find(link => new URL(link.href).searchParams.get('page') === '2')?.href);
        assert.ok(pageTwo, 'Staff analysis should expose its second page.');
        assert.equal(new URL(pageTwo).searchParams.get('job_level'), 'Staff');
        await cdp.run(page, href => document.querySelector(`a[href="${href}"]`).click(), pageTwo);
        await cdp.until(page, target => new URLSearchParams(location.search).get('page') === '2'
            && document.readyState === 'complete' && !!document.querySelector(`[data-position-id="${target}"]`), ids.target);
        assert.deepEqual(await cdp.run(page, () => Object.fromEntries(new URLSearchParams(location.search))), context);

        await cdp.run(page, () => document.querySelector('[data-filter-chip="job_level"]').click());
        await cdp.until(page, () => location.pathname === '/formasi' && document.readyState === 'complete'
            && !new URLSearchParams(location.search).has('job_level'));
        const removedLevel = await cdp.run(page, () => Object.fromEntries(new URLSearchParams(location.search)));
        for (const [name, value] of Object.entries(context).filter(([name]) => !['job_level', 'page'].includes(name))) {
            assert.equal(removedLevel[name], value, `Removing Level must preserve ${name}.`);
        }
        assert.notEqual(removedLevel.page, '2', 'Removing a filter must restart pagination.');

        await visit('/formasi?' + new URLSearchParams({ search: 'Formasi Browser', job_level: 'Manager', confirmation: 'unconfirmed', per_page: '20' }));
        await quickStatus('excess', ids.level_positions.excess.id);
        assert.equal(await cdp.run(page, () => new URLSearchParams(location.search).get('job_level')), 'Manager');
        assert.equal(await cdp.run(page, () => new URLSearchParams(location.search).get('search')), 'Formasi Browser');
        await quickStatus('all', ids.level_positions.excess.id);
        assert.equal(await cdp.run(page, () => new URLSearchParams(location.search).get('job_level')), 'Manager');
        await visit('/formasi?' + new URLSearchParams({ search: 'Formasi Browser', per_page: '20' }));
        for (const status of ['shortage', 'fulfilled', 'excess', 'vacant']) {
            await quickStatus(status, ids.level_positions[status].id);
        }
        await quickStatus('unconfirmed');
        const unconfirmedStatuses = await cdp.run(page, () => [...document.querySelectorAll('[data-position-id]')].map(row => row.children[8].textContent.trim()));
        assert.equal(unconfirmedStatuses.length, 20);
        assert.ok(unconfirmedStatuses.every(status => status === 'Belum ditetapkan'));

        await visit(analysisPath);
        await cdp.run(page, target => document.querySelector(`[data-position-expand="${target}"]`).click(), ids.target);
        await cdp.until(page, target => document.querySelector(`[data-position-expand="${target}"]`)?.getAttribute('aria-expanded') === 'true'
            && document.querySelector(`[data-position-details="${target}"]`)?.getBoundingClientRect().height > 0, ids.target);
        await responsive('analysis-row-details');
        await cdp.run(page, target => document.querySelector(`[data-position-expand="${target}"]`).click(), ids.target);
        await cdp.until(page, target => document.querySelector(`[data-position-expand="${target}"]`)?.getAttribute('aria-expanded') === 'false'
            && document.querySelector(`[data-position-details="${target}"]`)?.getBoundingClientRect().height === 0, ids.target);

        await cdp.run(page, () => document.querySelector('[data-filter-trigger]').click());
        await cdp.until(page, () => document.querySelector('#formation-filters')?.getBoundingClientRect().width > 0);
        const hydrated = await filterState();
        for (const [name, value] of Object.entries(context).filter(([name]) => ['directorate_id', 'department_id', 'unit_id', 'section_id', 'confirmation'].includes(name))) {
            assert.equal(hydrated[name].value, value, `Teleported filter must hydrate ${name}.`);
        }
        assert.equal(hydrated.gap_status.disabled, true);
        await responsive('filter-drawer');

        await select('directorate_id', second.directorate);
        const changedDirectorate = await filterState();
        for (const [name, id] of [['department_id', second.department], ['unit_id', second.unit], ['section_id', second.section]]) {
            assert.equal(changedDirectorate[name].value, '', `${name} must reset after Directorate changes.`);
            assert.deepEqual(changedDirectorate[name].options, [String(id)], `${name} must contain only descendants of the selected Directorate.`);
        }
        await select('department_id', second.department);
        await select('unit_id', second.unit);
        await select('section_id', second.section);
        await select('confirmation', '');
        await select('gap_status', 'vacant');
        assert.equal((await filterState()).confirmation.value, 'confirmed', 'Choosing a gap status must require confirmed needs.');
        await select('confirmation', 'unconfirmed');
        const unconfirmed = await filterState();
        assert.equal(unconfirmed.gap_status.value, '');
        assert.equal(unconfirmed.gap_status.disabled, true);
        await cdp.run(page, () => document.querySelector('#formation-filters form').requestSubmit());
        await cdp.until(page, directorateId => location.pathname === '/formasi' && new URLSearchParams(location.search).get('directorate_id') === String(directorateId) && document.readyState === 'complete', second.directorate);
        assert.equal(await cdp.run(page, () => document.querySelectorAll('[data-position-id]').length), 1);
        assert.equal(await cdp.run(page, () => document.querySelector('[data-position-id]').innerText.includes('Formasi Browser Lain')), true);

        await visit(analysisPath);
        await cdp.run(page, target => document.querySelector(`[data-position-id="${target}"] a`).click(), ids.target);
        await waitForPath(`/formasi/positions/${ids.target}/kebutuhan`);
        const dedicatedForm = await cdp.run(page, () => ({
            count: document.querySelector('input[name="formation_count"]').value,
            confirmed: document.querySelector('input[name="confirm_formation"]').checked,
            editableFields: [...document.querySelectorAll('form input:not([type="hidden"]), form select')].map(input => input.name),
            cancel: [...document.querySelectorAll('form a')].find(link => link.textContent.trim() === 'Batal').href,
        }));
        assert.equal(dedicatedForm.count, '1');
        assert.equal(dedicatedForm.confirmed, false);
        assert.deepEqual(dedicatedForm.editableFields, ['formation_count', 'confirm_formation']);
        assert.deepEqual(Object.fromEntries(new URL(dedicatedForm.cancel).searchParams), context);
        await responsive('manual-requirement');
        await cdp.run(page, () => [...document.querySelectorAll('form a')].find(link => link.textContent.trim() === 'Batal').click());
        await waitForPath('/formasi');
        assert.deepEqual(await cdp.run(page, () => Object.fromEntries(new URLSearchParams(location.search))), context, 'Cancel must preserve the analysis filters and page.');
        assert.equal(await cdp.run(page, target => document.querySelector(`[data-position-id="${target}"]`).children[5].textContent.trim(), ids.target), 'Belum ditetapkan');

        await cdp.run(page, target => document.querySelector(`[data-position-id="${target}"] a`).click(), ids.target);
        await waitForPath(`/formasi/positions/${ids.target}/kebutuhan`);
        await cdp.run(page, () => {
            document.querySelector('input[name="formation_count"]').value = '1';
            document.querySelector('input[name="confirm_formation"]').click();
            document.querySelector('input[name="formation_count"]').form.requestSubmit();
        });
        await cdp.until(page, () => location.pathname === '/formasi' && new URLSearchParams(location.search).get('page') === '1' && !!document.querySelector('[role="status"]') && document.readyState === 'complete');
        assert.deepEqual(await cdp.run(page, () => Object.fromEntries(new URLSearchParams(location.search))), { ...context, page: '1' }, 'A disappearing page-2 row must return to the last valid page without losing filters.');
        const saved = await cdp.run(page, target => ({
            flash: document.querySelector('[role="status"]').innerText,
            flashVisible: document.querySelector('[role="status"]').getBoundingClientRect().height > 0,
            rows: document.querySelectorAll('[data-position-id]').length,
            removed: !document.querySelector(`[data-position-id="${target}"]`),
        }), ids.target);
        assert.match(saved.flash, /berhasil ditetapkan/);
        assert.ok(saved.flash.includes(`ID ${ids.target}`));
        assert.equal(saved.flashVisible, true);
        assert.equal(saved.rows, 20);
        assert.equal(saved.removed, true);
        await visit('/formasi?' + new URLSearchParams({ ...context, confirmation: 'confirmed', page: '1' }));
        const confirmedCells = await cdp.run(page, target => [...document.querySelector(`[data-position-id="${target}"]`).children].map(cell => cell.textContent.trim()), ids.target);
        assert.deepEqual(confirmedCells.slice(5, 9), ['1', '1', '0', 'Terpenuhi'], 'A manually confirmed need of one must match one linked employee.');

        await visit('/formasi/struktur');
        await cdp.run(page, () => document.querySelector('[data-structure-expand]').click());
        await cdp.until(page, () => {
            const branches = [...document.querySelectorAll('details[data-structure-branch]')];
            return branches.length > 0 && branches.every(branch => branch.open);
        });
        await responsive('structure');
        await theme('dark');
        await responsive('structure-dark');
        await theme('light');
        await cdp.run(page, () => document.querySelector('[data-structure-collapse]').click());
        await cdp.until(page, () => [...document.querySelectorAll('details[data-structure-branch]')].every(branch => !branch.open));
        await cdp.run(page, () => document.querySelector('[data-structure-expand]').click());
        await cdp.until(page, () => [...document.querySelectorAll('details[data-structure-branch]')].every(branch => branch.open));
        await visit('/formasi/master');
        await masterTabs();
        await responsive('master');
        for (const area of ['directorates', 'departments', 'units', 'sections', 'positions']) {
            await cdp.run(page, path => document.querySelector(`[data-app-content] a[href="${location.origin}${path}"]`).click(), `/organization/${area}`);
            await waitForPath(`/organization/${area}`);
            await masterTabs();
            const back = await cdp.run(page, () => document.querySelector('[data-app-content] a[aria-label="Master Organisasi"]').href);
            assert.equal(back, base + '/formasi/master');
            await cdp.run(page, () => document.querySelector('[data-app-content] a[aria-label="Master Organisasi"]').click());
            await waitForPath('/formasi/master');
        }

        await visit('/organization/directorates');
        await cdp.run(page, () => document.querySelector('a[href$="/organization/directorates/create"]').click());
        await waitForPath('/organization/directorates/create');
        await masterTabs();
        await responsive('master-create');
        await cdp.run(page, () => {
            document.querySelector('input[name="code"]').value = 'FB-TEMP';
            document.querySelector('input[name="name"]').value = 'Directorate Browser Tambahan';
            document.querySelector('input[name="name"]').form.requestSubmit();
        });
        await waitForPath('/organization/directorates');
        await masterTabs();
        await cdp.until(page, () => [...document.querySelectorAll('tbody tr')].some(row => row.innerText.includes('Directorate Browser Tambahan')));
        const editPath = await cdp.run(page, () => {
            const row = [...document.querySelectorAll('tbody tr')].find(row => row.innerText.includes('Directorate Browser Tambahan'));
            return new URL(row.querySelector('a').href).pathname;
        });
        await cdp.run(page, path => document.querySelector(`a[href="${location.origin}${path}"]`).click(), editPath);
        await waitForPath(editPath);
        await masterTabs();
        await responsive('master-edit');
        await cdp.run(page, () => [...document.querySelectorAll('form a')].find(link => link.textContent.trim() === 'Batal').click());
        await waitForPath('/organization/directorates');
        await masterTabs();
        await visit(editPath);
        await cdp.run(page, () => {
            document.querySelector('input[name="name"]').value = 'Directorate Browser Diperbarui';
            document.querySelector('input[name="name"]').form.requestSubmit();
        });
        await waitForPath('/organization/directorates');
        await masterTabs();
        await cdp.until(page, () => [...document.querySelectorAll('tbody tr')].some(row => row.innerText.includes('Directorate Browser Diperbarui')));
        await cdp.run(page, () => [...document.querySelectorAll('tbody tr')].find(row => row.innerText.includes('Directorate Browser Diperbarui')).querySelector('form').requestSubmit());
        await cdp.until(page, () => !!document.querySelector('.swal2-popup .swal2-confirm'));
        await cdp.run(page, () => document.querySelector('.swal2-cancel').click());
        await cdp.until(page, () => !document.querySelector('.swal2-popup'));
        assert.equal(await cdp.run(page, () => document.querySelector('tbody').innerText.includes('Directorate Browser Diperbarui')), true);
        await cdp.run(page, () => [...document.querySelectorAll('tbody tr')].find(row => row.innerText.includes('Directorate Browser Diperbarui')).querySelector('form').requestSubmit());
        await cdp.until(page, () => !!document.querySelector('.swal2-popup .swal2-confirm'));
        await cdp.run(page, () => document.querySelector('.swal2-confirm').click());
        await cdp.until(page, () => location.pathname === '/organization/directorates' && document.readyState === 'complete' && !document.querySelector('tbody').innerText.includes('Directorate Browser Diperbarui'));
        await masterTabs();
        await cdp.run(page, () => document.querySelector('[data-app-content] a[aria-label="Master Organisasi"]').click());
        await waitForPath('/formasi/master');
        await masterTabs();

        assert.deepEqual(cdp.errors, [], 'Browser JavaScript errors');
        console.log('Verified isolated Formasi journeys: visible Level filter, pagination and return context, removable chips, quick statuses, row details, teleported filters, hierarchy cascades and expand/collapse, explicit need=1, page clamp with flash, master CRUD/tabs, light/dark mobile and desktop layout.');
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
            && basename(absoluteTemporaryDirectory).startsWith('hc-formasi-browser-'), 'Unsafe browser test cleanup path.');
        rmSync(absoluteTemporaryDirectory, { recursive: true, force: true, maxRetries: 10, retryDelay: 200 });
    }
});
