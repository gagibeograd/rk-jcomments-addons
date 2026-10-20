<?php
/**
 * RK add-ons for JComments: template "rk" (comment form).
 * Lives in components/com_jcomments/tpl/rk/ (or a copy in templates/<site template>/html/com_jcomments/rk/).
 * Element ids and names are the ones jcomments-v4.0.js expects; do not rename them.
 *
 * @package  RK add-ons for JComments
 * @since    1.0.0
 */

defined('_JEXEC') or die;

use Joomla\CMS\Captcha\Captcha;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

class jtt_tpl_form extends JoomlaTuneTemplate
{
	public function render()
	{
		self::assets();

		if ($this->getVar('comments-form-message', 0) == 1)
		{
			$this->getMessage($this->getVar('comments-form-message-text'));

			return;
		}

		$objectID    = $this->getVar('comment-object_id');
		$objectGroup = $this->getVar('comment-object_group');

		if ($this->getVar('comments-form-link', 0) == 1)
		{
			?>
			<div id="comments-form-link" class="rkjc-formlink">
				<a id="addcomments" href="#addcomments" class="rkjc-btn rkjc-btn--primary showform"
				   data-object_id="<?php echo $objectID; ?>" data-object_group="<?php echo $objectGroup; ?>">
					<?php echo $this->icon('comment'); ?><?php echo Text::_('FORM_HEADER'); ?>
				</a>
			</div>
			<?php
			return;
		}
		?>
		<section class="rkjc-form-wrap">
			<?php echo $this->getVar('comments-html-before-form'); ?>

			<a id="addcomments" href="#addcomments"></a>
			<?php // The card is the <form> itself: the core JS moves only the form when replying/editing, so the look travels with it ?>
			<form class="rkjc-form validate" id="comments-form" name="comments-form" action="javascript:void();" autocomplete="off" data-config="<?php echo $this->formConfig(); ?>">
				<h3 class="rkjc-title"><?php echo Text::_('FORM_HEADER'); ?></h3>

				<?php if ($this->getVar('comments-form-policy', 0) == 1): ?>
					<div class="rkjc-policy"><?php echo $this->icon('info'); ?><div><?php echo $this->getVar('comments-policy'); ?></div></div>
				<?php endif; ?>

				<?php $this->getFormFields($this->getVar('comments-form-html-prepend')); ?>

				<div class="rkjc-fields">
					<?php
					$this->field('name', 'comments-form-user-name', 'comments-form-user-name-required', 'FORM_NAME', 'FORM_NAME_REQUIRED', 1, $this->getVar('comment-name-maxlength'));
					$this->field('email', 'comments-form-user-email', 'comments-form-email-required', 'FORM_EMAIL', 'FORM_EMAIL_REQUIRED', 1);
					$this->field('homepage', 'comments-form-user-homepage', 'comments-form-homepage-required', 'FORM_HOMEPAGE', 'FORM_HOMEPAGE_REQUIRED', 0);
					$this->field('title', 'comments-form-title', 'comments-form-title-required', 'FORM_TITLE', 'FORM_TITLE_REQUIRED', 0);
					?>
				</div>

				<?php // Plugins (avatar picker etc.) ?>
				<?php $this->getFormFields($this->getVar('comments-form-html-append')); ?>

				<div class="rkjc-editor">
					<label class="rkjc-sr-only" for="comments-form-comment"><?php echo Text::_('NOTIFICATION_COMMENT_TEXT'); ?></label>
					<textarea class="rkjc-textarea" id="comments-form-comment" name="comment" cols="65" rows="7"
							  placeholder="<?php echo htmlspecialchars(Text::_('NOTIFICATION_COMMENT_TEXT'), ENT_QUOTES); ?>..."></textarea>
				</div>

				<?php if ($this->getVar('comments-form-subscribe', 0) == 1): ?>
					<label class="rkjc-check" for="comments-form-subscribe">
						<input id="comments-form-subscribe" type="checkbox" name="subscribe" value="1"/>
						<span><?php echo Text::_('FORM_SUBSCRIBE'); ?></span>
					</label>
				<?php endif; ?>

				<?php if ($this->getVar('var_show_checkbox_terms_of_use', 0) == 1): ?>
					<label class="rkjc-check" id="checkbox_terms_of_use" for="show_checkbox_terms_of_use">
						<input id="show_checkbox_terms_of_use" type="checkbox" name="name_checkbox_terms_of_use" value="1" required/>
						<span><?php echo $this->getVar('var_tos_text'); ?></span>
					</label>
				<?php endif; ?>

				<?php $this->captcha(); ?>

				<input type="hidden" name="object_id" value="<?php echo $objectID; ?>"/>
				<input type="hidden" name="object_group" value="<?php echo $objectGroup; ?>"/>

				<div id="comments-form-buttons" class="rkjc-form-actions">
					<button class="rkjc-btn rkjc-btn--primary" id="comments-form-send" type="submit"
							title="<?php echo htmlspecialchars(Text::_('FORM_SEND_HINT'), ENT_QUOTES); ?>"><?php echo $this->icon('send'); ?><?php echo Text::_('FORM_SEND'); ?></button>
					<button class="rkjc-btn rkjc-btn--ghost" id="comments-form-reset" type="reset"
							title="<?php echo htmlspecialchars(Text::_('JCLEAR'), ENT_QUOTES); ?>"><?php echo Text::_('JCLEAR'); ?></button>
					<button class="rkjc-btn rkjc-btn--ghost" id="comments-form-cancel" type="button" style="display: none;"
							title="<?php echo htmlspecialchars(Text::_('JCANCEL'), ENT_QUOTES); ?>"><?php echo Text::_('JCANCEL'); ?></button>
				</div>


				<?php echo $this->getVar('comments-html-after-form'); ?>
			</form>
		</section>
		<?php
	}

	/**
	 * Form validation, the emoji picker (rk-emoji.js next to this file) and its texts.
	 * Skipped in AJAX answers (no HTML document); the picker script watches the DOM for a late form.
	 */
	private static function assets()
	{
		static $done = false;

		if ($done)
		{
			return;
		}

		$done = true;

		try
		{
			$app = Factory::getApplication();
			$doc = $app->getDocument();

			if ($doc->getType() !== 'html')
			{
				return;
			}

			$wa = $doc->getWebAssetManager();
			$wa->useScript('form.validate');

			$js = __DIR__ . '/rk-emoji.js';

			if (!is_file($js))
			{
				return;
			}

			$app->getLanguage()->load('files_rkjcomments', JPATH_SITE);

			$keys = [
				'title'          => 'FILES_RKJCOMMENTS_EMOJI_TITLE',
				'search'         => 'FILES_RKJCOMMENTS_EMOJI_SEARCH',
				'searchLabel'    => 'FILES_RKJCOMMENTS_EMOJI_SEARCH_LABEL',
				'recent'         => 'FILES_RKJCOMMENTS_EMOJI_RECENT',
				'results'        => 'FILES_RKJCOMMENTS_EMOJI_RESULTS',
				'noResults'      => 'FILES_RKJCOMMENTS_EMOJI_NO_RESULTS',
				'close'          => 'FILES_RKJCOMMENTS_CLOSE',
				'captchaTimeout' => 'FILES_RKJCOMMENTS_CAPTCHA_TIMEOUT',
			];
			$cats = ['smileys', 'people', 'nature', 'food', 'activities', 'travel', 'objects', 'symbols'];
			$i18n = ['cat' => []];

			foreach ($keys as $k => $key)
			{
				$i18n[$k] = Text::_($key);
			}

			foreach ($cats as $cat)
			{
				$i18n['cat'][$cat] = Text::_('FILES_RKJCOMMENTS_EMOJI_CAT_' . strtoupper($cat));
			}

			$rel = ltrim(str_replace('\\', '/', substr(__DIR__, strlen(JPATH_ROOT))), '/');

			$wa->addInlineScript(
				'window.rkjcI18n = ' . json_encode($i18n, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';',
				['name' => 'rk.jcomments.emoji.i18n']
			);
			$wa->registerAndUseScript('rk.jcomments.emoji', Uri::root(true) . '/' . $rel . '/rk-emoji.js?v=' . filemtime($js), [], ['defer' => true]);
		}
		catch (\Throwable $e)
		{
			// non-HTML document or no asset manager: nothing to add
		}
	}

	/**
	 * One text field of the form (name, email, homepage, title).
	 */
	private function field($name, $showVar, $reqVar, $label, $labelRequired, $showDefault, $maxlength = 0)
	{
		if ($this->getVar($showVar, $showDefault) != 1)
		{
			return;
		}

		$required = $this->getVar($reqVar, 1);
		$text     = $required ? Text::_($labelRequired) : Text::_($label);
		$type     = $name === 'email' ? 'email' : ($name === 'homepage' ? 'url' : 'text');

		// Keep type="text" for validation parity with the core (its JS validates the value itself)
		$type = 'text';
		?>
		<div class="rkjc-field rkjc-field--<?php echo $name; ?>">
			<label class="rkjc-label" for="comments-form-<?php echo $name; ?>"><?php echo $text; ?></label>
			<input class="rkjc-input" id="comments-form-<?php echo $name; ?>" type="<?php echo $type; ?>" name="<?php echo $name; ?>" value=""
				<?php echo $maxlength ? ' maxlength="' . (int) $maxlength . '"' : ''; ?>
				<?php echo $required ? ' required' : ''; ?>
				<?php echo $name === 'email' ? ' inputmode="email"' : ''; ?>/>
		</div>
		<?php
	}

	private function captcha()
	{
		if ($this->getVar('comments-form-captcha', 0) != 1)
		{
			return;
		}

		$html = (!extension_loaded('gd') || !function_exists('imagecreatefrompng')) ? '' : $this->getVar('comments-form-captcha-html');

		if ($html == 'kcaptcha')
		{
			$link = JCommentsFactory::getLink('captcha');
			?>
			<div class="captcha-container rkjc-captcha">
				<img class="captcha" id="comments-form-captcha-image" src="<?php echo $link; ?>" width="121" height="60"
					 alt="<?php echo htmlspecialchars(Text::_('FORM_CAPTCHA'), ENT_QUOTES); ?>"/>
				<div class="rkjc-captcha__side">
					<label class="rkjc-label" for="comments-form-captcha"><?php echo Text::_('FORM_CAPTCHA'); ?></label>
					<input class="rkjc-input captcha" id="comments-form-captcha" type="text" name="captcha_refid" value="" size="5" autocomplete="off" required/>
					<div class="captcha-reload">
						<a href="javascript:void(0);" id="cmd-captcha-reload"><?php echo $this->icon('refresh'); ?><?php echo Text::_('FORM_CAPTCHA_REFRESH'); ?></a>
					</div>
				</div>
			</div>
			<?php
		}
		elseif ($html == 'recaptcha')
		{
			echo '<div class="rkjc-captcha rkjc-captcha--widget">';
			echo Captcha::getInstance($html, array('namespace' => 'jcomments'))->display('recaptcha', 'dynamic_recaptcha_1', 'g-recaptcha');
			echo '</div>';
		}
		elseif ($html == 'recaptcha_invisible')
		{
			echo Captcha::getInstance($html, array('namespace' => 'jcomments'))->display('recaptcha', 'dynamic_recaptcha_invisible_1', 'g-recaptcha');
		}
		elseif ($html == 'hcaptcha')
		{
			echo '<div class="rkjc-captcha rkjc-captcha--widget">';
			echo Captcha::getInstance($html, array('namespace' => 'jcomments'))->display('hcaptcha', 'dynamic_hcaptcha_1', 'hcaptcha');
			echo '</div>';
		}
		elseif ($html == 'turnstile')
		{
			echo '<div class="rkjc-captcha rkjc-captcha--widget">';
			echo Captcha::getInstance($html, array('namespace' => 'jcomments'))->display('turnstile', 'dynamic_turnstile_1', 'turnstile');
			echo '</div>';
		}
	}

	/**
	 * Editor config (bbcode buttons, smilies, counter) as inert JSON in the form's data-config.
	 * JComments 5.0.5+ applies it in jcomments.initForm(); no inline script, so the form also works
	 * when it arrives through AJAX ("show form on click"), where scripts are stripped.
	 */
	private function formConfig()
	{
		$config = array('buttons' => array(), 'smiles' => array(), 'smilesUrl' => '', 'counter' => null);

		if ($this->getVar('comments-form-bbcode', 0) == 1)
		{
			$bbcodes = array(
				'b'     => array(Text::_('FORM_BBCODE_B'), Text::_('BBCODE_HINT_ENTER_TEXT')),
				'i'     => array(Text::_('FORM_BBCODE_I'), Text::_('BBCODE_HINT_ENTER_TEXT')),
				'u'     => array(Text::_('FORM_BBCODE_U'), Text::_('BBCODE_HINT_ENTER_TEXT')),
				's'     => array(Text::_('FORM_BBCODE_S'), Text::_('BBCODE_HINT_ENTER_TEXT')),
				'img'   => array(Text::_('FORM_BBCODE_IMG'), Text::_('BBCODE_HINT_ENTER_FULL_URL_TO_THE_IMAGE')),
				'url'   => array(Text::_('FORM_BBCODE_URL'), Text::_('BBCODE_HINT_ENTER_FULL_URL')),
				'hide'  => array(Text::_('FORM_BBCODE_HIDE'), Text::_('BBCODE_HINT_ENTER_TEXT_TO_HIDE_IT_FROM_UNREGISTERED')),
				'quote' => array(Text::_('FORM_BBCODE_QUOTE'), Text::_('BBCODE_HINT_ENTER_TEXT_TO_QUOTE')),
				'list'  => array(Text::_('FORM_BBCODE_LIST'), Text::_('BBCODE_HINT_ENTER_LIST_ITEM_TEXT')),
			);

			foreach ($bbcodes as $k => $v)
			{
				if ($this->getVar('comments-form-bbcode-' . $k, 0) == 1)
				{
					$config['buttons'][] = array($k, trim($v[0]), trim($v[1]));
				}
			}
		}

		$customBBCodes = $this->getVar('comments-form-custombbcodes');

		if (!empty($customBBCodes))
		{
			foreach ($customBBCodes as $code)
			{
				if ($code->button_enabled)
				{
					$config['buttons'][] = array(
						'custombbcode' . $code->id,
						trim($code->button_title),
						empty($code->button_prompt) ? Text::_('BBCODE_HINT_ENTER_TEXT') : Text::_($code->button_prompt),
						$code->button_open_tag,
						$code->button_close_tag,
						$code->button_css,
						$code->button_image,
					);
				}
			}
		}

		$smiles = $this->getVar('comment-form-smiles');

		if (!empty($smiles))
		{
			$config['smilesUrl'] = $this->getVar('smilesurl');

			foreach ($smiles as $code => $icon)
			{
				$config['smiles'][] = array(trim($code), trim($icon));
			}
		}

		if ($this->getVar('comments-form-showlength-counter', 0) == 1)
		{
			$config['counter'] = array(
				(int) $this->getVar('comment-maxlength'),
				Text::_('FORM_CHARSLEFT_PREFIX'),
				Text::_('FORM_CHARSLEFT_SUFFIX'),
				'counter',
			);
		}

		return htmlspecialchars(
			json_encode($config, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR),
			ENT_QUOTES,
			'UTF-8'
		);
	}

	/**
	 * Service message instead of the form (comments closed, no rights...).
	 */
	public function getMessage($text)
	{
		?>
		<a id="addcomments" href="#addcomments"></a>
		<?php
		echo $this->getVar('comments-html-before-form');

		if ($text != '')
		{
			?>
			<p class="message rkjc-message"><?php echo $this->icon('info'); ?><span><?php echo $text; ?></span></p>
			<?php
		}

		echo $this->getVar('comments-html-after-form');
	}

	/**
	 * Fields returned by plugins (onJCommentsFormPrepend / onJCommentsFormAppend).
	 */
	public function getFormFields($fields)
	{
		if (empty($fields))
		{
			return;
		}

		$fields = is_array($fields) ? $fields : array($fields);

		foreach ($fields as $field)
		{
			$labelElement = '';

			if (is_array($field))
			{
				$labelElement = $field['label'] ?? '';
				$inputElement = $field['input'] ?? '';
			}
			else
			{
				$inputElement = $field;
			}

			if (!empty($inputElement))
			{
				?>
				<div class="rkjc-plugin-field"><?php echo $inputElement; ?><?php echo $labelElement; ?></div>
				<?php
			}
		}
	}

	/**
	 * Inline SVG icons (no dependency on the site's icon font).
	 */
	public static function icon($name)
	{
		$paths = array(
			'comment' => '<path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7A8.4 8.4 0 0 1 4 11.5 8.5 8.5 0 0 1 8.7 3.9 8.4 8.4 0 0 1 12.5 3h.5a8.5 8.5 0 0 1 8 8v.5z"/>',
			'send'    => '<path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4 20-7z"/>',
			'refresh' => '<path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.5 9a9 9 0 0 1 14.9-3.4L23 10M1 14l4.6 4.4A9 9 0 0 0 20.5 15"/>',
			'info'    => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
		);

		if (!isset($paths[$name]))
		{
			return '';
		}

		return '<svg class="rkjc-ico rkjc-ico--' . $name . '" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[$name] . '</svg>';
	}
}
