<?php
/**
* Zen Block © 2011-2013, Bugo
* Subs-ZenBlock.php
* License http://opensource.org/licenses/artistic-license-2.0
* Support and updates for this software can be found at
* http://dragomano.ru/page/zen-block
*/

if (!defined('SMF'))
	die('Hacking attempt...');

loadLanguage('ZenBlock');

// Uses integrate_menu_buttons
function zen_preload()
{
	global $modSettings, $context;

	if (empty($modSettings['zen_block_enable']) || empty($context['current_board']))
		return;

	$ignore_boards = array();
	
	if (!empty($modSettings['zen_ignore_boards']))
		$ignore_boards = explode(",", $modSettings['zen_ignore_boards']);

	if (!empty($modSettings['recycle_board']))
		$ignore_boards[] = $modSettings['recycle_board'];
	
	if (in_array($context['current_board'], $ignore_boards))
		return;

	if (!empty($context['current_topic']) && isset($context['topic_first_message'])) {
		if (empty($modSettings['zen_block_enable']))
			return;
		
		if ($modSettings['zen_block_enable'] == 1 ? $context['first_message'] != $context['topic_first_message'] : $modSettings['zen_block_enable'] == 2)
			zen_block();
	}
}

/**
* Get topic first message (content)
* Check topic popularity
* Make attachment block
* Loading from zen_preload (see above)
*/
function zen_block()
{
	global $txt, $smcFunc, $context, $boarddir, $scripturl, $modSettings, $settings;

	loadTemplate('ZenBlock', 'zen');
	$context['template_layers'][] = 'zen';

	if (($context['zen_block'] = cache_get_data('zen_block_' . $context['current_topic'], 3600)) == null) {
		$request = $smcFunc['db_query']('', '
			SELECT body
			FROM {db_prefix}messages
			WHERE id_topic = {int:current_topic}
				AND id_msg = {int:first_message}
			LIMIT 1',
			array(
				'current_topic' => $context['current_topic'],
				'first_message' => $context['topic_first_message'],
			)
		);
		
		while ($row = $smcFunc['db_fetch_assoc']($request)) {
			censorText($row['body']);
			$context['zen_block'] = parse_bbc($row['body']);
		}
		
		$smcFunc['db_free_result']($request);
		
		cache_put_data('zen_block_' . $context['current_topic'], $context['zen_block'], 3600);
	}
	
	// Check topic popularity
	$context['top_topic'] = false;
	
	if (!file_exists($boarddir . '/SSI.php'))
		return;
	
	require_once($boarddir . '/SSI.php');
	$link = $scripturl . '?topic=' . $context['current_topic'] . '.0';
	$topics = ssi_topTopicsReplies(1, null, 'array');
	
	foreach ($topics as $topic) {
		if ($link == $topic['href'])
			$context['top_topic'] = true;
	}

	// Make attachment block
	$context['zen_attachments'] = '';
	
	if (!empty($modSettings['zen_attach_block']) && allowedTo('view_attachments')) {	
		$attachment_ext = explode(",", $modSettings['attachmentExtensions']);
		
		$request = $smcFunc['db_query']('', '
			SELECT id_attach, id_thumb, filename, width, height
			FROM {db_prefix}attachments
			WHERE id_msg = {int:first_message}
				AND attachment_type = 0
				AND fileext IN ({array_string:attachment_ext})
				AND approved = 1
			ORDER BY id_attach ASC
			LIMIT {int:num_attachments}',
			array(
				'first_message'   => $context['topic_first_message'],
				'attachment_ext'  => $attachment_ext,
				'num_attachments' => $modSettings['attachmentNumPerPostLimit'],
			)
		);
		
		$context['attachments'] = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))	{
			$filename = preg_replace('~&amp;#(\\d{1,7}|x[0-9a-fA-F]{1,6});~', '&#\\1;', htmlspecialchars($row['filename']));
			
			$context['attachments'][$row['id_attach']] = array(
				'file' => array(
					'filename' => $filename,
					'href'     => $scripturl . '?action=dlattach;topic=' . $context['current_topic'] . '.0;attach=' . $row['id_attach'],
					'is_image' => !empty($row['width']) && !empty($row['height']) && !empty($modSettings['attachmentShowImages']),
				),
			);
			
			if ($context['attachments'][$row['id_attach']]['file']['is_image'])	{
				$id_thumb = empty($row['id_thumb']) ? $row['id_attach'] : $row['id_thumb'];
				$context['attachments'][$row['id_attach']]['file']['image'] = array(
					'id'   => $id_thumb,
					'href' => $scripturl . '?action=dlattach;topic=' . $context['current_topic'] . '.0;attach=' . $id_thumb . ';image',
				);
			}
		}
		
		$smcFunc['db_free_result']($request);
		
		foreach ($context['attachments'] as $attach) {
			if (!$attach['file']['is_image'])
				$link = '<img src="' . $settings['images_url'] . '/icons/clip.gif" alt="" />&nbsp;<a href="' . $attach['file']['href'] . '">' . $attach['file']['filename'] . '</a>';
			else
				$link = '<img src="' . $settings['images_url'] . '/icons/clip.gif" alt="" />&nbsp;<a href="' . $attach['file']['href'] . '" class="imgTip' . $attach['file']['image']['id'] . '">' . $attach['file']['filename'] . '</a>';
			$context['zen_attachments'] .= $link . '<br/>';
		}
		
		if (!isset($context['tooltips']))
			$context['tooltips'] = 'dark';
	}
}

// Uses integrate_admin_areas
function zen_admin_areas(&$admin_areas)
{
	global $txt;
	
	$admin_areas['config']['areas']['modsettings']['subsections']['zen'] = array($txt['zen_settings']);
}

// Uses integrate_modify_modifications
function zen_modifications(&$subActions)
{
	$subActions['zen'] = 'zen_settings';
}

// Loading from zen_modifications (see above)
function zen_settings()
{
	global $txt, $context, $scripturl, $modSettings;
	
	loadTemplate('ZenBlock');
	
	$context['page_title'] = $context['settings_title'] = $txt['zen_settings'];
	$context['post_url'] = $scripturl . '?action=admin;area=modsettings;save;sa=zen';
	$context[$context['admin_menu_name']]['tab_data']['tabs']['zen'] = array('description' => $txt['zen_desc']);
	
	if (!isset($modSettings['zen_yashare_blocks']))
		updateSettings(array('zen_yashare_blocks' => $txt['zen_yashare_icons']));
		
	zen_ignore_boards();
	
	$config_vars = array(
		array('select', 'zen_block_enable', $txt['zen_where_is']),
		array('select', 'zen_block_status', $txt['zen_block_status_set']),
		array('check', 'zen_attach_block'),
		array('check', 'zen_img_preview'),
		array('check', 'zen_gplus'),
		array('select', 'zen_yashare', $txt['zen_yashare_set'])
	);
	
	if (!empty($modSettings['zen_yashare'])) {
		$config_vars[] = array('title', 'zen_yashare_title');
		$config_vars[] = array('desc', 'zen_yashare_desc');
		$config_vars[] = array('large_text', 'zen_yashare_blocks', '" style="width:80%');
		$config_vars[] = array('large_text', 'zen_yashare_array', '" style="width:80%');
	}
	
	$config_vars[] = array('title', 'zen_ignore_boards');
	$config_vars[] = array('desc', 'zen_ignore_boards_desc');
	$config_vars[] = array('callback', 'zen_ignored_boards');
	
	// Saving?
	if (isset($_GET['save'])) {
		if (empty($_POST['ignore_brd']))
			$_POST['ignore_brd'] = array();

		unset($_POST['ignore_boards']);
		if (isset($_POST['ignore_brd'])) {
			if (!is_array($_POST['ignore_brd']))
				$_POST['ignore_brd'] = array($_POST['ignore_brd']);

			foreach ($_POST['ignore_brd'] as $k => $d) {
				$d = (int) $d;
				if ($d != 0)
					$_POST['ignore_brd'][$k] = $d;
				else
					unset($_POST['ignore_brd'][$k]);
			}
			$_POST['ignore_boards'] = implode(',', $_POST['ignore_brd']);
			unset($_POST['ignore_brd']);
		}
	
		checkSession();
		saveDBSettings($config_vars);
		updateSettings(array('zen_ignore_boards' => $_POST['ignore_boards']));
		redirectexit('action=admin;area=modsettings;sa=zen');
	}
	
	prepareDBSettingContext($config_vars);
}

// Loading from zen_settings (see above)
function zen_ignore_boards()
{
	global $txt, $user_info, $context, $modSettings, $smcFunc;

	$request = $smcFunc['db_query']('order_by_board_order', '
		SELECT b.id_cat, c.name AS cat_name, b.id_board, b.name, b.child_level,
			'. (!empty($modSettings['zen_ignore_boards']) ? 'b.id_board IN ({array_int:ignore_boards})' : '0') . ' AS is_ignored
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
		WHERE redirect = {string:empty_string}' . (!empty($modSettings['recycle_board']) ? '
			AND b.id_board != {int:recycle_board}' : ''),
		array(
			'ignore_boards' => !empty($modSettings['zen_ignore_boards']) ? explode(',', $modSettings['zen_ignore_boards']) : array(),
			'recycle_board' => !empty($modSettings['recycle_board']) ? $modSettings['recycle_board'] : null,
			'empty_string'  => '',
		)
	);
	
	$context['num_boards'] = $smcFunc['db_num_rows']($request);
	$context['categories'] = array();
	
	while ($row = $smcFunc['db_fetch_assoc']($request))	{
		if (!isset($context['categories'][$row['id_cat']]))
			$context['categories'][$row['id_cat']] = array(
				'id'     => $row['id_cat'],
				'name'   => $row['cat_name'],
				'boards' => array(),
			);

		$context['categories'][$row['id_cat']]['boards'][$row['id_board']] = array(
			'id'          => $row['id_board'],
			'name'        => $row['name'],
			'child_level' => $row['child_level'],
			'selected'    => $row['is_ignored'],
		);
	}
	$smcFunc['db_free_result']($request);

	$temp_boards = array();
	foreach ($context['categories'] as $category) {
		$context['categories'][$category['id']]['child_ids'] = array_keys($category['boards']);

		$temp_boards[] = array(
			'name'      => $category['name'],
			'child_ids' => array_keys($category['boards']),
		);
		$temp_boards = array_merge($temp_boards, array_values($category['boards']));
	}

	$max_boards = ceil(count($temp_boards) / 2);
	if ($max_boards == 1)
		$max_boards = 2;

	$context['board_columns'] = array();
	for ($i = 0; $i < $max_boards; $i++) {
		$context['board_columns'][] = $temp_boards[$i];
		if (isset($temp_boards[$i + $max_boards]))
			$context['board_columns'][] = $temp_boards[$i + $max_boards];
		else
			$context['board_columns'][] = array();
	}
}

?>