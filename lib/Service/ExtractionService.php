<?php

/**
 * @author Paul Lereverend
 * @copyright 2012-2022 Paul Lereverend <paulereverend@gmail.com>
 * @author Claus-Justus Heine
 * @copyright 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Extract\Service;

use \OCP\ILogger;
use OCP\IL10N;

use ZipArchive;

class ExtractionService
{

	/** @var IL10N */
	private $l;

	/** @var LoggerInterface */
	private $logger;

	public function __construct(
		IL10N $l,
		ILogger $logger
	) {
		$this->l = $l;
		$this->logger = $logger;
	}

	public function extractZip($file, $extractTo)
	{
		$response = array();

		if (!extension_loaded("zip")) {
			$response = array_merge($response, array("code" => 0, "desc" => $this->l->t("Zip extension is not available")));
			return $response;
		}

		$zip = new ZipArchive();

		if (!$zip->open($file) === TRUE) {
			$response = array_merge($response, array("code" => 0, "desc" => $this->l->t("Cannot open Zip file")));
			return $response;
		}

		$success = $zip->extractTo($extractTo);
		$zip->close();
		$response = array_merge($response, array("code" => 1));
		return $response;
	}

	public function extractRar($file, $extractTo)
	{
		$response = array();

		if (!extension_loaded("rar")) {
			$output = [];
			$return = -1;
			exec('unrar x ' . escapeshellarg($file) . ' -R ' . escapeshellarg($extractTo) . '/ -o+', $output, $return);
			if (sizeof($output) <= 4) {
				$response = array_merge($response, array("code" => 0, "desc" => $this->l->t("Oops something went wrong. Check that you have rar extension or unrar installed")));
				return $response;
			}
		} else {
			$this->logger->debug();
			$rar_file = rar_open($file);
			$list = rar_list($rar_file);
			foreach ($list as $archive_file) {
				$entry = rar_entry_get($rar_file, $archive_file->getName());
				$entry->extract($extractTo);
			}
			rar_close($rar_file);
		}

		$response = array_merge($response, array("code" => 1));
		return $response;
	}

	public function extractOther($file, $extractTo)
	{
		$response = array();
		$output = [];
		$return = -1;
		exec('7za -y x ' . escapeshellarg($file) . ' -o' . escapeshellarg($extractTo), $output, $return);

		if (sizeof($output) <= 5) {
			$response = array_merge($response, array("code" => 0, "desc" => $this->l->t("Oops something went wrong. Check that you have p7zip installed")));
			$this->logger->error(__METHOD__ . ': ' . $output);
			return $response;
		}
		$response = array_merge($response, array("code" => 1));
		return $response;
	}
}
