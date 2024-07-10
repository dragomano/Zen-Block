<?php

/**
 * Class-ZenBlock.php
 *
 * @package Zen Block
 * @link https://dragomano.ru/mods/zen-block
 * @author Bugo <bugo@dragomano.ru>
 * @copyright 2011-2024 Bugo
 * @license https://opensource.org/licenses/artistic-license-2.0 Artistic License
 *
 * @version 1.2
 */

if (! defined('SMF'))
	die('No direct access...');

final class ZenBlock
{
	private array $services = [
		'messenger, vkontakte, odnoklassniki, telegram, twitter, viber, whatsapp, moimir, pinterest, skype, tumblr,
		 evernote, linkedin, lj, blogger, delicious, digg, reddit, pocket, qzone, renren, sinaWeibo, surfingbird, tencentWeibo'
	];

	public function hooks(): void
	{
		add_integration_function('integrate_load_theme', self::class . '::loadTheme#', false, __FILE__);
		add_integration_function('integrate_menu_buttons', self::class . '::menuButtons#', false, __FILE__);
		add_integration_function('integrate_display_topic', self::class . '::displayTopic#', false, __FILE__);
		add_integration_function('integrate_prepare_display_context', self::class . '::prepareDisplayContext#', false, __FILE__);
		add_integration_function('integrate_admin_areas', self::class . '::adminAreas#', false, __FILE__);
		add_integration_function('integrate_admin_search', self::class . '::adminSearch#', false, __FILE__);
		add_integration_function('integrate_modify_modifications', self::class . '::modifyModifications#', false, __FILE__);
	}

	public function loadTheme(): void
	{
		loadLanguage('ZenBlock/');
	}

	public function menuButtons(): void
	{
		global $modSettings, $context;

		if (empty($modSettings['zen_block_enable']) || empty($context['current_board']))
			return;

		$ignored_boards = array();

		if (! empty($modSettings['zen_ignored_boards'])) {
			$ignored_boards = explode(",", $modSettings['zen_ignored_boards']);
		}

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

	public function displayTopic(array &$topic_selects): void
	{
		global $modSettings;

		if (empty($modSettings['zen_block_enable']) || in_array('ms.body AS topic_first_message', $topic_selects))
			return;

		$topic_selects[] = 'ms.body AS topic_first_message';
	}

	public function prepareDisplayContext(array $output): void
	{
		global $modSettings, $options, $context;

		if (empty($modSettings['zen_block_enable']))
			return;

		$current_counter = empty($options['view_newest_first']) ? $context['start'] : $context['total_visible_posts'] - $context['start'];

		if ($modSettings['zen_block_enable'] == 1 ? $current_counter == $output['counter'] && ! empty($context['start']) : $current_counter == $output['counter']) {
			$this->prepareZenBlock();
			$this->checkTopicPopularity();

			loadTemplate('ZenBlock');
			show_zen_block();
		}
	}

	public function adminAreas(array &$admin_areas): void
	{
		global $txt;

		$admin_areas['config']['areas']['modsettings']['subsections']['zen'] = [$txt['zen_settings']];
	}

	public function adminSearch(array &$language_files, array &$include_files, array &$settings_search): void
	{
		$settings_search[] = [[$this, 'settings'], 'area=modsettings;sa=zen'];
	}

	public function modifyModifications(array &$subActions): void
	{
		$subActions['zen'] = [$this, 'settings'];
	}

	/**
	 * @return array|void
	 */
	public function settings(bool $return_config = false)
	{
		global $context, $txt, $scripturl, $modSettings;

		$context['page_title'] = $context['settings_title'] = $txt['zen_settings'];
		$context['post_url'] = $scripturl . '?action=admin;area=modsettings;save;sa=zen';
		$context[$context['admin_menu_name']]['tab_data']['tabs']['zen'] = ['description' => $txt['zen_desc']];

		$addSettings = [];
		if (! isset($modSettings['zen_yashare_services']))
			$addSettings['zen_yashare_services'] = $txt['zen_yashare_services_def'];
		if (! isset($modSettings['zen_yashare_limit']))
			$addSettings['zen_yashare_limit'] = 3;
		if (! isset($modSettings['zen_yashare_shape']))
			$addSettings['zen_yashare_shape'] = 'normal';
		if (! isset($modSettings['zen_yashare_size']))
			$addSettings['zen_yashare_size'] = 'm';

		if (! empty($addSettings)) {
			updateSettings($addSettings);
		}

		$txt['select_boards_from_list'] = $txt['zen_ignored_boards_desc'];

		$config_vars = [
			['select', 'zen_block_enable', $txt['zen_where_is']],
			['select', 'zen_block_status', $txt['zen_block_status_set']],
			['boards', 'zen_ignored_boards'],
			['check', 'zen_yashare']
		];

		if (! empty($modSettings['zen_yashare'])) {
			$config_vars[] = ['title', 'zen_yashare_title'];
			$config_vars[] = [
				'desc',
				'zen_yashare_desc',
				'label' => sprintf($txt['zen_yashare_desc'], implode(',', $this->services))
			];
			$config_vars[] = ['large_text', 'zen_yashare_services', '" style="width:80%'];
			$config_vars[] = ['select', 'zen_yashare_color_scheme', $txt['zen_yashare_color_scheme_set']];
			$config_vars[] = ['int', 'zen_yashare_limit'];
			$config_vars[] = ['select', 'zen_yashare_shape', $txt['zen_yashare_shape_set']];
			$config_vars[] = ['select', 'zen_yashare_size', $txt['zen_yashare_size_set']];
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

	private function prepareZenBlock(): void
	{
		global $context;

		if (($context['zen_block'] = cache_get_data('zen_block_' . $context['current_topic'], 3600)) == null) {
			censorText($context['topicinfo']['topic_first_message']);

			$context['zen_block'] = parse_bbc($context['topicinfo']['topic_first_message']);

			cache_put_data('zen_block_' . $context['current_topic'], $context['zen_block'], 3600);
		}
	}

	private function checkTopicPopularity(): void
	{
		global $context, $boarddir;

		$context['top_topic'] = false;

		if (! is_file($boarddir . '/SSI.php'))
			return;

		if (($topics = cache_get_data('zen_block_popular_topics', 3600)) == null) {
			require_once $boarddir . '/SSI.php';

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
