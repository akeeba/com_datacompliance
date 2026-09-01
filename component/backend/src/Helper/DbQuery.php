<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\QueryInterface;

/**
 * Database query factory, isolating the createQuery() compatibility branch.
 *
 * `DatabaseDriver::createQuery()` was added in Joomla 5.1, which is below our minimum supported
 * Joomla version, so it can be called unconditionally.
 *
 * This class still exists because `createQuery()` is only declared on `DatabaseInterface` from
 * Joomla 6.0. Most callers hold a `DatabaseInterface` — that is the key they resolve from the DI
 * container — so calling it directly is correct at runtime but unprovable to a static analyser on
 * Joomla 5.x. Confining that to one method confines the analyser's complaint to one method.
 *
 * @since  4.0.4
 */
final class DbQuery
{
	/**
	 * Create a new, empty query object.
	 *
	 * @param   DatabaseInterface  $db  The database driver to create the query for.
	 *
	 * @return  QueryInterface
	 * @since   4.0.4
	 */
	public static function create(DatabaseInterface $db): QueryInterface
	{
		/** @noinspection PhpPossiblePolymorphicInvocationInspection */
		return $db->createQuery();
	}
}
