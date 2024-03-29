<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\CatalogImportExport\Model\Import;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\DriverPool;
use Magento\Framework\App\ObjectManager;

/**
 * Import entity product model
 *
 * @api
 * @since 100.0.2
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Uploader extends \Magento\MediaStorage\Model\File\Uploader
{

    /**
     * HTTP scheme
     * used to compare against the filename and select the proper DriverPool adapter
     * @var string
     */
    private $httpScheme = 'http://';

    /**
     * Temp directory.
     *
     * @var string
     */
    protected $_tmpDir = '';

    /**
     * Download directory for url-based resources.
     *
     * @var string
     */
    private $downloadDir;

    /**
     * Destination directory.
     *
     * @var string
     */
    protected $_destDir = '';

    /**
     * All mime types.
     *
     * @var array
     */
    protected $_allowedMimeTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'png' => 'image/png',
    ];

    const DEFAULT_FILE_TYPE = 'application/octet-stream';

    /**
     * Image factory.
     *
     * @var \Magento\Framework\Image\AdapterFactory
     */
    protected $_imageFactory;

    /**
     * Validator.
     *
     * @var \Magento\MediaStorage\Model\File\Validator\NotProtectedExtension
     */
    protected $_validator;

    /**
     * Instance of filesystem directory write interface.
     *
     * @var \Magento\Framework\Filesystem\Directory\WriteInterface
     */
    protected $_directory;

    /**
     * Instance of filesystem read factory.
     *
     * @var \Magento\Framework\Filesystem\File\ReadFactory
     */
    protected $_readFactory;

    /**
     * Instance of media file storage database.
     *
     * @var \Magento\MediaStorage\Helper\File\Storage\Database
     */
    protected $_coreFileStorageDb;

    /**
     * Instance of media file storage.
     *
     * @var \Magento\MediaStorage\Helper\File\Storage
     */
    protected $_coreFileStorage;

    /**
     * Instance of random data generator.
     *
     * @var \Magento\Framework\Math\Random
     */
    private $random;

    /**
     * @var \Magento\Framework\App\Filesystem\DirectoryResolver
     */
    private $directoryResolver;

    /**
     * @param \Magento\MediaStorage\Helper\File\Storage\Database $coreFileStorageDb
     * @param \Magento\MediaStorage\Helper\File\Storage $coreFileStorage
     * @param \Magento\Framework\Image\AdapterFactory $imageFactory
     * @param \Magento\MediaStorage\Model\File\Validator\NotProtectedExtension $validator
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\Framework\Filesystem\File\ReadFactory $readFactory
     * @param string|null $filePath
     * @param \Magento\Framework\App\Filesystem\DirectoryResolver|null $directoryResolver
     * @param \Magento\Framework\Math\Random|null $random
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function __construct(
        \Magento\MediaStorage\Helper\File\Storage\Database $coreFileStorageDb,
        \Magento\MediaStorage\Helper\File\Storage $coreFileStorage,
        \Magento\Framework\Image\AdapterFactory $imageFactory,
        \Magento\MediaStorage\Model\File\Validator\NotProtectedExtension $validator,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Framework\Filesystem\File\ReadFactory $readFactory,
        $filePath = null,
        \Magento\Framework\App\Filesystem\DirectoryResolver $directoryResolver = null,
        \Magento\Framework\Math\Random $random = null
    ) {
        if ($filePath !== null) {
            $this->_setUploadFile($filePath);
        }
        $this->_imageFactory = $imageFactory;
        $this->_coreFileStorageDb = $coreFileStorageDb;
        $this->_coreFileStorage = $coreFileStorage;
        $this->_validator = $validator;
        $this->_directory = $filesystem->getDirectoryWrite(DirectoryList::ROOT);
        $this->_readFactory = $readFactory;
        $this->directoryResolver = $directoryResolver
            ?: ObjectManager::getInstance()->get(\Magento\Framework\App\Filesystem\DirectoryResolver::class);
        $this->random = $random
            ?: ObjectManager::getInstance()->get(\Magento\Framework\Math\Random::class);
        $this->downloadDir = DirectoryList::getDefaultConfig()[DirectoryList::TMP][DirectoryList::PATH];
    }

    /**
     * Initiate uploader default settings
     *
     * @return void
     */
    public function init()
    {
        $this->setAllowRenameFiles(true);
        $this->setAllowCreateFolders(true);
        $this->setFilesDispersion(true);
        $this->setAllowedExtensions(array_keys($this->_allowedMimeTypes));
        $imageAdapter = $this->_imageFactory->create();
        $this->addValidateCallback('catalog_product_image', $imageAdapter, 'validateUploadFile');
        $this->_uploadType = self::SINGLE_STYLE;
    }

    /**
     * Proceed moving a file from TMP to destination folder
     *
     * @param string $fileName
     * @param bool $renameFileOff
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function move($fileName, $renameFileOff = false)
    {
        $this->setAllowRenameFiles(!$renameFileOff);

        if (preg_match('/\bhttps?:\/\//i', $fileName, $matches)) {
            $url = str_replace($matches[0], '', $fileName);
            $driver = ($matches[0] === $this->httpScheme) ? DriverPool::HTTP : DriverPool::HTTPS;
            $tmpFilePath = $this->downloadFileFromUrl($url, $driver);
        } else {
            $tmpDir = $this->getTmpDir() ? ($this->getTmpDir() . '/') : '';
            $tmpFilePath = $this->_directory->getRelativePath($tmpDir . $fileName);
        }

        $this->_setUploadFile($tmpFilePath);
        $destDir = $this->_directory->getAbsolutePath($this->getDestDir());
        $result = $this->save($destDir);
        unset($result['path']);
        $result['name'] = self::getCorrectFileName($result['name']);

        return $result;
    }

    /**
     * Writes a url-based file to the temp directory.
     *
     * @param string $url
     * @param string $driver
     * @return string
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function downloadFileFromUrl($url, $driver)
    {
        $parsedUrlPath = parse_url($url, PHP_URL_PATH);
        if (!$parsedUrlPath) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Could not parse resource url.'));
        }
        $urlPathValues = explode('/', $parsedUrlPath);
        $fileName = preg_replace('/[^a-z0-9\._-]+/i', '', end($urlPathValues));
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
        if ($fileExtension && !$this->checkAllowedExtension($fileExtension)) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Disallowed file type.'));
        }
        $tmpFileName = str_replace(".$fileExtension", '', $fileName);
        $tmpFileName .= '_' . $this->random->getRandomString(16);
        $tmpFileName .= $fileExtension ? ".$fileExtension" : '';
        $tmpFilePath = $this->_directory->getRelativePath($this->downloadDir . '/' . $tmpFileName);
        $this->_directory->writeFile(
            $tmpFilePath,
            $this->_readFactory->create($url, $driver)->readAll()
        );
        return $tmpFilePath;
    }

    /**
     * Prepare information about the file for moving
     *
     * @param string $filePath
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _setUploadFile($filePath)
    {
        if (!$this->_directory->isReadable($filePath)) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('File \'%1\' was not found or has read restriction.', $filePath)
            );
        }
        $this->_file = $this->_readFileInfo($filePath);

        $this->_validateFile();
    }

    /**
     * Reads file info
     *
     * @param string $filePath
     * @return array
     */
    protected function _readFileInfo($filePath)
    {
        $fullFilePath = $this->_directory->getAbsolutePath($filePath);
        $fileInfo = pathinfo($fullFilePath);
        return [
            'name' => $fileInfo['basename'],
            'type' => $this->_getMimeTypeByExt($fileInfo['extension']),
            'tmp_name' => $filePath,
            'error' => 0,
            'size' => $this->_directory->stat($filePath)['size']
        ];
    }

    /**
     * Validate uploaded file by type and etc.
     *
     * @return void
     * @throws \Exception
     */
    protected function _validateFile()
    {
        $filePath = $this->_file['tmp_name'];
        if ($this->_directory->isReadable($filePath)) {
            $this->_fileExists = true;
        } else {
            $this->_fileExists = false;
        }

        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
        if (!$this->checkAllowedExtension($fileExtension)) {
            throw new \Exception('Disallowed file type.');
        }
        //run validate callbacks
        foreach ($this->_validateCallbacks as $params) {
            if (is_object($params['object'])
                && method_exists($params['object'], $params['method'])
                && is_callable([$params['object'], $params['method']])
            ) {
                $params['object']->{$params['method']}($this->_directory->getAbsolutePath($filePath));
            }
        }
    }

    /**
     * Returns file MIME type by extension
     *
     * @param string $ext
     * @return string
     */
    protected function _getMimeTypeByExt($ext)
    {
        if (array_key_exists($ext, $this->_allowedMimeTypes)) {
            return $this->_allowedMimeTypes[$ext];
        }
        return '';
    }

    /**
     * Obtain TMP file path prefix
     *
     * @return string
     */
    public function getTmpDir()
    {
        return $this->_tmpDir;
    }

    /**
     * Set TMP file path prefix
     *
     * @param string $path
     * @return bool
     */
    public function setTmpDir($path)
    {
        if (is_string($path)
            && $this->_directory->isReadable($path)
            && $this->directoryResolver->validatePath($this->_directory->getAbsolutePath($path), DirectoryList::ROOT)
        ) {
            $this->_tmpDir = $path;
            return true;
        }
        return false;
    }

    /**
     * Obtain destination file path prefix
     *
     * @return string
     */
    public function getDestDir()
    {
        return $this->_destDir;
    }

    /**
     * Set destination file path prefix
     *
     * @param string $path
     * @return bool
     */
    public function setDestDir($path)
    {
        if (is_string($path) && $this->_directory->isWritable($path)) {
            $this->_destDir = $path;
            return true;
        }
        return false;
    }

    /**
     * Move files from TMP folder into destination folder
     *
     * @param string $tmpPath
     * @param string $destPath
     * @return bool
     */
    protected function _moveFile($tmpPath, $destPath)
    {
        if ($this->_directory->isFile($tmpPath)) {
            $tmpRealPath = $this->_directory->getDriver()->getRealPath(
                $this->_directory->getAbsolutePath($tmpPath)
            );
            $destinationRealPath = $this->_directory->getDriver()->getRealPath($destPath);
            $relativeDestPath = $this->_directory->getRelativePath($destPath);
            $isSameFile = $tmpRealPath === $destinationRealPath;
            return $isSameFile ?: $this->_directory->copyFile($tmpPath, $relativeDestPath);
        } else {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function chmod($file)
    {
        return;
    }
}
