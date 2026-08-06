<?php
$view = RSR_Routes::view();
get_header();
?>
<div class="rsr-page rsr-manage-page" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>" data-rsr-manage-app>
    <?php RSR_Routes::context_controls(['fallback' => $view['urls']['radar']]); ?>
    <header class="rsr-hero">
        <div>
            <p class="rsr-eyebrow"><?php esc_html_e('Restricted operations workspace', RSR_TEXT_DOMAIN); ?></p>
            <h1><?php esc_html_e('Radar and Trend Operations', RSR_TEXT_DOMAIN); ?></h1>
            <p><?php esc_html_e('Govern sources, run idempotent ingestion, review reports, publish only after approval, and retain rollback evidence.', RSR_TEXT_DOMAIN); ?></p>
        </div>
        <span class="rsr-status"><?php echo esc_html((string)$view['diagnostics']['plugin_version']); ?></span>
    </header>

    <section class="rsr-dashboard-grid">
        <article class="rsr-stat"><strong><?php echo esc_html((string)count((array)$view['sources'])); ?></strong><span><?php esc_html_e('Visible sources', RSR_TEXT_DOMAIN); ?></span></article>
        <article class="rsr-stat"><strong><?php echo esc_html((string)count((array)$view['reports'])); ?></strong><span><?php esc_html_e('Visible reports', RSR_TEXT_DOMAIN); ?></span></article>
        <article class="rsr-stat"><strong><?php echo esc_html((string)array_sum((array)($view['diagnostics']['rows'] ?? []))); ?></strong><span><?php esc_html_e('Governed rows', RSR_TEXT_DOMAIN); ?></span></article>
        <article class="rsr-stat"><strong><?php echo empty($view['diagnostics']['missing_tables']) ? '✓' : '!'; ?></strong><span><?php esc_html_e('Schema health', RSR_TEXT_DOMAIN); ?></span></article>
    </section>

    <?php if (!empty($view['can_manage_sources']) || !empty($view['can_run_ingestion'])) : ?>
        <section class="rsr-panel">
            <div class="rsr-section-heading">
                <h2><?php esc_html_e('Trend sources', RSR_TEXT_DOMAIN); ?></h2>
                <?php if (!empty($view['can_manage_sources'])) : ?><button type="button" class="rsr-button" data-rsr-source-new><?php esc_html_e('Add source', RSR_TEXT_DOMAIN); ?></button><?php endif; ?>
            </div>
            <div class="rsr-table-wrap">
                <table class="rsr-table">
                    <thead><tr><th><?php esc_html_e('Source', RSR_TEXT_DOMAIN); ?></th><th><?php esc_html_e('Provider', RSR_TEXT_DOMAIN); ?></th><th><?php esc_html_e('License', RSR_TEXT_DOMAIN); ?></th><th><?php esc_html_e('Status', RSR_TEXT_DOMAIN); ?></th><th><?php esc_html_e('Last success', RSR_TEXT_DOMAIN); ?></th><th><?php esc_html_e('Actions', RSR_TEXT_DOMAIN); ?></th></tr></thead>
                    <tbody data-rsr-source-list>
                        <?php foreach ((array)$view['sources'] as $source) : ?>
                            <tr data-source='<?php echo esc_attr(wp_json_encode($source)); ?>'>
                                <td><strong><?php echo esc_html((string)$source['name']); ?></strong><br><small><?php echo esc_html((string)$source['dataset']); ?></small></td>
                                <td><?php echo esc_html((string)$source['provider_key']); ?></td>
                                <td><?php echo esc_html((string)$source['license_code']); ?></td>
                                <td><span class="rsr-status"><?php echo esc_html((string)$source['status']); ?></span></td>
                                <td><?php echo esc_html((string)($source['last_success_at'] ?? '—')); ?></td>
                                <td>
                                    <?php if (!empty($view['can_manage_sources'])) : ?><button type="button" class="rsr-text-button" data-rsr-source-edit><?php esc_html_e('Edit', RSR_TEXT_DOMAIN); ?></button><?php endif; ?>
                                    <?php if (!empty($view['can_run_ingestion'])) : ?><button type="button" class="rsr-text-button" data-rsr-source-ingest><?php esc_html_e('Ingest', RSR_TEXT_DOMAIN); ?></button><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <span class="rsr-live" data-rsr-source-status aria-live="polite"></span>

            <?php if (!empty($view['can_manage_sources'])) : ?>
                <div class="rsr-inline-editor" hidden data-rsr-source-editor>
                    <h3><?php esc_html_e('Source editor', RSR_TEXT_DOMAIN); ?></h3>
                    <form data-rsr-source-form>
                        <input type="hidden" name="public_id"><input type="hidden" name="version" value="0">
                        <div class="rsr-field"><label><?php esc_html_e('Name', RSR_TEXT_DOMAIN); ?><input name="name" required maxlength="191"></label></div>
                        <div class="rsr-field"><label><?php esc_html_e('Dataset', RSR_TEXT_DOMAIN); ?><input name="dataset" required maxlength="191"></label></div>
                        <div class="rsr-field"><label><?php esc_html_e('Edition', RSR_TEXT_DOMAIN); ?><input name="edition" maxlength="191"></label></div>
                        <div class="rsr-field"><label><?php esc_html_e('Provider', RSR_TEXT_DOMAIN); ?><select name="provider_key"><?php foreach ((array)$view['providers'] as $key => $provider) : ?><option value="<?php echo esc_attr((string)$key); ?>"><?php echo esc_html((string)$key); ?></option><?php endforeach; ?></select></label></div>
                        <div class="rsr-field"><label><?php esc_html_e('License', RSR_TEXT_DOMAIN); ?><select name="license_code"><?php foreach (RSR_Domain::allowed_source_licenses() as $license) : ?><option value="<?php echo esc_attr($license); ?>"><?php echo esc_html($license); ?></option><?php endforeach; ?></select></label></div>
                        <div class="rsr-field"><label><?php esc_html_e('Last source review date', RSR_TEXT_DOMAIN); ?><input name="review_date" type="date" required max="<?php echo esc_attr(gmdate('Y-m-d')); ?>"></label></div>
                        <div class="rsr-field"><label><?php esc_html_e('Territory', RSR_TEXT_DOMAIN); ?><input name="territory" value="global" maxlength="100"></label></div>
                        <div class="rsr-field"><label><?php esc_html_e('Quality score', RSR_TEXT_DOMAIN); ?><input name="quality_score" type="number" min="0" max="1" step="0.01" value="0.5"></label></div>
                        <div class="rsr-field"><label><?php esc_html_e('Status', RSR_TEXT_DOMAIN); ?><select name="status"><option>configured</option><option>healthy</option><option>degraded</option><option>quota_exhausted</option><option>disabled</option></select></label></div>
                        <div class="rsr-field"><label><?php esc_html_e('Hourly request limit', RSR_TEXT_DOMAIN); ?><input name="rate_limit_per_hour" type="number" min="1" max="1000000" value="1000"></label></div>
                        <div class="rsr-field"><label><?php esc_html_e('Quota remaining', RSR_TEXT_DOMAIN); ?><input name="quota_remaining" type="number" min="0" placeholder="Unlimited"></label></div>
                        <div class="rsr-field"><label><?php esc_html_e('Cost model', RSR_TEXT_DOMAIN); ?><select name="cost_model"><option value="free">free</option><option value="fixed">fixed</option><option value="per-request">per-request</option><option value="per-record">per-record</option><option value="internal">internal</option></select></label></div>
                        <div class="rsr-field"><label><?php esc_html_e('Stale after seconds', RSR_TEXT_DOMAIN); ?><input name="stale_after_seconds" type="number" min="3600" max="31536000" value="86400"></label></div>
                        <div class="rsr-field rsr-field-wide"><label><?php esc_html_e('Restrictions and permitted uses', RSR_TEXT_DOMAIN); ?><textarea name="restrictions" rows="3"></textarea></label></div>
                        <div class="rsr-field rsr-field-wide"><label><?php esc_html_e('Secret-manager reference (never a raw secret)', RSR_TEXT_DOMAIN); ?><input name="credentials_ref" maxlength="191" autocomplete="off"></label></div>
                        <div class="rsr-field rsr-field-wide"><label><?php esc_html_e('Manual aggregate rows JSON', RSR_TEXT_DOMAIN); ?><textarea name="rows_json" rows="8">[]</textarea></label></div>
                        <div class="rsr-form-actions"><button class="rsr-button" type="submit"><?php esc_html_e('Save source', RSR_TEXT_DOMAIN); ?></button><button class="rsr-button rsr-button-quiet" type="button" data-rsr-source-cancel><?php esc_html_e('Cancel', RSR_TEXT_DOMAIN); ?></button></div>
                    </form>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if (!empty($view['can_manage_reports'])) : ?>
        <section class="rsr-panel">
            <h2><?php esc_html_e('Editorial report workflow', RSR_TEXT_DOMAIN); ?></h2>
            <div class="rsr-table-wrap">
                <table class="rsr-table">
                    <thead><tr><th><?php esc_html_e('Window', RSR_TEXT_DOMAIN); ?></th><th><?php esc_html_e('Geography', RSR_TEXT_DOMAIN); ?></th><th><?php esc_html_e('Status', RSR_TEXT_DOMAIN); ?></th><th><?php esc_html_e('Version', RSR_TEXT_DOMAIN); ?></th><th><?php esc_html_e('Actions', RSR_TEXT_DOMAIN); ?></th></tr></thead>
                    <tbody>
                        <?php foreach ((array)$view['reports'] as $report) : ?>
                            <tr data-report='<?php echo esc_attr(wp_json_encode($report)); ?>'>
                                <td><?php echo esc_html((string)$report['window_type']); ?><br><small><?php echo esc_html((string)$report['window_start_utc']); ?> — <?php echo esc_html((string)$report['window_end_utc']); ?></small></td>
                                <td><?php echo esc_html((string)$report['geography']); ?></td>
                                <td><span class="rsr-status"><?php echo esc_html((string)$report['status']); ?></span></td>
                                <td><?php echo esc_html((string)$report['version']); ?></td>
                                <td><div class="rsr-action-row">
                                    <?php if (!empty($view['can_review_reports'])) : foreach (['analyst_review', 'editorial_review', 'approved'] as $state) : ?><button type="button" class="rsr-text-button" data-rsr-report-transition="<?php echo esc_attr($state); ?>"><?php echo esc_html(str_replace('_', ' ', ucfirst($state))); ?></button><?php endforeach; endif; ?>
                                    <?php if (!empty($view['can_publish_reports'])) : ?><button type="button" class="rsr-text-button" data-rsr-report-transition="published"><?php esc_html_e('Publish', RSR_TEXT_DOMAIN); ?></button><?php endif; ?>
                                    <?php if (!empty($view['can_correct_reports'])) : ?><button type="button" class="rsr-text-button rsr-danger" data-rsr-report-correct><?php esc_html_e('Correct/retract', RSR_TEXT_DOMAIN); ?></button><?php endif; ?>
                                </div></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <span class="rsr-live" data-rsr-report-status aria-live="polite"></span>
        </section>
    <?php endif; ?>

    <?php if (!empty($view['can_view_diagnostics'])) : ?>
        <section class="rsr-panel"><h2><?php esc_html_e('Diagnostics', RSR_TEXT_DOMAIN); ?></h2><pre class="rsr-code-block"><?php echo esc_html(wp_json_encode($view['diagnostics'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre></section>
    <?php endif; ?>
</div>
<?php get_footer(); ?>
