<?php
/**
 * RK add-ons for JComments: avatar gallery for guests and registered users.
 *
 * The visitor picks one of the avatars from images/jcomment/avatars while writing a comment.
 * The choice is stored in #__jcomments_rkavatar (comment_id, avatar) and rendered through
 * the standard onPrepareAvatars event, so the JComments core is never patched.
 * Comments without a choice get a stable avatar derived from the author name.
 *
 * @package   plg_jcomments_rkavatar
 * @copyright 2026 Radnička Klasa
 * @license   GNU/GPL v2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\ParameterType;

class PlgJcommentsRkavatar extends CMSPlugin
{
	protected $autoloadLanguage = true;

	/** @var array|null Cached list of gallery file names */
	private $gallery = null;

	/** @var bool Whether the inline script for the picker has already been queued */
	private static $scriptAdded = false;

	/* ------------------------------------------------------------------ helpers */

	private function galleryPath(): string
	{
		$path = trim((string) $this->params->get('gallery_path', 'images/jcomment/avatars'), "/\\ ");

		return $path !== '' ? $path : 'images/jcomment/avatars';
	}

	/**
	 * File names available in the gallery folder, naturally sorted (a01, a02, ...).
	 */
	private function gallery(): array
	{
		if ($this->gallery === null)
		{
			$this->gallery = [];
			$dir = JPATH_ROOT . '/' . $this->galleryPath();

			if (is_dir($dir))
			{
				foreach (scandir($dir) ?: [] as $file)
				{
					if (preg_match('/^[a-z0-9_-]+\.(png|jpe?g|webp|svg)$/i', $file))
					{
						$this->gallery[] = $file;
					}
				}

				sort($this->gallery, SORT_NATURAL | SORT_FLAG_CASE);
			}
		}

		return $this->gallery;
	}

	private function url(string $file): string
	{
		return Uri::root() . $this->galleryPath() . '/' . $file;
	}

	/**
	 * Stable fallback: same author name (or user id) always gets the same avatar.
	 */
	private function fallback(object $comment): string
	{
		$gallery = $this->gallery();

		if (!$gallery)
		{
			return '';
		}

		$key = !empty($comment->userid) ? 'u' . (int) $comment->userid : mb_strtolower(trim((string) ($comment->name ?? '')));

		return $gallery[crc32($key) % count($gallery)];
	}

	private function ensureTable(): void
	{
		static $done = false;

		if ($done)
		{
			return;
		}

		$done = true;
		$db   = Factory::getContainer()->get('DatabaseDriver');

		try
		{
			$db->setQuery(
				'CREATE TABLE IF NOT EXISTS ' . $db->quoteName('#__jcomments_rkavatar') . ' ('
				. $db->quoteName('comment_id') . ' INT UNSIGNED NOT NULL, '
				. $db->quoteName('avatar') . " VARCHAR(64) NOT NULL DEFAULT '', "
				. 'PRIMARY KEY (' . $db->quoteName('comment_id') . ')'
				. ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci'
			)->execute();
		}
		catch (\RuntimeException $e)
		{
			Log::add($e->getMessage(), Log::ERROR, 'plg_jcomments_rkavatar');
		}
	}

	/**
	 * Avatar chosen in the submitted form, validated against the gallery ('' when none).
	 */
	private function submittedAvatar(): string
	{
		$file = basename((string) Factory::getApplication()->getInput()->post->getString('rk_avatar', ''));

		return ($file !== '' && in_array($file, $this->gallery(), true)) ? $file : '';
	}

	private function store(int $commentId, string $file): void
	{
		if ($commentId <= 0)
		{
			return;
		}

		$this->ensureTable();
		$db = Factory::getContainer()->get('DatabaseDriver');

		try
		{
			if ($file === '')
			{
				$query = $db->getQuery(true)
					->delete($db->quoteName('#__jcomments_rkavatar'))
					->where($db->quoteName('comment_id') . ' = :id')
					->bind(':id', $commentId, ParameterType::INTEGER);
			}
			else
			{
				$query = 'REPLACE INTO ' . $db->quoteName('#__jcomments_rkavatar')
					. ' (' . $db->quoteName('comment_id') . ', ' . $db->quoteName('avatar') . ') VALUES ('
					. (int) $commentId . ', ' . $db->quote($file) . ')';
			}

			$db->setQuery($query)->execute();
		}
		catch (\RuntimeException $e)
		{
			Log::add($e->getMessage(), Log::ERROR, 'plg_jcomments_rkavatar');
		}
	}

	/* ------------------------------------------------------------------ events */

	/**
	 * Called by plg_system_rkjccount on the article page so the picker script is on the page even when the form
	 * arrives through AJAX ("show form on click" with at least one comment). The picker CSS lives in the rk template (style.css).
	 */
	public function onRkjcPageAssets(): void
	{
		if ((int) $this->params->get('show_picker', 1) && $this->gallery())
		{
			$this->addScript();
		}
	}

	/**
	 * Picker markup appended to the comment form (JComments wraps it in a div).
	 */
	public function onJCommentsFormAppend($objectID, $objectGroup)
	{
		if (!(int) $this->params->get('show_picker', 1))
		{
			return '';
		}

		$gallery = $this->gallery();

		if (!$gallery)
		{
			return '';
		}

		$remember = (int) $this->params->get('remember', 1);
		$pick     = htmlspecialchars(Text::_('PLG_JCOMMENTS_RKAVATAR_PICK'), ENT_QUOTES);
		$userIcon = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';

		$html = '<div class="rkjc-avatar-picker" data-rkjc-picker data-remember="' . $remember . '">'
			// Trigger (in the form): preview circle + label
			. '<button type="button" class="rkjc-avatar-trigger" data-rkjc-open aria-haspopup="dialog">'
			. '<span class="rkjc-avatar-trigger__preview">' . $userIcon . '<img src="" alt="" width="40" height="40" hidden></span>'
			. '<span class="rkjc-avatar-trigger__text">'
			. '<span class="rkjc-avatar-trigger__label" data-rkjc-label data-pick="' . $pick . '" data-change="' . htmlspecialchars(Text::_('PLG_JCOMMENTS_RKAVATAR_CHANGE'), ENT_QUOTES) . '">' . Text::_('PLG_JCOMMENTS_RKAVATAR_PICK') . '</span>'
			. '<span class="rkjc-avatar-trigger__hint">' . Text::_('PLG_JCOMMENTS_RKAVATAR_OPTIONAL') . '</span>'
			. '</span>'
			. '</button>'
			. '<input type="hidden" name="rk_avatar" id="comments-form-rk_avatar" value="">'
			// Modal (moved to <body> by the script when opened)
			. '<div class="rkjc-avatar-modal" role="dialog" aria-modal="true" aria-label="' . $pick . '" hidden>'
			. '<div class="rkjc-avatar-modal__backdrop" data-rkjc-close></div>'
			. '<div class="rkjc-avatar-modal__box" tabindex="-1">'
			. '<div class="rkjc-avatar-modal__head">'
			. '<div class="rkjc-avatar-modal__title">' . Text::_('PLG_JCOMMENTS_RKAVATAR_PICK') . '</div>'
			. '<button type="button" class="rkjc-avatar-modal__close" data-rkjc-close aria-label="' . htmlspecialchars(Text::_('JCLOSE'), ENT_QUOTES) . '">&times;</button>'
			. '</div>'
			. '<div class="rkjc-avatar-picker__grid" role="listbox" aria-label="' . $pick . '">';

		foreach ($gallery as $i => $file)
		{
			$html .= '<button type="button" class="rkjc-avatar-opt" role="option" aria-selected="false"'
				. ' data-avatar="' . htmlspecialchars($file, ENT_QUOTES) . '"'
				. ' title="' . htmlspecialchars(Text::sprintf('PLG_JCOMMENTS_RKAVATAR_N', $i + 1), ENT_QUOTES) . '">'
				. '<img src="' . htmlspecialchars($this->url($file), ENT_QUOTES) . '" alt="" width="64" height="64" loading="lazy" decoding="async">'
				. '</button>';
		}

		$html .= '</div>'
			. '<div class="rkjc-avatar-modal__foot">'
			. '<button type="button" class="rkjc-btn rkjc-btn--ghost" data-rkjc-clear>' . Text::_('PLG_JCOMMENTS_RKAVATAR_NONE') . '</button>'
			. '<button type="button" class="rkjc-btn rkjc-btn--primary" data-rkjc-close>' . Text::_('PLG_JCOMMENTS_RKAVATAR_DONE') . '</button>'
			. '</div>'
			. '</div>'
			. '</div>'
			. '</div>';

		$this->addScript();

		return $html;
	}

	/**
	 * Small delegated script: select, remember (localStorage); form reset clears the choice.
	 */
	private function addScript(): void
	{
		if (self::$scriptAdded)
		{
			return;
		}

		self::$scriptAdded = true;

		$js = <<<'JS'
(function(){
	var KEY='rkjc_avatar', openModal=null, lastFocus=null;
	function modalOf(p){
		var m=p._rkModal;
		if(!m){m=p.querySelector('.rkjc-avatar-modal'); if(!m){return null;} p._rkModal=m; m._rkPicker=p;}
		return m;
	}
	function pickerOf(el){
		var p=el.closest('[data-rkjc-picker]');
		if(p){return p;}
		var m=el.closest('.rkjc-avatar-modal');
		return m?m._rkPicker:null;
	}
	function select(p,file,save){
		var m=modalOf(p), input=p.querySelector('input[name="rk_avatar"]'), url='';
		if(m){m.querySelectorAll('.rkjc-avatar-opt').forEach(function(b){
			var on=b.getAttribute('data-avatar')===file;
			b.classList.toggle('is-selected',on);
			b.setAttribute('aria-selected',on?'true':'false');
			if(on){url=b.querySelector('img').src;}
		});}
		if(input){input.value=file||'';}
		var prev=p.querySelector('.rkjc-avatar-trigger__preview img'), lbl=p.querySelector('[data-rkjc-label]');
		if(prev){ if(url){prev.src=url;prev.hidden=false;}else{prev.removeAttribute('src');prev.hidden=true;} }
		p.classList.toggle('has-avatar',!!file);
		if(lbl){lbl.textContent=file?lbl.getAttribute('data-change'):lbl.getAttribute('data-pick');}
		if(save&&p.getAttribute('data-remember')==='1'){
			try{ if(file){localStorage.setItem(KEY,file);}else{localStorage.removeItem(KEY);} }catch(e){}
		}
	}
	function restore(p){
		if(p.getAttribute('data-remember')!=='1'){return;}
		var file='', m=modalOf(p);
		try{file=localStorage.getItem(KEY)||'';}catch(e){}
		if(file&&m&&m.querySelector('.rkjc-avatar-opt[data-avatar="'+file+'"]')){select(p,file,false);}
	}
	function open(p){
		var m=modalOf(p); if(!m){return;}
		if(m.parentNode!==document.body){document.body.appendChild(m);}
		lastFocus=document.activeElement;
		m.hidden=false; openModal=m;
		document.documentElement.classList.add('rkjc-modal-open');
		requestAnimationFrame(function(){m.classList.add('is-open'); var f=m.querySelector('.is-selected')||m.querySelector('.rkjc-avatar-opt'); if(f){f.focus();}});
	}
	function close(){
		var m=openModal; if(!m){return;}
		m.classList.remove('is-open'); openModal=null;
		document.documentElement.classList.remove('rkjc-modal-open');
		setTimeout(function(){m.hidden=true;},180);
		if(lastFocus&&lastFocus.focus){lastFocus.focus();}
	}
	document.addEventListener('click',function(e){
		var t=e.target;
		var o=t.closest('[data-rkjc-open]'); if(o){e.preventDefault();open(pickerOf(o));return;}
		var c=t.closest('[data-rkjc-close]'); if(c){e.preventDefault();close();return;}
		var x=t.closest('[data-rkjc-clear]'); if(x){e.preventDefault();var px=pickerOf(x); if(px){select(px,'',true);} close();return;}
		var b=t.closest('.rkjc-avatar-opt'); if(b){e.preventDefault();var p=pickerOf(b); if(p){select(p,b.getAttribute('data-avatar'),true);} close();}
	});
	document.addEventListener('keydown',function(e){ if(e.key==='Escape'&&openModal){e.preventDefault();close();} });
	document.addEventListener('reset',function(e){
		var f=e.target;
		if(!f||!f.querySelector){return;}
		f.querySelectorAll('[data-rkjc-picker]').forEach(function(p){setTimeout(function(){select(p,'',true);},0);}); // form reset = no avatar
	},true);
	function init(){document.querySelectorAll('[data-rkjc-picker]:not([data-rkjc-init])').forEach(function(p){p.setAttribute('data-rkjc-init','1');restore(p);});}
	if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',init);}else{init();}
	new MutationObserver(init).observe(document.documentElement,{childList:true,subtree:true});
})();
JS;

		try
		{
			Factory::getApplication()->getDocument()->getWebAssetManager()->addInlineScript($js, ['name' => 'plg_jcomments_rkavatar.picker']);
		}
		catch (\Throwable $e)
		{
			// Non-HTML document (AJAX): the picker still works once the page script is present.
		}
	}

	public function onJCommentsCommentAfterAdd(&$comment)
	{
		$this->store((int) ($comment->id ?? 0), $this->submittedAvatar());
	}

	public function onJCommentsCommentAfterChange(&$comment)
	{
		$file = $this->submittedAvatar();

		// Editing without touching the picker keeps the stored avatar.
		if ($file !== '')
		{
			$this->store((int) ($comment->id ?? 0), $file);
		}
	}

	public function onJCommentsCommentAfterDelete(&$comment)
	{
		$this->store((int) ($comment->id ?? 0), '');
	}

	public function onPrepareAvatar(&$comment)
	{
		$comments    = [];
		$comments[0] = &$comment;
		$this->onPrepareAvatars($comments);
	}

	public function onPrepareAvatars(array &$comments)
	{
		if (!$comments)
		{
			return;
		}

		$ids = [];

		foreach ($comments as $comment)
		{
			if (!empty($comment->id))
			{
				$ids[] = (int) $comment->id;
			}
		}

		$map = [];

		if ($ids)
		{
			$this->ensureTable();
			$db = Factory::getContainer()->get('DatabaseDriver');

			try
			{
				$query = $db->getQuery(true)
					->select($db->quoteName(['comment_id', 'avatar']))
					->from($db->quoteName('#__jcomments_rkavatar'))
					->whereIn($db->quoteName('comment_id'), $ids);
				$map = $db->setQuery($query)->loadAssocList('comment_id', 'avatar') ?: [];
			}
			catch (\RuntimeException $e)
			{
				Log::add($e->getMessage(), Log::ERROR, 'plg_jcomments_rkavatar');
			}
		}

		$gallery = $this->gallery();

		foreach ($comments as $comment)
		{
			$file = $map[(int) ($comment->id ?? 0)] ?? '';

			if ($file === '' || !in_array($file, $gallery, true))
			{
				$file = $this->fallback($comment);
			}

			$comment->profileLink       = '';
			$comment->profileLinkTarget = '';
			$comment->rkAvatarFile      = $file;
			$comment->avatar            = $file !== '' ? $this->url($file) : '';
		}
	}
}
