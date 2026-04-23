<?php
/**
 * @var array $view_data
 */

?>
<tr valign="top">
	<th scope="row" class="titledesc">
		<label for="<?php echo esc_attr( $view_data['field_key'] ); ?>"><?php echo esc_html( $view_data['field_title'] ); ?></label>
	</th>
	<td class="forminp">
		<div class="fs-method-logo-field" data-field-key="<?php echo esc_attr( $view_data['field_key'] ); ?>">
			<input
				type="hidden"
				name="<?php echo esc_attr( $view_data['field_key'] ); ?>"
				id="<?php echo esc_attr( $view_data['field_key'] ); ?>"
				value="<?php echo esc_attr( $view_data['attachment_id'] ); ?>"
			/>
			<div class="fs-method-logo-field__preview" <?php echo '' === $view_data['image_url'] ? 'hidden' : ''; ?>>
				<img
					class="fs-method-logo-field__image"
					src="<?php echo esc_url( $view_data['image_url'] ); ?>"
					alt="<?php echo esc_attr( $view_data['image_alt'] ); ?>"
					style="max-width:96px;max-height:48px;width:auto;height:auto;"
				/>
			</div>
			<p class="fs-method-logo-field__actions">
				<button type="button" class="button fs-method-logo-field__select">
					<?php echo esc_html( $view_data['select_label'] ); ?>
				</button>
				<a
					href="#"
					class="button-link-delete fs-method-logo-field__remove"
					<?php echo ! $view_data['is_logo_selected'] ? 'hidden' : ''; ?>
				>
					<?php echo esc_html( $view_data['remove_label'] ); ?>
				</a>
			</p>
			<?php if ( '' !== $view_data['description'] ) : ?>
				<p class="description"><?php echo wp_kses_post( $view_data['description'] ); ?></p>
			<?php endif; ?>
		</div>
	</td>
</tr>
