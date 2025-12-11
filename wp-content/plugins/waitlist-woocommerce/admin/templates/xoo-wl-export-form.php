<?php if( empty( $fields ) ) return; ?>


<div class="xoo-wl-exp-container">

	<button class="xoo-wl-exp-toogle button-primary">Export Waitlist</button>

	<form class="xoo-wl-exp-form">

		<h3>Include</h3>

		<?php foreach ( $fields as $id => $field_data ): ?>
			<div class="xoo-wl-expf-cont">
				<input type="checkbox" id="<?php echo $id; ?>" name="<?php echo $id; ?>" <?php echo $field_data['checked'] === "yes" ? 'checked' : ''; ?> value="yes" >
				<label for="<?php echo $id; ?>"><?php echo $field_data['title']; ?></label>
			</div>
		<?php endforeach; ?>

		<input type="hidden" name="table_type" value="<?php echo $table_type; ?>">

		<?php if( isset( $product_id ) ): ?>
			<input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
		<?php endif; ?>

		<button type="submit" class="xoo-wl-exp-form-btn button-primary">Export</button>

	</form>

	<div class="xoo-wl-exp-notice"></div>
	
</div>