<?php
$view = RSR_Routes::view();
$error = $view['error'];
get_header();
?>
<div class="rsr-page"><section class="rsr-empty"><h1><?php esc_html_e('Access unavailable', RSR_TEXT_DOMAIN); ?></h1><p><?php echo esc_html(is_wp_error($error) ? $error->get_error_message() : __('This page is unavailable.', RSR_TEXT_DOMAIN)); ?></p><a class="rsr-button" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', RSR_TEXT_DOMAIN); ?></a></section></div>
<?php get_footer(); ?>
