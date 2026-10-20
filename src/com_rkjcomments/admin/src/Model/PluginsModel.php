<?php
/**
 * @package   com_rkjcomments
 * @copyright 2026 Radnička Klasa
 * @license   GNU/GPL v2 or later
 */

namespace RadnickaKlasa\Component\Rkjcomments\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Router\Route;

class PluginsModel extends BaseDatabaseModel
{
	/** Our plugins: JComments group, element starting with "rk". Known ones first, in a sensible order. */
	private const ORDER = ['rkavatar', 'rkreactions', 'rkpick', 'rkbadges', 'rkvoice', 'rkpurge'];

	public function getItems(): array
	{
		$db = $this->getDatabase();
		$db->setQuery(
			'SELECT extension_id, element, name, enabled, manifest_cache FROM ' . $db->quoteName('#__extensions')
			. ' WHERE type = ' . $db->quote('plugin') . ' AND folder = ' . $db->quote('jcomments') . ' AND element LIKE ' . $db->quote('rk%')
		);
		$rows = $db->loadObjectList() ?: [];
		$lang = Factory::getApplication()->getLanguage();

		foreach ($rows as $r)
		{
			$lang->load('plg_jcomments_' . $r->element, JPATH_ADMINISTRATOR);
			$lang->load('plg_jcomments_' . $r->element, JPATH_PLUGINS . '/jcomments/' . $r->element);
			$manifest    = json_decode((string) $r->manifest_cache, true) ?: [];
			$r->title    = Text::_($r->name);
			$r->version  = (string) ($manifest['version'] ?? '');
			$descKey     = 'COM_RKJCOMMENTS_DESC_' . strtoupper($r->element);
			$r->desc     = $lang->hasKey($descKey) ? Text::_($descKey) : Text::_((string) ($manifest['description'] ?? ''));
			$r->editLink = Route::_('index.php?option=com_plugins&task=plugin.edit&extension_id=' . (int) $r->extension_id);
			$k           = array_search($r->element, self::ORDER, true);
			$r->order    = $k === false ? 99 : $k;
		}

		usort($rows, static fn($a, $b) => [$a->order, $a->element] <=> [$b->order, $b->element]);

		return $rows;
	}

	/**
	 * @return bool|null new state, or null when the id is not one of ours
	 */
	public function toggle(int $id): ?bool
	{
		if ($id <= 0)
		{
			return null;
		}

		$db  = $this->getDatabase();
		$row = $db->setQuery(
			'SELECT extension_id, enabled FROM ' . $db->quoteName('#__extensions') . ' WHERE extension_id = ' . $id
			. ' AND type = ' . $db->quote('plugin') . ' AND folder = ' . $db->quote('jcomments') . ' AND element LIKE ' . $db->quote('rk%')
		)->loadObject();

		if (!$row)
		{
			return null;
		}

		$new = (int) $row->enabled ? 0 : 1;
		$db->setQuery('UPDATE ' . $db->quoteName('#__extensions') . ' SET enabled = ' . $new . ' WHERE extension_id = ' . $id)->execute();

		return (bool) $new;
	}
}
