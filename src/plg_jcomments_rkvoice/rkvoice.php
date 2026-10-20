<?php
/**
 * RK add-ons for JComments: voice comments.
 *
 * The visitor records a short clip in the browser (MediaRecorder), it is uploaded ahead of the comment
 * through com_ajax and parked as a temporary file; when the comment is stored (onJCommentsCommentAfterAdd)
 * the clip is attached to it: images/jcomment/voice/<comment id>.<ext> + row in #__jcomments_rkvoice.
 * The comment goes through the normal moderation; the clip shows only with a published comment.
 * The rk template prints $comment->rkVoiceHtml above the text. No core changes.
 *
 * @package   plg_jcomments_rkvoice
 * @copyright 2026 Radnička Klasa
 * @license   GNU/GPL v2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;

class PlgJcommentsRkvoice extends CMSPlugin
{
	protected $autoloadLanguage = true;

	private static $cssAdded = false;

	private static $jsAdded = false;

	/** Allowed containers: mime => extension */
	private const TYPES = [
		'audio/webm' => 'webm',
		'video/webm' => 'webm',
		'audio/ogg'  => 'ogg',
		'audio/mp4'  => 'm4a',
		'video/mp4'  => 'm4a',
		'audio/mpeg' => 'mp3',
		'audio/wav'  => 'wav',
		'audio/x-wav' => 'wav',
	];

	/* ------------------------------------------------------------------ helpers */

	private function dir(): string
	{
		return trim((string) $this->params->get('folder', 'images/jcomment/voice'), "/\\ ") ?: 'images/jcomment/voice';
	}

	private function path(string $sub = ''): string
	{
		$p = JPATH_ROOT . '/' . $this->dir() . ($sub !== '' ? '/' . $sub : '');

		if (!is_dir($p))
		{
			@mkdir($p, 0755, true);
			@file_put_contents($p . '/index.html', '<!DOCTYPE html><title></title>');
		}

		return $p;
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

		try
		{
			$db = $this->db();
			$db->setQuery(
				'CREATE TABLE IF NOT EXISTS ' . $db->quoteName('#__jcomments_rkvoice') . ' ('
				. '`comment_id` INT UNSIGNED NOT NULL, `file` VARCHAR(64) NOT NULL, `mime` VARCHAR(32) NOT NULL DEFAULT \'\', '
				. '`duration` DECIMAL(6,1) NOT NULL DEFAULT 0, `size` INT UNSIGNED NOT NULL DEFAULT 0, `transcript` TEXT NULL, `date` DATETIME NOT NULL, '
				. 'PRIMARY KEY (`comment_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci'
			)->execute();
		}
		catch (\RuntimeException $e)
		{
			Log::add($e->getMessage(), Log::ERROR, 'plg_jcomments_rkvoice');
		}
	}

	private function canRecord(): bool
	{
		$user = Factory::getApplication()->getIdentity();

		return ($user && $user->id) || (int) $this->params->get('guests', 1) === 1;
	}

	/** Remove temp clips older than a day. */
	private function sweepTemp(): void
	{
		$tmp = $this->path('tmp');

		foreach (glob($tmp . '/*.*') ?: [] as $f)
		{
			if (basename($f) !== 'index.html' && filemtime($f) < time() - 86400)
			{
				@unlink($f);
			}
		}
	}

	/** @return array<int, array{file:string,mime:string,duration:float,transcript:?string}> */
	private function load(array $ids): array
	{
		$ids = array_values(array_unique(array_map('intval', $ids)));

		if (!$ids)
		{
			return [];
		}

		$this->ensureTable();

		try
		{
			$db  = $this->db();
			$sql = 'SELECT comment_id, file, mime, duration, transcript FROM ' . $db->quoteName('#__jcomments_rkvoice') . ' WHERE comment_id IN (' . implode(',', $ids) . ')';
			$out = [];

			foreach ($db->setQuery($sql)->loadAssocList() ?: [] as $r)
			{
				$out[(int) $r['comment_id']] = $r;
			}

			return $out;
		}
		catch (\RuntimeException $e)
		{
			Log::add($e->getMessage(), Log::ERROR, 'plg_jcomments_rkvoice');

			return [];
		}
	}

	private function render(array $v): string
	{
		$src = Uri::root() . $this->dir() . '/' . rawurlencode($v['file']);
		$dur = (float) $v['duration'];
		$len = $dur > 0 ? sprintf('%d:%02d', floor($dur / 60), (int) $dur % 60) : '';
		$html = '<div class="rkjc-voice">'
			. '<span class="rkjc-voice__ico" aria-hidden="true">🎤</span>'
			. '<span class="rkjc-voice__label">' . Text::_('PLG_JCOMMENTS_RKVOICE_LABEL') . ($len !== '' ? ' <span class="rkjc-voice__len">' . $len . '</span>' : '') . '</span>'
			. '<audio class="rkjc-voice__player" controls preload="none" src="' . htmlspecialchars($src, ENT_QUOTES) . '"></audio>';

		if (!empty($v['transcript']))
		{
			$html .= '<details class="rkjc-voice__tr"><summary>' . Text::_('PLG_JCOMMENTS_RKVOICE_TRANSCRIPT') . '</summary><p>' . nl2br(htmlspecialchars($v['transcript'])) . '</p></details>';
		}

		return $html . '</div>';
	}

	private function addAssets(bool $withRecorder): void
	{
		if (self::$cssAdded && (!$withRecorder || self::$jsAdded))
		{
			return;
		}

		try
		{
			$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
		}
		catch (\Throwable $e)
		{
			return;
		}

		$css = <<<'CSS'
.rkjc-voice{display:flex;flex-wrap:wrap;align-items:center;gap:6px 10px;margin:0 0 10px;padding:10px 12px;background:var(--rkjc-accent-soft,rgba(107,127,149,.14));border:1px solid var(--rkjc-accent,#6b7f95);border-radius:12px}
.rkjc-voice__ico{font-size:1.1rem}
.rkjc-voice__label{font-size:.78rem;font-weight:700;letter-spacing:.03em;text-transform:uppercase;color:var(--rkjc-accent-ink,#4a5d70)}
.rkjc-voice__len{font-weight:600;color:var(--rkjc-muted,#8a8f98);margin-left:4px}
.rkjc-voice__player{flex:1 1 220px;height:36px;max-width:100%}
.rkjc-voice__tr{flex-basis:100%;font-size:.88rem}
.rkjc-voice__tr summary{cursor:pointer;color:var(--rkjc-accent-ink,#4a5d70);font-weight:600}
.rkjc-voice__tr p{margin:6px 0 0;color:var(--rkjc-text,#3b3f46)}
.rkjc-rec{display:flex;flex-wrap:wrap;align-items:center;gap:10px;padding:10px 12px;background:var(--rkjc-bg,#fff);border:1px dashed var(--rkjc-line,rgba(37,45,55,.22));border-radius:12px}
.rkjc-rec__btn{appearance:none;display:inline-flex;align-items:center;gap:8px;padding:9px 16px;border-radius:999px;border:1px solid var(--rkjc-line,rgba(37,45,55,.12));background:var(--rkjc-soft,#f7f8fa);color:var(--rkjc-dark,#252d37);font:inherit;font-size:.86rem;font-weight:700;cursor:pointer;transition:all .15s}
.rkjc-rec__btn:hover{border-color:var(--rkjc-accent,#6b7f95);background:var(--rkjc-accent-soft,rgba(107,127,149,.14))}
.rkjc-rec.is-recording .rkjc-rec__btn--start{background:var(--rkjc-danger,#c0392b);border-color:var(--rkjc-danger,#c0392b);color:#fff}
.rkjc-rec__dot{width:10px;height:10px;border-radius:50%;background:var(--rkjc-danger,#c0392b);display:none}
.rkjc-rec.is-recording .rkjc-rec__dot{display:inline-block;animation:rkjc-blink 1s infinite}
@keyframes rkjc-blink{50%{opacity:.2}}
.rkjc-rec__time{font-variant-numeric:tabular-nums;font-size:.86rem;color:var(--rkjc-muted,#8a8f98);min-width:44px}
.rkjc-rec__player{height:36px;flex:1 1 200px;max-width:100%;display:none}
.rkjc-rec.has-clip .rkjc-rec__player{display:block}
.rkjc-rec__del{appearance:none;border:1px solid var(--rkjc-line,rgba(37,45,55,.12));background:transparent;color:var(--rkjc-muted,#8a8f98);border-radius:999px;padding:8px 12px;font:inherit;font-size:.8rem;cursor:pointer;display:none}
.rkjc-rec.has-clip .rkjc-rec__del{display:inline-flex}
.rkjc-rec__hint{flex-basis:100%;font-size:.76rem;color:var(--rkjc-muted,#8a8f98)}
.rkjc-rec__hint.is-err{color:var(--rkjc-danger,#c0392b)}
.rkjc-rec.is-busy{opacity:.6;pointer-events:none}
.rkjc-rec__help{flex-basis:100%;display:none;margin-top:4px;padding:12px 14px;border-radius:10px;background:#fff6f5;border:1px solid rgba(192,57,43,.35);color:var(--rkjc-text,#3b3f46);font-size:.86rem;line-height:1.5}
.rkjc-rec__help.is-open{display:block}
.rkjc-rec__help strong{display:block;color:var(--rkjc-danger,#c0392b);margin-bottom:4px}
.rkjc-rec__help ol{margin:6px 0 0 18px;padding:0}
.rkjc-rec__help li{margin:2px 0}
.rkjc-rec__help button{appearance:none;border:1px solid var(--rkjc-line,rgba(37,45,55,.12));background:var(--rkjc-bg,#fff);border-radius:999px;padding:6px 12px;font:inherit;font-size:.8rem;cursor:pointer;margin-top:8px}
CSS;

		if (!self::$cssAdded)
		{
			self::$cssAdded = true;

			try
			{
				$wa->addInlineStyle($css, ['name' => 'plg_jcomments_rkvoice.css']);
			}
			catch (\Throwable $e)
			{
			}
		}

		if (!$withRecorder || self::$jsAdded)
		{
			return;
		}

		self::$jsAdded = true;

		$url   = Uri::root(true) . '/index.php?option=com_ajax&group=jcomments&plugin=rkvoice&format=json';
		$max   = (int) $this->params->get('max_seconds', 60);
		$maxKb = (int) $this->params->get('max_kb', 2048);
		$txt   = json_encode([
			'start'   => Text::_('PLG_JCOMMENTS_RKVOICE_START'),
			'stop'    => Text::_('PLG_JCOMMENTS_RKVOICE_STOP'),
			'again'   => Text::_('PLG_JCOMMENTS_RKVOICE_AGAIN'),
			'nosup'   => Text::_('PLG_JCOMMENTS_RKVOICE_NOSUPPORT'),
			'denied'  => Text::_('PLG_JCOMMENTS_RKVOICE_DENIED'),
			'uploading' => Text::_('PLG_JCOMMENTS_RKVOICE_UPLOADING'),
			'ready'   => Text::_('PLG_JCOMMENTS_RKVOICE_READY'),
			'toolong' => Text::sprintf('PLG_JCOMMENTS_RKVOICE_TOOLONG', $max),
			'err'     => Text::_('PLG_JCOMMENTS_RKVOICE_ERROR'),
			'filler'  => Text::_('PLG_JCOMMENTS_RKVOICE_FILLER'),
			'helpTitle' => Text::_('PLG_JCOMMENTS_RKVOICE_HELP_TITLE'),
			'helpDenied' => Text::_('PLG_JCOMMENTS_RKVOICE_HELP_DENIED'),
			'helpNoMic' => Text::_('PLG_JCOMMENTS_RKVOICE_HELP_NOMIC'),
			'helpBusy' => Text::_('PLG_JCOMMENTS_RKVOICE_HELP_BUSY'),
			'helpHttps' => Text::_('PLG_JCOMMENTS_RKVOICE_HELP_HTTPS'),
			'helpSteps' => Text::_('PLG_JCOMMENTS_RKVOICE_HELP_STEPS'),
			'close' => Text::_('JCLOSE'),
		], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

		$js = <<<JS
(function(){
	var URL='$url', MAX=$max, MAXKB=$maxKb, T=$txt;
	function fmt(s){ s=Math.floor(s); return Math.floor(s/60)+':'+('0'+(s%60)).slice(-2); }
	function pickMime(){ var c=['audio/webm;codecs=opus','audio/webm','audio/mp4','audio/ogg;codecs=opus','audio/ogg']; for(var i=0;i<c.length;i++){ if(window.MediaRecorder&&MediaRecorder.isTypeSupported(c[i])){return c[i];} } return ''; }
	function init(box){
		if(box.hasAttribute('data-rk-init')){return;} box.setAttribute('data-rk-init','1');
		var start=box.querySelector('.rkjc-rec__btn--start'), del=box.querySelector('.rkjc-rec__del'), time=box.querySelector('.rkjc-rec__time'), hint=box.querySelector('.rkjc-rec__hint'), player=box.querySelector('.rkjc-rec__player'), input=box.querySelector('input[name="rk_voice"]');
		var form=box.closest('form'), ta=form?form.querySelector('#comments-form-comment'):null;
		var rec=null, chunks=[], t0=0, timer=null, stream=null, blob=null;
		function say(m,err){ hint.textContent=m||''; hint.classList.toggle('is-err',!!err); }
		var helpBox=box.querySelector('.rkjc-rec__help');
		function help(why, steps){ if(!helpBox){return;} helpBox.innerHTML='<strong>'+T.helpTitle+'</strong>'+why+(steps?'<ol>'+T.helpSteps+'</ol>':'')+'<button type="button">'+T.close+'</button>'; helpBox.classList.add('is-open'); helpBox.querySelector('button').onclick=function(){helpBox.classList.remove('is-open');}; }
		if(!window.MediaRecorder||!navigator.mediaDevices||!navigator.mediaDevices.getUserMedia){ say(T.nosup,true); start.disabled=true; help(window.isSecureContext?T.nosup:T.helpHttps,false); return; }
		function reset(){ blob=null; input.value=''; player.removeAttribute('src'); box.classList.remove('has-clip'); time.textContent='0:00'; start.querySelector('span:last-child').textContent=T.start; say(''); if(ta&&ta.value.trim()===T.filler){ta.value='';} }
		function stop(){ if(rec&&rec.state!=='inactive'){rec.stop();} if(stream){stream.getTracks().forEach(function(t){t.stop();});} clearInterval(timer); box.classList.remove('is-recording'); }
		function upload(b, secs){
			box.classList.add('is-busy'); say(T.uploading);
			var fd=new FormData(); fd.append('clip', b, 'voice.'+(b.type.indexOf('mp4')>-1?'m4a':(b.type.indexOf('ogg')>-1?'ogg':'webm'))); fd.append('duration', secs.toFixed(1));
			fetch(URL,{method:'POST',body:fd,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){return r.json();}).then(function(j){
				var d=j&&j.data&&j.data[0]; if(!d||!d.token){ throw new Error((j&&j.message)||T.err); }
				input.value=d.token; box.classList.remove('is-busy'); say(T.ready);
				if(ta&&ta.value.trim()===''){ ta.value=T.filler; ta.dispatchEvent(new Event('input',{bubbles:true})); ta.dispatchEvent(new KeyboardEvent('keyup',{bubbles:true})); }
			}).catch(function(e){ box.classList.remove('is-busy'); say((e&&e.message)||T.err,true); input.value=''; });
		}
		start.addEventListener('click',function(){
			if(box.classList.contains('is-recording')){ stop(); return; }
			if(blob){ reset(); }
			navigator.mediaDevices.getUserMedia({audio:true}).then(function(s){
				stream=s; chunks=[]; var mime=pickMime(); rec=mime?new MediaRecorder(s,{mimeType:mime}):new MediaRecorder(s);
				rec.ondataavailable=function(e){ if(e.data&&e.data.size){chunks.push(e.data);} };
				rec.onstop=function(){
					blob=new Blob(chunks,{type:rec.mimeType||mime||'audio/webm'}); var secs=(Date.now()-t0)/1000;
					if(blob.size>MAXKB*1024){ say(T.err+' ('+Math.round(blob.size/1024)+' KB)',true); return; }
					player.src=window.URL.createObjectURL(blob); box.classList.add('has-clip'); start.querySelector('span:last-child').textContent=T.again;
					upload(blob, Math.min(secs,MAX));
				};
				rec.start(250); t0=Date.now(); box.classList.add('is-recording'); start.querySelector('span:last-child').textContent=T.stop; say('');
				timer=setInterval(function(){ var s=(Date.now()-t0)/1000; time.textContent=fmt(s); if(s>=MAX){ stop(); say(T.toolong); } },200);
			}).catch(function(err){ var name=(err&&err.name)||''; var why=T.helpDenied; if(name==='NotFoundError'||name==='DevicesNotFoundError'){why=T.helpNoMic;} else if(name==='NotReadableError'||name==='TrackStartError'){why=T.helpBusy;} else if(!window.isSecureContext){why=T.helpHttps;} say(T.denied,true); help(why, name==='NotAllowedError'||name==='PermissionDeniedError'||name===''); });
		});
		del.addEventListener('click',function(){ stop(); reset(); });
		if(form){ form.addEventListener('reset',function(){ setTimeout(function(){ stop(); reset(); },0); }); }
	}
	function scan(){ document.querySelectorAll('.rkjc-rec').forEach(init); }
	if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',scan);}else{scan();}
	new MutationObserver(scan).observe(document.documentElement,{childList:true,subtree:true});
})();
JS;

		try
		{
			$wa->addInlineScript($js, ['name' => 'plg_jcomments_rkvoice.js'], ['defer' => true]);
		}
		catch (\Throwable $e)
		{
		}
	}

	/* ------------------------------------------------------------------ events */

	/**
	 * Called by plg_system_rkjccount on the article page (onBeforeCompileHead) so the recorder CSS and JS are on the
	 * page even when the form arrives through AJAX ("show form on click" with at least one comment). The script watches the DOM.
	 */
	public function onRkjcPageAssets(): void
	{
		$this->addAssets($this->canRecord());
	}

	/**
	 * Recorder block in the comment form.
	 */
	public function onJCommentsFormAppend($objectID, $objectGroup)
	{
		if (!$this->canRecord())
		{
			return '';
		}

		$this->addAssets(true);
		$max = (int) $this->params->get('max_seconds', 60);

		return '<div class="rkjc-rec" data-rkjc-rec>'
			. '<button type="button" class="rkjc-rec__btn rkjc-rec__btn--start" title="' . htmlspecialchars(Text::_('PLG_JCOMMENTS_RKVOICE_TOOLTIP'), ENT_QUOTES) . '"><span class="rkjc-rec__dot"></span><span>🎤</span><span>' . Text::_('PLG_JCOMMENTS_RKVOICE_START') . '</span></button>'
			. '<span class="rkjc-rec__time">0:00</span>'
			. '<audio class="rkjc-rec__player" controls></audio>'
			. '<button type="button" class="rkjc-rec__del">' . Text::_('PLG_JCOMMENTS_RKVOICE_DELETE') . '</button>'
			. '<input type="hidden" name="rk_voice" id="comments-form-rk_voice" value="">'
			. '<span class="rkjc-rec__hint">' . Text::sprintf('PLG_JCOMMENTS_RKVOICE_HINT', $max) . '</span>'
			. '<div class="rkjc-rec__help" role="alert"></div>'
			. '</div>';
	}

	/**
	 * Upload endpoint: parks the clip in tmp and returns a token.
	 */
	public function onAjaxRkvoice()
	{
		$app   = Factory::getApplication();
		$input = $app->getInput();
		$app->allowCache(false);
		$app->setHeader('X-LiteSpeed-Cache-Control', 'no-cache', true);

		if (!$this->canRecord())
		{
			throw new \RuntimeException(Text::_('PLG_JCOMMENTS_RKVOICE_LOGIN'), 403);
		}

		$file = $input->files->get('clip', null, 'raw');

		if (!$file || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name']) || (int) $file['error'] !== UPLOAD_ERR_OK)
		{
			throw new \InvalidArgumentException(Text::_('PLG_JCOMMENTS_RKVOICE_ERROR'), 400);
		}

		$maxBytes = (int) $this->params->get('max_kb', 2048) * 1024;

		if ((int) $file['size'] > $maxBytes || (int) $file['size'] < 200)
		{
			throw new \InvalidArgumentException(Text::_('PLG_JCOMMENTS_RKVOICE_TOOBIG'), 400);
		}

		// container check by magic bytes (finfo on Windows is unreliable and the declared type is client-controlled)
		$head = (string) @file_get_contents($file['tmp_name'], false, null, 0, 16);
		$mime = '';

		if (strncmp($head, "\x1A\x45\xDF\xA3", 4) === 0)
		{
			$mime = 'audio/webm';
		}
		elseif (strncmp($head, 'OggS', 4) === 0)
		{
			$mime = 'audio/ogg';
		}
		elseif (substr($head, 4, 4) === 'ftyp')
		{
			$mime = 'audio/mp4';
		}
		elseif (strncmp($head, 'ID3', 3) === 0 || (strlen($head) > 1 && ord($head[0]) === 0xFF && (ord($head[1]) & 0xE0) === 0xE0))
		{
			$mime = 'audio/mpeg';
		}
		elseif (strncmp($head, 'RIFF', 4) === 0 && substr($head, 8, 4) === 'WAVE')
		{
			$mime = 'audio/wav';
		}

		if ($mime === '' || !isset(self::TYPES[$mime]))
		{
			throw new \InvalidArgumentException(Text::_('PLG_JCOMMENTS_RKVOICE_BADTYPE'), 400);
		}

		$this->sweepTemp();
		$token = bin2hex(random_bytes(16));
		$ext   = self::TYPES[$mime];
		$dest  = $this->path('tmp') . '/' . $token . '.' . $ext;

		if (!move_uploaded_file($file['tmp_name'], $dest))
		{
			throw new \RuntimeException(Text::_('PLG_JCOMMENTS_RKVOICE_ERROR'), 500);
		}

		$dur = min((float) $input->post->getString('duration', '0'), (float) $this->params->get('max_seconds', 60) + 1);
		@file_put_contents($dest . '.json', json_encode(['mime' => $mime, 'duration' => $dur, 'size' => (int) $file['size'], 'ip' => $input->server->getString('REMOTE_ADDR', '')]));

		return ['token' => $token . '.' . $ext, 'duration' => $dur];
	}

	/**
	 * The comment is stored: attach the parked clip.
	 */
	public function onJCommentsCommentAfterAdd(&$comment)
	{
		$token = basename((string) Factory::getApplication()->getInput()->post->getString('rk_voice', ''));

		if ($token === '' || empty($comment->id) || !preg_match('/^[a-f0-9]{32}\.(webm|ogg|m4a|mp3|wav)$/', $token))
		{
			return;
		}

		$src = $this->path('tmp') . '/' . $token;

		if (!is_file($src))
		{
			return;
		}

		$ext  = pathinfo($token, PATHINFO_EXTENSION);
		$name = (int) $comment->id . '.' . $ext;
		$meta = json_decode((string) @file_get_contents($src . '.json'), true) ?: [];

		if (!@rename($src, $this->path() . '/' . $name))
		{
			Log::add('rkvoice: could not move ' . $token, Log::ERROR, 'plg_jcomments_rkvoice');

			return;
		}

		@unlink($src . '.json');
		$this->ensureTable();

		try
		{
			$db = $this->db();
			$db->setQuery(
				'REPLACE INTO ' . $db->quoteName('#__jcomments_rkvoice') . ' (comment_id, file, mime, duration, size, date) VALUES ('
				. (int) $comment->id . ', ' . $db->quote($name) . ', ' . $db->quote((string) ($meta['mime'] ?? '')) . ', ' . (float) ($meta['duration'] ?? 0) . ', ' . (int) ($meta['size'] ?? 0) . ', ' . $db->quote(Factory::getDate()->toSql()) . ')'
			)->execute();
		}
		catch (\RuntimeException $e)
		{
			Log::add($e->getMessage(), Log::ERROR, 'plg_jcomments_rkvoice');
		}
	}

	public function onJCommentsCommentAfterDelete(&$comment)
	{
		if (empty($comment->id))
		{
			return;
		}

		$v = $this->load([(int) $comment->id]);

		if (isset($v[(int) $comment->id]))
		{
			@unlink($this->path() . '/' . $v[(int) $comment->id]['file']);
		}

		$this->ensureTable();

		try
		{
			$db = $this->db();
			$db->setQuery('DELETE FROM ' . $db->quoteName('#__jcomments_rkvoice') . ' WHERE comment_id = ' . (int) $comment->id)->execute();
		}
		catch (\RuntimeException $e)
		{
		}
	}

	public function onJCommentsCommentsPrepare(array &$rows)
	{
		$ids = [];

		foreach ($rows as $row)
		{
			if (!empty($row->id) && empty($row->deleted))
			{
				$ids[] = (int) $row->id;
			}
		}

		$voices = $this->load($ids);

		if (!$voices)
		{
			return;
		}

		foreach ($rows as $row)
		{
			if (isset($voices[(int) ($row->id ?? 0)]))
			{
				$row->rkVoiceHtml = $this->render($voices[(int) $row->id]);
				$row->rkVoiceDone = true;
			}
		}

		$this->addAssets(false);
	}

	public function onJCommentsCommentAfterPrepare(&$comment)
	{
		if (!empty($comment->rkVoiceDone) || empty($comment->id))
		{
			return;
		}

		$v = $this->load([(int) $comment->id]);

		if (isset($v[(int) $comment->id]))
		{
			$comment->rkVoiceHtml = $this->render($v[(int) $comment->id]);
			$this->addAssets(false);
		}
	}
}
