<?php
$view = RSR_Routes::view();
get_header();
$report = $view['report'];
?>
<div class="rsr-page rsr-trend-report-page" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
    <?php RSR_Routes::context_controls(['fallback' => $view['urls']['trends']]); ?>
    <?php if (is_wp_error($report)) : ?>
        <section class="rsr-empty">
            <h1><?php esc_html_e('Trend report unavailable', RSR_TEXT_DOMAIN); ?></h1>
            <p><?php echo esc_html($report->get_error_message()); ?></p>
        </section>
    <?php else : ?>
        <?php $is_retracted = (string)$report['status'] === 'retracted'; ?>
        <header class="rsr-hero">
            <div>
                <p class="rsr-eyebrow"><?php echo esc_html((string)$report['window_type']); ?> · <?php echo esc_html((string)$report['geography']); ?></p>
                <h1><?php esc_html_e('Trend Intelligence Report', RSR_TEXT_DOMAIN); ?></h1>
                <p><?php echo esc_html((string)$report['window_start_utc']); ?> — <?php echo esc_html((string)$report['window_end_utc']); ?></p>
            </div>
            <span class="rsr-status <?php echo ($is_retracted || !empty($report['stale'])) ? 'rsr-status-warning' : ''; ?>">
                <?php
                if ($is_retracted) {
                    esc_html_e('Retracted', RSR_TEXT_DOMAIN);
                } elseif (!empty($report['stale'])) {
                    esc_html_e('Stale source window', RSR_TEXT_DOMAIN);
                } elseif ((string)$report['status'] === 'corrected') {
                    esc_html_e('Corrected', RSR_TEXT_DOMAIN);
                } else {
                    esc_html_e('Published', RSR_TEXT_DOMAIN);
                }
                ?>
            </span>
        </header>

        <?php if (!empty($report['corrections'])) : ?>
            <aside class="rsr-notice rsr-notice-warning">
                <strong><?php echo $is_retracted ? esc_html__('Retraction notice', RSR_TEXT_DOMAIN) : esc_html__('Correction history', RSR_TEXT_DOMAIN); ?></strong>
                <ul>
                    <?php foreach ((array)$report['corrections'] as $correction) : ?>
                        <li><?php echo esc_html((string)$correction['public_notice']); ?> <small><?php echo esc_html((string)$correction['created_at']); ?></small></li>
                    <?php endforeach; ?>
                </ul>
            </aside>
        <?php endif; ?>

        <?php if ($is_retracted) : ?>
            <section class="rsr-empty rsr-notice-warning">
                <h2><?php esc_html_e('This report has been withdrawn', RSR_TEXT_DOMAIN); ?></h2>
                <p><?php esc_html_e('Its former rankings and source activity are no longer presented as current or reliable. The permanent URL is retained for transparent correction history.', RSR_TEXT_DOMAIN); ?></p>
            </section>
        <?php else : ?>
            <aside class="rsr-safety"><strong><?php esc_html_e('Limits:', RSR_TEXT_DOMAIN); ?></strong> <?php esc_html_e('This report summarizes source activity. It neither estimates true disease prevalence nor provides diagnosis or treatment.', RSR_TEXT_DOMAIN); ?></aside>
            <section class="rsr-panel">
                <h2><?php esc_html_e('Method and provenance', RSR_TEXT_DOMAIN); ?></h2>
                <dl class="rsr-definition-grid">
                    <div><dt><?php esc_html_e('Method version', RSR_TEXT_DOMAIN); ?></dt><dd><?php echo esc_html((string)$report['method_version']); ?></dd></div>
                    <div><dt><?php esc_html_e('Display time zone', RSR_TEXT_DOMAIN); ?></dt><dd><?php echo esc_html((string)$report['display_timezone']); ?></dd></div>
                    <div><dt><?php esc_html_e('Confidence', RSR_TEXT_DOMAIN); ?></dt><dd><?php echo esc_html(number_format_i18n((float)$report['confidence'] * 100, 1)); ?>%</dd></div>
                    <div><dt><?php esc_html_e('Fresh until', RSR_TEXT_DOMAIN); ?></dt><dd><?php echo esc_html((string)$report['stale_at']); ?></dd></div>
                </dl>
            </section>
            <section class="rsr-panel">
                <h2><?php esc_html_e('Ranked source activity', RSR_TEXT_DOMAIN); ?></h2>
                <div class="rsr-table-wrap">
                    <table class="rsr-table">
                        <thead><tr><th>#</th><th><?php esc_html_e('Topic', RSR_TEXT_DOMAIN); ?></th><th><?php esc_html_e('Score', RSR_TEXT_DOMAIN); ?></th><th><?php esc_html_e('Change', RSR_TEXT_DOMAIN); ?></th><th><?php esc_html_e('Confidence', RSR_TEXT_DOMAIN); ?></th><th><?php esc_html_e('Sources', RSR_TEXT_DOMAIN); ?></th></tr></thead>
                        <tbody>
                            <?php foreach ((array)($report['results']['topics'] ?? []) as $index => $topic) : ?>
                                <tr><td><?php echo esc_html((string)($index + 1)); ?></td><td><?php echo esc_html((string)$topic['topic_label']); ?></td><td><meter min="0" max="100" value="<?php echo esc_attr((string)$topic['score']); ?>"><?php echo esc_html((string)$topic['score']); ?></meter> <?php echo esc_html(number_format_i18n((float)$topic['score'], 1)); ?>%</td><td><?php echo esc_html(number_format_i18n((float)($topic['change_ratio'] ?? 0) * 100, 1)); ?>%</td><td><?php echo esc_html(number_format_i18n((float)$topic['confidence'] * 100, 1)); ?>%</td><td><?php echo esc_html((string)$topic['source_count']); ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <section class="rsr-panel">
                <h2><?php esc_html_e('Sources', RSR_TEXT_DOMAIN); ?></h2>
                <div class="rsr-card-grid">
                    <?php foreach ((array)$report['sources'] as $source) : ?>
                        <article class="rsr-card"><h3><?php echo esc_html((string)$source['name']); ?></h3><dl><div><dt><?php esc_html_e('Dataset', RSR_TEXT_DOMAIN); ?></dt><dd><?php echo esc_html((string)$source['dataset']); ?></dd></div><?php if (!empty($source['edition'])) : ?><div><dt><?php esc_html_e('Edition', RSR_TEXT_DOMAIN); ?></dt><dd><?php echo esc_html((string)$source['edition']); ?></dd></div><?php endif; ?><div><dt><?php esc_html_e('License', RSR_TEXT_DOMAIN); ?></dt><dd><?php echo esc_html((string)$source['license_code']); ?></dd></div><div><dt><?php esc_html_e('Reviewed', RSR_TEXT_DOMAIN); ?></dt><dd><?php echo esc_html((string)$source['review_date']); ?></dd></div><div><dt><?php esc_html_e('Territory', RSR_TEXT_DOMAIN); ?></dt><dd><?php echo esc_html((string)$source['territory']); ?></dd></div><div><dt><?php esc_html_e('Quality', RSR_TEXT_DOMAIN); ?></dt><dd><?php echo esc_html(number_format_i18n((float)$source['quality_score'] * 100, 1)); ?>%</dd></div><?php if (!empty($source['restrictions'])) : ?><div><dt><?php esc_html_e('Restrictions', RSR_TEXT_DOMAIN); ?></dt><dd><?php echo esc_html((string)$source['restrictions']); ?></dd></div><?php endif; ?></dl></article>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php if (!empty($report['results']['limitations'])) : ?>
                <section class="rsr-panel"><h2><?php esc_html_e('Limitations', RSR_TEXT_DOMAIN); ?></h2><ul><?php foreach ((array)$report['results']['limitations'] as $limit) : ?><li><?php echo esc_html((string)$limit); ?></li><?php endforeach; ?></ul></section>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php get_footer(); ?>
