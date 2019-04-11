<?php

function show_zen_block()
{
	global $context, $modSettings, $txt, $settings, $scripturl;

	if (empty($context['zen_block']))
		return;

	echo '
	<div id="zen">
		<div class="windowbg description">
			<div class="zen-head ', empty($modSettings['zen_block_status']) ? 'full' : 'mini', '_text information">', $txt['zen_block_enable'], '</div>
			<div', empty($modSettings['zen_block_status']) ? ' class="zen-body"' : '', '>
				<div class="roundframe">
					<div class="zen_message">', $context['zen_block'], '</div>
					<hr>
					<div class="smalltext">
						<span class="zen_symbol lefttext">&#31146;</span>
						<span class="floatright">';

	// Если включен блок «Поделиться»
	if (!empty($modSettings['zen_yashare'])) {
		echo '
							<script src="//yastatic.net/share2/share.js" async="async"></script>
							<span class="ya-share2" data-services="', str_replace(' ', '', $modSettings['zen_yashare_services']), '"', $modSettings['zen_yashare'] == 'menu' ? ' data-limit="3"' : '', !empty($settings['og_image']) ? ' data-image="' . $settings['og_image'] . '"' : '', !empty($context['meta_description']) ? ' data-description="' . $context['meta_description'] . '"' : '', ' data-title="', $context['page_title'], '" data-url="', $scripturl, '?topic=', $context['current_topic'], '.0" data-lang="', $txt['lang_dictionary'], '"', $modSettings['zen_yashare'] == 'menu' ? ' data-popup-position="outer"' : '', '></span>';
	}

	// Если тема популярна
	if ($context['top_topic']) {
		echo '
							&nbsp;<img class="icon" src="' . $settings['default_images_url'] . '/zen/zen_pop.png" alt="' . $txt['zen_block_topic'] . '" title="' . $txt['zen_block_topic'] . '">';
	}

	// Если установлен мод Bookmarks
	if (!empty($context['can_make_bookmarks'])) {
		echo '
							&nbsp;<a class="floatright" href="',  $scripturl, '?action=bookmarks;sa=add;topic=', $context['current_topic'], ';', $context['session_var'], '=', $context['session_id'], '"><img class="icon" src="', $settings['default_images_url'], '/zen/zen_bookmark.png" alt="" title="', $txt['bookmark_add'], '"></a>';
	}

	echo '
							&nbsp;<a class="floatright" href="', $scripturl, '?topic=', $context['current_topic'], '.msg', $context['topic_first_message'], '#msg', $context['topic_first_message'], '" title="', $txt['zen_block_link'], '"><img class="icon" src="', $settings['default_images_url'], '/zen/zen_anchor.png" alt="', $txt['zen_block_link'], '"></a>
						</span>
					</div>
				</div>
			</div>
		</div>
	</div>';
}
