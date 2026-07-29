<?php defined( 'ABSPATH' ) || exit; ?>
<div class="srf-comparison" tabindex="0" aria-label="Selected remedy comparison">
	<table>
		<thead><tr><th scope="col">Comparison Field</th><?php foreach ( $ids as $id ) : ?><th scope="col"><a href="<?php echo esc_url( get_permalink( $id ) ); ?>"><?php echo esc_html( get_the_title( $id ) ); ?></a></th><?php endforeach; ?></tr></thead>
		<tbody>
			<?php foreach ( array( 'body_system' => 'Main Sphere of Action', 'key_points' => 'Key Educational Points', 'symptoms' => 'Symptoms or Characteristics', 'modalities' => 'Modalities', 'safety' => 'Safety and Limitations', 'references' => 'References' ) as $field => $label ) : ?>
			<tr><th scope="row"><?php echo esc_html( $label ); ?></th><?php foreach ( $ids as $id ) : $value = HE_Content::meta( $id, $field ); ?><td><?php echo $value ? nl2br( esc_html( $value ) ) : '<span class="srf-not-supplied">Not supplied</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td><?php endforeach; ?></tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<p>Comparison is for educational review only and does not select treatment, potency, or dosage.</p>
</div>
