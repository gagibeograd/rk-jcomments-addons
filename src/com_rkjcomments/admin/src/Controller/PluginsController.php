<?php
/**
 * @package   com_rkjcomments
 * @copyright 2026 Radnička Klasa
 * @license   GNU/GPL v2 or later
 */

namespace RadnickaKlasa\Component\Rkjcomments\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

class PluginsController extends BaseController
{
	/**
	 * Switch one RK plugin on/off (POST with token), then back to the list.
	 */
	public function toggle(): void
	{
		$this->checkToken();

		if (!$this->app->getIdentity()->authorise('core.manage', 'com_plugins'))
		{
			$this->setRedirect(Route::_('index.php?option=com_rkjcomments', false), Text::_('JERROR_ALERTNOAUTHOR'), 'error');

			return;
		}

		$id = $this->input->getInt('id', 0);

		/** @var \RadnickaKlasa\Component\Rkjcomments\Administrator\Model\PluginsModel $model */
		$model = $this->getModel('Plugins');
		$state = $model->toggle($id);

		if ($state === null)
		{
			$this->setRedirect(Route::_('index.php?option=com_rkjcomments', false), Text::_('COM_RKJCOMMENTS_TOGGLE_FAIL'), 'warning');

			return;
		}

		$this->setRedirect(Route::_('index.php?option=com_rkjcomments', false), Text::_($state ? 'COM_RKJCOMMENTS_ENABLED' : 'COM_RKJCOMMENTS_DISABLED'), 'message');
	}
}
