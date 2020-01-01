<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\Admin\Helper;

use Akeeba\DataCompliance\Admin\Model\EmailTemplates;
use Akeeba\ReleaseSystem\Site\Helper\Filter;
use FOF30\Container\Container;
use JFactory;
use JFile;
use JHtml;
use JLoader;
use Joomla\Registry\Registry;
use JText;
use JUser;

defined('_JEXEC') or die;

/**
 * A helper class for sending out emails
 */
abstract class Email
{
	/**
	 * The component's container
	 *
	 * @var   Container
	 */
	protected static $container;

	/**
	 * Returns the component's container
	 *
	 * @return  Container
	 */
	protected static function getContainer()
	{
		if (is_null(self::$container))
		{
			self::$container = Container::getInstance('com_datacompliance');
		}

		return self::$container;
	}

	/**
	 * Gets the email keys currently known to the component
	 *
	 * @param   bool  $jhtmlOptions  False: raw options list. True: JHtml options.
	 *
	 * @return  array|string
	 */
	public static function getEmailKeys($jhtmlOptions = false)
	{
		$rawOptions = [
			'user_user'           => JText::_('COM_DATACOMPLIANCE_EMAILTEMPLATE_LBL_USER_USER'),
			'user_admin'          => JText::_('COM_DATACOMPLIANCE_EMAILTEMPLATE_LBL_USER_ADMIN'),
			'user_lifecycle'      => JText::_('COM_DATACOMPLIANCE_EMAILTEMPLATE_LBL_USER_LIFECYCLE'),
			'user_warnlifecycle'  => JText::_('COM_DATACOMPLIANCE_EMAILTEMPLATE_LBL_USER_WARNLIFECYCLE'),
			'admin_user'          => JText::_('COM_DATACOMPLIANCE_EMAILTEMPLATE_LBL_ADMIN_USER'),
			'admin_admin'         => JText::_('COM_DATACOMPLIANCE_EMAILTEMPLATE_LBL_ADMIN_ADMIN'),
			'admin_lifecycle'     => JText::_('COM_DATACOMPLIANCE_EMAILTEMPLATE_LBL_ADMIN_LIFECYCLE'),
			'admin_warnlifecycle' => JText::_('COM_DATACOMPLIANCE_EMAILTEMPLATE_LBL_ADMIN_WARNLIFECYCLE'),
		];

		static $htmlOptions = null;
		static $shortlist = null;

		if (!$jhtmlOptions)
		{
			return $rawOptions;
		}

		if (is_null($htmlOptions))
		{
			$htmlOptions = array();

			foreach ($rawOptions as $k => $v)
			{
				$htmlOptions[] = JHTML::_('select.option', $k, $v);
			}
		}

		return $htmlOptions;
	}

	/**
	 * Loads an email template from the database
	 *
	 * @param   string   $key    The language key, in the form 'user_admin'
	 * @param   JUser    $user   The user whose preferred language will be loaded
	 *
	 * @return  array  isHTML: If it's HTML override from the db; text: The unprocessed translation string
	 */
	private static function loadEmailTemplate($key, $user = null)
	{
		if (is_null($user))
		{
			$user = self::getContainer()->platform->getUser();
		}

		// Initialise
		$templateText = '';
		$subject      = '';

		// Look for desired languages
		$jLang     = JFactory::getLanguage();
		$userLang  = $user->getParam('language', '');
		$languages = array(
			$userLang,
			$jLang->getTag(),
			$jLang->getDefault(),
			'en-GB',
			'*'
		);

		// Look for an override in the database
		/** @var EmailTemplates $templatesModel */
		$templatesModel = self::getContainer()->factory
			->model('EmailTemplates')->tmpInstance();

		$allTemplates = $templatesModel->key($key)->enabled(1)->get(true);

		if (empty($allTemplates))
		{
			return array('', '');
		}

		// Pass 1 - Give match scores to each template
		$preferredIndex = null;
		$preferredScore = 0;

		/** @var EmailTemplates $template */
		foreach ($allTemplates as $template)
		{
			// Get the language
			$myLang  = $template->language;

			// Make sure the language matches one of our desired languages, otherwise skip it
			$langPos = array_search($myLang, $languages);

			if ($langPos === false)
			{
				continue;
			}

			// Calculate the score. If it's winning, use it
			$score = 5 - $langPos;

			if ($score > $preferredScore)
			{
				$subject        = $template->subject;
				$templateText   = $template->body;
				$preferredScore = $score;
			}
		}

		// Because SpamAssassin demands there is a body and surrounding html tag even though it's not necessary.
		if (strpos($templateText, '<body') == false)
		{
			$templateText = '<body>' . $templateText . '</body>';
		}

		if (strpos($templateText, '<html') == false)
		{
			$templateText = <<< HTML
<html>
<head>
<title>{$subject}</title>
</head>
$templateText
</html>
HTML;

		}

		return array($subject, $templateText);
	}

	/**
	 * Creates a PHPMailer instance
	 *
	 * @param   boolean $isHTML
	 *
	 * @return  \JMail  A mailer instance
	 */
	private static function &getMailer($isHTML = true)
	{
		$mailer = clone JFactory::getMailer();

		$mailer->IsHTML($isHTML);

		// Required in order not to get broken characters
		$mailer->CharSet = 'UTF-8';

		return $mailer;
	}

	/**
	 * Creates a mailer instance, preloads its subject and body with your email data based on the key and extra
	 * substitution parameters and waits for you to send a recipient and send the email.
	 *
	 * @param   string  $key      The email key, in the form user_admin
	 * @param   int     $user_id  The user ID
	 * @param   array   $extras   Any optional substitution strings you want to introduce
	 *
	 * @return  \JMail|boolean False if something bad happened, the PHPMailer instance in any other case
	 */
	public static function getPreloadedMailer($key, $user_id = null, array $extras = array())
	{
		// Load the template
		$user = self::getContainer()->platform->getUser($user_id);
		list($subject, $templateText) = self::loadEmailTemplate($key, $user);

		if (empty($subject))
		{
			return false;
		}

		$templateText = self::processTags($templateText, $user, $extras);
		$subject      = self::processTags($subject, $user, $extras);

		// Get the mailer
		$mailer = self::getMailer(true);
		$mailer->setSubject($subject);

		// Include inline images
		$pattern           = '/(src)=\"([^"]*)\"/i';
		$number_of_matches = preg_match_all($pattern, $templateText, $matches, PREG_OFFSET_CAPTURE);

		if ($number_of_matches > 0)
		{
			$substitutions = $matches[2];
			$last_position = 0;
			$temp          = '';

			// Loop all URLs
			$imgidx    = 0;
			$imageSubs = array();

			foreach ($substitutions as &$entry)
			{
				// Copy unchanged part, if it exists
				if ($entry[1] > 0)
				{
					$temp .= substr($templateText, $last_position, $entry[1] - $last_position);
				}

				// Examine the current URL
				$url = $entry[0];

				if ((substr($url, 0, 7) == 'http://') || (substr($url, 0, 8) == 'https://'))
				{
					// External link, skip
					$temp .= $url;
				}
				else
				{
					$ext = strtolower(JFile::getExt($url));

					if (!JFile::exists($url) || !in_array($ext, array('jpg', 'png', 'gif')))
					{
						// Not an image or inexistent file
						$temp .= $url;
					}
					else
					{
						// Image found, substitute
						if (!array_key_exists($url, $imageSubs))
						{
							// First time I see this image, add as embedded image and push to
							// $imageSubs array.
							$imgidx ++;
							$mailer->AddEmbeddedImage($url, 'img' . $imgidx, basename($url));
							$imageSubs[ $url ] = $imgidx;
						}

						// Do the substitution of the image
						$temp .= 'cid:img' . $imageSubs[ $url ];
					}
				}

				// Calculate next starting offset
				$last_position = $entry[1] + strlen($entry[0]);
			}

			// Do we have any remaining part of the string we have to copy?
			if ($last_position < strlen($templateText))
			{
				$temp .= substr($templateText, $last_position);
			}

			// Replace content with the processed one
			$templateText = $temp;
		}

		$mailer->setBody($templateText);

		return $mailer;
	}

	/**
	 * Pre-processes the message text in $text, replacing merge tags with those
	 * fetched based on subscription $sub
	 *
	 * @param   string  $text    The message to process
	 * @param   JUser   $user    A user object
	 * @param   array   $extras  Any optional substitution strings you want to introduce
	 *
	 * @return  string  The processed string
	 */
	public static function processTags($text, $user, $extras = array())
	{
		$substitutions = [
			'[NAME]'          => $user->name,
			'[EMAIL]'         => $user->email,
			'[USERNAME]'      => $user->username,
			'[REGISTERDATE]'  => $user->registerDate,
			'[LASTVISITDATE]' => $user->lastvisitDate,
			'[REQUIRERESET]'  => $user->requireReset,
			'[RESETCOUNT]'    => $user->resetCount,
			'[LASTRESETTIME]' => $user->lastResetTime,
			'[ACTIVATION]'    => empty($user->activation) ? JText::_('JNO') : $user->activation,
			'[BLOCK]'         => $user->block ? JText::_('JYES') : JText::_('JNO'),
			'[ID]'            => $user->id,
		];

		foreach ($substitutions as $tag => $v)
		{
			$text = str_replace($tag, $v, $text);
		}

		// User fields
		if (is_string($user->params))
		{
			$user->params = new Registry($user->params);
		}

		$customParams = $user->params->toArray();

		foreach ($customParams as $k => $v)
		{
			if (is_object($v))
			{
				continue;
			}

			if (is_array($v))
			{
				continue;
			}

			$tag = '[PARAM:' . strtoupper($k) . ']';

			$text = str_replace($tag, $v, $text);
		}

		// Extra variables replacement

		// -- Get the site name
		$config   = self::getContainer()->platform->getConfig();
		$sitename = $config->get('sitename');

		// -- Site URL
		$container = self::getContainer();
		$isCli     = $container->platform->isCli();

		if ($isCli)
		{
			JLoader::import('joomla.application.component.helper');
			$baseURL    = \JComponentHelper::getParams('com_datacompliance')->get('siteurl', 'http://www.example.com');
		}
		else
		{
			$baseURL    = \JURI::base();
		}

		$baseURL    = str_replace('/administrator', '', $baseURL);

		// Download ID

		if (!class_exists('Akeeba\ReleaseSystem\Site\Helper\Filter') && file_exists(JPATH_SITE . '/components/com_ars/Helper/Filter.php'))
		{
			@include_once JPATH_SITE . '/components/com_ars/Helper/Filter.php';
		}

		$dlid = '';

		if (class_exists('Akeeba\ReleaseSystem\Site\Helper\Filter'))
		{
			$dlid = Filter::myDownloadID($user->id);
		}

		// -- The actual replacement
		$extras = array_merge(array(
			"\\n"                      => "\n",
			'[SITENAME]'               => $sitename,
			'[SITEURL]'                => $baseURL,
			'[DLID]'                   => $dlid,
		), $extras);

		foreach ($extras as $key => $value)
		{
			$text = str_replace($key, $value, $text);
		}

		return $text;
	}
}
