<?php
$view = RSR_Routes::view();
get_header();
?>
<div class="rsr-page rsr-studies-page" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>" data-rsr-studies-app>
    <?php RSR_Routes::context_controls(['fallback' => $view['urls']['radar']]); ?>
    <header class="rsr-hero"><div><p class="rsr-eyebrow"><?php esc_html_e('Private · encrypted · verified-doctor only', RSR_TEXT_DOMAIN); ?></p><h1><?php esc_html_e('Saved Radar Studies', RSR_TEXT_DOMAIN); ?></h1><p><?php esc_html_e('Store non-patient research queries and notes. These records never become clinical charts or public trend inputs.', RSR_TEXT_DOMAIN); ?></p></div></header>
    <?php if (!is_user_logged_in()) : ?>
        <section class="rsr-empty"><h2><?php esc_html_e('Sign in required', RSR_TEXT_DOMAIN); ?></h2><p><?php esc_html_e('Public Radar research remains available without an account. Saving a private study requires an approved verified-doctor account.', RSR_TEXT_DOMAIN); ?></p><a class="rsr-button" href="<?php echo esc_url($view['urls']['login']); ?>"><?php esc_html_e('Log in', RSR_TEXT_DOMAIN); ?></a></section>
    <?php elseif (is_wp_error($view['studies'])) : ?>
        <div class="rsr-notice rsr-notice-error"><?php echo esc_html($view['studies']->get_error_message()); ?></div>
    <?php else : ?>
        <aside class="rsr-safety"><strong><?php esc_html_e('Privacy rule:', RSR_TEXT_DOMAIN); ?></strong> <?php esc_html_e('Do not enter names, phone numbers, email addresses, identity numbers, addresses, dates of birth, medical-record numbers, or other patient-identifying details.', RSR_TEXT_DOMAIN); ?></aside>
        <section class="rsr-panel">
            <div class="rsr-section-heading"><h2><?php esc_html_e('Create or edit a study', RSR_TEXT_DOMAIN); ?></h2><button type="button" class="rsr-button rsr-button-quiet" data-rsr-study-reset><?php esc_html_e('New study', RSR_TEXT_DOMAIN); ?></button></div>
            <form class="rsr-study-form" data-rsr-study-form>
                <input type="hidden" name="public_id" value=""><input type="hidden" name="version" value="0">
                <div class="rsr-field rsr-field-wide"><label for="study-title"><?php esc_html_e('Study title', RSR_TEXT_DOMAIN); ?></label><input id="study-title" name="title" required maxlength="160"></div>
                <div class="rsr-field"><label for="study-keyword"><?php esc_html_e('Radar keyword', RSR_TEXT_DOMAIN); ?></label><input id="study-keyword" name="keyword" maxlength="240"></div>
                <div class="rsr-field"><label for="study-remedies"><?php esc_html_e('Remedy references (up to three, comma-separated)', RSR_TEXT_DOMAIN); ?></label><input id="study-remedies" name="remedy_refs" maxlength="600"></div>
                <div class="rsr-field"><label for="study-tags"><?php esc_html_e('Tags (comma-separated)', RSR_TEXT_DOMAIN); ?></label><input id="study-tags" name="tags" maxlength="400"></div>
                <div class="rsr-field rsr-field-wide"><label for="study-query-json"><?php esc_html_e('Structured query JSON', RSR_TEXT_DOMAIN); ?></label><textarea id="study-query-json" name="query_json" rows="6" spellcheck="false">{"mode":"AND","keyword":"","filters":{}}</textarea><p class="rsr-help"><?php esc_html_e('Use the public Radar page to build the query, then save its structure here.', RSR_TEXT_DOMAIN); ?></p></div>
                <div class="rsr-field rsr-field-wide"><label for="study-notes"><?php esc_html_e('Private research notes', RSR_TEXT_DOMAIN); ?></label><textarea id="study-notes" name="notes" rows="8" maxlength="32768"></textarea></div>
                <div class="rsr-form-actions"><button class="rsr-button" type="submit"><?php esc_html_e('Save private study', RSR_TEXT_DOMAIN); ?></button><a class="rsr-button rsr-button-secondary" href="<?php echo esc_url(rest_url(RSR_API::NS . '/studies/export')); ?>" data-rsr-export><?php esc_html_e('Export my studies', RSR_TEXT_DOMAIN); ?></a><span class="rsr-live" aria-live="polite" data-rsr-study-status></span></div>
            </form>
        </section>
        <section class="rsr-results"><div class="rsr-section-heading"><h2><?php esc_html_e('My studies', RSR_TEXT_DOMAIN); ?></h2><span><?php echo esc_html((string)count((array)$view['studies']['items'])); ?></span></div><div class="rsr-card-grid" data-rsr-study-list>
            <?php foreach ((array)$view['studies']['items'] as $study) : ?>
                <article class="rsr-card rsr-study-card" data-study='<?php echo esc_attr(wp_json_encode($study)); ?>'><header><h3><?php echo esc_html((string)$study['title']); ?></h3><span class="rsr-status"><?php echo esc_html((string)$study['status']); ?></span></header><p><?php echo esc_html(RSR_Domain::explain_query((array)$study['query'])); ?></p><p class="rsr-meta"><?php echo esc_html((string)$study['updated_at']); ?></p><?php if (!empty($study['notes_unavailable'])) : ?><p class="rsr-notice rsr-notice-warning"><?php esc_html_e('Encrypted notes are unavailable until a previous key is restored; editing is blocked to prevent data loss.', RSR_TEXT_DOMAIN); ?></p><?php endif; ?><div class="rsr-card-actions"><button class="rsr-text-button" type="button" data-rsr-study-edit <?php disabled(!empty($study['notes_unavailable'])); ?>><?php esc_html_e('Edit', RSR_TEXT_DOMAIN); ?></button><button class="rsr-text-button rsr-danger" type="button" data-rsr-study-delete><?php esc_html_e('Delete', RSR_TEXT_DOMAIN); ?></button></div></article>
            <?php endforeach; ?>
        </div><?php if (!empty($view['studies']['next_cursor'])) : ?><p><a class="rsr-button rsr-button-secondary" href="<?php echo esc_url(add_query_arg('cursor', rawurlencode((string)$view['studies']['next_cursor']), $view['urls']['studies'])); ?>"><?php esc_html_e('Load older studies', RSR_TEXT_DOMAIN); ?></a></p><?php endif; ?></section>
    <?php endif; ?>
</div>
<?php get_footer(); ?>
