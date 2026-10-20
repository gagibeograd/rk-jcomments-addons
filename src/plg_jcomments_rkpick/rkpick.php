<?php
/**
 * RK add-ons for JComments: editor's pick.
 *
 * A moderator marks a comment with a star: the comment gets a badge on the site and, when it is
 * a top-level comment, its thread moves to the top of the list. Data in #__jcomments_rkpick.
 * Toggle: index.php?option=com_ajax&group=jcomments&plugin=rkpick&format=raw&id=N&return=<base64 url>
 * (needs comment.publish on com_jcomments; site or administrator). No core changes.
 *
 * @package   plg_jcomments_rkpick
 * @copyright 2026 Radnička Klasa
 * @license   GNU/GPL v2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;

class PlgJcommentsRkpick extends CMSPlugin
{
	protected $autoloadLanguage = true;

	private function db()
	{
		return Factory::getContainer()->get('DatabaseDriver');
	}

	private function ensureTable(): void
	{
		static $done = false;

		if ($done)
		{
			return;
		}

		$done = true;
		$db   = $this->db();

		try
		{
			$db->setQuery(
				'CREATE TABLE IF NOT EXISTS ' . $db->quoteName('#__jcomments_rkpick') . ' ('
				. '`comment_id` INT UNSIGNED NOT NULL, `picked_by` INT UNSIGNED NOT NULL DEFAULT 0, `date` DATETIME NOT NULL, '
				. 'PRIMARY KEY (`comment_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci'
			)->execute();
		}
		catch (\RuntimeException $e)
		{
			Log::add($e->getMessage(), Log::ERROR, 'plg_jcomments_rkpick');
		}
	}

	/** @return int[] picked comment ids among $ids */
	private function picked(array $ids): array
	{
		$ids = array_values(array_unique(array_map('intval', $ids)));

		if (!$ids)
		{
			return [];
		}

		$this->ensureTable();
		$db = $this->db();

		try
		{
			$query = $db->getQuery(true)->select($db->quoteName('comment_id'))->from($db->quoteName('#__jcomments_rkpick'))->whereIn($db->quoteName('comment_id'), $ids);

			return array_map('intval', $db->setQuery($query)->loadColumn() ?: []);
		}
		catch (\RuntimeException $e)
		{
			Log::add($e->getMessage(), Log::ERROR, 'plg_jcomments_rkpick');

			return [];
		}
	}

	private function canPick(): bool
	{
		$user = Factory::getApplication()->getIdentity();

		return $user && $user->id && $user->authorise('comment.publish', 'com_jcomments');
	}

	/**
	 * Toggle URL for the site toolbar (the RK template prints it when set).
	 */
	private function toggleUrl(int $cid): string
	{
		$return = base64_encode((string) Uri::getInstance());

		return Uri::root(true) . '/index.php?option=com_ajax&group=jcomments&plugin=rkpick&format=raw&id=' . $cid . '&return=' . $return;
	}

	private function mark(object $comment, bool $picked): void
	{
		$comment->rkPick      = $picked;
		$comment->rkPickLabel = Text::_('PLG_JCOMMENTS_RKPICK_BADGE');

		if ($this->canPick())
		{
			$comment->rkPickUrl  = $this->toggleUrl((int) $comment->id);
			$comment->rkPickText = Text::_($picked ? 'PLG_JCOMMENTS_RKPICK_UNPICK' : 'PLG_JCOMMENTS_RKPICK_PICK');
		}
	}

	/* ------------------------------------------------------------------ events */

	public function onJCommentsCommentsPrepare(array &$rows)
	{
		if (!$rows)
		{
			return;
		}

		$ids = [];

		foreach ($rows as $row)
		{
			if (!empty($row->id))
			{
				$ids[] = (int) $row->id;
			}
		}

		$picked = $this->picked($ids);

		foreach ($rows as $row)
		{
			if (!empty($row->id))
			{
				$this->mark($row, in_array((int) $row->id, $picked, true));
				$row->rkPickDone = true;
			}
		}

		if (!$picked || !(int) $this->params->get('move_top', 1))
		{
			return;
		}

		// Move threads whose ROOT comment is picked to the front, keeping the rest in place.
		// Tree mode: rows carry level/parent; a thread = a level-0 row plus the rows that follow until the next level-0 row.
		$threads = [];
		$idx     = -1;

		foreach ($rows as $row)
		{
			$level = (int) ($row->level ?? 0);

			if ($level === 0 || $idx < 0)
			{
				$idx++;
				$threads[$idx] = ['picked' => in_array((int) $row->id, $picked, true), 'rows' => []];
			}

			$threads[$idx]['rows'][] = $row;
		}

		$front = [];
		$rest  = [];

		foreach ($threads as $t)
		{
			if ($t['picked'])
			{
				$front = array_merge($front, $t['rows']);
			}
			else
			{
				$rest = array_merge($rest, $t['rows']);
			}
		}

		$rows = array_merge($front, $rest);
	}

	public function onJCommentsCommentAfterPrepare(&$comment)
	{
		if (!empty($comment->rkPickDone) || empty($comment->id))
		{
			return;
		}

		$this->mark($comment, (bool) $this->picked([(int) $comment->id]));
	}

	public function onJCommentsCommentAfterDelete(&$comment)
	{
		if (empty($comment->id))
		{
			return;
		}

		$this->ensureTable();

		try
		{
			$db = $this->db();
			$db->setQuery('DELETE FROM ' . $db->quoteName('#__jcomments_rkpick') . ' WHERE comment_id = ' . (int) $comment->id)->execute();
		}
		catch (\RuntimeException $e)
		{
			Log::add($e->getMessage(), Log::ERROR, 'plg_jcomments_rkpick');
		}
	}

	/**
	 * Toggle endpoint (site and administrator): flips the pick and redirects back.
	 */
	public function onAjaxRkpick()
	{
		$app   = Factory::getApplication();
		$input = $app->getInput();
		$app->allowCache(false);
		$app->setHeader('X-LiteSpeed-Cache-Control', 'no-cache', true);

		if (!$this->canPick())
		{
			throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		$cid = $input->getInt('id', 0);

		if ($cid <= 0)
		{
			throw new \InvalidArgumentException('id', 400);
		}

		$this->ensureTable();
		$db     = $this->db();
		$exists = $db->setQuery('SELECT id FROM ' . $db->quoteName('#__jcomments') . ' WHERE id = ' . $cid)->loadResult();

		if (!$exists)
		{
			throw new \RuntimeException(Text::_('PLG_JCOMMENTS_RKPICK_NOT_FOUND'), 404);
		}

		$was = (bool) $db->setQuery('SELECT comment_id FROM ' . $db->quoteName('#__jcomments_rkpick') . ' WHERE comment_id = ' . $cid)->loadResult();

		if ($was)
		{
			$db->setQuery('DELETE FROM ' . $db->quoteName('#__jcomments_rkpick') . ' WHERE comment_id = ' . $cid)->execute();
		}
		else
		{
			$db->setQuery(
				'INSERT INTO ' . $db->quoteName('#__jcomments_rkpick') . ' (comment_id, picked_by, date) VALUES ('
				. $cid . ', ' . (int) $app->getIdentity()->id . ', ' . $db->quote(Factory::getDate()->toSql()) . ')'
			)->execute();
		}

		$return = (string) $input->getBase64('return', '');
		$url    = $return !== '' ? base64_decode($return) : '';

		if ($url === '' || !Uri::isInternal($url))
		{
			$url = Uri::base();
		}

		$app->enqueueMessage(Text::_($was ? 'PLG_JCOMMENTS_RKPICK_MSG_UNPICKED' : 'PLG_JCOMMENTS_RKPICK_MSG_PICKED'), 'message');
		$app->redirect($url);

		return true;
	}
}
