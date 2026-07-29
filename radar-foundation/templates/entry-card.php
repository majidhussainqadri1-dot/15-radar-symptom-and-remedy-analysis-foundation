<?php defined( 'ABSPATH' ) || exit; $terms = get_the_terms( $entry_id, SRF_Helpers::TAX ); $entry_title = get_the_title( $entry_id ); ?>
<article class="srf-card" data-radar-entry="<?php echo absint( $entry_id ); ?>">
	<div class="srf-card-top"><span class="srf-type"><?php echo esc_html( $terms && ! is_wp_error( $terms ) ? $terms[0]->name : 'Radar Entry' ); ?></span><?php if ( $can_save ) : ?><label class="srf-select"><input type="checkbox" value="<?php echo absint( $entry_id ); ?>" data-srf-select-entry aria-label="<?php echo esc_attr( 'Select ' . $entry_title . ' for a private Radar study' ); ?>"> Select for Study</label><?php endif; ?></div>
	<h3><a href="<?php echo esc_url( get_permalink( $entry_id ) ); ?>"><?php echo esc_html( $entry_title ); ?></a></h3>
	<p><?php echo esc_html( wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $entry_id ) ), 30 ) ); ?></p>
	<dl>
		<?php foreach ( array( 'body_region' => 'Region', 'location' => 'Location', 'sensation' => 'Sensation', 'aggravation' => 'Aggravation', 'amelioration' => 'Amelioration', 'related_remedies' => 'Related Remedies', 'reference_source' => 'Reference Source' ) as $key => $label ) : $value = SRF_Helpers::meta( $entry_id, $key ); if ( $value ) : ?><div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( $value ); ?></dd></div><?php endif; endforeach; ?>
	</dl>
	<footer><span>Reviewed <?php echo esc_html( SRF_Helpers::meta( $entry_id, 'reviewed_date', 'date not supplied' ) ); ?></span><a href="<?php echo esc_url( get_permalink( $entry_id ) ); ?>">View Referenced Entry</a></footer>
</article>
