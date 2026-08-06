<?php
/** @var array<string, mixed> $view */
$view = RSR_Routes::view();
get_header();
?>
<div class="rsr-page rsr-radar-page" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
    <?php RSR_Routes::context_controls(['fallback' => home_url('/')]); ?>
    <header class="rsr-hero">
        <div>
            <p class="rsr-eyebrow"><?php esc_html_e('File 15 · Source-linked research', RSR_TEXT_DOMAIN); ?></p>
            <h1><?php esc_html_e('Radar Symptom and Remedy Research', RSR_TEXT_DOMAIN); ?></h1>
            <p><?php esc_html_e('Narrow educational possibilities through governed symptom dimensions and reviewed references.', RSR_TEXT_DOMAIN); ?></p>
        </div>
        <div class="rsr-hero-actions">
            <a class="rsr-button rsr-button-secondary" href="<?php echo esc_url($view['urls']['compare']); ?>"><?php esc_html_e('Compare remedies', RSR_TEXT_DOMAIN); ?></a>
            <?php if (!empty($view['can_save'])) : ?><a class="rsr-button" href="<?php echo esc_url($view['urls']['studies']); ?>"><?php esc_html_e('Private studies', RSR_TEXT_DOMAIN); ?></a><?php endif; ?>
        </div>
    </header>

    <aside class="rsr-safety" role="note" aria-label="<?php esc_attr_e('Medical safety notice', RSR_TEXT_DOMAIN); ?>">
        <strong><?php esc_html_e('Educational research only:', RSR_TEXT_DOMAIN); ?></strong>
        <?php echo esc_html((string)$view['safety']['message']); ?>
        <span><?php echo esc_html((string)$view['safety']['red_flags']); ?></span>
    </aside>

    <section class="rsr-panel" aria-labelledby="rsr-search-title">
        <h2 id="rsr-search-title"><?php esc_html_e('Build a structured query', RSR_TEXT_DOMAIN); ?></h2>
        <form method="get" action="<?php echo esc_url($view['urls']['radar']); ?>" class="rsr-search-form" data-rsr-search-form autocomplete="off">
            <div class="rsr-field rsr-field-wide">
                <label for="rsr-keyword"><?php esc_html_e('Keyword', RSR_TEXT_DOMAIN); ?></label>
                <input id="rsr-keyword" name="keyword" type="search" maxlength="240" value="<?php echo esc_attr((string)$view['query']['keyword']); ?>" placeholder="<?php esc_attr_e('e.g., burning, morning, cold air', RSR_TEXT_DOMAIN); ?>">
                <p class="rsr-help"><?php esc_html_e('Research query variants are not indexed and are sent with no-referrer and no-store controls.', RSR_TEXT_DOMAIN); ?></p>
            </div>
            <div class="rsr-field">
                <label for="rsr-mode"><?php esc_html_e('Match rule', RSR_TEXT_DOMAIN); ?></label>
                <select id="rsr-mode" name="mode">
                    <option value="AND" <?php selected($view['query']['mode'], 'AND'); ?>><?php esc_html_e('Match all selected features', RSR_TEXT_DOMAIN); ?></option>
                    <option value="OR" <?php selected($view['query']['mode'], 'OR'); ?>><?php esc_html_e('Match any selected feature', RSR_TEXT_DOMAIN); ?></option>
                </select>
            </div>
            <div class="rsr-filter-grid">
                <?php foreach ((array)$view['schema']['dimensions'] as $key => $dimension) :
                    $selected_values = (array)($view['query']['filters'][$key] ?? []);
                    $options = (array)($view['schema']['values'][$key] ?? []);
                    ?>
                    <details class="rsr-filter" <?php echo $selected_values !== [] ? 'open' : ''; ?>>
                        <summary><?php echo esc_html((string)$dimension['label']); ?><?php if ($selected_values !== []) : ?><span class="rsr-count"><?php echo esc_html((string)count($selected_values)); ?></span><?php endif; ?></summary>
                        <p class="rsr-help"><?php echo esc_html((string)$dimension['description']); ?></p>
                        <?php if ($options !== []) : ?>
                            <select name="filter_<?php echo esc_attr($key); ?>[]" multiple size="5" aria-label="<?php echo esc_attr((string)$dimension['label']); ?>">
                                <?php foreach ($options as $option) :
                                    $option_key = (string)($option['key'] ?? $option['label']);
                                    $option_label = (string)($option['label'] ?? $option_key);
                                    $is_selected = in_array($option_key, $selected_values, true) || in_array($option_label, $selected_values, true);
                                    ?>
                                    <option value="<?php echo esc_attr($option_key); ?>" <?php echo $is_selected ? 'selected' : ''; ?>><?php echo esc_html($option_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php else : ?>
                            <input type="text" name="filter_<?php echo esc_attr($key); ?>[]" maxlength="240" value="<?php echo esc_attr((string)($selected_values[0] ?? '')); ?>">
                        <?php endif; ?>
                    </details>
                <?php endforeach; ?>
            </div>
            <div class="rsr-form-actions">
                <button class="rsr-button" type="submit"><span aria-hidden="true">⌕</span><?php esc_html_e('Research', RSR_TEXT_DOMAIN); ?></button>
                <a class="rsr-button rsr-button-quiet" href="<?php echo esc_url($view['urls']['radar']); ?>"><?php esc_html_e('Clear', RSR_TEXT_DOMAIN); ?></a>
            </div>
        </form>
    </section>

    <?php if ($view['results'] !== null) : ?>
        <section class="rsr-results" aria-labelledby="rsr-results-title" aria-live="polite">
            <h2 id="rsr-results-title"><?php esc_html_e('Source-linked results', RSR_TEXT_DOMAIN); ?></h2>
            <?php if (is_wp_error($view['results'])) : ?>
                <div class="rsr-notice rsr-notice-error"><?php echo esc_html($view['results']->get_error_message()); ?></div>
            <?php else : ?>
                <?php if (!empty($view['results']['safety_escalation'])) : ?>
                    <div class="rsr-notice rsr-notice-error" role="alert"><strong><?php esc_html_e('Urgent safety boundary:', RSR_TEXT_DOMAIN); ?></strong> <?php echo esc_html((string)$view['results']['safety_escalation']['message']); ?></div>
                <?php endif; ?>
                <?php if (empty($view['results']['results'])) : ?>
                    <div class="rsr-empty">
                        <h3><?php esc_html_e('No eligible governed mapping matched', RSR_TEXT_DOMAIN); ?></h3>
                        <p><?php esc_html_e('Radar will not invent a remedy or convert an empty result into a prescription.', RSR_TEXT_DOMAIN); ?></p>
                        <?php $recovery = (array)($view['results']['zero_result_recovery'] ?? []); ?>
                        <?php if (!empty($recovery['suggestions'])) : ?>
                            <ul class="rsr-reference-list">
                                <?php foreach ((array)$recovery['suggestions'] as $suggestion) : ?><li><?php echo esc_html((string)$suggestion); ?></li><?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <?php if (!empty($recovery['gap_submission_url'])) : ?><a class="rsr-button rsr-button-secondary" href="<?php echo esc_url((string)$recovery['gap_submission_url']); ?>"><?php esc_html_e('Submit a governed source gap', RSR_TEXT_DOMAIN); ?></a><?php endif; ?>
                    </div>
                <?php else : ?>
                    <p class="rsr-query-explanation"><?php echo esc_html((string)$view['results']['explanation']); ?></p>
                    <div class="rsr-notice"><strong><?php esc_html_e('Why this order:', RSR_TEXT_DOMAIN); ?></strong> <?php echo esc_html((string)($view['results']['ranking']['notice'] ?? '')); ?></div>
                    <form method="get" action="<?php echo esc_url($view['urls']['compare']); ?>" data-rsr-compare-form>
                        <div class="rsr-card-grid">
                            <?php foreach ((array)$view['results']['results'] as $result) :
                                $remedy = (array)($result['remedy'] ?? []);
                                $id = (string)($result['remedy_public_id'] ?? $remedy['public_id'] ?? '');
                                $why = (array)($result['why_this_result'] ?? []);
                                ?>
                                <article class="rsr-card">
                                    <header><h3><?php echo esc_html((string)($remedy['title'] ?? $id)); ?></h3><span class="rsr-status"><?php printf(esc_html__('%d evidence matches', RSR_TEXT_DOMAIN), (int)($result['evidence_match_count'] ?? $result['match_count'] ?? 0)); ?></span></header>
                                    <?php if (!empty($remedy['summary'])) : ?><div class="rsr-summary"><?php echo wp_kses_post((string)$remedy['summary']); ?></div><?php endif; ?>
                                    <details>
                                        <summary><?php esc_html_e('Why this result', RSR_TEXT_DOMAIN); ?></summary>
                                        <p><?php echo esc_html((string)($why['meaning'] ?? '')); ?></p>
                                        <?php if (!empty($why['matched_rubrics'])) : ?><ul class="rsr-reference-list"><?php foreach (array_slice((array)$why['matched_rubrics'], 0, 5) as $matched) : ?><li><?php echo esc_html((string)$matched); ?></li><?php endforeach; ?></ul><?php endif; ?>
                                        <?php if (!empty($why['latest_review_date'])) : ?><p class="rsr-meta"><?php printf(esc_html__('Latest mapped review: %s', RSR_TEXT_DOMAIN), esc_html((string)$why['latest_review_date'])); ?></p><?php endif; ?>
                                    </details>
                                    <details><summary><?php esc_html_e('References and matched rubrics', RSR_TEXT_DOMAIN); ?></summary>
                                        <ul class="rsr-reference-list">
                                            <?php foreach (array_slice((array)($result['mappings'] ?? []), 0, 8) as $mapping) : ?>
                                                <li><strong><?php echo esc_html((string)$mapping['explanation']); ?></strong><br><span><?php echo esc_html((string)$mapping['reference']); ?> · <?php echo esc_html((string)$mapping['license']); ?> · <?php echo esc_html((string)$mapping['review_date']); ?></span></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </details>
                                    <div class="rsr-card-actions">
                                        <?php if (!empty($remedy['url'])) : ?><a class="rsr-text-link" href="<?php echo esc_url((string)$remedy['url']); ?>" rel="noreferrer"><?php esc_html_e('Open encyclopedia entry', RSR_TEXT_DOMAIN); ?></a><?php endif; ?>
                                        <label class="rsr-compare-choice"><input type="checkbox" name="remedies[]" value="<?php echo esc_attr($id); ?>"><span><?php esc_html_e('Compare', RSR_TEXT_DOMAIN); ?></span></label>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                        <div class="rsr-sticky-action"><span data-rsr-compare-count>0 / 3</span><button type="submit" class="rsr-button" disabled data-rsr-compare-submit><?php esc_html_e('Compare selected', RSR_TEXT_DOMAIN); ?></button></div>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <footer class="rsr-page-footer"><p><?php esc_html_e('Radar results are research aids, not clinical orders. Source, license, review date, and correction status remain visible by design.', RSR_TEXT_DOMAIN); ?></p></footer>
</div>
<?php get_footer(); ?>
