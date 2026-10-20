<?php
/**
 * RK add-ons for JComments: emoji reactions on comments.
 *
 * One reaction per visitor and comment (registered user id, or a browser token for guests).
 * Data lives in #__jcomments_rkreactions; the JComments core is not patched.
 * Rendering: the plugin puts ready HTML into $comment->rkReactionsHtml (the RK template prints it)
 * and, when hide_vote is on, sets $comment->rkHideVote so the template drops the +/- vote.
 * AJAX: index.php?option=com_ajax&group=jcomments&plugin=rkreactions&format=json (POST).
 *
 * @package   plg_jcomments_rkreactions
 * @copyright 2026 Radnička Klasa
 * @license   GNU/GPL v2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;

class PlgJcommentsRkreactions extends CMSPlugin
{
	protected $autoloadLanguage = true;

	private static $assetsAdded = false;

	/* ------------------------------------------------------------------ helpers */

	/** @return array<string,string> emoji => label, in configured order */
	private function reactions(): array
	{
		$out = [];

		foreach (preg_split('/\r\n|\r|\n/', (string) $this->params->get('reactions', '')) as $line)
		{
			$line = trim($line);

			if ($line === '')
			{
				continue;
			}

			[$emoji, $label] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
			$emoji = mb_substr($emoji, 0, 8);

			if ($emoji !== '')
			{
				$out[$emoji] = $label !== '' ? $label : $emoji;
			}
		}

		if (!$out)
		{
			// Defaults come from the language files, so every site language gets its own labels.
			$out = [
				'👍' => Text::_('PLG_JCOMMENTS_RKREACTIONS_DEFAULT_LIKE'),
				'❤️' => Text::_('PLG_JCOMMENTS_RKREACTIONS_DEFAULT_LOVE'),
				'😂' => Text::_('PLG_JCOMMENTS_RKREACTIONS_DEFAULT_HAHA'),
				'😮' => Text::_('PLG_JCOMMENTS_RKREACTIONS_DEFAULT_WOW'),
				'😢' => Text::_('PLG_JCOMMENTS_RKREACTIONS_DEFAULT_SAD'),
				'😡' => Text::_('PLG_JCOMMENTS_RKREACTIONS_DEFAULT_ANGRY'),
			];
		}

		return $out;
	}

	private function db()
	{
		return Factory::getContainer()->get('DatabaseDriver');
	}

	private function ensureTable(): void
	{
		static $done = false;

		if ($done)
		{
			return;
		}

		$done = true;
		$db   = $this->db();

		try
		{
			$db->setQuery(
				'CREATE TABLE IF NOT EXISTS ' . $db->quoteName('#__jcomments_rkreactions') . ' ('
				. '`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, '
				. '`comment_id` INT UNSIGNED NOT NULL, '
				. '`reaction` VARCHAR(16) NOT NULL, '
				. '`visitor` VARCHAR(64) NOT NULL, '
				. '`userid` INT UNSIGNED NOT NULL DEFAULT 0, '
				. '`name` VARCHAR(255) NOT NULL DEFAULT \'\', '
				. '`ip` VARCHAR(45) NOT NULL DEFAULT \'\', '
				. '`date` DATETIME NOT NULL, '
				. 'PRIMARY KEY (`id`), UNIQUE KEY `uq_comment_visitor` (`comment_id`, `visitor`), KEY `idx_comment` (`comment_id`)'
				. ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci'
			)->execute();
		}
		catch (\RuntimeException $e)
		{
			Log::add($e->getMessage(), Log::ERROR, 'plg_jcomments_rkreactions');
		}
	}

	/**
	 * Visitor key: user id for members, otherwise the browser token sent by the script (hashed).
	 */
	private function visitorKey(string $token = ''): string
	{
		$user = Factory::getApplication()->getIdentity();

		if ($user && $user->id)
		{
			return 'u' . (int) $user->id;
		}

		$token = preg_replace('/[^a-f0-9]/i', '', $token);

		return strlen($token) >= 16 ? 'g' . substr(hash('sha256', $token), 0, 40) : '';
	}

	/**
	 * Counts per reaction for a set of comment ids, and the current visitor's reaction.
	 *
	 * @return array{counts: array<int, array<string,int>>, names: array<int, array<string,string[]>>, mine: array<int,string>}
	 */
	private function load(array $ids, string $visitor): array
	{
		$ids = array_values(array_unique(array_map('intval', $ids)));
		$res = ['counts' => [], 'names' => [], 'mine' => []];

		if (!$ids)
		{
			return $res;
		}

		$this->ensureTable();
		$db = $this->db();

		try
		{
			$query = $db->getQuery(true)
				->select($db->quoteName(['comment_id', 'reaction', 'visitor', 'name']))
				->from($db->quoteName('#__jcomments_rkreactions'))
				->whereIn($db->quoteName('comment_id'), $ids)
				->order($db->quoteName('date') . ' DESC');
			$rows = $db->setQuery($query)->loadAssocList() ?: [];
		}
		catch (\RuntimeException $e)
		{
			Log::add($e->getMessage(), Log::ERROR, 'plg_jcomments_rkreactions');

			return $res;
		}

		foreach ($rows as $r)
		{
			$cid = (int) $r['comment_id'];
			$res['counts'][$cid][$r['reaction']] = ($res['counts'][$cid][$r['reaction']] ?? 0) + 1;

			if ($r['name'] !== '')
			{
				$res['names'][$cid][$r['reaction']][] = $r['name'];
			}

			if ($visitor !== '' && $r['visitor'] === $visitor)
			{
				$res['mine'][$cid] = $r['reaction'];
			}
		}

		return $res;
	}

	/**
	 * HTML for one comment's reactions block.
	 */
	private function render(int $cid, array $counts, array $names, string $mine): string
	{
		$reactions = $this->reactions();
		$total     = array_sum($counts);
		$label     = trim((string) $this->params->get('button_label', ''));
		$label     = htmlspecialchars($label !== '' ? $label : Text::_('PLG_JCOMMENTS_RKREACTIONS_DEFAULT_BUTTON'), ENT_QUOTES);
		$showNames = (int) $this->params->get('show_names', 1);

		// summary: emoji ordered by count, then total
		arsort($counts);
		$sum = '';

		foreach ($counts as $emoji => $n)
		{
			if (!isset($reactions[$emoji]) || $n < 1)
			{
				continue;
			}

			$title = $reactions[$emoji] . ': ' . $n;

			if ($showNames && !empty($names[$emoji]))
			{
				$list  = array_slice($names[$emoji], 0, 5);
				$title .= ' (' . implode(', ', $list) . (count($names[$emoji]) > 5 ? '…' : '') . ')';
			}

			$sum .= '<span class="rkjc-react__ico" title="' . htmlspecialchars($title, ENT_QUOTES) . '">' . $emoji . '</span>';
		}

		$html = '<div class="rkjc-react' . ($mine !== '' ? ' has-mine' : '') . '" data-rkjc-react="' . $cid . '" data-mine="' . htmlspecialchars($mine, ENT_QUOTES) . '">'
			. '<button type="button" class="rkjc-react__btn" aria-haspopup="true" aria-expanded="false" title="' . $label . '">'
			. '<span class="rkjc-react__btn-ico">👍</span>'
			. '<span class="rkjc-react__btn-txt">' . $label . '</span>'
			. '</button>'
			. '<span class="rkjc-react__sum' . ($total ? '' : ' is-empty') . '">' . $sum . ($total ? '<span class="rkjc-react__n">' . $total . '</span>' : '') . '</span>'
			. '<div class="rkjc-react__bar" role="menu">';

		foreach ($reactions as $emoji => $name)
		{
			$html .= '<button type="button" class="rkjc-react__opt' . ($mine === $emoji ? ' is-mine' : '') . '" role="menuitem" data-r="' . htmlspecialchars($emoji, ENT_QUOTES) . '" title="' . htmlspecialchars($name, ENT_QUOTES) . '" aria-label="' . htmlspecialchars($name, ENT_QUOTES) . '">' . $emoji . '</button>';
		}

		$html .= '</div></div>';

		return $html;
	}

	private function addAssets(): void
	{
		if (self::$assetsAdded)
		{
			return;
		}

		self::$assetsAdded = true;

		try
		{
			$doc = Factory::getApplication()->getDocument();
			$wa  = $doc->getWebAssetManager();
		}
		catch (\Throwable $e)
		{
			return;
		}

		$url = Uri::root(true) . '/index.php?option=com_ajax&group=jcomments&plugin=rkreactions&format=json';
		$msgErr = htmlspecialchars(Text::_('PLG_JCOMMENTS_RKREACTIONS_ERROR'), ENT_QUOTES);

		$css = <<<'CSS'
.rkjc-react{position:relative;display:inline-flex;align-items:center;gap:8px;font-size:.8rem;line-height:1}
.rkjc-react__btn{appearance:none;display:inline-flex;align-items:center;gap:6px;padding:5px 12px 5px 8px;border-radius:999px;border:1px solid var(--rkjc-line,rgba(37,45,55,.12));background:var(--rkjc-bg,#fff);color:var(--rkjc-muted,#8a8f98);font:inherit;font-size:.8rem;font-weight:700;cursor:pointer;transition:border-color .15s,color .15s,background .15s,transform .15s}
.rkjc-react__btn:hover{border-color:var(--rkjc-accent,#6b7f95);color:var(--rkjc-dark,#252d37);background:var(--rkjc-accent-soft,rgba(107,127,149,.14))}
.rkjc-react.has-mine .rkjc-react__btn{border-color:var(--rkjc-accent,#6b7f95);color:var(--rkjc-accent-ink,#4a5d70);background:var(--rkjc-accent-soft,rgba(107,127,149,.14))}
.rkjc-react__btn-ico{font-size:1rem;line-height:1;font-family:"Apple Color Emoji","Segoe UI Emoji","Noto Color Emoji",sans-serif}
.rkjc-react__sum{display:inline-flex;align-items:center;gap:2px;color:var(--rkjc-muted,#8a8f98)}
.rkjc-react__sum.is-empty{display:none}
.rkjc-react__ico{font-size:.95rem;line-height:1;margin-left:-4px;font-family:"Apple Color Emoji","Segoe UI Emoji","Noto Color Emoji",sans-serif}
.rkjc-react__ico:first-child{margin-left:0}
.rkjc-react__n{margin-left:5px;font-weight:700;color:var(--rkjc-dark,#252d37)}
.rkjc-react__bar{position:absolute;left:0;bottom:calc(100% + 6px);z-index:30;display:flex;gap:2px;padding:6px;background:var(--rkjc-bg,#fff);border:1px solid var(--rkjc-line,rgba(37,45,55,.12));border-radius:999px;box-shadow:0 12px 30px rgba(20,24,30,.18);opacity:0;visibility:hidden;transform:translateY(6px) scale(.96);transform-origin:left bottom;transition:opacity .15s,transform .15s,visibility .15s}
.rkjc-react__bar::after{content:"";position:absolute;left:-10px;right:-10px;top:100%;height:16px}
.rkjc-react.is-open .rkjc-react__bar,.rkjc-react:focus-within .rkjc-react__bar{opacity:1;visibility:visible;transform:none}
.rkjc-react__opt{appearance:none;border:0;background:transparent;width:38px;height:38px;border-radius:50%;font-size:1.5rem;line-height:1;cursor:pointer;transition:transform .12s,background .12s;font-family:"Apple Color Emoji","Segoe UI Emoji","Noto Color Emoji",sans-serif}
.rkjc-react__opt:hover{transform:translateY(-5px) scale(1.35)}
.rkjc-react__opt.is-mine{background:var(--rkjc-accent-soft,rgba(107,127,149,.14))}
.rkjc-react.is-busy{opacity:.6;pointer-events:none}
@media (hover:none){.rkjc-react:hover .rkjc-react__bar{opacity:0;visibility:hidden}.rkjc-react.is-open .rkjc-react__bar{opacity:1;visibility:visible;transform:none}}
CSS;

		$js = <<<JS
(function(){
	var URL='$url', KEY='rkjc_vid', ERR='$msgErr';
	function token(){ var t=''; try{t=localStorage.getItem(KEY)||'';}catch(e){} if(!t){ var m=document.cookie.match(/(?:^|; )rkjc_vid=([a-f0-9]{32})/); t=m?m[1]:''; } if(!t){ t=''; for(var i=0;i<32;i++){t+=Math.floor(Math.random()*16).toString(16);} } try{localStorage.setItem(KEY,t);}catch(e){} try{document.cookie=KEY+'='+t+';path=/;max-age=31536000;SameSite=Lax';}catch(e){} return t; }
	token();
	function closeAll(except){ document.querySelectorAll('.rkjc-react.is-open').forEach(function(el){ if(el!==except){ el.classList.remove('is-open'); var b=el.querySelector('.rkjc-react__btn'); if(b){b.setAttribute('aria-expanded','false');} } }); }
	function send(box, reaction){
		var cid=box.getAttribute('data-rkjc-react');
		box.classList.add('is-busy');
		var fd=new FormData(); fd.append('comment_id',cid); fd.append('reaction',reaction); fd.append('token',token());
		fetch(URL,{method:'POST',body:fd,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}})
			.then(function(r){return r.json();})
			.then(function(j){
				var d=(j&&j.data&&j.data[0])||null;
				if(!d||!d.html){ throw new Error((j&&j.message)||ERR); }
				var tmp=document.createElement('div'); tmp.innerHTML=d.html;
				var nu=tmp.firstElementChild; box.replaceWith(nu);
			})
			.catch(function(e){ box.classList.remove('is-busy'); closeAll(); if(window.console){console.warn('rkreactions',e);} window.alert(ERR+' ('+(e&&e.message?e.message:'')+')'); });
	}
	document.addEventListener('click',function(e){
		var opt=e.target.closest('.rkjc-react__opt');
		if(opt){ e.preventDefault(); var box=opt.closest('.rkjc-react'); var r=opt.getAttribute('data-r'); send(box, box.getAttribute('data-mine')===r?'':r); return; }
		var btn=e.target.closest('.rkjc-react__btn');
		if(btn){ e.preventDefault(); var bx=btn.closest('.rkjc-react'); var mine=bx.getAttribute('data-mine');
			if(mine){ send(bx,''); return; }
			var open=bx.classList.toggle('is-open'); btn.setAttribute('aria-expanded',open?'true':'false'); closeAll(bx); return; }
		if(!e.target.closest('.rkjc-react')){ closeAll(); }
	});
	document.addEventListener('keydown',function(e){ if(e.key==='Escape'){ closeAll(); } });
	// mouse: open on hover, close half a second after the pointer leaves (so the bar survives the trip to an emoji)
	var timers=new WeakMap();
	document.addEventListener('mouseover',function(e){
		if(!e.target.closest('.rkjc-react__btn, .rkjc-react__bar')){return;}
		var box=e.target.closest('.rkjc-react'); if(!box){return;}
		var t=timers.get(box); if(t){clearTimeout(t); timers.delete(box);}
		if(!box.classList.contains('is-open')){ closeAll(box); box.classList.add('is-open'); var b=box.querySelector('.rkjc-react__btn'); if(b){b.setAttribute('aria-expanded','true');} }
	});
	document.addEventListener('mouseout',function(e){
		var box=e.target.closest('.rkjc-react'); if(!box){return;}
		if(e.relatedTarget && box.contains(e.relatedTarget)){return;}
		timers.set(box,setTimeout(function(){ box.classList.remove('is-open'); var b=box.querySelector('.rkjc-react__btn'); if(b){b.setAttribute('aria-expanded','false');} },500));
	});
})();
JS;

		try
		{
			$wa->addInlineStyle($css, ['name' => 'plg_jcomments_rkreactions.css']);
			$wa->addInlineScript($js, ['name' => 'plg_jcomments_rkreactions.js'], ['defer' => true]);
		}
		catch (\Throwable $e)
		{
			// AJAX/feed document: nothing to add
		}
	}

	/* ------------------------------------------------------------------ events */

	/**
	 * List rendering: load everything in one query, attach HTML to each comment.
	 */
	public function onJCommentsCommentsPrepare(array &$rows)
	{
		if (!$rows)
		{
			return;
		}

		$ids = [];

		foreach ($rows as $row)
		{
			if (!empty($row->id) && empty($row->deleted))
			{
				$ids[] = (int) $row->id;
			}
		}

		$visitor = $this->visitorKey((string) Factory::getApplication()->getInput()->cookie->get('rkjc_vid', '', 'string'));
		$data    = $this->load($ids, $visitor);
		$hide    = (int) $this->params->get('hide_vote', 1) === 1;

		foreach ($rows as $row)
		{
			if (empty($row->id) || !empty($row->deleted))
			{
				continue;
			}

			$cid = (int) $row->id;
			$row->rkReactionsHtml = $this->render($cid, $data['counts'][$cid] ?? [], $data['names'][$cid] ?? [], $data['mine'][$cid] ?? '');
			$row->rkHideVote      = $hide;
			$row->rkReactionsDone = true;
		}

		$this->addAssets();
	}

	/**
	 * Single comment rendering (after add, after edit, permalink).
	 */
	public function onJCommentsCommentAfterPrepare(&$comment)
	{
		if (!empty($comment->rkReactionsDone) || empty($comment->id) || !empty($comment->deleted))
		{
			return;
		}

		$cid  = (int) $comment->id;
		$data = $this->load([$cid], '');
		$comment->rkReactionsHtml = $this->render($cid, $data['counts'][$cid] ?? [], $data['names'][$cid] ?? [], '');
		$comment->rkHideVote      = (int) $this->params->get('hide_vote', 1) === 1;
		$this->addAssets();
	}

	public function onJCommentsCommentAfterDelete(&$comment)
	{
		if (empty($comment->id))
		{
			return;
		}

		$this->ensureTable();
		$db = $this->db();

		try
		{
			$db->setQuery('DELETE FROM ' . $db->quoteName('#__jcomments_rkreactions') . ' WHERE comment_id = ' . (int) $comment->id)->execute();
		}
		catch (\RuntimeException $e)
		{
			Log::add($e->getMessage(), Log::ERROR, 'plg_jcomments_rkreactions');
		}
	}

	/**
	 * com_ajax endpoint: set / change / remove the visitor's reaction, return fresh HTML.
	 */
	public function onAjaxRkreactions()
	{
		$app   = Factory::getApplication();
		$input = $app->getInput();
		$app->allowCache(false);
		$app->setHeader('X-LiteSpeed-Cache-Control', 'no-cache', true);
		$app->setHeader('Cache-Control', 'no-store', true);

		$cid      = $input->post->getInt('comment_id', 0);
		$reaction = mb_substr(trim((string) $input->post->getString('reaction', '')), 0, 8);
		$token    = (string) $input->post->getString('token', '');
		$user     = $app->getIdentity();

		if ($cid <= 0)
		{
			throw new \InvalidArgumentException(Text::_('PLG_JCOMMENTS_RKREACTIONS_ERROR'), 400);
		}

		if (!$user->id && !(int) $this->params->get('guests', 1))
		{
			throw new \RuntimeException(Text::_('PLG_JCOMMENTS_RKREACTIONS_LOGIN'), 403);
		}

		$visitor = $this->visitorKey($token);

		if ($visitor === '')
		{
			throw new \InvalidArgumentException(Text::_('PLG_JCOMMENTS_RKREACTIONS_TOKEN'), 400);
		}

		$reactions = $this->reactions();

		if ($reaction !== '' && !isset($reactions[$reaction]))
		{
			throw new \InvalidArgumentException(Text::_('PLG_JCOMMENTS_RKREACTIONS_BAD'), 400);
		}

		$this->ensureTable();
		$db = $this->db();

		// the comment must exist and be published
		// the comment must exist; guests may react only to published ones, members (moderators) to any
		$row = $db->setQuery('SELECT published, deleted FROM ' . $db->quoteName('#__jcomments') . ' WHERE id = ' . $cid)->loadAssoc();

		if (!$row || (int) $row['deleted'] === 1 || ((int) $row['published'] !== 1 && !$user->id))
		{
			throw new \RuntimeException(Text::_('PLG_JCOMMENTS_RKREACTIONS_NOT_FOUND'), 404);
		}

		try
		{
			if ($reaction === '')
			{
				$db->setQuery('DELETE FROM ' . $db->quoteName('#__jcomments_rkreactions') . ' WHERE comment_id = ' . $cid . ' AND visitor = ' . $db->quote($visitor))->execute();
			}
			else
			{
				$name = $user->id ? (string) $user->name : '';
				$ip   = (string) ($input->server->getString('REMOTE_ADDR', '') ?: '');
				$now  = Factory::getDate()->toSql();
				$db->setQuery(
					'INSERT INTO ' . $db->quoteName('#__jcomments_rkreactions') . ' (comment_id, reaction, visitor, userid, name, ip, date) VALUES ('
					. $cid . ', ' . $db->quote($reaction) . ', ' . $db->quote($visitor) . ', ' . (int) $user->id . ', ' . $db->quote($name) . ', ' . $db->quote($ip) . ', ' . $db->quote($now) . ')'
					. ' ON DUPLICATE KEY UPDATE reaction = VALUES(reaction), date = VALUES(date), ip = VALUES(ip)'
				)->execute();
			}
		}
		catch (\RuntimeException $e)
		{
			Log::add($e->getMessage(), Log::ERROR, 'plg_jcomments_rkreactions');
			throw new \RuntimeException(Text::_('PLG_JCOMMENTS_RKREACTIONS_ERROR'), 500);
		}

		$data = $this->load([$cid], $visitor);

		return [
			'comment_id' => $cid,
			'mine'       => $data['mine'][$cid] ?? '',
			'counts'     => $data['counts'][$cid] ?? [],
			'html'       => $this->render($cid, $data['counts'][$cid] ?? [], $data['names'][$cid] ?? [], $data['mine'][$cid] ?? ''),
		];
	}
}
