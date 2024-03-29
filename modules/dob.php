<?php

if (function_exists('wpcf7_add_form_tag')) {
	wpcf7_add_form_tag('dob', 'watts_dob_form_tag_handler', true);
	wpcf7_add_form_tag('dob*', 'watts_dob_form_tag_handler', true);
}

function watts_dob_form_tag_handler($tag)
{
	if (empty($tag->name)) {
		return '';
	}

	$validation_error = wpcf7_get_validation_error($tag->name);

	$class = wpcf7_form_controls_class($tag->type);

	$class .= ' wpcf7-validates-as-dob';

	if ($validation_error) {
		$class .= ' wpcf7-not-valid';
	}

	$atts = array();

	$atts['class'] = $tag->get_class_option($class);
	$atts['id'] = $tag->get_id_option();
	$atts['tabindex'] = $tag->get_option('tabindex', 'signed_int', true);

	if ($tag->is_required()) {
		$atts['aria-required'] = 'true';
	}

	if ($validation_error) {
		$atts['aria-invalid'] = 'true';
		$atts['aria-describedby'] = wpcf7_get_validation_error_reference(
			$tag->name
		);
	} else {
		$atts['aria-invalid'] = 'false';
	}

	$include_blank = $tag->has_option('include_blank');

	$value = (string) reset($tag->values);

	$value = $tag->get_default_option($value);
	if ($value) {
		$datetime_obj = date_create_immutable($value, wp_timezone());

		if ($datetime_obj) {
			// NOTE: プルダウンのvalueが0サプレスなので、初期値も合わせる
			$default_value = [
				'year' => $datetime_obj->format('Y'),
				'month' => $datetime_obj->format('n'),
				'day' => $datetime_obj->format('j'),
			];
		}
	}

	// NOTE: 寿命をもとに生年月日の範囲を算出
	$max_lifespan = 120;
	$start_year = intval(date_i18n('Y'));
	$until_year = $start_year - $max_lifespan;
	$html .= watts_dob_form_part($tag, $atts, 'year', $default_value['year'], range($start_year, $until_year), '年');
	$html .= watts_dob_form_part($tag, $atts, 'month', $default_value['month'], range(1, 12), '月');
	$html .= watts_dob_form_part($tag, $atts, 'day', $default_value['day'], range(1, 31), '日');

	$html = sprintf(
		'<span class="wpcf7-form-control-wrap %1$s">%2$s%3$s</span>',
		sanitize_html_class($tag->name), $html, $validation_error
	);

	return $html;
}

function watts_dob_form_part($tag, $atts, $name_key, $default_value, $values, $blank_item)
{
	$atts['name'] = sprintf('%1$s[%2$s]', $tag->name, $name_key);

	$labels = $values;

	$include_blank = $tag->has_option('include_blank');

	if ($include_blank) {
		array_unshift($labels, $blank_item);
		array_unshift($values, '');
	}

	$html = '';
	$hangover = wpcf7_get_hangover($tag->name);

	foreach ($values as $key => $value) {
		if ($hangover) {
			$selected = $value === (int) $hangover[$name_key];
		} else {
			$selected = $value === (int) $default_value;
		}

		$item_atts = array(
			'value' => $value,
			'selected' => $selected ? 'selected' : '',
		);

		$item_atts = wpcf7_format_atts($item_atts);

		$label = isset($labels[$key]) ? $labels[$key] : $value;

		$html .= sprintf(
			'<option %1$s>%2$s</option>',
			$item_atts,
			esc_html($label)
		);
	}
	$atts = wpcf7_format_atts($atts);

	$html = sprintf(
		'<select %1$s>%2$s</select>',
		$atts, $html
	);

	return $html;
}

add_filter('wpcf7_posted_data_dob', 'watts_posted_data_dob', 10, 3);
add_filter('wpcf7_posted_data_dob*', 'watts_posted_data_dob', 10, 3);

function watts_posted_data_dob($value, $value_orig, $tag)
{
	if (!isset($value_orig) ||
		!isset($value_orig['year']) ||
		!isset($value_orig['month']) ||
		!isset($value_orig['day']) ||
		in_array('', $value_orig, true)) {
		return '';
	}
	return $value_orig['year'] . '/' . $value_orig['month'] . '/' . $value_orig['day'];
}

add_filter('wpcf7_validate_dob', 'watts_dob_validation_filter', 10, 2);
add_filter('wpcf7_validate_dob*', 'watts_dob_validation_filter', 10, 2);

function watts_dob_validation_filter($result, $tag)
{
	$name = $tag->name;

	$values = [
		'year' => isset($_POST[$name]['year']) ? trim($_POST[$name]['year']) : '',
		'month' => isset($_POST[$name]['month']) ? trim($_POST[$name]['month']) : '',
		'day' => isset($_POST[$name]['day']) ? trim($_POST[$name]['day']) : '',
	];

	$blank_flags = [
		'year' => $values['year'] === '',
		'month' => $values['month'] === '',
		'day' => $values['day'] === '',
	];

	if (!in_array(false, $blank_flags, true) !== false) {
		// すべて入力されていない
		if ($tag->is_required()) {
			// 必須項目
			$result->invalidate($name, '必須項目に入力してください。');
		}
		// 必須項目ではなく、全て入力されていない場合は許容
	} else if (in_array(true, $blank_flags, true) !== false) {
		// 中途半端な入力
		$result->invalidate($name, '不正な日付です。');
	} else if (in_array(false, array_map('is_numeric', array_values($values)), true)) {
		// 数値かどうか
		$result->invalidate($name, '不正な入力値です。');
	} else if (!checkdate($values['month'], $values['day'], $values['year'])) {
		// 数値が入力されていた場合のチェック
		$result->invalidate($name, '存在しない日付です。');
	}

	return $result;
}

add_action('wpcf7_admin_init', 'watts_add_tag_generator_dob', 19, 0);

function watts_add_tag_generator_dob()
{
	$tag_generator = WPCF7_TagGenerator::get_instance();
	$tag_generator->add('dob', __('生年月日', 'contact-form-7'),
		'watts_tag_generator_dob');
}

function watts_tag_generator_dob($contact_form, $args = '')
{
	$args = wp_parse_args($args, array());
	$type = 'dob';

	$description = '生年月日入力項目のフォームタグを生成します。';

	?>
<div class="control-box">
<fieldset>
<legend><?php echo esc_html($description); ?></legend>

<table class="form-table">
<tbody>
	<tr>
	<th scope="row"><?php echo esc_html(__('Field type', 'contact-form-7')); ?></th>
	<td>
		<fieldset>
		<legend class="screen-reader-text"><?php echo esc_html(__('Field type', 'contact-form-7')); ?></legend>
		<label><input type="checkbox" name="required" /> <?php echo esc_html(__('Required field', 'contact-form-7')); ?></label>
		</fieldset>
	</td>
	</tr>

	<tr>
	<th scope="row"><label for="<?php echo esc_attr($args['content'] . '-name'); ?>"><?php echo esc_html(__('Name', 'contact-form-7')); ?></label></th>
	<td><input type="text" name="name" class="tg-name oneline" id="<?php echo esc_attr($args['content'] . '-name'); ?>" /></td>
	</tr>

	<tr>
	<th scope="row"><label for="<?php echo esc_attr($args['content'] . '-values'); ?>"><?php echo esc_html(__('Default value', 'contact-form-7')); ?></label></th>
	<td><input type="text" name="values" class="oneline" id="<?php echo esc_attr($args['content'] . '-values'); ?>" /><br />
	</tr>

	<tr>
	<th scope="row"><?php echo esc_html(__('Options', 'contact-form-7')); ?></th>
	<td>
		<fieldset>
		<legend class="screen-reader-text"><?php echo esc_html(__('Options', 'contact-form-7')); ?></legend>
		<label><input type="checkbox" name="include_blank" class="option" /> <?php echo esc_html(__('Insert a blank item as the first option', 'contact-form-7')); ?></label>
		</fieldset>
	</td>
	</tr>

	<tr>
	<th scope="row"><label for="<?php echo esc_attr($args['content'] . '-id'); ?>"><?php echo esc_html(__('Id attribute', 'contact-form-7')); ?></label></th>
	<td><input type="text" name="id" class="idvalue oneline option" id="<?php echo esc_attr($args['content'] . '-id'); ?>" /></td>
	</tr>

	<tr>
	<th scope="row"><label for="<?php echo esc_attr($args['content'] . '-class'); ?>"><?php echo esc_html(__('Class attribute', 'contact-form-7')); ?></label></th>
	<td><input type="text" name="class" class="classvalue oneline option" id="<?php echo esc_attr($args['content'] . '-class'); ?>" /></td>
	</tr>
</tbody>
</table>
</fieldset>
</div>

<div class="insert-box">
	<input type="text" name="<?php echo $type; ?>" class="tag code" readonly="readonly" onfocus="this.select()" />

	<div class="submitbox">
	<input type="button" class="button button-primary insert-tag" value="<?php echo esc_attr(__('Insert Tag', 'contact-form-7')); ?>" />
	</div>

	<br class="clear" />

	<p class="description mail-tag"><label for="<?php echo esc_attr($args['content'] . '-mailtag'); ?>"><?php echo sprintf(esc_html(__("To use the value input through this field in a mail field, you need to insert the corresponding mail-tag (%s) into the field on the Mail tab.", 'contact-form-7')), '<strong><span class="mail-tag"></span></strong>'); ?><input type="text" class="mail-tag code hidden" readonly="readonly" id="<?php echo esc_attr($args['content'] . '-mailtag'); ?>" /></label></p>
</div>
<?php
}
