<?php
/**
 * Package script: enables the plugins after install/update and reminds about the template setting.
 *
 * @package   RK add-ons for JComments
 * @copyright 2026 Radnička Klasa
 * @license   GNU/GPL v2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\Database\DatabaseInterface;

return new class () implements InstallerScriptInterface
{
	private const JCOMMENTS_PLUGINS = ['rkavatar', 'rkreactions', 'rkpick', 'rkbadges', 'rkvoice', 'rkpurge'];

	public function install(InstallerAdapter $adapter): bool
	{
		return true;
	}

	public function update(InstallerAdapter $adapter): bool
	{
		return true;
	}

	public function uninstall(InstallerAdapter $adapter): bool
	{
		return true;
	}

	public function preflight(string $type, InstallerAdapter $adapter): bool
	{
		if (!is_dir(JPATH_ROOT . '/components/com_jcomments'))
		{
			Factory::getApplication()->enqueueMessage('RK add-ons for JComments: JComments 5 is not installed. Install JComments first.', 'error');

			return false;
		}

		return true;
	}

	public function postflight(string $type, InstallerAdapter $adapter): bool
	{
		if ($type !== 'install' && $type !== 'update')
		{
			return true;
		}

		$db = Factory::getContainer()->get(DatabaseInterface::class);

		$db->setQuery(
			'UPDATE ' . $db->quoteName('#__extensions') . ' SET ' . $db->quoteName('enabled') . ' = 1'
			. ' WHERE ' . $db->quoteName('type') . ' = ' . $db->quote('plugin') . ' AND ('
			. '(' . $db->quoteName('folder') . ' = ' . $db->quote('jcomments') . ' AND ' . $db->quoteName('element') . ' IN (' . implode(',', array_map([$db, 'quote'], self::JCOMMENTS_PLUGINS)) . '))'
			. ' OR (' . $db->quoteName('folder') . ' = ' . $db->quote('system') . ' AND ' . $db->quoteName('element') . ' = ' . $db->quote('rkjccount') . ')'
			. ')'
		)->execute();

		$app = Factory::getApplication();
		$app->enqueueMessage('RK add-ons for JComments: the seven plugins are enabled. To see the add-ons on the site select the template "rk" in Components: JComments: Options: View: Template. The Add-ons page is in the JComments menu.', 'message');

		return true;
	}
};
