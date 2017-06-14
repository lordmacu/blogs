<form class="settings" method="post" action="<?php echo $this->baseUrl ?>" enctype="multipart/form-data">

<div class="wpallexport-header">
	<div class="wpallexport-logo"></div>
	<div class="wpallexport-title">
		<p><?php _e('WP All Export', 'wp_all_export_plugin'); ?></p>
		<h3><?php _e('Settings', 'wp_all_export_plugin'); ?></h3>			
	</div>	
</div>
<h2 style="padding:0px;"></h2>
<?php if ($this->errors->get_error_codes()): ?>
	<?php $this->error() ?>
<?php endif ?>

</form>
<br />

<form name="settings" method="post" action="<?php echo $this->baseUrl ?>">

	<h3><?php _e('Cron Exports', 'wp_all_export_plugin') ?></h3>
	
	<table class="form-table">
		<tbody>
			<tr>
				<th scope="row"><label><?php _e('Secret Key', 'wp_all_export_plugin'); ?></label></th>
				<td>
					<input type="text" class="regular-text" name="cron_job_key" value="<?php echo esc_attr($post['cron_job_key']); ?>"/>
					<p class="description"><?php _e('Changing this will require you to re-create your existing cron jobs.', 'wp_all_export_plugin'); ?></p>
				</td>
			</tr>			
		</tbody>
	</table>	

	<div class="clear"></div>
	
	<h3><?php _e('Files', 'wp_all_export_plugin') ?></h3>
	
	<table class="form-table">
		<tbody>
			<tr>
				<th scope="row"><label><?php _e('Secure Mode', 'wp_all_export_plugin'); ?></label></th>
				<td>
					<fieldset style="padding:0;">
						<legend class="screen-reader-text"><span><?php _e('Secure Mode', 'wp_all_export_plugin'); ?></span></legend>
						<input type="hidden" name="secure" value="0"/>
						<label for="secure"><input type="checkbox" value="1" id="secure" name="secure" <?php echo (($post['secure']) ? 'checked="checked"' : ''); ?>><?php _e('Randomize folder names', 'wp_all_export_plugin'); ?></label>																				
					</fieldset>														
					<p class="description">
						<?php
							$wp_uploads = wp_upload_dir();
						?>
						<?php printf(__('Exported files and temporary files will be placed in a folder with a randomized name inside of %s.', 'wp_all_export_plugin'), $wp_uploads['basedir'] . DIRECTORY_SEPARATOR . WP_ALL_EXPORT_UPLOADS_BASE_DIRECTORY ); ?>
					</p>
				</td>
			</tr>			
		</tbody>
	</table>	

	<div class="clear"></div>	

	<p class="submit-buttons">
		<?php wp_nonce_field('edit-settings', '_wpnonce_edit-settings') ?>
		<input type="hidden" name="is_settings_submitted" value="1" />
		<input type="submit" class="button-primary" value="Save Settings" />
	</p>

</form>

<?php
	$uploads = wp_upload_dir();
	$functions = $uploads['basedir'] . DIRECTORY_SEPARATOR . WP_ALL_EXPORT_UPLOADS_BASE_DIRECTORY . DIRECTORY_SEPARATOR . 'functions.php';
	$functions_content = file_get_contents($functions);
?>
<hr />
<br>
<h3><?php _e('Function Editor', 'pmxe_plugin') ?></h3>

<textarea id="wp_all_export_code" name="wp_all_export_code"><?php echo (empty($functions_content)) ? "<?php\n\n?>": $functions_content;?></textarea>						

<div class="input" style="margin-top: 10px;">

	<div class="input" style="display:inline-block; margin-right: 20px;">
		<input type="button" class="button-primary wp_all_export_save_functions" value="<?php _e("Save Functions", 'wp_all_export_plugin'); ?>"/>
		<a href="#help" class="wpallexport-help" title="<?php printf(__("Add functions here for use during your export. You can access this file at %s", "wp_all_export_plugin"), preg_replace("%.*wp-content%", "wp-content", $functions));?>" style="top: 0;">?</a>							
		<div class="wp_all_export_functions_preloader"></div>
	</div>						
	<div class="input wp_all_export_saving_status" style="display:inline-block;">

	</div>

</div>

<a href="http://soflyy.com/" target="_blank" class="wpallexport-created-by"><?php _e('Created by', 'wp_all_export_plugin'); ?> <span></span></a>
