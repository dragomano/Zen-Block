<?php

function show_zen_block(): void
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

	if (! empty($modSettings['zen_yashare']) && ! empty($modSettings['zen_yashare_services'])) {
		$lang = $txt['lang_dictionary'];
		if (! in_array($txt['lang_dictionary'], ['az', 'be', 'hy', 'ka', 'kk', 'ro', 'ru', 'tr', 'tt', 'uk', 'uz']))
			$lang = 'en';

		echo '
							<script src="https://yastatic.net/share2/share.js" async="async"></script>
							<span
								class="ya-share2"
								data-curtain
								data-services="', str_replace(' ', '', $modSettings['zen_yashare_services']), '"', ! empty($modSettings['zen_yashare_limit']) ? '
								data-limit="' . (int) $modSettings['zen_yashare_limit'] . '"' : '', ! empty($settings['og_image']) ? '
								data-image="' . $settings['og_image'] . '"' : '', ! empty($context['meta_description']) ? '
								data-description="' . $context['meta_description'] . '"' : '', '
								data-title="', $context['page_title'], '"
								data-url="', $scripturl, '?topic=', $context['current_topic'], '.0"
								data-lang="', $lang, '"
								data-popup-position="outer"
								data-color-scheme="', ! empty($modSettings['zen_yashare_color_scheme']) ? $modSettings['zen_yashare_color_scheme'] : 'normal', '"
								data-shape="', ! empty($modSettings['zen_yashare_shape']) ? $modSettings['zen_yashare_shape'] : 'normal', '"
								data-size="', ! empty($modSettings['zen_yashare_size']) ? $modSettings['zen_yashare_size'] : 'm', '"
							></span>';
	}

	if ($context['top_topic']) {
		echo '
							&nbsp;<img class="icon" src="' . $settings['default_images_url'] . '/zen/zen_pop.png" alt="' . $txt['zen_block_topic'] . '" title="' . $txt['zen_block_topic'] . '">';
	}

	if (! empty($context['can_make_bookmarks'])) {
		echo '
							&nbsp;<a class="floatright" href="',  $scripturl, '?action=bookmarks;sa=add;topic=', $context['current_topic'], ';', $context['session_var'], '=', $context['session_id'], '">
								<img class="icon" src="', $settings['default_images_url'], '/zen/zen_bookmark.png" alt="" title="', $txt['bookmark_add'], '">
							</a>';
	}

	echo '
							&nbsp;<a class="floatright" href="', $scripturl, '?msg=', $context['topic_first_message'], '" title="', $txt['zen_block_link'], '">
								<img class="icon" src="', $settings['default_images_url'], '/zen/zen_anchor.png" alt="', $txt['zen_block_link'], '">
							</a>
						</span>
					</div>
				</div>
			</div>
		</div>
	</div>';
}

function template_callback_supported_services(): void
{
	global $context;

	echo '
		<ul class="half_content">';

	$i = 0;
	$limit = ceil(count($context['zen_yashare_services']) / 2);
	foreach ($context['zen_yashare_services'] as $service => $show) {
		if ($i == $limit)
			echo '
		</ul>
		<ul class="half_content">';

		echo '
			<li>
				<label for="service_', $service, '">
					<input
						type="checkbox"
						id="service_', $service, '"
						name="services[', $service, ']"
						value="', $service, '"', empty($show) ? '' : '
						checked="checked"', '
						class="input_check"
					> ', $service, '
				</label>
			</li>';

		$i++;
	}

	echo '
		</ul>
		<br class="clear">';
}
