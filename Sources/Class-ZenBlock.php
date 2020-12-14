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
 * @version 1.0
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
		add_integration_function('integrate_load_theme', __CLASS__ . '::loadTheme', false, __FILE__);
		add_integration_function('integrate_menu_buttons', __CLASS__ . '::menuButtons', false, __FILE__);
		add_integration_function('integrate_display_topic', __CLASS__ . '::displayTopic', false, __FILE__);
		add_integration_function('integrate_prepare_display_context', __CLASS__ . '::prepareDisplayContext', false, __FILE__);
		add_integration_function('integrate_admin_areas', __CLASS__ . '::adminAreas', false, __FILE__);
		add_integration_function('integrate_admin_search', __CLASS__ . '::adminSearch', false, __FILE__);
		add_integration_function('integrate_modify_modifications', __CLASS__ . '::modifyModifications', false, __FILE__);
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
	 * Получаем дополнительные данные при выборке сообщений темы
	 *
	 * @param array $topic_selects
	 * @return void
	 */
	public static function displayTopic(&$topic_selects)
	{
		global $modSettings;

		if (empty($modSettings['zen_block_enable']) || in_array('ms.body AS topic_first_message', $topic_selects))
			return;

		$topic_selects[] = 'ms.body AS topic_first_message';
	}

	/**
	 * Отображение дзен-блока
	 *
	 * @param array $output
	 * @return void
	 */
	public static function prepareDisplayContext(&$output)
	{
		global $modSettings, $options, $context;

		if (empty($modSettings['zen_block_enable']))
			return;

		$current_counter = empty($options['view_newest_first']) ? $context['start'] : $context['total_visible_posts'] - $context['start'];

		if ($modSettings['zen_block_enable'] == 1 ? $current_counter == $output['counter'] && !empty($context['start']) : $current_counter == $output['counter']) {
			self::prepareZenBlock();
			self::checkTopicPopularity();

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
		$settings_search[] = array(__CLASS__ . '::settings', 'area=modsettings;sa=zen');
	}

	/**
	 * Подключаем настройки мода
	 *
	 * @param array $subActions
	 * @return void
	 */
	public static function modifyModifications(&$subActions)
	{
		$subActions['zen'] = array(__CLASS__, 'settings');
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

		$addSettings = [];
		if (!isset($modSettings['zen_yashare_services']))
			$addSettings['zen_yashare_services'] = $txt['zen_yashare_services_def'];
		if (!isset($modSettings['zen_yashare_limit']))
			$addSettings['zen_yashare_limit'] = 3;
		if (!isset($modSettings['zen_yashare_shape']))
			$addSettings['zen_yashare_shape'] = 'normal';
		if (!isset($modSettings['zen_yashare_size']))
			$addSettings['zen_yashare_size'] = 'm';

		if (!empty($addSettings))
			updateSettings($addSettings);

		$txt['select_boards_from_list'] = $txt['zen_ignored_boards_desc'];

		$config_vars = array(
			array('select', 'zen_block_enable', $txt['zen_where_is']),
			array('select', 'zen_block_status', $txt['zen_block_status_set']),
			array('boards', 'zen_ignored_boards'),
			array('check', 'zen_yashare')
		);

		if (!empty($modSettings['zen_yashare'])) {
			$config_vars[] = array('title', 'zen_yashare_title');
			$config_vars[] = array('desc', 'zen_yashare_desc');
			$config_vars[] = array('large_text', 'zen_yashare_services', '" style="width:80%');
			$config_vars[] = array('select', 'zen_yashare_color_scheme', $txt['zen_yashare_color_scheme_set']);
			$config_vars[] = array('int', 'zen_yashare_limit');
			$config_vars[] = array('select', 'zen_yashare_shape', $txt['zen_yashare_shape_set']);
			$config_vars[] = array('select', 'zen_yashare_size', $txt['zen_yashare_size_set']);
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

	/**
	 * Получаем содержание первого сообщения
	 *
	 * @return void
	 */
	private static function prepareZenBlock()
	{
		global $context;

		if (($context['zen_block'] = cache_get_data('zen_block_' . $context['current_topic'], 3600)) == null) {
			censorText($context['topicinfo']['topic_first_message']);

			$context['zen_block'] = parse_bbc($context['topicinfo']['topic_first_message']);

			cache_put_data('zen_block_' . $context['current_topic'], $context['zen_block'], 3600);
		}
	}

	/**
	 * Проверяем популярность темы
	 *
	 * @return void
	 */
	private static function checkTopicPopularity()
	{
		global $context, $boarddir, $scripturl;

		$context['top_topic'] = false;

		if (!is_file($boarddir . '/SSI.php'))
			return;

		if (($topics = cache_get_data('zen_block_popular_topics', 3600)) == null) {
			require_once($boarddir . '/SSI.php');

			$link = $scripturl . '?topic=' . $context['current_topic'] . '.0';
			$topics = ssi_topTopicsReplies(10, 'array');

			cache_put_data('zen_block_popular_topics', $topics, 3600);
		}

		foreach ($topics as $topic) {
			if ($context['current_topic'] == $topic['id']) {
				$context['top_topic'] = true;

				break;
			}
		}
	}
}
