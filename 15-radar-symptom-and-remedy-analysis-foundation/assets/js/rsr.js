(() => {
    'use strict';

    const config = window.RSR_CONFIG || {};
    const qs = (selector, root = document) => root.querySelector(selector);
    const qsa = (selector, root = document) => Array.from(root.querySelectorAll(selector));

    const sameOriginUrl = (candidate, fallback = '/') => {
        try {
            const url = new URL(candidate || fallback, window.location.origin);
            return url.origin === window.location.origin ? url.href : new URL(fallback, window.location.origin).href;
        } catch (error) {
            return new URL(fallback, window.location.origin).href;
        }
    };

    const safeBack = (button) => {
        const fallback = sameOriginUrl(button.dataset.fallback || '/', '/');
        try {
            if (document.referrer) {
                const referrer = new URL(document.referrer);
                if (referrer.origin === window.location.origin && window.history.length > 1) {
                    window.history.back();
                    return;
                }
            }
        } catch (error) {
            // Safe fallback below.
        }
        window.location.assign(fallback);
    };

    qsa('[data-rsr-back]').forEach((button) => button.addEventListener('click', () => safeBack(button)));

    const compareForm = qs('[data-rsr-compare-form]');
    if (compareForm) {
        const boxes = qsa('input[type="checkbox"][name="remedies[]"]', compareForm);
        const count = qs('[data-rsr-compare-count]', compareForm);
        const submit = qs('[data-rsr-compare-submit]', compareForm);
        const refresh = (changed) => {
            const selected = boxes.filter((box) => box.checked);
            if (selected.length > Number(config.maxCompare || 3) && changed) {
                changed.checked = false;
            }
            const actual = boxes.filter((box) => box.checked).length;
            if (count) count.textContent = `${actual} / ${Number(config.maxCompare || 3)}`;
            if (submit) submit.disabled = actual < 1;
        };
        boxes.forEach((box) => box.addEventListener('change', () => refresh(box)));
        refresh();
    }

    const apiFetch = async (path, options = {}) => {
        const headers = new Headers(options.headers || {});
        headers.set('Accept', 'application/json');
        if (options.body && !headers.has('Content-Type')) headers.set('Content-Type', 'application/json');
        if (config.nonce) headers.set('X-WP-Nonce', config.nonce);
        if (config.traceId) headers.set('X-RSR-Trace-Id', config.traceId);
        const response = await fetch(`${config.restRoot || '/wp-json/rsr/v1/'}${path}`, {
            credentials: 'same-origin',
            cache: 'no-store',
            referrerPolicy: 'no-referrer',
            ...options,
            headers,
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            const message = payload.message || payload?.data?.message || config.strings?.error || 'Request failed.';
            const error = new Error(message);
            error.code = payload.code || 'request_failed';
            error.status = response.status;
            throw error;
        }
        return payload.data ?? payload;
    };

    const parseJson = (text, fallback) => {
        try {
            return JSON.parse(text);
        } catch (error) {
            return fallback;
        }
    };

    const setLive = (element, message, state = 'ok') => {
        if (!element) return;
        element.textContent = message;
        element.dataset.state = state;
    };

    const studiesApp = qs('[data-rsr-studies-app]');
    if (studiesApp) {
        const form = qs('[data-rsr-study-form]', studiesApp);
        const status = qs('[data-rsr-study-status]', studiesApp);
        const list = qs('[data-rsr-study-list]', studiesApp);
        const reset = () => {
            if (!form) return;
            form.reset();
            form.elements.public_id.value = '';
            form.elements.version.value = '0';
            form.elements.query_json.value = '{"mode":"AND","keyword":"","filters":{}}';
            setLive(status, '');
            qs('[name="title"]', form)?.focus();
        };

        qs('[data-rsr-study-reset]', studiesApp)?.addEventListener('click', reset);

        const formPayload = () => {
            const query = parseJson(form.elements.query_json.value, null);
            if (!query || typeof query !== 'object') {
                throw new Error(config.strings?.invalidStructuredQuery || 'Structured query JSON is invalid.');
            }
            query.keyword = String(form.elements.keyword.value || '').trim();
            return {
                title: form.elements.title.value,
                version: Number(form.elements.version.value || 0),
                query,
                remedy_refs: String(form.elements.remedy_refs.value || '').split(',').map((item) => item.trim()).filter(Boolean),
                tags: String(form.elements.tags.value || '').split(',').map((item) => item.trim()).filter(Boolean),
                notes: form.elements.notes.value,
                status: 'saved',
            };
        };

        form?.addEventListener('submit', async (event) => {
            event.preventDefault();
            setLive(status, config.strings?.loading || 'Loading…');
            try {
                const payload = formPayload();
                const id = form.elements.public_id.value;
                const result = await apiFetch(id ? `studies/${encodeURIComponent(id)}` : 'studies', {
                    method: id ? 'PUT' : 'POST',
                    body: JSON.stringify(payload),
                });
                setLive(status, config.strings?.saved || 'Saved.');
                window.setTimeout(() => window.location.reload(), 450);
                return result;
            } catch (error) {
                setLive(status, error.message, 'error');
            }
        });

        list?.addEventListener('click', async (event) => {
            const card = event.target.closest('[data-study]');
            if (!card || !form) return;
            const study = parseJson(card.dataset.study || '{}', {});
            if (event.target.closest('[data-rsr-study-edit]')) {
                form.elements.public_id.value = study.public_id || '';
                form.elements.version.value = String(study.version || 0);
                form.elements.title.value = study.title || '';
                form.elements.keyword.value = study.query?.keyword || '';
                form.elements.query_json.value = JSON.stringify(study.query || {}, null, 2);
                form.elements.remedy_refs.value = (study.remedy_refs || []).join(', ');
                form.elements.tags.value = (study.tags || []).join(', ');
                form.elements.notes.value = study.notes || '';
                form.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
                form.elements.title.focus();
            }
            if (event.target.closest('[data-rsr-study-delete]')) {
                if (!window.confirm(config.strings?.confirmDelete || 'Delete this study?')) return;
                setLive(status, config.strings?.loading || 'Loading…');
                try {
                    await apiFetch(`studies/${encodeURIComponent(study.public_id)}?version=${encodeURIComponent(study.version)}`, {
                        method: 'DELETE',
                        headers: { 'If-Match': `"${study.version}"` },
                    });
                    card.remove();
                    setLive(status, config.strings?.deleted || 'Deleted.');
                } catch (error) {
                    setLive(status, error.message, 'error');
                }
            }
        });

        qs('[data-rsr-export]', studiesApp)?.addEventListener('click', async (event) => {
            event.preventDefault();
            setLive(status, config.strings?.loading || 'Loading…');
            try {
                const data = await apiFetch('studies/export');
                const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = `file-15-private-radar-studies-${new Date().toISOString().slice(0, 10)}.json`;
                document.body.appendChild(link);
                link.click();
                link.remove();
                window.setTimeout(() => URL.revokeObjectURL(url), 0);
                setLive(status, config.strings?.exportPrepared || 'Export prepared.');
            } catch (error) {
                setLive(status, error.message, 'error');
            }
        });
    }

    window.RSR_API = Object.freeze({ fetch: apiFetch });
})();
