<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Administrator\Exception;

defined('_JEXEC') or die;

use RuntimeException;

/**
 * A user account cannot be wiped for a reason which is safe to show to the user.
 *
 * Only throw it with human-readable, translated messages. Any other exception thrown while wiping a user account is
 * treated as an internal error: it is logged, and the user is only shown a generic message.
 *
 * @since  4.1.0
 */
class WipeRefusedException extends RuntimeException
{
}
