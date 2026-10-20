<?php
/**
 * RK add-ons for JComments: badges under the commenter's avatar.
 *
 * Levels (icon, name, minimum number of comments) are configured in the plugin; the count is taken live
 * from #__jcomments (members by user id, guests by e-mail). Staff user groups get a fixed staff badge.
 * The plugin only fills $comment->rkBadgeHtml; the rk template prints it under the avatar. No core changes.
 *
 * @package   plg_jcomments_rkbadges
 * @copyright 2026 Radnička Klasa
 * @license   GNU/GPL v2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Access\Access;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;

class PlgJcommentsRkbadges extends CMSPlugin
{
	protected $autoloadLanguage = true;

	/** @var array<int,bool> user id => is staff (cache per request) */
	private $staffCache = [];

	/** @return array<int, array{icon:string,name:string,min:int}> sorted by min ascending */
	private function levels(): array
	{
		$raw = $this->params->get('levels');
		$out = [];

		if (is_object($raw) || is_array($raw))
		{
			foreach ((array) $raw as $row)
			{
				$row  = (array) $row;
				$name = trim((string) ($row['name'] ?? ''));
				$min  = (int) ($row['min'] ?? 0);

				if ($name !== '' && $min >= 0)
				{
					$out[] = ['icon' => trim((string) ($row['icon'] ?? '')), 'name' => $name, 'min' => $min];
				}
			}
		}

		if (!$out)
		{
			// Defaults come from the language files, so every site language gets its own names.
			$out = [
				['icon' => '🏠', 'name' => Text::_('PLG_JCOMMENTS_RKBADGES_DEFAULT_LEVEL1'), 'min' => 3],
				['icon' => '🍲', 'name' => Text::_('PLG_JCOMMENTS_RKBADGES_DEFAULT_LEVEL2'), 'min' => 10],
				['icon' => '👑', 'name' => Text::_('PLG_JCOMMENTS_RKBADGES_DEFAULT_LEVEL3'), 'min' => 30],
			];
		}

		usort($out, static fn($a, $b) => $a['min'] <=> $b['min']);

		return $out;
	}

	private function isStaff(int $userId): bool
	{
		if ($userId <= 0)
		{
			return false;
		}

		if (!isset($this->staffCache[$userId]))
		{
			$groups = array_map('intval', (array) $this->params->get('staff_groups', [6, 7, 8]));
			$this->staffCache[$userId] = (bool) array_intersect($groups, Access::getGroupsByUser($userId, false));
		}

		return $this->staffCache[$userId];
	}

	/**
	 * Identity key of a commenter: member by user id, guest by e-mail (or name when no e-mail).
	 */
	private function key(object $c): string
	{
		if (!empty($c->userid))
		{
			return 'u' . (int) $c->userid;
		}

		$email = mb_strtolower(trim((string) ($c->email ?? '')));

		return $email !== '' ? 'e' . $email : 'n' . mb_strtolower(trim((string) ($c->name ?? '')));
	}

	/**
	 * Comment counts for a set of commenters, one query.
	 *
	 * @return array<string,int> key => count
	 */
	private function counts(array $comments): array
	{
		$users = [];
		$mails = [];
		$names = [];

		foreach ($comments as $c)
		{
			$k = $this->key($c);

			if ($k[0] === 'u')
			{
				$users[] = (int) substr($k, 1);
			}
			elseif ($k[0] === 'e')
			{
				$mails[] = substr($k, 1);
			}
			else
			{
				$names[] = substr($k, 1);
			}
		}

		$db    = Factory::getContainer()->get('DatabaseDriver');
		$where = [];

		if ($users)
		{
			$where[] = $db->quoteName('userid') . ' IN (' . implode(',', array_unique($users)) . ')';
		}

		if ($mails)
		{
			$where[] = '(' . $db->quoteName('userid') . ' = 0 AND LOWER(' . $db->quoteName('email') . ') IN (' . implode(',', array_map([$db, 'quote'], array_unique($mails))) . '))';
		}

		if ($names)
		{
			$where[] = '(' . $db->quoteName('userid') . ' = 0 AND ' . $db->quoteName('email') . ' = \'\' AND LOWER(' . $db->quoteName('name') . ') IN (' . implode(',', array_map([$db, 'quote'], array_unique($names))) . '))';
		}

		if (!$where)
		{
			return [];
		}

		$sql = 'SELECT ' . $db->quoteName('userid') . ', LOWER(' . $db->quoteName('email') . ') AS email, LOWER(' . $db->quoteName('name') . ') AS name'
			. ' FROM ' . $db->quoteName('#__jcomments')
			. ' WHERE ' . $db->quoteName('deleted') . ' = 0'
			. ((int) $this->params->get('published_only', 1) ? ' AND ' . $db->quoteName('published') . ' = 1' : '')
			. ' AND (' . implode(' OR ', $where) . ')';

		$out = [];

		try
		{
			foreach ($db->setQuery($sql)->loadObjectList() ?: [] as $r)
			{
				$k = $this->key($r);
				$out[$k] = ($out[$k] ?? 0) + 1;
			}
		}
		catch (\RuntimeException $e)
		{
			Log::add($e->getMessage(), Log::ERROR, 'plg_jcomments_rkbadges');
		}

		return $out;
	}

	private function icon(string $icon): string
	{
		if ($icon === '')
		{
			return '';
		}

		if (preg_match('#\.(png|jpe?g|webp|svg|gif)$#i', $icon))
		{
			$src = preg_match('#^https?://#i', $icon) ? $icon : Uri::root() . ltrim($icon, '/');

			return '<img class="rkjc-badge__img" src="' . htmlspecialchars($src, ENT_QUOTES) . '" alt="" width="20" height="20" loading="lazy">';
		}

		return '<span class="rkjc-badge__ico">' . htmlspecialchars($icon, ENT_QUOTES) . '</span>';
	}

	private function html(string $icon, string $name, string $title, string $extra = ''): string
	{
		return '<span class="rkjc-badge' . $extra . '" title="' . htmlspecialchars($title, ENT_QUOTES) . '">'
			. $this->icon($icon) . '<span class="rkjc-badge__txt">' . htmlspecialchars($name, ENT_QUOTES) . '</span></span>';
	}

	private function badgeFor(object $c, int $count): string
	{
		if ($this->isStaff((int) ($c->userid ?? 0)))
		{
			$name = trim((string) $this->params->get('staff_name', ''));

			return $this->html((string) $this->params->get('staff_icon', '✍️'), $name !== '' ? $name : Text::_('PLG_JCOMMENTS_RKBADGES_DEFAULT_STAFF'), Text::_('PLG_JCOMMENTS_RKBADGES_STAFF_TITLE'), ' rkjc-badge--staff');
		}

		$got = null;

		foreach ($this->levels() as $level)
		{
			if ($count >= $level['min'])
			{
				$got = $level;
			}
		}

		if ($got === null)
		{
			return '';
		}

		return $this->html($got['icon'], $got['name'], Text::plural('PLG_JCOMMENTS_RKBADGES_N_COMMENTS', $count));
	}

	/* ------------------------------------------------------------------ events */

	public function onJCommentsCommentsPrepare(array &$rows)
	{
		if (!$rows)
		{
			return;
		}

		$counts = $this->counts($rows);

		foreach ($rows as $row)
		{
			if (!empty($row->deleted))
			{
				continue;
			}

			$row->rkBadgeHtml = $this->badgeFor($row, (int) ($counts[$this->key($row)] ?? 0));
			$row->rkBadgeDone = true;
		}
	}

	public function onJCommentsCommentAfterPrepare(&$comment)
	{
		if (!empty($comment->rkBadgeDone) || !empty($comment->deleted))
		{
			return;
		}

		$counts = $this->counts([$comment]);
		$comment->rkBadgeHtml = $this->badgeFor($comment, (int) ($counts[$this->key($comment)] ?? 0));
	}
}
