<?php
namespace OCA\Extract\Controller;

use ZipArchive;
use Rar;

// Only in order to access Filesystem::isFileBlacklisted().
use OC\Files\Filesystem;

use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\Files\NotFoundException;
use OCP\Files\IRootFolder;
use OCP\Files\File;
use OCP\Files\Folder;

use OCP\IL10N;
use OCP\Share\IManager;
use OCP\EventDispatcher\IEventDispatcher;

use \OCP\ILogger;

//use OCP\App\IAppManager;

use OCA\Extract\Service\ExtractionService;

class ExtractionController extends Controller {

	/** @var IL10N */
	private $l;

	/** @var LoggerInterface */
	private $logger;

	/** @var IRootFolder */
	private $rootFolder;

	/** @var Folder */
	private $userFolder;

	/** @var string */
	private $userId;

	/**  @var ExtractionService */
	private $extractionService;

 	public function __construct(
		string $AppName
		, IRequest $request
		, ExtractionService $extractionService
		, IRootFolder $rootFolder
		, IL10N $l
		, ILogger $logger
		//, IManager $encryptionManager
		, $UserId
	){
		parent::__construct($AppName, $request);
		$this->l = $l;
		$this->logger = $logger;
		//$this->encryptionManager = $encryptionManager;
		$this->userId = $UserId;
		$this->extractionService = $extractionService;
		$this->rootFolder = $rootFolder;
		$this->userFolder = $this->rootFolder->getUserFolder($this->userId);
	}

	private function getFile($directory, $fileName){
		$fileNode = $this->userFolder->get($directory . '/' . $fileName);
		return $fileNode->getStorage()->getLocalFile($fileNode->getInternalPath());
	}

	/**
	 * Register the new files to the NC filesystem.
	 *
	 * @param string $fileName The Nextcloud file name.
	 *
	 * @param srting $directory The Nextcloud directory name.
	 *
	 * @param string $extractTo The local file-system path of the directory
	 * with the extracted data, i.e. this is the OS path.
	 *
	 * @param null|string $tmpPath The Nextcloud temporary path. This is only
	 * non-null when extracting from external storage.
	 */
	private function postExtract(string $fileName, string $directory, string $extractTo, ?string $tmpPath){

		$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($extractTo));
		foreach ($iterator as $file) {
			/** @var \SplFileInfo $file */
			if (Filesystem::isFileBlacklisted($file->getBasename())) {
				$this->logger->warning(__METHOD__ . ': removing blacklisted file: ' . $file->getPathname());
				// remove it
				unlink($file->getPathname());
			}
		}

		$NCDestination = $directory . '/' . $fileName;
		if($tmpPath != ""){
			$tmpFolder = $this->rootFolder->get($tmpPath);
			$tmpFolder->move($this->userFolder->getFullPath($NCDestination));
		}else{
			$scanner = new \OC\Files\Utils\Scanner($this->userId, \OC::$server->getDatabaseConnection(), \OC::$server->getLogger());
			$scanner->scan($this->userFolder->getFullPath($NCDestination));
		}
	}

	/**
	 * The only AJAX callback. This is a hook for ordinary cloud-users, os no admin required.
	 *
	 * @NoAdminRequired
	 */
	public function extract($nameOfFile, $directory, $external, $type){
		$this->logger->warning(\OC::$server->getEncryptionManager()->isEnabled());
		if (\OC::$server->getEncryptionManager()->isEnabled()) {
			$response = array();
			$response = array_merge($response, array("code" => 0, "desc" => $this->l->t("Encryption is not supported yet")));
			return new DataResponse($response);
		}
		$file = $this->getFile($directory, $nameOfFile);
		$dir = dirname($file);
		//name of the file without extension
		$fileName = pathinfo($nameOfFile, PATHINFO_FILENAME);
		$extractTo = $dir . '/' . $fileName;

		// if the file is un external storage
		if($external){

			$appPath = $this->userId . '/' . $this->appName;
			try {
				$appDirectory = $this->rootFolder->get($appPath);
			} catch (\OCP\Files\NotFoundException $e) {
				$appDirectory = $this->rootFolder->newFolder($appPath);
			}
			if(pathinfo($fileName, PATHINFO_EXTENSION) == "tar"){
				$archiveDir = pathinfo($fileName, PATHINFO_FILENAME);
			} else {
				$archiveDir = $fileName;
			}

			// remove temporary directory if exists from interrupted previous runs
			try {
				$appDirectory->get($archiveDir)->delete();
			} catch (\OCP\Files\NotFoundException $e) {
				// ok
			}

			$tmpPath = $appDirectory->getPath() . '/' . $archiveDir;
			$extractTo = $appDirectory->getStorage()->getLocalFile($appDirectory->getInternalPath()) . '/' . $archiveDir;
		} else {
			$tmpPath = "";
		}
		
		switch ($type) {
			case 'zip':
				$response = $this->extractionService->extractZip($file, $fileName, $extractTo);
				break;
			case 'rar':
				$response = $this->extractionService->extractRar($file, $fileName, $extractTo);
				break;
			default:
				// Check if the file is .tar.gz in order to do the extraction on a single step
				if(pathinfo($fileName, PATHINFO_EXTENSION) == "tar"){
					$cleanFileName = pathinfo($fileName, PATHINFO_FILENAME);
					$extractTo = dirname($extractTo) . '/' . $cleanFileName;
					$response = $this->extractionService->extractOther($file, $cleanFileName, $extractTo);
					$file = $extractTo . '/' . pathinfo($file, PATHINFO_FILENAME);
					$fileName = $cleanFileName;
					$response = $this->extractionService->extractOther($file, $fileName, $extractTo);

					// remove .tar file
					unlink($file);
				}else{
					$response = $this->extractionService->extractOther($file, $fileName, $extractTo);
				}
				break;
		}

		$this->postExtract($fileName, $directory, $extractTo, $tmpPath);

		return new DataResponse($response);
	}
}
