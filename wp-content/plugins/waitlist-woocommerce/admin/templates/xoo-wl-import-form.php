<div class="xoo-wl-imp-container">

	<button class="xoo-wl-imp-toogle button-primary">Import Waitlist</button>

	<form class="xoo-wl-imp-form" enctype="multipart/form-data" method="post">
		
		<h3>Upload CSV File</h3>
		<a href="<?php echo esc_url( add_query_arg('xoo-wl-import-format', 'yes') ) ?>">Download CSV Format</a>
		<div style="margin-bottom: 10px;"><i>Product ID & Email field are mandatory, rest are optional.</i></div>
		<input type="file" name="xoo-wl-imp-file" accept=".csv">
		<button type="submit" class="xoo-wl-imp-form-btn button-primary">Import</button>
		<?php wp_nonce_field( 'xoo_wl_imp_nonce', 'xoo-wl-imp-nonce' ); ?>

	</form>
	
</div>

<?php if( get_option( 'xoo-wl-import-started-notice', true ) === "yes" ): ?>
	<span class="xoo-wl-imp-progress">Import started in the background, It can take upto few seconds to minutes depending on your list.</span>
	<?php update_option( 'xoo-wl-import-started-notice', 'no' ); ?>
<?php endif; ?>

<?php if( get_option( 'xoo-wl-import-in-progress', true ) === "yes" ): ?>
	<span class="xoo-wl-imp-progress">Import is already in progress</span>
<?php endif; ?>

<?php if( get_option( 'xoo-wl-import-success', true ) === "yes" ): ?>
	<span class="xoo-wl-imp-progress">Imported successfully.</span>
	<?php update_option( 'xoo-wl-import-success', 'no' ); ?>
<?php endif; ?>