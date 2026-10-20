<?php
/**
 * RK add-ons for JComments: system plugin with two jobs.
 *
 * 1) Bridge for the other RK add-ons on the article page. When JComments shows the comment form "on click"
 *    (form_show = 2) and the article already has comments, the form arrives through AJAX as a bare fragment, so the
 *    scripts and styles the add-ons register through the WebAssetManager never reach the page. Before the <head> is
 *    compiled this plugin imports the jcomments plugin group and triggers the onRkjcPageAssets event (every add-on
 *    that implements it adds its own CSS/JS), and initialises the Joomla captcha JComments uses (reCAPTCHA, hCaptcha,
 *    Turnstile ...) so the late form works as well. The add-on scripts watch the DOM themselves (MutationObserver).
 *
 * 2) Optional comment count in article cards that a template or a Gantry particle cannot compute in PHP. The
 *    markup prints an empty placeholder
 *      <span class="g-article-comments" data-rkjc="ARTICLE ID" data-rkjc-href="ARTICLE URL#jc"></span>
 *    and after rendering this plugin replaces it with the published comment count (only where comments are enabled
 *    for the article: category on the JComments list and no {jcomments off} in the text) or removes it. One query
 *    for the articles and one for the counts, whatever the number of cards. The class name and the icon are plugin
 *    parameters.
 *
 * @package   RK add-ons for JComments
 * @copyright 2026 Radnička Klasa
 * @license   GNU/GPL v2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Captcha\Captcha;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;

class PlgSystemRkjccount extends CMSPlugin
{
	/**
	 * Add-on assets and captcha on the article page, before the <head> is compiled.
	 */
	public function onBeforeCompileHead(): void
	{
		$app = Factory::getApplication();

		if (!$app->isClient('site'))
		{
			return;
		}

		$input = $app->getInput();

		if ($input->get('format', 'html') !== 'html' || $input->get('option') !== 'com_content' || $input->get('view') !== 'article')
		{
			return;
		}

		if (!ComponentHelper::isEnabled('com_jcomments') || $app->getDocument()->getType() !== 'html')
		{
			return;
		}

		// 1) RK add-ons: every one that implements onRkjcPageAssets() adds its own CSS/JS
		try
		{
			PluginHelper::importPlugin('jcomments');
			$app->triggerEvent('onRkjcPageAssets');
		}
		catch (\Throwable $e)
		{
			// a failing add-on must not break the page
		}

		// 2) the Joomla captcha JComments uses (recaptcha_invisible, recaptcha, hcaptcha, turnstile ...): API script + init
		try
		{
			$engine = (string) ComponentHelper::getParams('com_jcomments')->get('captcha_engine', '');

			if ($engine !== '' && $engine !== 'kcaptcha' && PluginHelper::isEnabled('captcha', $engine))
			{
				Captcha::getInstance($engine)->initialise('comments-form-captcha');
			}
		}
		catch (\Throwable $e)
		{
			// no keys or plugin unavailable: the form keeps working as before when it is rendered at once
		}
	}

	/**
	 * Replace the count placeholders in the rendered page.
	 */
	public function onAfterRender(): void
	{
		if ((int) $this->params->get('count', 1) !== 1)
		{
			return;
		}

		$app = Factory::getApplication();

		if (!$app->isClient('site') || $app->getInput()->get('format', 'html') !== 'html')
		{
			return;
		}

		$body = $app->getBody();

		if ($body === '' || strpos($body, 'data-rkjc="') === false)
		{
			return;
		}

		$class   = trim((string) $this->params->get('placeholder_class', 'g-article-comments')) ?: 'g-article-comments';
		$pattern = '#<span class="' . preg_quote($class, '#') . '" data-rkjc="(\d+)" data-rkjc-href="([^"]*)"></span>#';

		if (!preg_match_all($pattern, $body, $m, PREG_SET_ORDER))
		{
			return;
		}

		$ids    = array_values(array_unique(array_map('intval', array_column($m, 1))));
		$counts = $this->counts($ids);
		$icon   = (string) $this->params->get('icon_html', '<i class="fa fa-comment-o"></i>');
		$class  = htmlspecialchars($class, ENT_QUOTES);

		$body = preg_replace_callback($pattern, static function ($mm) use ($counts, $icon, $class) {
			$id = (int) $mm[1];

			if (!isset($counts[$id]))
			{
				return '';
			}

			return '<span class="' . $class . '"><a href="' . $mm[2] . '">' . $icon . $counts[$id] . '</a></span>';
		}, $body);

		$app->setBody($body);
	}

	/**
	 * Published comment count per article: an article without comments gets 0, an article where comments are
	 * disabled has no key.
	 */
	private function counts(array $ids): array
	{
		$class = JPATH_ROOT . '/components/com_jcomments/jcomments.class.php';

		if (!$ids || !ComponentHelper::isEnabled('com_jcomments') || !is_file($class))
		{
			return [];
		}

		require_once $class;

		$db  = Factory::getContainer()->get('DatabaseDriver');
		$in  = implode(',', $ids);
		$out = [];

		$db->setQuery('SELECT id, catid, (introtext LIKE ' . $db->quote('%{jcomments off}%') . ' OR ' . $db->quoteName('fulltext') . ' LIKE ' . $db->quote('%{jcomments off}%') . ') AS off'
			. ' FROM ' . $db->quoteName('#__content') . ' WHERE id IN (' . $in . ')');

		foreach ($db->loadObjectList() as $row)
		{
			if (!(int) $row->off && JCommentsContent::checkCategory((int) $row->catid))
			{
				$out[(int) $row->id] = 0;
			}
		}

		if (!$out)
		{
			return [];
		}

		$db->setQuery('SELECT object_id, COUNT(*) AS n FROM ' . $db->quoteName('#__jcomments')
			. ' WHERE object_group = ' . $db->quote('com_content') . ' AND published = 1 AND deleted = 0'
			. ' AND object_id IN (' . implode(',', array_keys($out)) . ') GROUP BY object_id');

		foreach ($db->loadObjectList() as $row)
		{
			$out[(int) $row->object_id] = (int) $row->n;
		}

		return $out;
	}
}
