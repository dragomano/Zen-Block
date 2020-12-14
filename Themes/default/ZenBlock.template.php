<?php

function template_zen_above()
{
	global $context, $modSettings, $txt, $settings, $scripturl;

	if (!empty($context['zen_block'])) {
		echo '
	<div id="zen"', $context['is_poll'] && empty($context['linked_calendar_events']) ? ' style="margin-top: 10px"' : (!empty($context['is_poll']) ? '' : ''), '>
		<div class="description">
			<div class="zen-head ', empty($modSettings['zen_block_status']) ? 'full' : 'mini', '_text information">', $txt['zen_block_enable'], '</div>
			<div', empty($modSettings['zen_block_status']) ? ' class="zen-body"' : '', '>';

		if ($settings['name'] != 'ClearSky') {
			echo '
				<span class="upperframe"><span></span></span>
				<div class="roundframe">';
		} else {
			echo '
				<div class="sky">';
		}

		echo '
					<div class="zen_message">', $context['zen_block'], '</div>
					<hr />
					<div class="smalltext">
						<span class="zen_symbol lefttext">&#31146;</span>
						<span class="floatright" style="margin-left: 1em">';

		if ($context['top_topic']) {
			echo '
							&nbsp;<img src="' . $settings['default_images_url'] . '/zen/zen_pop.png" alt="' . $txt['zen_block_topic'] . '" title="' . $txt['zen_block_topic'] . '" />';
		}

		if (!empty($context['can_make_bookmarks'])) {
			echo '
							&nbsp;<a href="' .  $scripturl . '?action=bookmarks;sa=add;topic=' . $context['current_topic'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '"><img src="' . $settings['default_images_url'] . '/zen/zen_bookmark.png" alt="" title="' . $txt['bookmark_add'] . '" /></a>';
		}

		echo '
							&nbsp;<a href="', $scripturl, '?topic=', $context['current_topic'], '.msg', $context['topic_first_message'], '#msg', $context['topic_first_message'], '" title="', $txt['zen_block_link'], '"><img src="', $settings['default_images_url'], '/zen/zen_anchor.png" alt="', $txt['zen_block_link'], '" /></a>
						</span>';

		if (!empty($modSettings['zen_yashare'])) {
			echo '
						<span id="yashare-zen" class="floatright"></span>
						<br class="clear" />';
		}

		echo '
					</div>
				</div>';

		if ($settings['name'] != 'ClearSky') {
			echo '
				<span class="lowerframe"><span></span></span>';
		}

		echo '
			</div>
		</div>
	</div>';
	}
}

function template_zen_below()
{
	global $context, $modSettings, $txt, $scripturl, $topicinfo;

	if (empty($context['zen_block']))
		return;

	echo '
		<script type="text/javascript">window.jQuery || document.write(unescape(\'%3Cscript src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"%3E%3C/script%3E\'))</script>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				$(".zen-head").click(function() {
					$(this).toggleClass("full_text").toggleClass("mini_text").next().toggle();
				})
			})
		</script>';

	if (!empty($modSettings['zen_yashare'])) {
		$lang = $txt['lang_dictionary'];
		if (!in_array($txt['lang_dictionary'], array('az', 'be', 'hy', 'ka', 'kk', 'ro', 'ru', 'tr', 'tt', 'uk', 'uz')))
			$lang = 'en';

		echo '
		<script type="text/javascript" src="//yandex.st/share2/share.js"></script>
		<script type="text/javascript">
			new Ya.share2("yashare-zen", {
				content: {
					url: "' . $scripturl . '?topic=' . $context['current_topic'] . '.0",
					title: "' . $topicinfo['subject'] . '",
					description: "' . (!empty($context['optimus_description']) ? $context['optimus_description'] : $context['page_title_html_safe']) . '"' . (!empty($context['optimus_og_image']) ? ',
					image: "' . $context['optimus_og_image'] . '"' : '') . '
				},
				theme: {
					services: "' . str_replace(' ', '', $modSettings['zen_yashare_services']) . '",
					lang: "' . $lang . '",
					limit: ' . (!empty($modSettings['zen_yashare_limit']) ? (int) $modSettings['zen_yashare_limit'] : 0) . ',
					colorScheme: "' . (!empty($modSettings['zen_yashare_color_scheme']) ? $modSettings['zen_yashare_color_scheme'] : 'normal') . '",
					shape: "' . (!empty($modSettings['zen_yashare_shape']) ? $modSettings['zen_yashare_shape'] : 'normal') . '",
					size: "' . (!empty($modSettings['zen_yashare_size']) ? $modSettings['zen_yashare_size'] : 'm') . '",
					bare: false,
					curtain: true
				}
			});
		</script>';
	}
}

function template_callback_setting_zen_ignored_boards()
{
	global $context;

	echo '
		<dt></dt><dd></dd></dl>
		<ul class="ignoreboards floatleft" style="margin-top: -30px">';

	$i = 0;
	$limit = ceil($context['num_boards'] / 2);

	foreach ($context['categories'] as $category) {
		if ($i == $limit) {
			echo '
		</ul>
		<ul class="ignoreboards floatright" style="margin-top: -30px">';

			$i++;
		}

		echo '
			<li class="category">
				<strong>', $category['name'], '</strong>
				<ul>';

		foreach ($category['boards'] as $board)	{
			if ($i == $limit)
				echo '
				</ul>
			</li>
		</ul>
		<ul class="ignoreboards floatright">
			<li class="category">
				<ul>';

			echo '
					<li class="board" style="margin-', $context['right_to_left'] ? 'right' : 'left', ': ', $board['child_level'], 'em;">
						<label for="ignore_brd', $board['id'], '"><input type="checkbox" id="ignore_brd', $board['id'], '" name="ignore_brd[', $board['id'], ']" value="', $board['id'], '"', $board['selected'] ? ' checked="checked"' : '', ' class="input_check" /> ', $board['name'], '</label>
					</li>';

			$i++;
		}

		echo '
				</ul>
			</li>';
	}

	echo '
		</ul>
		<br class="clear" />
		<dl><dt></dt><dd></dd>';
}
