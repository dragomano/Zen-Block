<?php

/**
 * Class-ZenBlock.php
 *
 * @package Zen Block
 * @link https://dragomano.ru/mods/zen-block
 * @author Bugo <bugo@dragomano.ru>
 * @copyright 2011-2020 Bugo
 * @license https://opensource.org/licenses/artistic-license-2.0 Artistic License
 *
 * @version 0.9
 */

if (!defined('SMF'))
	die('Hacking attempt...');

class ZenBlock
{
	/**
	 * Подключаем используемые хуки
	 *
	 * @return void
	 */
	public static function hooks()
	{
		add_integration_function('integrate_load_theme', __CLASS__ . '::loadTheme', false);
		add_integration_function('integrate_menu_buttons', __CLASS__ . '::menuButtons', false);
		add_integration_function('integrate_admin_areas', __CLASS__ . '::adminAreas', false);
		add_integration_function('integrate_modify_modifications', __CLASS__ . '::modifyModifications', false);
	}

	/**
	 * Подключаем языковой файл
	 *
	 * @return void
	 */
	public static function loadTheme()
	{
		loadLanguage('ZenBlock/');
	}

	/**
	 * Вызываем функцию отображения блока с первым сообщением темы
	 *
	 * @return void
	 */
	public static function menuButtons()
	{
		global $modSettings, $context;

		if (empty($modSettings['zen_block_enable']) || empty($context['current_board']))
			return;

		$ignored_boards = array();

		if (!empty($modSettings['zen_ignored_boards']))
			$ignored_boards = explode(",", $modSettings['zen_ignored_boards']);

		if (in_array($context['current_board'], $ignored_boards))
			return;

		if (!empty($context['current_topic']) && isset($context['topic_first_message'])) {
			if (empty($modSettings['zen_block_enable']))
				return;

			if ($modSettings['zen_block_enable'] == 1 ? $context['first_message'] != $context['topic_first_message'] : $modSettings['zen_block_enable'] == 2)
				self::showZenBlock();
		}
	}

	/**
	 * Отображение блока с первым сообщением темы
	 *
	 * @return void
	 */
	private static function showZenBlock()
	{
		global $context, $smcFunc, $boarddir, $scripturl, $modSettings, $settings;

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
					'first_message' => $context['topic_first_message']
				)
			);

			while ($row = $smcFunc['db_fetch_assoc']($request)) {
				censorText($row['body']);
				$context['zen_block'] = parse_bbc($row['body']);
			}

			$smcFunc['db_free_result']($request);

			cache_put_data('zen_block_' . $context['current_topic'], $context['zen_block'], 3600);
		}

		// Определяем, популярна ли тема
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
	}

	/**
	 * Определяем название вкладки с настройками мода в админке
	 *
	 * @param array $admin_areas
	 * @return void
	 */
	public static function adminAreas(&$admin_areas)
	{
		global $txt;

		$admin_areas['config']['areas']['modsettings']['subsections']['zen'] = array($txt['zen_settings']);
	}

	/**
	 * Подключаем настройки мода
	 *
	 * @param array $subActions
	 * @return void
	 */
	public static function modifyModifications(&$subActions)
	{
		$subActions['zen'] = array('ZenBlock', 'settings');
	}

	/**
	 * Определяем настройки мода
	 *
	 * @return void
	 */
	public static function settings()
	{
		global $txt, $context, $scripturl, $modSettings;

		loadTemplate('ZenBlock');

		$context['page_title'] = $context['settings_title'] = $txt['zen_settings'];
		$context['post_url'] = $scripturl . '?action=admin;area=modsettings;save;sa=zen';
		$context[$context['admin_menu_name']]['tab_data']['tabs']['zen'] = array('description' => $txt['zen_desc']);

		if (!isset($modSettings['zen_yashare_blocks']))
			updateSettings(array('zen_yashare_blocks' => $txt['zen_yashare_icons']));

		self::ignoreBoards();

		$config_vars = array(
			array('select', 'zen_block_enable', $txt['zen_where_is']),
			array('select', 'zen_block_status', $txt['zen_block_status_set']),
			array('select', 'zen_yashare', $txt['zen_yashare_set'])
		);

		if (!empty($modSettings['zen_yashare'])) {
			$config_vars[] = array('title', 'zen_yashare_title');
			$config_vars[] = array('desc', 'zen_yashare_desc');
			$config_vars[] = array('large_text', 'zen_yashare_blocks', '" style="width:80%');
			$config_vars[] = array('large_text', 'zen_yashare_array', '" style="width:80%');
		}

		$config_vars[] = array('title', 'zen_ignored_boards');
		$config_vars[] = array('desc', 'zen_ignored_boards_desc');
		$config_vars[] = array('callback', 'setting_zen_ignored_boards');

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
			updateSettings(array('zen_ignored_boards' => $_POST['ignore_boards']));
			redirectexit('action=admin;area=modsettings;sa=zen');
		}

		prepareDBSettingContext($config_vars);
	}

	/**
	 * Получаем игнорируемые разделы
	 *
	 * @return void
	 */
	private static function ignoreBoards()
	{
		global $smcFunc, $modSettings, $context;

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
				'empty_string'  => ''
			)
		);

		$context['num_boards'] = $smcFunc['db_num_rows']($request);
		$context['categories'] = array();

		while ($row = $smcFunc['db_fetch_assoc']($request))	{
			if (!isset($context['categories'][$row['id_cat']]))
				$context['categories'][$row['id_cat']] = array(
					'id'     => $row['id_cat'],
					'name'   => $row['cat_name'],
					'boards' => array()
				);

			$context['categories'][$row['id_cat']]['boards'][$row['id_board']] = array(
				'id'          => $row['id_board'],
				'name'        => $row['name'],
				'child_level' => $row['child_level'],
				'selected'    => $row['is_ignored']
			);
		}
		$smcFunc['db_free_result']($request);

		$temp_boards = array();
		foreach ($context['categories'] as $category) {
			$context['categories'][$category['id']]['child_ids'] = array_keys($category['boards']);

			$temp_boards[] = array(
				'name'      => $category['name'],
				'child_ids' => array_keys($category['boards'])
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
}
