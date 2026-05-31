/**
 * E2E: два WebSocket-клиента (вкладки) + export/mail через main-app REST.
 */
const path = require('node:path');

function loadPusher() {
    const candidates = [
        path.join(__dirname, 'node_modules', 'pusher-js'),
        path.join(__dirname, '..', 'main-app', 'node_modules', 'pusher-js'),
    ];

    for (const candidate of candidates) {
        try {
            return require(candidate).Pusher;
        } catch {
            // try next path
        }
    }

    return require('pusher-js').Pusher;
}

const Pusher = loadPusher();

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

const token = process.env.TOKEN;
const reverbKey = process.env.REVERB_APP_KEY ?? 'local-app-key';
const reverbHost = process.env.REVERB_HOST ?? 'localhost';
const reverbPort = Number(process.env.REVERB_PORT ?? 8090);
const authBaseUrl = process.env.AUTH_BASE_URL ?? 'http://localhost';
const mainAppBaseUrl = process.env.MAIN_APP_URL ?? authBaseUrl;
const serviceBBaseUrl = process.env.SERVICE_B_URL ?? 'http://localhost:8082';
const timeoutMs = Number(process.env.E2E_TIMEOUT_MS ?? 120000);

if (!token) {
    console.error('TOKEN env is required');
    process.exit(1);
}

const eventsByTab = [[], []];

function createClient(tabIndex) {
    const pusher = new Pusher(reverbKey, {
        wsHost: reverbHost,
        wsPort: reverbPort,
        wssPort: reverbPort,
        forceTLS: false,
        enabledTransports: ['ws', 'wss'],
        cluster: '',
        authEndpoint: `${authBaseUrl}/broadcasting/auth`,
        auth: {
            headers: {
                Authorization: `Bearer ${token}`,
                Accept: 'application/json',
            },
        },
        wsOptions: {
            headers: {
                Origin: authBaseUrl,
            },
        },
    });

    const channel = pusher.subscribe('private-report-jobs.stats');

    channel.bind('pusher:subscription_succeeded', () => {
        console.log(`[tab-${tabIndex + 1}] subscribed to private-report-jobs.stats`);
    });

    channel.bind('pusher:subscription_error', (status) => {
        console.error(`[tab-${tabIndex + 1}] subscription error`, status);
    });

    const handleStatsEvent = (payload) => {
        const entry = {
            at: new Date().toISOString(),
            csv_pending: payload?.by_type?.csv_download?.pending ?? null,
            csv_processing: payload?.by_type?.csv_download?.processing ?? null,
            csv_completed: payload?.by_type?.csv_download?.completed ?? null,
            mail_pending: payload?.by_type?.html_email?.pending ?? null,
            mail_processing: payload?.by_type?.html_email?.processing ?? null,
            mail_completed: payload?.by_type?.html_email?.completed ?? null,
        };
        eventsByTab[tabIndex].push(entry);
        console.log(`[tab-${tabIndex + 1}] event ${JSON.stringify(entry)}`);
    };

    channel.bind('ReportJobStatsChanged', handleStatsEvent);
    channel.bind('.ReportJobStatsChanged', handleStatsEvent);

    pusher.connection.bind('connected', () => {
        console.log(`[tab-${tabIndex + 1}] connected`);
    });

    pusher.connection.bind('error', (err) => {
        console.error(`[tab-${tabIndex + 1}] connection error`, err);
    });

    return pusher;
}

async function waitFor(predicate, label) {
    const started = Date.now();
    while (Date.now() - started < timeoutMs) {
        if (predicate()) {
            return;
        }
        await sleep(250);
    }
    throw new Error(`Timeout: ${label}`);
}

async function fetchMainAppJson(path, options = {}) {
    const response = await fetch(`${mainAppBaseUrl}${path}`, {
        ...options,
        headers: {
            Accept: 'application/json',
            Authorization: `Bearer ${token}`,
            ...(options.headers ?? {}),
        },
    });

    if (!response.ok) {
        const body = await response.text();
        throw new Error(`${options.method ?? 'GET'} ${path} -> ${response.status}: ${body}`);
    }

    return response.json();
}

async function createReportJob(reportType) {
    const response = await fetch(`${serviceBBaseUrl}/api/sales-outlets/reports`, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-User-Id': '1',
        },
        body: JSON.stringify({
            report_type: reportType,
            columns: ['id', 'shop'],
            sort: 'id',
            direction: 'asc',
        }),
    });

    if (!response.ok) {
        const body = await response.text();
        throw new Error(`POST service-b report (${reportType}) -> ${response.status}: ${body}`);
    }

    return response.json();
}

function bothTabsMatch(check) {
    const last0 = eventsByTab[0].at(-1);
    const last1 = eventsByTab[1].at(-1);
    return last0 && last1 && check(last0) && check(last1);
}

function bothTabsHaveEvents(min = 1) {
    return eventsByTab[0].length >= min && eventsByTab[1].length >= min;
}

(async () => {
    const clients = [createClient(0), createClient(1)];

    await sleep(3000);

    const initial = await fetchMainAppJson('/objects-sales-outlets-2/reports/stats');
    const initialCsvPending = initial.by_type.csv_download.pending;
    const initialMailPending = initial.by_type.html_email.pending;
    const initialCsvCompleted = initial.by_type.csv_download.completed;
    const initialMailCompleted = initial.by_type.html_email.completed;

    console.log(`[e2e] initial csv pending=${initialCsvPending}, mail pending=${initialMailPending}`);

    console.log('[e2e] triggering CSV export (service-b)...');
    const exportJob = await createReportJob('csv_download');
    console.log(`[e2e] export uuid=${exportJob.uuid}`);

    const expectedCsvPending = initialCsvPending + 1;
    await waitFor(
        () => bothTabsHaveEvents() && bothTabsMatch((e) => e.csv_pending === expectedCsvPending),
        `both tabs see csv pending=${expectedCsvPending}`,
    );
    console.log(`[e2e] PASS both tabs received csv pending=${expectedCsvPending}`);

    await waitFor(
        () => bothTabsMatch((e) => e.csv_processing >= 1),
        'both tabs see csv processing',
    );
    console.log('[e2e] PASS both tabs received csv processing');

    const expectedCsvCompleted = initialCsvCompleted + 1;
    await waitFor(
        () => bothTabsMatch((e) => e.csv_completed === expectedCsvCompleted && e.csv_processing === 0),
        `both tabs see csv completed=${expectedCsvCompleted}`,
    );
    console.log(`[e2e] PASS both tabs received csv completed=${expectedCsvCompleted}`);

    console.log('[e2e] triggering mail report (service-b)...');
    const mailJob = await createReportJob('html_email');
    console.log(`[e2e] mail uuid=${mailJob.uuid}`);

    const expectedMailPending = initialMailPending + 1;
    await waitFor(
        () => bothTabsHaveEvents(2) && bothTabsMatch((e) => e.mail_pending === expectedMailPending),
        `both tabs see mail pending=${expectedMailPending}`,
    );
    console.log(`[e2e] PASS both tabs received mail pending=${expectedMailPending}`);

    await waitFor(
        () => bothTabsMatch((e) => e.mail_processing >= 1),
        'both tabs see mail processing',
    );
    console.log('[e2e] PASS both tabs received mail processing');

    const expectedMailCompleted = initialMailCompleted + 1;
    await waitFor(
        () => bothTabsMatch((e) => e.mail_completed === expectedMailCompleted && e.mail_processing === 0),
        `both tabs see mail completed=${expectedMailCompleted}`,
    );
    console.log(`[e2e] PASS both tabs received mail completed=${expectedMailCompleted}`);

    const finalStats = await fetchMainAppJson('/objects-sales-outlets-2/reports/stats');
    console.log(`[e2e] final REST stats: ${JSON.stringify(finalStats.by_type)}`);
    console.log(`[e2e] tab1 events=${eventsByTab[0].length}, tab2 events=${eventsByTab[1].length}`);
    console.log('[e2e] ALL CHECKS PASSED');

    clients.forEach((client) => client.disconnect());
})().catch((error) => {
    console.error('[e2e] FAILED', error);
    process.exit(1);
});
