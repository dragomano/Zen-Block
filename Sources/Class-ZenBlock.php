<?php

/**
 * Class-ZenBlock.php
 *
 * @package Zen Block
 * @link https://dragomano.ru/mods/zen-block
 * @author Bugo <bugo@dragomano.ru>
 * @copyright 2011-2019 Bugo
 * @license https://opensource.org/licenses/artistic-license-2.0 Artistic License
 *
 * @version 0.8
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
		add_integration_function('integrate_load_theme', 'ZenBlock::loadTheme', false);
		add_integration_function('integrate_menu_buttons', 'ZenBlock::menuButtons', false);
		add_integration_function('integrate_prepare_display_context', 'ZenBlock::prepareDisplayContext', false);
		add_integration_function('integrate_admin_areas', 'ZenBlock::adminAreas', false);
		add_integration_function('integrate_admin_search', 'ZenBlock::adminSearch', false);
		add_integration_function('integrate_modify_modifications', 'ZenBlock::modifyModifications', false);
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
	 * Подключаем используемые стили и скрипты, а также вызываем необходимые функции
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

		if (in_array($context['current_board'], $ignored_boards)) {
			$modSettings['zen_block_enable'] = false;
			return;
		}

		loadCSSFile('zen.css');

		addInlineJavaScript('
		jQuery(document).ready(function($) {
			$(".zen-head").on("click", function() {
				$(this).toggleClass("full_text").toggleClass("mini_text").next().toggle();
			});
		});', true);
	}

	/**
	 * Получаем содержание первого сообщения
	 *
	 * @return void
	 */
	private static function showZenBlock()
	{
		global $context, $smcFunc, $boarddir, $scripturl;

		if (($context['zen_block'] = cache_get_data('zen_block_' . $context['current_topic'], 3600)) == null) {
			if (!empty($context['topicinfo']['topic_first_message'])) {
				censorText($context['topicinfo']['topic_first_message']);
				$context['zen_block'] = parse_bbc($context['topicinfo']['topic_first_message']);
			} else {
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
			}

			cache_put_data('zen_block_' . $context['current_topic'], $context['zen_block'], 3600);
		}

		// Check topic popularity
		$context['top_topic'] = false;

		if (!is_file($boarddir . '/SSI.php'))
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
	 * Отображение дзен-блока
	 *
	 * @param array $output массив с параметрами исходного сообщения
	 * @return void
	 */
	public static function prepareDisplayContext(&$output, &$message, $counter)
	{
		global $context, $modSettings;

		if ($counter % $modSettings['defaultMaxMessages'] != 1 || empty($modSettings['zen_block_enable']))
			return;

		if ($modSettings['zen_block_enable'] == 1 ? $output['id'] != $context['topic_first_message'] : $modSettings['zen_block_enable'] == 2) {
			self::showZenBlock();

			loadTemplate('ZenBlock');
			show_zen_block();
		}
	}

	/**
	 * Добавляем секцию с названием мода в раздел настроек
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
	 * Легкий доступ к настройкам мода через быстрый поиск в админке
	 *
	 * @param array $language_files
	 * @param array $include_files
	 * @param array $settings_search
	 * @return void
	 */
	public static function adminSearch(&$language_files, &$include_files, &$settings_search)
	{
		$settings_search[] = array('ZenBlock::settings', 'area=modsettings;sa=zen');
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
	 * @param boolean $return_config
	 * @return array|void
	 */
	public static function settings($return_config = false)
	{
		global $context, $txt, $scripturl, $modSettings;

		$context['page_title'] = $context['settings_title'] = $txt['zen_settings'];
		$context['post_url'] = $scripturl . '?action=admin;area=modsettings;save;sa=zen';
		$context[$context['admin_menu_name']]['tab_data']['tabs']['zen'] = array('description' => $txt['zen_desc']);

		if (!isset($modSettings['zen_yashare_services']))
			updateSettings(array('zen_yashare_services' => $txt['zen_yashare_services_set']));

		$txt['select_boards_from_list'] = $txt['zen_ignored_boards_desc'];

		$config_vars = array(
			array('select', 'zen_block_enable', $txt['zen_where_is']),
			array('select', 'zen_block_status', $txt['zen_block_status_set']),
			array('boards', 'zen_ignored_boards'),
			array('select', 'zen_yashare', $txt['zen_yashare_set'])
		);

		if (!empty($modSettings['zen_yashare'])) {
			$config_vars[] = array('title', 'zen_yashare_title');
			$config_vars[] = array('desc', 'zen_yashare_desc');
			$config_vars[] = array('large_text', 'zen_yashare_services', '" style="width:80%');
		}

		if ($return_config)
			return $config_vars;

		// Saving?
		if (isset($_GET['save'])) {
			checkSession();
			$save_vars = $config_vars;
			saveDBSettings($save_vars);
			clean_cache();
			redirectexit('action=admin;area=modsettings;sa=zen');
		}

		prepareDBSettingContext($config_vars);
	}
}
