<?php
/**
 * @var array $view_data
 */

?>
<script type="text/javascript">
	jQuery( function( $ ) {
		const chooseLabel = <?php echo wp_json_encode( $view_data['choose_label'] ); ?>;
		const changeLabel = <?php echo wp_json_encode( $view_data['change_label'] ); ?>;

		$( document.body ).on( 'click', '.fs-method-logo-field__select', function( event ) {
			event.preventDefault();

			const field = $( this ).closest( '.fs-method-logo-field' );
			const input = field.find( 'input[type="hidden"]' );
			const preview = field.find( '.fs-method-logo-field__preview' );
			const image = field.find( '.fs-method-logo-field__image' );
			const removeButton = field.find( '.fs-method-logo-field__remove' );
			const selectButton = $( this );
			const frame = wp.media( {
				title: changeLabel,
				library: {
					type: 'image'
				},
				button: {
					text: changeLabel
				},
				multiple: false
			} );

			frame.on( 'select', function() {
				const attachment = frame.state().get( 'selection' ).first().toJSON();
				const imageUrl = attachment?.sizes?.thumbnail?.url || attachment?.url || '';

				if ( ! imageUrl ) {
					return;
				}

				input.val( attachment.id || '' );
				image.attr( 'src', imageUrl );
				image.attr( 'alt', attachment.alt || attachment.title || '' );
				preview.prop( 'hidden', false );
				removeButton.prop( 'hidden', false );
				selectButton.text( changeLabel );
			} );

			frame.open();
		} );

		$( document.body ).on( 'click', '.fs-method-logo-field__remove', function( event ) {
			event.preventDefault();

			const field = $( this ).closest( '.fs-method-logo-field' );
			field.find( 'input[type="hidden"]' ).val( '' );
			field.find( '.fs-method-logo-field__image' ).attr( 'src', '' ).attr( 'alt', '' );
			field.find( '.fs-method-logo-field__preview' ).prop( 'hidden', true );
			field.find( '.fs-method-logo-field__select' ).text( chooseLabel );
			$( this ).prop( 'hidden', true );
		} );
	} );
</script>
