<?php
$view = RSR_Routes::view();
get_header();
?>
<div class="rsr-page rsr-compare-page" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
    <?php RSR_Routes::context_controls(['fallback' => $view['urls']['radar']]); ?>
    <header class="rsr-hero"><div><p class="rsr-eyebrow"><?php esc_html_e('Maximum three remedies', RSR_TEXT_DOMAIN); ?></p><h1><?php esc_html_e('Source-linked Remedy Comparison', RSR_TEXT_DOMAIN); ?></h1><p><?php esc_html_e('Compare reviewed similarities and differences without turning a research view into a prescription.', RSR_TEXT_DOMAIN); ?></p></div></header>
    <aside class="rsr-safety" role="note"><strong><?php esc_html_e('Boundary:', RSR_TEXT_DOMAIN); ?></strong> <?php echo esc_html((string)$view['safety']['message']); ?></aside>
    <section class="rsr-panel">
        <form method="get" action="<?php echo esc_url($view['urls']['compare']); ?>" class="rsr-inline-form" data-rsr-manual-compare autocomplete="off">
            <?php for ($i = 0; $i < 3; $i++) : ?>
                <div class="rsr-field"><label for="remedy-<?php echo esc_attr((string)$i); ?>"><?php printf(esc_html__('Remedy reference %d', RSR_TEXT_DOMAIN), $i + 1); ?></label><input id="remedy-<?php echo esc_attr((string)$i); ?>" name="remedies[]" value="<?php echo esc_attr((string)($view['selected_ids'][$i] ?? '')); ?>" maxlength="191"></div>
            <?php endfor; ?>
            <button class="rsr-button" type="submit"><?php esc_html_e('Compare', RSR_TEXT_DOMAIN); ?></button>
        </form>
    </section>
    <?php if ($view['comparison'] !== null) : ?>
        <?php if (is_wp_error($view['comparison'])) : ?>
            <div class="rsr-notice rsr-notice-error"><?php echo esc_html($view['comparison']->get_error_message()); ?></div>
        <?php else :
            $remedy_titles = [];
            foreach ((array)$view['comparison']['remedies'] as $remedy_item) {
                $remedy_titles[(string)$remedy_item['public_id']] = (string)$remedy_item['title'];
            }
            ?>
            <section class="rsr-results">
                <div class="rsr-notice"><strong><?php esc_html_e('No clinical ranking:', RSR_TEXT_DOMAIN); ?></strong> <?php echo esc_html((string)$view['comparison']['interpretation']); ?></div>
                <div class="rsr-card-grid rsr-remedy-heads">
                    <?php foreach ((array)$view['comparison']['remedies'] as $remedy) : ?><article class="rsr-card"><h2><?php echo esc_html((string)$remedy['title']); ?></h2><?php if (!empty($remedy['url'])) : ?><a class="rsr-text-link" href="<?php echo esc_url((string)$remedy['url']); ?>" rel="noreferrer"><?php esc_html_e('Canonical entry', RSR_TEXT_DOMAIN); ?></a><?php endif; ?><p><?php echo wp_kses_post((string)($remedy['summary'] ?? '')); ?></p><p class="rsr-meta"><?php printf(esc_html__('Entry version %1$s · reviewed %2$s', RSR_TEXT_DOMAIN), esc_html((string)($remedy['version'] ?? '—')), esc_html((string)($remedy['review_date'] ?? '—'))); ?></p></article><?php endforeach; ?>
                </div>
                <section class="rsr-panel"><h2><?php esc_html_e('Common reviewed rubrics', RSR_TEXT_DOMAIN); ?></h2><?php if (empty($view['comparison']['common_rubrics'])) : ?><p><?php esc_html_e('No common reviewed mapping is currently available.', RSR_TEXT_DOMAIN); ?></p><?php else : ?><div class="rsr-table-wrap"><table class="rsr-table"><thead><tr><th><?php esc_html_e('Rubric', RSR_TEXT_DOMAIN); ?></th><th><?php esc_html_e('References, licence and review date', RSR_TEXT_DOMAIN); ?></th></tr></thead><tbody><?php foreach ((array)$view['comparison']['common_rubrics'] as $rubric) : ?><tr><td><?php echo esc_html((string)$rubric['explanation']); ?></td><td><?php foreach ((array)$rubric['remedies'] as $remedy_id => $reference) : ?><span class="rsr-reference-chip"><strong><?php echo esc_html((string)($remedy_titles[$remedy_id] ?? $remedy_id)); ?></strong>: <?php echo esc_html((string)$reference['reference']); ?> · <?php echo esc_html((string)$reference['license']); ?> · <?php echo esc_html((string)$reference['review_date']); ?></span><?php endforeach; ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
                <section class="rsr-panel"><h2><?php esc_html_e('Differing reviewed rubrics', RSR_TEXT_DOMAIN); ?></h2><?php if (empty($view['comparison']['differing_rubrics'])) : ?><p><?php esc_html_e('No differing reviewed mapping is currently available.', RSR_TEXT_DOMAIN); ?></p><?php else : ?><div class="rsr-accordion-list"><?php foreach ((array)$view['comparison']['differing_rubrics'] as $rubric) : ?><details><summary><?php echo esc_html((string)$rubric['explanation']); ?></summary><ul><?php foreach ((array)$rubric['remedies'] as $remedy_id => $reference) : ?><li><strong><?php echo esc_html((string)($remedy_titles[$remedy_id] ?? $remedy_id)); ?></strong>: <?php echo esc_html((string)$reference['reference']); ?> · <?php echo esc_html((string)$reference['license']); ?> · <?php echo esc_html((string)$reference['review_date']); ?></li><?php endforeach; ?></ul></details><?php endforeach; ?></div><?php endif; ?></section>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php get_footer(); ?>
