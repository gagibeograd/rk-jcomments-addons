<?php
/**
 * RK add-ons for JComments: template "rk" (threaded list).
 * Same nesting logic as the core tpl_tree, header/footer shared with tpl_list.
 *
 * @package  RK add-ons for JComments
 * @since    1.0.0
 */

defined('_JEXEC') or die;

if (!class_exists('jtt_tpl_list', false))
{
	require_once __DIR__ . '/tpl_list.php';
}

class jtt_tpl_tree extends JoomlaTuneTemplate
{
	public function render()
	{
		$comments = $this->getVar('comments-items');

		if (isset($comments))
		{
			jtt_tpl_list::header($this);
			?>
			<div class="comments-list rkjc-list rkjc-list--tree" id="comments-list-0">
			<?php
			$count        = count($comments);
			$currentLevel = 0;
			$i            = 0;
			$j            = 0;

			foreach ($comments as $id => $comment)
			{
				if ($currentLevel < $comment->level)
				{
					?>
					</div>
					<div class="comments-list-child rkjc-children" id="comments-list-<?php echo $comment->parent; ?>">
					<?php
				}
				else
				{
					if ($currentLevel >= $comment->level)
					{
						$j = $currentLevel - $comment->level;
					}
					elseif ($comment->level > 0 && $i == $count - 1)
					{
						$j = $comment->level;
					}

					while ($j > 0)
					{
						?>
						</div>
						<?php
						$j--;
					}
				}
				?>
				<div class="comment <?php echo $i % 2 ? 'odd' : 'even'; ?>" id="comment-item-<?php echo $id; ?>">
				<?php
				echo $comment->html;

				if ($comment->children == 0)
				{
					?>
					</div>
					<?php
				}

				if ($comment->level > 0 && $i == $count - 1)
				{
					$j = $comment->level;
				}

				while ($j > 0)
				{
					?>
					</div>
					<?php
					$j--;
				}

				$i++;
				$currentLevel = $comment->level;
			}
			?>
			</div>
			<?php
			jtt_tpl_list::footer($this);
		}
		else
		{
			$comment = $this->getVar('comment-item');

			if (isset($comment))
			{
				$i  = $this->getVar('comment-modulo');
				$id = $this->getVar('comment-id');
				?>
				<div class="comment <?php echo $i % 2 ? 'odd' : 'even'; ?>" id="comment-item-<?php echo $id; ?>"><?php echo $comment; ?></div>
				<?php
			}
			else
			{
				?>
				<div class="comments-list rkjc-list rkjc-list--tree" id="comments-list-0"></div>
				<?php
			}
		}
	}
}
