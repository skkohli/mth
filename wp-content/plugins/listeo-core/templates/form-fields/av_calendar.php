<!-- Section -->
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
	$field = $data->field;
	$key = $data->key;


if(is_array($field['value'])) :
$i=0;

?>
	
<div class="row">
	<div class="col-md-12">
		
			<table id="availability-list-container">
				<?php foreach ($field['value'] as $m_key => $menu) { ?>
                    <tr class="availability-list-item pattern">
				        <td>
                        <div class="fm-move"><i class="sl sl-icon-cursor-move"></i></div>
                        <div class="fm-input availability-name"><input type="text" subtype="daterange" name="_nonav_date[]" value="<?php echo $menu; ?>" /></div>
                        <div class="fm-close"><a class="delete" href="#"><i class="fa fa-remove"></i></a></div>
                        </td>
                    </tr>
                <?php
				$i++;
				} ?>
		</table>
		<a href="#" class="button add-availability-list-item"><?php esc_html_e('Add Dates','listeo_core'); ?></a>
	</div>
</div>

<?php else : ?>
<div class="row">
	<div class="col-md-12">
		<table id="availability-list-container">
			<tr class="availability-list-item pattern">
				<td>
					<div class="fm-move"><i class="sl sl-icon-cursor-move"></i></div>
					<div class="fm-input availability-name"><input type="text" subtype="daterange" name="_nonav_date[]" /></div>
					<div class="fm-close"><a class="delete" href="#"><i class="fa fa-remove"></i></a></div>
				</td>
			</tr>
		</table>
		<a href="#" class="button add-availability-list-item"><?php esc_html_e('Add Dates','listeo_core'); ?></a>
	</div>
</div>
<?php endif; ?>
