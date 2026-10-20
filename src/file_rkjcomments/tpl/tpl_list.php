<?php
/**
 * RK add-ons for JComments: template "rk" (flat list).
 *
 * @package  RK add-ons for JComments
 * @since    1.0.0
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

class jtt_tpl_list extends JoomlaTuneTemplate
{
	public function render()
	{
		$comments = $this->getVar('comments-items');

		if (isset($comments))
		{
			self::header($this);

			if ($this->getVar('comments-nav-top') == 1)
			{
				?>
				<div id="nav-top" class="rkjc-nav"><?php echo LayoutHelper::render('pagination', $this, JPATH_ROOT . '/components/com_jcomments/layouts/'); ?></div>
				<?php
			}
			?>
			<div id="comments-list" class="comments-list rkjc-list">
				<?php
				$i = 0;

				foreach ($comments as $id => $comment)
				{
					?>
					<div class="<?php echo $i % 2 ? 'odd' : 'even'; ?>" id="comment-item-<?php echo $id; ?>"><?php echo $comment; ?></div>
					<?php
					$i++;
				}
				?>
			</div>
			<?php
			if ($this->getVar('comments-nav-bottom') == 1)
			{
				?>
				<div id="nav-bottom" class="rkjc-nav"><?php echo LayoutHelper::render('pagination', $this, JPATH_ROOT . '/components/com_jcomments/layouts/'); ?></div>
				<?php
			}

			self::footer($this);
		}
		else
		{
			$comment = $this->getVar('comment-item');

			if (isset($comment))
			{
				$i  = $this->getVar('comment-modulo');
				$id = $this->getVar('comment-id');
				?>
				<div class="<?php echo $i % 2 ? 'odd' : 'even'; ?>" id="comment-item-<?php echo $id; ?>"><?php echo $comment; ?></div>
				<?php
			}
			else
			{
				?>
				<div id="comments-list" class="comments-list rkjc-list"></div>
				<?php
			}
		}
	}

	/**
	 * List header: title + refresh + RSS (shared with the tree template).
	 */
	public static function header($tpl)
	{
		$objectID    = $tpl->getVar('comment-object_id');
		$objectGroup = $tpl->getVar('comment-object_group');
		?>
		<div class="comments-list-header rkjc-list-head">
			<h3 class="rkjc-title"><?php echo Text::_('COMMENTS_LIST_HEADER'); ?></h3>
			<div class="rkjc-list-head__tools">
				<?php if ($tpl->getVar('comments-refresh', true)): ?>
					<a href="#" class="rkjc-iconbtn" title="<?php echo htmlspecialchars(Text::_('BUTTON_REFRESH'), ENT_QUOTES); ?>" onclick="jcomments.showPage(<?php echo (int) $objectID; ?>, '<?php echo $objectGroup; ?>', 0); return false;"><?php echo self::ico('refresh'); ?></a>
				<?php endif; ?>
				<?php if ($tpl->getVar('comments-rss', 1) == 1): ?>
					<a href="<?php echo Route::_('index.php?option=com_jcomments&view=comments&task=rss&object_id=' . $objectID . '&object_group=' . $objectGroup . '&format=feed'); ?>" class="rkjc-iconbtn" title="<?php echo htmlspecialchars(Text::_('BUTTON_RSS'), ENT_QUOTES); ?>" target="_blank" rel="noopener"><?php echo self::ico('rss'); ?></a>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * List footer: subscribe link (refresh and RSS already live in the header).
	 */
	public static function footer($tpl)
	{
		if ($tpl->getVar('comments-can-subscribe', 0) != 1)
		{
			return;
		}

		$objectID     = $tpl->getVar('comment-object_id');
		$objectGroup  = $tpl->getVar('comment-object_group');
		$isSubscribed = $tpl->getVar('comments-user-subscribed', 0);
		$text         = $isSubscribed ? Text::_('BUTTON_UNSUBSCRIBE') : Text::_('BUTTON_SUBSCRIBE');
		$func         = $isSubscribed ? 'remove' : 'add';
		$url          = 'index.php?option=com_jcomments&task=subscriptions.' . $func . '&object_id=' . $objectID
			. '&object_group=' . $objectGroup . '&return=' . base64_encode(Uri::getInstance());
		?>
		<div class="comments-list-footer rkjc-list-foot">
			<a href="<?php echo Route::_($url); ?>" class="rkjc-act cmd-subscribe" rel="nofollow"><?php echo self::ico('bell'); ?><?php echo $text; ?></a>
		</div>
		<?php
	}

	public static function ico($name)
	{
		$paths = array(
			'refresh' => '<path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.5 9a9 9 0 0 1 14.9-3.4L23 10M1 14l4.6 4.4A9 9 0 0 0 20.5 15"/>',
			'rss'     => '<path d="M4 11a9 9 0 0 1 9 9"/><path d="M4 4a16 16 0 0 1 16 16"/><circle cx="5" cy="19" r="1"/>',
			'bell'    => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
		);

		return isset($paths[$name])
			? '<svg class="rkjc-ico rkjc-ico--' . $name . '" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[$name] . '</svg>'
			: '';
	}
}
