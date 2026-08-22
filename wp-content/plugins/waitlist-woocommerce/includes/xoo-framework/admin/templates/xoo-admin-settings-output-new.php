<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

$options 	= apply_filters( 'xoo_aff_export_options', $adminObj->tabs, $adminObj->helper->slug );
$sections 	= $adminObj->sections;

?>

<div class="xoo-as-container">


		
	<div class="xoo-as-topbar">

		<div class="xoo-as-tbar-second">

			<ul class="xoo-sc-tbar-tabs">

				<?php foreach( $tabs as $tab_id => $tab_data ): ?>

					<li data-tab="<?php echo esc_attr( $tab_id ); ?>" <?php if( $tab_data['pro'] === 'yes' ) echo 'class="xoo-as-is-pro"'; ?>>

						<?php if( isset( $tab_data['args']['icon'] ) ): ?>

							<span class="xoo-as-icon <?php echo esc_html( $tab_data['args']['icon'] ); ?>"></span>

						<?php endif; ?>

						<span><?php echo esc_html( $tab_data['title'] ); ?></span>

					</li>

				<?php endforeach; ?>

			</ul>

		</div>
	</div>



	<div class="xoo-settings-container">

		<div class="xoo-as-setsidebar">

			<div class="xoo-as-setsec-container">

				<?php foreach( $tabs as $tab_id => $tab_data ): ?>

					<?php if( !isset( $sections[ $tab_id ] ) ) continue; ?>
					
					<div class="xoo-as-setsections" data-tab="<?php echo esc_attr( $tab_id ) ?>">

						<?php foreach ( $sections[$tab_id] as $section_id => $section_data ): ?>

		
							<a href="#<?php echo esc_attr( $tab_id.'_'.$section_id ) ?>" class="xoo-as-setsbar-section <?php echo ( isset( $section_data['pro'] ) && $section_data['pro'] === 'yes' ) ? 'xoo-sec-pro' : '' ?>">

								<?php if( isset( $section_data['args']['icon'] ) ): ?>

									<span class="xoo-as-icon <?php echo esc_html( $section_data['args']['icon'] ); ?>"></span>

								<?php endif; ?>

								<span class="xoo-as-setbar-sectitle"><?php echo esc_html( $section_data['title'] ); ?></span>

							</a>

						<?php endforeach; ?>

					</div>
				
				

				<?php endforeach; ?>

			</div>

			<div class="xoo-as-setbar-help">
				<div><span class="xoo-as-icon xoo-icon-help"></span>Need Help?</div>
				<span>Check our documentation or contact support.</span>
				<a href="https://xootix.com/contact" class="xoo-btn xoo-btn-secondary" target="__blank"><span class="xoo-as-icon xoo-icon-window"></span>Contact</a>
			</div>

		</div>

		<div class="xoo-as-setmain">
			<form class="xoo-as-form">

				<?php foreach( $tabs as $tab_id => $tab_data ): ?>
					<div class="xoo-sc-tab-content" data-tab="<?php echo esc_attr( $tab_id ); ?>">

						<?php do_action( 'xoo_tab_page_start', $tab_id, $tab_data ); ?>
						<?php $adminObj->create_settings_html( $tab_id ); ?>
						<?php do_action( 'xoo_tab_page_end', $tab_id, $tab_data ); ?>
					</div>
				<?php endforeach; ?>

				<div class="xoo-sc-bottom-btns">

					<?php if( $hasPRO ): ?>
						<a class="xoo-as-pro-toggle xoo-btn xoo-btn-primary "><span class="xoo-as-icon xoo-icon-crown"></span>Show Pro options</a>
						<a class="xoo-as-pro-toggle xoo-aspt-two xoo-btn xoo-btn-primary"><span class="xoo-as-icon xoo-icon-crown"></span>Hide Pro options</a>
					<?php endif; ?>

					

					<a class="xoo-as-form-reset xoo-btn xoo-btn-secondary" href="<?php echo esc_url( add_query_arg( 'reset', wp_create_nonce('reset') ) ) ?>"><span class="xoo-as-icon xoo-icon-refresh"></span>Reset</a>

					<div class="xoo-as-exim">
						<div class="xoo-as-eximbtns" style="display: none;">
							<span class="xoo-as-setexport" >Export Settings</span>
							<span class="xoo-as-setimport">Import Settings</span>
						</div>
						<button class="xoo-btn xoo-btn-secondary" type="button"><span class="xoo-as-icon xoo-icon-export"></span>Move Settings</button>
					</div>

					<button type="submit" class="xoo-as-form-save xoo-btn xoo-btn-primary"><span class="xoo-as-icon xoo-icon-save"></span>Save</button>

				</div>

			</form>

			<div class="xoo-as-modal">

				<div class="xoo-as-expimmodal">
					
					<div class="xoo-as-emod-cont">

						<span class="xoo-as-exipclose">X</span>

						<div class="xoo-as-excont">

							<div class="xoo-as-exoptions">

								<span>Export settings</span>

								<div class="xoo-as-expcheck">
									<?php

									foreach ( $options as $id => $data ) {
										if( !$data['option_key'] ) continue;
										?>
										<label>
											<?php esc_html_e( $data['title'] ); ?>
											<input type="checkbox" value="<?php echo esc_attr( $data['option_key'] ) ?>" checked>
										</label>
										<?php
									}

									?>
								</div>

								<i>Any unsaved changed will not be exported. Please make sure to save settings.</i>
							

								<button class="xoo-as-run-export">Export</button>

							</div>

							<div class="xoo-as-expdone">

								<b>Copy the value below and paste it into the 'Import settings' feature on the website you want to import it to</b>
								<i>Files/Image upload settings need to be done manually.</i>

								<textarea rows="10"></textarea>

							</div>
						</div>

						<div class="xoo-as-impcont">
							<b>Paste the copied value here.</b>
							<i>Files/Image upload settings need to be done manually.</i>
							<textarea rows="10"></textarea>
							<button class="xoo-as-run-import">Run Import</button>
							<span class="xoo-as-imported">Import Completed. Refreshing....</span>
						</div>
					</div>
				</div>
			</div>
		</div>


		<?php if( $hasSidebar ): ?>
			<div class="xoo-as-sidebar">
				<span class="dashicons dashicons-admin-collapse xoo-as-sbar-close"></span>
				<div class="xoo-as-sidebar-content">
					<?php do_action( 'xoo_as_setting_sidebar_'.$adminObj->helper->slug ); ?>
				</div>
			</div>
		<?php endif; ?>
	
	</div>


</div>