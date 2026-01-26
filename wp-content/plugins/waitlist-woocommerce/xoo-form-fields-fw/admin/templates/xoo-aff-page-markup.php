<div class="xoo-aff-settings-modal">

	<div class="xoo-aff-settings-topbar">
		<div class="xoo-aff-notice-holder"></div>
		<button id="xoo-aff-save"><span class="fas fa-save"></span>Save</button>
		<form class="xoo-aff-form-save" method="POST" style="display: none;"></form>
		<button class="xoo-aff-reset-field"><span class="fas fa-sync"></span>Reset</button>
	</div>
	

	<div class="xoo-aff-grpfield-cont">
		<div class="xoo-aff-field-groups">
			<?php foreach ( $groups as $group_id => $group_args ): ?>
				<div data-grpid="<?php echo $group_id ?>" class="xoo-aff-grp-selector"><?php echo $group_args['title']; ?></div>
			<?php endforeach; ?>
		</div>
		<button class="xoo-aff-add-field"><span class="fas fa-plus-circle"></span>Add Field</button>
	</div>

	<div class="xoo-aff-settings-container">

		<ul class="xoo-aff-fields-list"></ul>
		
		<div class="xoo-aff-field-view">
			<?php echo wp_kses_post( $sidebar_template ); ?>
			<div class="xoo-aff-field-settings-container"></div>
		</div>

		
	</div>

</div>