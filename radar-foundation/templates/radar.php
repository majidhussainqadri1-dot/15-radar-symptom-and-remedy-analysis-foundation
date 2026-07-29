<?php defined( 'ABSPATH' ) || exit; ?>
<main class="srf-shell" id="radar">
	<?php echo SRF_Helpers::navigation(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

	<header class="srf-hero">
		<div>
			<span>Structured Educational Research</span>
			<h1>Radar</h1>
			<p>Explore symptoms, locations, sensations, modalities, clinical relationships, and referenced remedy information through an organized research system.</p>
			<div class="srf-hero-actions">
				<a class="srf-button" href="#radar-search">Search Radar</a>
				<a class="srf-button srf-button-light" href="#remedy-comparison">Compare Remedies</a>
				<?php if ( $can_save ) : ?><a class="srf-button srf-button-light" href="<?php echo esc_url( $studies_url ); ?>">Saved Studies</a><?php endif; ?>
			</div>
		</div>
		<aside class="srf-safety"><strong>Educational research only</strong><p>Radar does not diagnose disease, select treatment, recommend dosage, or replace qualified medical care. Contact local emergency services immediately for urgent or severe symptoms.</p></aside>
	</header>

	<section class="srf-domain-links" aria-label="Radar research domains">
		<a href="<?php echo esc_url( add_query_arg( 'radar_category', 'mind', $radar_url ) ); ?>">Mind</a>
		<a href="<?php echo esc_url( add_query_arg( 'radar_category', 'general-symptoms', $radar_url ) ); ?>">General Symptoms</a>
		<a href="<?php echo esc_url( add_query_arg( 'radar_category', 'particular-symptoms', $radar_url ) ); ?>">Particular Symptoms</a>
		<a href="#remedy-comparison">Remedy Comparison</a>
	</section>

	<section class="srf-search-section" id="radar-search" aria-labelledby="radar-search-title">
		<div class="srf-section-head"><div><span>Symptom and Rubric Search</span><h2 id="radar-search-title">Search Structured Radar Entries</h2></div><p>Combine only the filters relevant to your research.</p></div>
		<form class="srf-search-form" method="get" action="<?php echo esc_url( $radar_url ); ?>">
			<label class="srf-wide">Keyword<input type="search" name="radar_keyword" value="<?php echo esc_attr( $filters['keyword'] ); ?>" placeholder="Search a symptom, modality, or clinical term"></label>
			<label>Category<select name="radar_category"><option value="">All categories</option><?php if ( ! is_wp_error( $categories ) ) : foreach ( $categories as $category ) : ?><option value="<?php echo esc_attr( $category->slug ); ?>" <?php selected( $filters['category'], $category->slug ); ?>><?php echo esc_html( $category->name ); ?></option><?php endforeach; endif; ?></select></label>
			<label>Body Region<input name="radar_body_region" value="<?php echo esc_attr( $filters['body_region'] ); ?>" placeholder="For example: Head"></label>
			<label>Location<input name="radar_location" value="<?php echo esc_attr( $filters['location'] ); ?>"></label>
			<label>Sensation<input name="radar_sensation" value="<?php echo esc_attr( $filters['sensation'] ); ?>"></label>
			<details class="srf-advanced srf-wide" <?php echo array_filter( array_slice( $filters, 4 ) ) ? 'open' : ''; ?>>
				<summary>Advanced Filters</summary>
				<div class="srf-advanced-grid">
					<label>Aggravation<input name="radar_aggravation" value="<?php echo esc_attr( $filters['aggravation'] ); ?>"></label>
					<label>Amelioration<input name="radar_amelioration" value="<?php echo esc_attr( $filters['amelioration'] ); ?>"></label>
					<label>Concomitants<input name="radar_concomitants" value="<?php echo esc_attr( $filters['concomitants'] ); ?>"></label>
					<label>Causation<input name="radar_causation" value="<?php echo esc_attr( $filters['causation'] ); ?>"></label>
					<label>Time<input name="radar_time" value="<?php echo esc_attr( $filters['time'] ); ?>"></label>
					<label>Temperature<input name="radar_temperature" value="<?php echo esc_attr( $filters['temperature'] ); ?>"></label>
					<label>Remedy Name<input name="radar_related_remedies" value="<?php echo esc_attr( $filters['related_remedies'] ); ?>"></label>
				</div>
			</details>
			<div class="srf-form-actions srf-wide"><button class="srf-button" type="submit">Search Radar</button><?php if ( array_filter( $filters ) ) : ?><a class="srf-clear" href="<?php echo esc_url( $radar_url ); ?>">Clear All Filters</a><?php endif; ?></div>
		</form>
	</section>

	<section class="srf-comparison-section" id="remedy-comparison" aria-labelledby="remedy-comparison-title">
		<div class="srf-section-head"><div><span>Educational Review</span><h2 id="remedy-comparison-title">Remedy Comparison</h2></div><p>Compare up to three published remedy entries from the Encyclopedia.</p></div>
		<?php if ( $remedies ) : ?>
			<form class="srf-remedy-form" method="get" action="<?php echo esc_url( $radar_url ); ?>#remedy-comparison">
				<?php for ( $slot = 0; $slot < 3; $slot++ ) : ?>
					<label>Remedy <?php echo absint( $slot + 1 ); ?><select name="radar_remedy_compare[]"><option value="">Choose a remedy</option><?php foreach ( $remedies as $remedy ) : ?><option value="<?php echo absint( $remedy->ID ); ?>" <?php selected( isset( $comparison[ $slot ] ) ? $comparison[ $slot ] : 0, $remedy->ID ); ?>><?php echo esc_html( $remedy->post_title ); ?></option><?php endforeach; ?></select></label>
				<?php endfor; ?>
				<button class="srf-button" type="submit">Compare Selected Remedies</button>
			</form>
			<?php if ( $comparison ) : echo SRF_Helpers::template( 'comparison', array( 'ids' => $comparison ) ); endif; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php else : ?>
			<div class="srf-empty"><h3>No published remedy entries are available yet</h3><p>Add referenced Remedy entries in the Encyclopedia to enable comparison.</p><a class="srf-button" href="<?php echo esc_url( $encyclopedia ); ?>">Open Encyclopedia</a></div>
		<?php endif; ?>
	</section>

	<section class="srf-results" aria-labelledby="radar-results-title">
		<div class="srf-section-head"><div><span>Educational Relationships</span><h2 id="radar-results-title">Radar Results</h2></div><p><?php echo absint( $query->found_posts ); ?> referenced entries found</p></div>
		<?php if ( $can_save ) : ?><div class="srf-study-toolbar"><strong>Verified Doctor Study Builder</strong><span data-srf-selection-status>Select up to three Radar entries.</span><button class="srf-button" type="button" data-srf-open-study disabled>Prepare Private Study</button></div><?php endif; ?>
		<div class="srf-grid">
			<?php if ( $query->have_posts() ) : while ( $query->have_posts() ) : $query->the_post(); echo SRF_Helpers::template( 'entry-card', array( 'entry_id' => get_the_ID(), 'can_save' => $can_save ) ); endwhile; else : ?>
				<div class="srf-empty"><h3>No Radar entries matched your search</h3><p>Remove one or more filters, try a broader phrase, or return after referenced entries have been published.</p></div>
			<?php endif; wp_reset_postdata(); ?>
		</div>
		<?php if ( $query->max_num_pages > 1 ) : $page = max( 1, absint( $query->get( 'paged' ) ) ); ?><nav class="srf-pagination" aria-label="Radar result pages"><?php if ( $page > 1 ) : ?><a href="<?php echo esc_url( add_query_arg( 'radar_page', $page - 1 ) ); ?>">Previous</a><?php endif; ?><span>Page <?php echo absint( $page ); ?> of <?php echo absint( $query->max_num_pages ); ?></span><?php if ( $page < $query->max_num_pages ) : ?><a href="<?php echo esc_url( add_query_arg( 'radar_page', $page + 1 ) ); ?>">Next</a><?php endif; ?></nav><?php endif; ?>
	</section>

	<?php if ( $can_save ) : ?>
	<section class="srf-study-panel" data-srf-study-panel hidden aria-labelledby="srf-study-title">
		<h2 id="srf-study-title">Save Private Radar Study</h2>
		<p>Use an anonymous research title. Do not enter a patient name, phone number, address, record number, or other identifying information.</p>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="srf_save_study"><input type="hidden" name="entry_ids" value="" data-srf-entry-ids><?php wp_nonce_field( 'srf_save_study', 'srf_nonce' ); ?>
			<label>Study Title<input name="study_title" maxlength="180" required placeholder="For example: Anonymous modality review"></label>
			<label>Private Research Notes<textarea name="study_notes" maxlength="3000" placeholder="Educational research notes only"></textarea></label>
			<label class="srf-check"><input type="checkbox" name="no_patient_identity" value="1" required> I confirm that these notes contain no patient-identifying information.</label>
			<button class="srf-button" type="submit">Save Private Study</button>
		</form>
	</section>
	<?php endif; ?>

	<section class="srf-connected"><div><span>Connected Knowledge</span><h2>Continue Responsible Research</h2><p>Review complete educational entries or connect with verified professionals.</p></div><div><a class="srf-button" href="<?php echo esc_url( $encyclopedia ); ?>">Open Encyclopedia</a><a class="srf-button srf-button-light" href="<?php echo esc_url( $doctors_url ); ?>">Find a Doctor</a></div></section>
	<p class="srf-disclaimer">Related remedies are presented for educational review only. Radar does not identify a best remedy, diagnose a condition, prescribe treatment, recommend potency or dosage, promise a cure, or replace qualified medical care.</p>
</main>
