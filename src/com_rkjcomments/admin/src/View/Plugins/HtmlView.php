<?php
/**
 * @package   com_rkjcomments
 * @copyright 2026 Radnička Klasa
 * @license   GNU/GPL v2 or later
 */

namespace RadnickaKlasa\Component\Rkjcomments\Administrator\View\Plugins;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
	/** @var object[] */
	public $items = [];

	public function display($tpl = null): void
	{
		$this->items = $this->getModel()->getItems();
		ToolbarHelper::title(Text::_('COM_RKJCOMMENTS_TITLE'), 'star');
		ToolbarHelper::link('index.php?option=com_plugins&view=plugins&filter[folder]=jcomments', 'COM_RKJCOMMENTS_ALL_PLUGINS', 'plug');
		ToolbarHelper::link('index.php?option=com_jcomments&view=comments', 'COM_RKJCOMMENTS_BACK', 'comments');
		parent::display($tpl);
	}
}
