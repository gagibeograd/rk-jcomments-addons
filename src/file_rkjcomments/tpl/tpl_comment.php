<?php
/**
 * RK add-ons for JComments: template "rk" (single comment).
 * Ids used by jcomments-v4.0.js are kept: comment-ID, comment-body-ID, comment-vote-holder-ID,
 * comment-toolbar-ID, hide-button-ID, show-button-ID.
 *
 * @package  RK add-ons for JComments
 * @since    1.0.0
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

class jtt_tpl_comment extends JoomlaTuneTemplate
{
	public function render()
	{
		$comment = $this->getVar('comment');

		if (!isset($comment))
		{
			return;
		}

		if ($this->getVar('get_comment_vote', 0) == 1)
		{
			echo $this->voteValue($comment);

			return;
		}

		if ($this->getVar('get_comment_body', 0) == 1)
		{
			echo $comment->comment;

			return;
		}

		$id      = (int) $comment->id;
		$deleted = !empty($comment->deleted);
		$avatar  = $this->getVar('avatar') == 1 && !empty($comment->avatar);
		?>
		<article class="rkjc-comment<?php echo $deleted ? ' rkjc-comment--deleted' : ''; ?><?php echo $comment->userid ? ' rkjc-comment--member' : ''; ?><?php echo $avatar ? '' : ' rkjc-comment--noavatar'; ?><?php echo !empty($comment->rkPick) ? ' rkjc-comment--pick' : ''; ?>">
			<?php if (!empty($comment->rkPick)): // plg_jcomments_rkpick ?>
				<span class="rkjc-pick"><?php echo self::icon('star'); ?><?php echo htmlspecialchars($comment->rkPickLabel ?? ''); ?></span>
			<?php endif; ?>
			<?php if ($avatar): ?>
				<div class="rkjc-avatar">
					<?php if (!empty($comment->profileLink)): ?>
						<a href="<?php echo $comment->profileLink; ?>" target="<?php echo $comment->profileLinkTarget ?: '_self'; ?>"><img src="<?php echo $comment->avatar; ?>" alt="" width="48" height="48" loading="lazy" decoding="async"></a>
					<?php else: ?>
						<img src="<?php echo $comment->avatar; ?>" alt="" width="48" height="48" loading="lazy" decoding="async">
					<?php endif; ?>
					<?php if (!empty($comment->rkBadgeHtml)): // plg_jcomments_rkbadges ?>
						<?php echo $comment->rkBadgeHtml; ?>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="rkjc-main">
				<header class="rkjc-head">
					<span class="rkjc-name" itemprop="author" itemscope itemtype="https://schema.org/Person">
						<?php if ($this->getVar('comment-show-homepage') == 1 && !empty($comment->homepage)): ?>
							<a href="<?php echo $comment->homepage; ?>" rel="nofollow noopener" itemprop="url" target="_blank"><span itemprop="name"><?php echo $comment->author; ?></span></a>
						<?php else: ?>
							<span itemprop="name"><?php echo $comment->author; ?></span>
						<?php endif; ?>
					</span>

					<?php if ($this->getVar('comment-show-email') > 0 && $comment->email != ''): ?>
						<span class="rkjc-email">
							<?php if (!Factory::getApplication()->getIdentity()->get('isRoot')): ?>
								<?php echo JComments::maskEmail($comment->id, $comment->email, true); ?>
							<?php else: ?>
								<a class="comment-email" href="mailto:<?php echo $comment->email; ?>"><?php echo $comment->email; ?></a>
							<?php endif; ?>
						</span>
					<?php endif; ?>

					<time class="rkjc-time" datetime="<?php echo HTMLHelper::_('date', $comment->date, 'c'); ?>" itemprop="datePublished">
						<?php echo HTMLHelper::_('date', $comment->date, 'd.m.Y. H:i'); ?>
					</time>

					<?php if ($comment->parent > 0): ?>
						<a class="rkjc-parent" href="#comment-item-<?php echo (int) $comment->parent; ?>" title="<?php echo htmlspecialchars(Text::_('GOTO_PREV_COMMENT_ANCHOR'), ENT_QUOTES); ?>"><?php echo self::icon('reply'); ?></a>
					<?php endif; ?>

					<a href="<?php echo $this->getVar('thisurl', ''); ?>#comment-<?php echo $id; ?>" class="rkjc-anchor comment-anchor" id="comment-<?php echo $id; ?>">#<?php echo $this->getVar('comment-number', 1); ?></a>

					<?php if ($this->getVar('comment-show-vote', 0) == 1 && empty($comment->rkHideVote)): ?>
						<span class="rkjc-vote comments-vote">
							<span id="comment-vote-holder-<?php echo $id; ?>">
								<?php if ($this->getVar('button-vote', 0) == 1): ?>
									<a href="#" class="rkjc-vote__btn rkjc-vote__btn--up" title="<?php echo htmlspecialchars(Text::_('BUTTON_VOTE_GOOD'), ENT_QUOTES); ?>" onclick="jcomments.voteComment(<?php echo $id; ?>, 1);return false;"><?php echo self::icon('up'); ?></a>
									<a href="#" class="rkjc-vote__btn rkjc-vote__btn--down" title="<?php echo htmlspecialchars(Text::_('BUTTON_VOTE_BAD'), ENT_QUOTES); ?>" onclick="jcomments.voteComment(<?php echo $id; ?>, -1);return false;"><?php echo self::icon('down'); ?></a>
								<?php endif; ?>
								<?php echo $this->voteValue($comment); ?>
							</span>
						</span>
					<?php endif; ?>
				</header>

				<?php if ($this->getVar('comment-display-title') > 0 && $comment->title != ''): ?>
					<div class="rkjc-ctitle comment-title"><?php echo $comment->title; ?></div>
				<?php endif; ?>

				<div class="comment-box">
					<?php if (!empty($comment->rkVoiceHtml)): // plg_jcomments_rkvoice ?>
						<?php echo $comment->rkVoiceHtml; ?>
					<?php endif; ?>
					<div class="comment-body rkjc-body" id="comment-body-<?php echo $id; ?>"><?php echo $comment->comment; ?></div>
				</div>

				<?php $this->buttons($comment); ?>
				<?php $this->adminPanel($comment); ?>
			</div>
		</article>
		<?php
	}

	private function buttons($comment)
	{
		$id     = (int) $comment->id;
		$reply  = $this->getVar('button-reply') == 1;
		$quote  = $this->getVar('button-quote') == 1;
		$report = $this->getVar('button-report') == 1;
		$kids   = isset($comment->children) && $comment->children != 0;
		$react  = !empty($comment->rkReactionsHtml); // plg_jcomments_rkreactions

		if (!$reply && !$quote && !$report && !$kids && !$react)
		{
			return;
		}
		?>
		<footer class="rkjc-actions comments-buttons">
			<?php if ($react): ?><?php echo $comment->rkReactionsHtml; ?><?php endif; ?>
			<?php if ($reply): ?>
				<a href="#" class="rkjc-act" onclick="jcomments.showReply(<?php echo $id; ?>); return false;"><?php echo self::icon('reply'); ?><?php echo Text::_('BUTTON_REPLY'); ?></a>
				<?php if ($quote): ?>
					<a href="#" class="rkjc-act" onclick="jcomments.showReply(<?php echo $id; ?>,1); return false;"><?php echo self::icon('quote'); ?><?php echo Text::_('BUTTON_REPLY_WITH_QUOTE'); ?></a>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ($quote): ?>
				<a href="#" class="rkjc-act" onclick="jcomments.quoteComment(<?php echo $id; ?>); return false;"><?php echo self::icon('quote'); ?><?php echo Text::_('BUTTON_QUOTE'); ?></a>
			<?php endif; ?>

			<?php if ($report): ?>
				<a href="#" class="rkjc-act rkjc-act--report" onclick="jcomments.reportComment(<?php echo $id; ?>); return false;"><?php echo self::icon('flag'); ?><?php echo Text::_('BUTTON_REPORT'); ?></a>
			<?php endif; ?>

			<?php if ($kids): ?>
				<a href="#" class="rkjc-act rkjc-act--toggle" id="hide-button-<?php echo $id; ?>" onclick="jcomments.hideChildComments(this, <?php echo $id; ?>); return false;"><?php echo self::icon('chevup'); ?><?php echo Text::_('BUTTON_HIDE'); ?></a>
				<a href="#" class="rkjc-act rkjc-act--toggle" id="show-button-<?php echo $id; ?>" onclick="jcomments.showChildComments(this, <?php echo $id; ?>); return false;" style="display: none"><?php echo self::icon('chevdown'); ?><?php echo Text::_('BUTTON_SHOW'); ?></a>
			<?php endif; ?>
		</footer>
		<?php
	}

	private function adminPanel($comment)
	{
		if ($this->getVar('comments-panel-visible', 0) != 1)
		{
			return;
		}

		$id = (int) $comment->id;
		?>
		<div class="toolbar rkjc-tools" id="comment-toolbar-<?php echo $id; ?>">
			<div class="main-actions rkjc-tools__group">
				<?php if ($this->getVar('button-edit') == 1): ?>
					<a class="rkjc-tool toolbar-button-edit" href="#" title="<?php echo htmlspecialchars(Text::_('BUTTON_EDIT'), ENT_QUOTES); ?>" onclick="jcomments.editComment(<?php echo $id; ?>); return false;"><?php echo self::icon('edit'); ?><span><?php echo Text::_('BUTTON_EDIT'); ?></span></a>
				<?php endif; ?>

				<?php if ($this->getVar('button-delete') == 1): ?>
					<a class="rkjc-tool rkjc-tool--danger toolbar-button-delete" href="#" title="<?php echo htmlspecialchars(Text::_('BUTTON_DELETE'), ENT_QUOTES); ?>" onclick="if (confirm('<?php echo JCommentsText::jsEscape(Text::_('BUTTON_DELETE_CONIRM')); ?>')){jcomments.deleteComment(<?php echo $id; ?>);}return false;"><?php echo self::icon('trash'); ?><span><?php echo Text::_('BUTTON_DELETE'); ?></span></a>
				<?php endif; ?>

				<?php if ($this->getVar('button-publish') == 1):
					$text  = $comment->published ? Text::_('BUTTON_UNPUBLISH') : Text::_('BUTTON_PUBLISH');
					$class = $comment->published ? 'rkjc-tool--on' : 'rkjc-tool--off'; ?>
					<a class="rkjc-tool <?php echo $class; ?>" href="#" title="<?php echo htmlspecialchars($text, ENT_QUOTES); ?>" onclick="jcomments.publishComment(<?php echo $id; ?>, this);return false;"><?php echo self::icon($comment->published ? 'eyeoff' : 'eye'); ?><span><?php echo $text; ?></span></a>
				<?php endif; ?>
			</div>

			<div class="user-actions rkjc-tools__group">
				<?php if (!empty($comment->rkPickUrl)): // plg_jcomments_rkpick ?>
					<a class="rkjc-tool rkjc-tool--pick<?php echo !empty($comment->rkPick) ? ' rkjc-tool--on' : ''; ?>" href="<?php echo $comment->rkPickUrl; ?>" title="<?php echo htmlspecialchars($comment->rkPickText ?? '', ENT_QUOTES); ?>"><?php echo self::icon('star'); ?><span><?php echo htmlspecialchars($comment->rkPickText ?? ''); ?></span></a>
				<?php endif; ?>
				<?php if ($this->getVar('button-ip') == 1): ?>
					<a class="rkjc-tool rkjc-tool--ip toolbar-button-ip" href="#" title="<?php echo htmlspecialchars(Text::_('BUTTON_IP') . ' ' . $comment->ip, ENT_QUOTES); ?>" onclick="jcomments.go('https://www.ripe.net/perl/whois?searchtext=<?php echo $comment->ip; ?>');return false;"><?php echo $comment->ip; ?></a>
				<?php endif; ?>

				<?php if ($this->getVar('button-ban') == 1): ?>
					<a class="rkjc-tool rkjc-tool--danger toolbar-button-ban" href="#" title="<?php echo htmlspecialchars(Text::_('BUTTON_BANIP'), ENT_QUOTES); ?>" onclick="jcomments.banIP(<?php echo $id; ?>);return false;"><?php echo self::icon('ban'); ?><span><?php echo Text::_('BUTTON_BANIP'); ?></span></a>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Vote score badge (also returned alone for the AJAX vote refresh).
	 */
	private function voteValue($comment)
	{
		$value = (int) $comment->isgood - (int) $comment->ispoor;

		if ($value == 0 && $this->getVar('button-vote', 0) == 0 && $this->getVar('get_comment_vote', 0) == 0)
		{
			return '';
		}

		if ($value < 0)
		{
			$class = 'rkjc-score--neg';
		}
		elseif ($value > 0)
		{
			$class = 'rkjc-score--pos';
			$value = '+' . $value;
		}
		else
		{
			$class = 'rkjc-score--zero';
		}

		return '<span class="vote-value rkjc-score ' . $class . '">' . $value . '</span>';
	}

	public static function icon($name)
	{
		$paths = array(
			'reply'    => '<path d="M9 17 4 12l5-5"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/>',
			'quote'    => '<path d="M3 21c3 0 7-1 7-8V5c0-1.25-.76-2.02-2-2H4c-1.25 0-2 .75-2 1.97V11c0 1.25.75 2 2 2 1 0 1 0 1 1v1c0 1-1 2-2 2s-1 .01-1 1.01V20c0 1 0 1 1 1z"/><path d="M15 21c3 0 7-1 7-8V5c0-1.25-.75-2.02-2-2h-4c-1.25 0-2 .75-2 1.97V11c0 1.25.75 2 2 2h.75c0 2.25.25 4-2.75 4v3c0 1 0 1 1 1z"/>',
			'flag'     => '<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><path d="M4 22v-7"/>',
			'chevup'   => '<path d="m18 15-6-6-6 6"/>',
			'chevdown' => '<path d="m6 9 6 6 6-6"/>',
			'up'       => '<path d="M7 10v12"/><path d="M15 5.88 14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2.76a2 2 0 0 0 1.79-1.11L12 2a3.13 3.13 0 0 1 3 3.88Z"/>',
			'down'     => '<path d="M17 14V2"/><path d="M9 18.12 10 14H4.17a2 2 0 0 1-1.92-2.56l2.33-8A2 2 0 0 1 6.5 2H20a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-2.76a2 2 0 0 0-1.79 1.11L12 22a3.13 3.13 0 0 1-3-3.88Z"/>',
			'edit'     => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
			'trash'    => '<path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
			'eye'      => '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
			'eyeoff'   => '<path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><path d="m2 2 20 20"/>',
			'ban'      => '<circle cx="12" cy="12" r="10"/><path d="m4.9 4.9 14.2 14.2"/>',
			'star'     => '<path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>',
		);

		if (!isset($paths[$name]))
		{
			return '';
		}

		return '<svg class="rkjc-ico rkjc-ico--' . $name . '" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[$name] . '</svg>';
	}
}
