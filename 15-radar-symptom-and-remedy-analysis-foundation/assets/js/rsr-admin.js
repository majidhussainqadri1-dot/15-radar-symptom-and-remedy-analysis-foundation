(() => {
    'use strict';
    const app = document.querySelector('[data-rsr-manage-app]');
    if (!app || !window.RSR_API) return;

    const editor = app.querySelector('[data-rsr-source-editor]');
    const form = app.querySelector('[data-rsr-source-form]');
    const sourceStatus = app.querySelector('[data-rsr-source-status]');
    const reportStatus = app.querySelector('[data-rsr-report-status]');

    const setStatus = (node, text, error = false) => {
        if (!node) return;
        node.textContent = text;
        node.dataset.state = error ? 'error' : 'ok';
    };

    const parse = (text, fallback = {}) => {
        try { return JSON.parse(text); } catch (error) { return fallback; }
    };

    const openEditor = (source = {}) => {
        if (!editor || !form) return;
        editor.hidden = false;
        form.reset();
        form.elements.public_id.value = source.public_id || '';
        form.elements.version.value = String(source.version || 0);
        form.elements.name.value = source.name || '';
        form.elements.dataset.value = source.dataset || '';
        form.elements.edition.value = source.edition || '';
        form.elements.provider_key.value = source.provider_key || 'manual';
        form.elements.license_code.value = source.license_code || 'internal-reviewed-aggregate';
        form.elements.review_date.value = source.review_date || new Date().toISOString().slice(0, 10);
        form.elements.territory.value = source.territory || 'global';
        form.elements.quality_score.value = String(source.quality_score ?? 0.5);
        form.elements.status.value = source.status || 'configured';
        form.elements.rate_limit_per_hour.value = String(source.rate_limit_per_hour ?? 1000);
        form.elements.quota_remaining.value = source.quota_remaining ?? '';
        form.elements.cost_model.value = source.cost_model || 'free';
        form.elements.stale_after_seconds.value = String(source.stale_after_seconds ?? 86400);
        form.elements.restrictions.value = source.restrictions || '';
        form.elements.credentials_ref.value = source.credentials_ref || '';
        form.elements.rows_json.value = JSON.stringify(source.config?.rows || [], null, 2);
        form.elements.name.focus();
        editor.scrollIntoView({ block: 'start' });
    };

    app.querySelector('[data-rsr-source-new]')?.addEventListener('click', () => openEditor());
    app.querySelector('[data-rsr-source-cancel]')?.addEventListener('click', () => { if (editor) editor.hidden = true; });

    app.querySelector('[data-rsr-source-list]')?.addEventListener('click', async (event) => {
        const row = event.target.closest('[data-source]');
        if (!row) return;
        const source = parse(row.dataset.source || '{}');
        if (event.target.closest('[data-rsr-source-edit]')) openEditor(source);
        if (event.target.closest('[data-rsr-source-ingest]')) {
            const windowType = window.prompt('Window: daily, weekly, monthly, or yearly', 'daily');
            if (!windowType) return;
            setStatus(sourceStatus, 'Running ingestion…');
            try {
                const token = `ui|${source.public_id}|${windowType}|${new Date().toISOString()}`;
                const payload = {
                    source_public_id: source.public_id,
                    window_type: windowType,
                    display_timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC',
                    geography: 'global',
                };
                if (Array.isArray(source.config?.rows)) payload.manual_rows = source.config.rows;
                const result = await window.RSR_API.fetch('manage/ingestion', {
                    method: 'POST',
                    headers: { 'Idempotency-Key': token },
                    body: JSON.stringify(payload),
                });
                setStatus(sourceStatus, `Ingestion ${result.status}.`);
                window.setTimeout(() => window.location.reload(), 700);
            } catch (error) {
                setStatus(sourceStatus, error.message, true);
            }
        }
    });

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        setStatus(sourceStatus, 'Saving source…');
        const rows = parse(form.elements.rows_json.value, null);
        if (!Array.isArray(rows)) {
            setStatus(sourceStatus, 'Manual aggregate rows must be a JSON array.', true);
            return;
        }
        const id = form.elements.public_id.value;
        const payload = {
            public_id: id || undefined,
            version: Number(form.elements.version.value || 0),
            name: form.elements.name.value,
            dataset: form.elements.dataset.value,
            edition: form.elements.edition.value,
            provider_key: form.elements.provider_key.value,
            license_code: form.elements.license_code.value,
            review_date: form.elements.review_date.value,
            territory: form.elements.territory.value || 'global',
            quality_score: Number(form.elements.quality_score.value || 0.5),
            status: form.elements.status.value,
            rate_limit_per_hour: Number(form.elements.rate_limit_per_hour.value || 1000),
            quota_remaining: form.elements.quota_remaining.value === '' ? null : Number(form.elements.quota_remaining.value),
            cost_model: form.elements.cost_model.value || 'free',
            stale_after_seconds: Number(form.elements.stale_after_seconds.value || 86400),
            restrictions: form.elements.restrictions.value,
            credentials_ref: form.elements.credentials_ref.value,
            config: { rows },
        };
        try {
            await window.RSR_API.fetch(id ? `manage/sources/${encodeURIComponent(id)}` : 'manage/sources', {
                method: id ? 'PUT' : 'POST',
                body: JSON.stringify(payload),
            });
            setStatus(sourceStatus, 'Source saved.');
            window.setTimeout(() => window.location.reload(), 600);
        } catch (error) {
            setStatus(sourceStatus, error.message, true);
        }
    });

    app.addEventListener('click', async (event) => {
        const row = event.target.closest('[data-report]');
        if (!row) return;
        const report = parse(row.dataset.report || '{}');
        const transition = event.target.closest('[data-rsr-report-transition]');
        if (transition) {
            const toState = transition.dataset.rsrReportTransition;
            const reason = window.prompt(`Reason for transition to ${toState}`, 'Reviewed according to File 15 editorial policy.');
            if (reason === null) return;
            setStatus(reportStatus, 'Updating report…');
            try {
                await window.RSR_API.fetch(`manage/reports/${encodeURIComponent(report.public_id)}/transition`, {
                    method: 'POST',
                    body: JSON.stringify({ to_state: toState, version: report.version, reason }),
                });
                setStatus(reportStatus, `Report moved to ${toState}.`);
                window.setTimeout(() => window.location.reload(), 600);
            } catch (error) {
                setStatus(reportStatus, error.message, true);
            }
        }
        if (event.target.closest('[data-rsr-report-correct]')) {
            const action = window.prompt('Type “correct” or “retract”', 'correct');
            if (!['correct', 'retract'].includes(String(action))) return;
            const reason = window.prompt('Internal correction reason');
            if (!reason) return;
            const publicNotice = window.prompt('Public correction notice');
            if (!publicNotice) return;
            setStatus(reportStatus, 'Applying correction…');
            try {
                await window.RSR_API.fetch(`manage/reports/${encodeURIComponent(report.public_id)}/correction`, {
                    method: 'POST',
                    body: JSON.stringify({ action, version: report.version, reason, public_notice: publicNotice }),
                });
                setStatus(reportStatus, `Report ${action === 'retract' ? 'retracted' : 'corrected'}.`);
                window.setTimeout(() => window.location.reload(), 600);
            } catch (error) {
                setStatus(reportStatus, error.message, true);
            }
        }
    });
})();
