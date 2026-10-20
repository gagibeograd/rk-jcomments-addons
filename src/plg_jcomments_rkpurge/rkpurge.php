<?php
/**
 * RK add-ons for JComments: cache purge when a comment is published, unpublished, added, edited or deleted.
 *
 * Without it the article page (and the cards that show the comment count) stay cached with the old state until
 * somebody purges by hand. The plugin cleans the Joomla site cache groups com_content, page and com_jcomments and,
 * when the LiteSpeed Cache plugin (plg_system_lscache) is enabled, sends the X-LiteSpeed-Purge header with the tags
 * com_content:<article id>, com_content:<category id> (LSCache tags an article page by the category of the menu
 * item it was reached through) and, optionally, com_categories:<category id> for the category pages.
 * Covers publish/unpublish in the administrator and on the site, add (when published at once), edit and delete on
 * the site. Delete and edit through the administrator list have no JComments event; there a manual purge is needed.
 * Without LSCache only the Joomla cache is cleaned.
 *
 * @package   RK add-ons for JComments
 * @copyright 2026 Radnička Klasa
 * @license   GNU/GPL v2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Cache\Cache;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;

class PlgJcommentsRkpurge extends CMSPlugin
{
	/** @var array<string,bool> tag sets already sent in this request */
	private static array $sent = [];

	public function onJCommentsCommentAfterPublish(&$comment): void
	{
		$this->purgeFor($comment);
	}

	public function onJCommentsCommentAfterAdd(&$comment): void
	{
		if (!empty($comment->published))
		{
			$this->purgeFor($comment);
		}
	}

	public function onJCommentsCommentAfterChange(&$comment): void
	{
		$this->purgeFor($comment);
	}

	public function onJCommentsCommentAfterDelete(&$comment): void
	{
		$this->purgeFor($comment);
	}

	private function purgeFor($comment): void
	{
		$objectId    = (int) ($comment->object_id ?? 0);
		$objectGroup = (string) ($comment->object_group ?? '');

		if ($objectId <= 0 || $objectGroup !== 'com_content')
		{
			return;
		}

		// 1) Joomla SITE cache. The plugin also runs in the administrator, where Factory::getCache would hit
		//    administrator/cache, so the cache base is set explicitly.
		$options = ['cachebase' => JPATH_SITE . '/cache'];

		foreach (['com_content', 'page', 'com_jcomments'] as $group)
		{
			try
			{
				Cache::getInstance('', $options)->clean($group);
			}
			catch (\Throwable $e)
			{
				// cache disabled or unavailable: not an error
			}
		}

		// 2) LiteSpeed cache, only when the LSCache plugin is enabled
		$base = JPATH_ROOT . '/plugins/system/lscache/lscachebase.php';
		$core = JPATH_ROOT . '/plugins/system/lscache/lscachecore.php';

		if (!PluginHelper::isEnabled('system', 'lscache') || !is_file($base) || !is_file($core) || headers_sent())
		{
			return;
		}

		$tags = ['com_content:' . $objectId];

		// LSCache tags the article page with com_content:<category id> (through the category blog menu item),
		// not with the article id, so the category and its parent are sent too; otherwise the purge misses and a
		// guest keeps seeing the old comment list until somebody purges by hand.
		$db = Factory::getContainer()->get('DatabaseDriver');
		$db->setQuery('SELECT c.catid, k.parent_id FROM ' . $db->quoteName('#__content', 'c')
			. ' LEFT JOIN ' . $db->quoteName('#__categories', 'k') . ' ON k.id = c.catid WHERE c.id = ' . $objectId);
		$row = $db->loadObject();

		if ($row && (int) $row->catid > 0)
		{
			$tags[] = 'com_content:' . (int) $row->catid;

			if ((int) $row->parent_id > 1)
			{
				$tags[] = 'com_content:' . (int) $row->parent_id;
			}

			if ((int) $this->params->get('categories', 1) === 1)
			{
				$tags[] = 'com_categories:' . (int) $row->catid;

				if ((int) $row->parent_id > 1)
				{
					$tags[] = 'com_categories:' . (int) $row->parent_id;
				}
			}
		}

		$key = implode(',', $tags);

		if (isset(self::$sent[$key]))
		{
			return;
		}

		self::$sent[$key] = true;

		require_once $base;
		require_once $core;

		(new LiteSpeedCacheCore())->purgePublic($key);
	}
}
