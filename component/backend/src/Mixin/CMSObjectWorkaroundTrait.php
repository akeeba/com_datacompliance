<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Administrator\Mixin;

trait CMSObjectWorkaroundTrait
{
	/**
	 * Calls a method to a suspected CMSObject and returns the result and error information.
	 *
	 * On older versions of Joomla it's equivalent to returning:
	 * [$object->{$method}($arguments), $object->getError(), $object->getErrors()]
	 *
	 * On newer versions of Joomla the method call may throw an exception. In that case we return:
	 * [$object->{$method}($arguments), $e->getMessage(), [$e->getMessage()]]
	 *
	 * @param   object  $object        The suspected CMSObject to call
	 * @param   string  $method        The method to call
	 * @param   mixed   ...$arguments  The arguments to pass to the method
	 *
	 * @return  array  Returns the result and error information [$result, $error, $errors]
	 * @since   5.4.0
	 */
	protected function cmsObjectSafeCall(object $object, string $method, ...$arguments): array
	{
		$arguments = $arguments ?? [];

		try
		{
			$result = $object->{$method}(...$arguments);
		}
		catch (\Exception $e)
		{
			$result = false;
			$error  = $e->getMessage();

			return [$result, $error, [$error]];
		}

		if ($result)
		{
			return [$result, '', []];
		}

		$error  = method_exists($object, 'getError') ? $object->getError() : [];
		$errors = method_exists($object, 'getErrors') ? $object->getErrors() : [];

		return [$result, $error, $errors];
	}

	/**
	 * Sets the errors into $this which is assumed to be a CMSObject.
	 *
	 * If it's not a CMSObject, or does not implement LegacyErrorHandlingTrait, we throw an exception.
	 *
	 * @param   string|array  $errors          The error string or array to set
	 * @param   string        $exceptionClass  The exception class to throw if the object does not implement
	 *                                         LegacyErrorHandlingTrait.
	 *
	 * @return  void
	 * @since   5.4.0
	 */
	protected function setErrorOrThrow($errors, string $exceptionClass = \RuntimeException::class): void
	{
		if (empty($errors))
		{
			return;
		}

		if (is_string($errors))
		{
			$errors = [$errors];
		}
		elseif (!is_array($errors))
		{
			throw new \InvalidArgumentException('Errors must be a string or an array');
		}

		$error = $errors[0];

		if (empty(trim($error)))
		{
			return;
		}

		if (method_exists($this, 'setErrors'))
		{
			$this->setErrors($errors);

			return;
		}

		if (method_exists($this, 'setError'))
		{
			/** @noinspection PhpDeprecationInspection */
			$this->setError($error);

			return;
		}

		throw new $exceptionClass($error);
	}
}