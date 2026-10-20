<?php
/**
 * Install script: puts the "Dodaci" menu item under JComments in the administrator menu
 * (or at the top level of Components when JComments is not installed).
 *
 * @package   com_rkjcomments
 * @copyright 2026 Radnička Klasa
 * @license   GNU/GPL v2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseInterface;

return new class () implements InstallerScriptInterface
{
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
		$this->removeMenu();

		return true;
	}

	public function preflight(string $type, InstallerAdapter $adapter): bool
	{
		return true;
	}

	public function postflight(string $type, InstallerAdapter $adapter): bool
	{
		if ($type === 'install' || $type === 'update' || $type === 'discover_install')
		{
			$this->ensureMenu();
		}

		return true;
	}

	private function db(): DatabaseInterface
	{
		return Factory::getContainer()->get(DatabaseInterface::class);
	}

	private function removeMenu(): void
	{
		$db = $this->db();
		$db->setQuery('DELETE FROM ' . $db->quoteName('#__menu') . ' WHERE client_id = 1 AND link = ' . $db->quote('index.php?option=com_rkjcomments'))->execute();
	}

	private function ensureMenu(): void
	{
		$db = $this->db();

		$exists = $db->setQuery('SELECT id FROM ' . $db->quoteName('#__menu') . ' WHERE client_id = 1 AND link = ' . $db->quote('index.php?option=com_rkjcomments'))->loadResult();

		if ($exists)
		{
			return;
		}

		$extId = (int) $db->setQuery('SELECT extension_id FROM ' . $db->quoteName('#__extensions') . ' WHERE type = ' . $db->quote('component') . ' AND element = ' . $db->quote('com_rkjcomments'))->loadResult();
		$parent = (int) $db->setQuery('SELECT id FROM ' . $db->quoteName('#__menu') . ' WHERE client_id = 1 AND level = 1 AND link LIKE ' . $db->quote('index.php?option=com_jcomments%') . ' ORDER BY id LIMIT 1')->loadResult();

		if ($parent <= 0)
		{
			$parent = 1;
		}

		$level = (int) $db->setQuery('SELECT level FROM ' . $db->quoteName('#__menu') . ' WHERE id = ' . $parent)->loadResult();

		/** @var \Joomla\CMS\Table\Menu $table */
		$table = Table::getInstance('Menu');
		$table->setLocation($parent, 'last-child');
		$data = [
			'menutype'     => 'main',
			'title'        => 'COM_RKJCOMMENTS_MENU',
			'alias'        => 'com-rkjcomments-dodaci',
			'link'         => 'index.php?option=com_rkjcomments',
			'type'         => 'component',
			'published'    => 1,
			'parent_id'    => $parent,
			'level'        => $level + 1,
			'component_id' => $extId,
			'client_id'    => 1,
			'img'          => 'class:component',
			'access'       => 1,
			'language'     => '*',
			'params'       => '{}',
			'home'         => 0,
		];

		if (!$table->bind($data) || !$table->check() || !$table->store())
		{
			Factory::getApplication()->enqueueMessage('com_rkjcomments: the menu item could not be saved: ' . $table->getError(), 'warning');

			return;
		}

		// "Add-ons" sits above "Settings"
		$settings = (int) $db->setQuery('SELECT id FROM ' . $db->quoteName('#__menu') . ' WHERE client_id = 1 AND parent_id = ' . $parent . ' AND link = ' . $db->quote('index.php?option=com_jcomments&view=settings'))->loadResult();

		if ($settings > 0)
		{
			$table->moveByReference($settings, 'before', (int) $table->id);
		}
	}
};
