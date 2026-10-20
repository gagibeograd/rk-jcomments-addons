<?php
/**
 * @package   com_rkjcomments
 * @copyright 2026 Radnička Klasa
 * @license   GNU/GPL v2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \RadnickaKlasa\Component\Rkjcomments\Administrator\View\Plugins\HtmlView $this */
$token = HTMLHelper::_('form.token');
?>
<div class="row">
	<div class="col-12">
		<div class="j-main-container">
			<div class="alert alert-info mb-3">
				<span class="icon-info-circle" aria-hidden="true"></span>
				<strong><?php echo Text::_('COM_RKJCOMMENTS_INFO_TITLE'); ?></strong>
				<?php echo Text::_('COM_RKJCOMMENTS_INFO_TEXT'); ?>
			</div>

			<?php if (!$this->items): ?>
				<div class="alert alert-warning"><?php echo Text::_('COM_RKJCOMMENTS_NONE'); ?></div>
			<?php else: ?>
				<table class="table">
					<caption class="visually-hidden"><?php echo Text::_('COM_RKJCOMMENTS_TITLE'); ?></caption>
					<thead>
						<tr>
							<th scope="col" class="w-10 text-center"><?php echo Text::_('JSTATUS'); ?></th>
							<th scope="col"><?php echo Text::_('COM_RKJCOMMENTS_COL_PLUGIN'); ?></th>
							<th scope="col" class="d-none d-md-table-cell"><?php echo Text::_('COM_RKJCOMMENTS_COL_DESC'); ?></th>
							<th scope="col" class="w-10 d-none d-md-table-cell"><?php echo Text::_('JVERSION'); ?></th>
							<th scope="col" class="w-15"></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($this->items as $p): ?>
							<tr>
								<td class="text-center">
									<form method="post" action="<?php echo Route::_('index.php?option=com_rkjcomments&task=plugins.toggle'); ?>" class="d-inline">
										<input type="hidden" name="id" value="<?php echo (int) $p->extension_id; ?>">
										<?php echo $token; ?>
										<button type="submit" class="tbody-icon<?php echo $p->enabled ? ' active' : ''; ?>" title="<?php echo Text::_($p->enabled ? 'COM_RKJCOMMENTS_CLICK_DISABLE' : 'COM_RKJCOMMENTS_CLICK_ENABLE'); ?>" style="font-size:1.4rem;color:<?php echo $p->enabled ? '#2e8b57' : '#adb5bd'; ?>">
											<span class="<?php echo $p->enabled ? 'icon-publish' : 'icon-unpublish'; ?>" aria-hidden="true"></span>
											<span class="visually-hidden"><?php echo Text::_($p->enabled ? 'JENABLED' : 'JDISABLED'); ?></span>
										</button>
									</form>
								</td>
								<th scope="row">
									<a href="<?php echo $p->editLink; ?>"><?php echo htmlspecialchars($p->title); ?></a>
									<?php if (!$p->enabled): ?><span class="badge bg-secondary ms-1"><?php echo Text::_('JDISABLED'); ?></span><?php endif; ?>
									<div class="small text-muted d-md-none"><?php echo htmlspecialchars($p->desc); ?></div>
								</th>
								<td class="d-none d-md-table-cell small"><?php echo htmlspecialchars($p->desc); ?></td>
								<td class="d-none d-md-table-cell small"><?php echo htmlspecialchars($p->version); ?></td>
								<td class="text-end">
									<a class="btn btn-sm btn-outline-secondary" href="<?php echo $p->editLink; ?>"><span class="icon-options" aria-hidden="true"></span> <?php echo Text::_('COM_RKJCOMMENTS_SETTINGS'); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<div class="card mt-3">
				<div class="card-body small text-muted">
					<?php echo Text::_('COM_RKJCOMMENTS_FOOT'); ?>
				</div>
			</div>
		</div>
	</div>
</div>
