<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\IntegrationTest\Engine;

defined('_JEXEC') or die;

use RuntimeException;

/**
 * Runs a command inside the site's php container.
 *
 * Needed for the parts of Data Compliance that are not reachable over HTTP at all — the
 * datacompliance:* console commands (lifecycle notify / delete, account delete). Those run through
 * Joomla's console application, and driving them any other way (calling the model in-process) would
 * exercise something other than what a real cron entry does.
 *
 * @since 4.1.0
 */
class ContainerCli
{
	/**
	 * The suite configuration.
	 *
	 * @var   Configuration
	 * @since 4.1.0
	 */
	private Configuration $config;

	/**
	 * Constructor.
	 *
	 * @param   Configuration|null  $config  The suite configuration.
	 *
	 * @since   4.1.0
	 */
	public function __construct(?Configuration $config = null)
	{
		$this->config = $config ?? Configuration::getInstance();
	}

	/**
	 * Run Joomla's console application inside the container.
	 *
	 * @param   string[]  $arguments  Arguments after `cli/joomla.php`, e.g. ['datacompliance:lifecycle:delete'].
	 *
	 * @return  array{0: int, 1: string}  Exit code and combined output.
	 * @since   4.1.0
	 */
	public function joomla(array $arguments): array
	{
		return $this->run(array_merge(['php', 'cli/joomla.php'], $arguments));
	}

	/**
	 * Run Joomla's console application, failing loudly on a non-zero exit.
	 *
	 * @param   string[]  $arguments  Arguments after `cli/joomla.php`.
	 *
	 * @return  string  The combined output.
	 * @throws  RuntimeException  When the command fails.
	 * @since   4.1.0
	 */
	public function joomlaOrFail(array $arguments): string
	{
		[$exitCode, $output] = $this->joomla($arguments);

		if ($exitCode !== 0)
		{
			throw new RuntimeException(
				sprintf("`cli/joomla.php %s` failed (exit %d):\n%s", implode(' ', $arguments), $exitCode, $output)
			);
		}

		return $output;
	}

	/**
	 * Run an arbitrary command inside the php container.
	 *
	 * @param   string[]  $command  The command and its arguments.
	 *
	 * @return  array{0: int, 1: string}  Exit code and combined output.
	 * @since   4.1.0
	 */
	public function run(array $command): array
	{
		$docker = $this->config->getDocker();

		return $this->compose(
			array_merge(['exec', '-T', '-w', '/var/www/html', $docker['php']], $command)
		);
	}

	/**
	 * Hash a password with the site's PHP, as the site itself would.
	 *
	 * Fixtures must not be hashed with the host's PHP: the default bcrypt cost differs between PHP versions. Joomla
	 * rehashes a password whose cost is not its own when the user logs in, and saving a changed password clears the
	 * account's activation token (Joomla 5.4.11 and 6.2.1, for example).
	 *
	 * The result is remembered, as each call to the container is slow: the same password gets the same hash.
	 *
	 * @param   string  $password  The plain text password.
	 *
	 * @return  string  Its bcrypt hash.
	 * @throws  RuntimeException  When the container does not return a hash.
	 * @since   4.1.1
	 */
	public function passwordHash(string $password): string
	{
		static $hashes = [];

		if (isset($hashes[$password]))
		{
			return $hashes[$password];
		}

		[$exitCode, $output] = $this->run(['php', '-r', 'echo password_hash($argv[1], PASSWORD_BCRYPT);', '--', $password]);

		// The output includes stderr, so take the hash rather than all of it.
		if ($exitCode !== 0 || !preg_match('#\$2y\$\d{2}\$[./A-Za-z0-9]{53}#', $output, $matches))
		{
			throw new RuntimeException(sprintf("Cannot hash a password in the php container (exit %d):\n%s", $exitCode, $output));
		}

		return $hashes[$password] = $matches[0];
	}

	/**
	 * Run the AWS CLI against the stack's S3 server, e.g. ['s3', 'ls', '--recursive', 's3://dc-audit/'].
	 *
	 * The `s3cli` service is pre-configured with the stack's credentials and endpoint (http://garage:3900).
	 * Garage has no published port, so this is the only way to look inside the bucket from the host.
	 *
	 * @param   string[]  $arguments  The aws arguments.
	 *
	 * @return  array{0: int, 1: string}  Exit code and combined output.
	 * @since   4.1.0
	 */
	public function s3(array $arguments): array
	{
		return $this->compose(array_merge(['--profile', 'tools', 'run', '--rm', '-T', '--no-deps', 's3cli'], $arguments));
	}

	/**
	 * Run a docker compose sub-command against the stack.
	 *
	 * @param   string[]  $arguments  Everything after `docker compose -f <file>`.
	 *
	 * @return  array{0: int, 1: string}  Exit code and combined output.
	 * @since   4.1.0
	 */
	private function compose(array $arguments): array
	{
		$docker = $this->config->getDocker();

		$parts = array_merge(
			// composeBin may be "docker compose" (two words) or "docker-compose".
			explode(' ', $docker['bin']),
			['-f', $docker['file']],
			$arguments
		);

		$escaped = implode(' ', array_map('escapeshellarg', $parts)) . ' 2>&1';

		exec($escaped, $output, $exitCode);

		return [$exitCode, implode("\n", $output)];
	}
}
