<?php
namespace Swoole\ToolKit;

class NotFound extends \Exception
{
}
/**
 * Class Unzipper
 */
class Unzipper
{
    public $localdir = '.';
    public $zipfiles = array();
    public $status;

    public function __construct()
    {
        // Read directory and pick .zip, .rar and .gz files.
        if ($dh = opendir($this->localdir)) {
            while (($file = readdir($dh)) !== false) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'zip'
                    || pathinfo($file, PATHINFO_EXTENSION) === 'gz'
                    || pathinfo($file, PATHINFO_EXTENSION) === 'rar'
                ) {
                    $this->zipfiles[] = $file;
                }
            }
            closedir($dh);

            if (!empty($this->zipfiles)) {
                $this->status = array('info' => '.zip or .gz or .rar files found, ready for extraction');
            } else {
                $this->status = array('info' => 'No .zip or .gz or rar files found. So only zipping functionality available.');
            }
        }
    }

    /**
     * Prepare and check zipfile for extraction.
     *
     * @param string $archive
     *   The archive name including file extension. E.g. my_archive.zip.
     * @param string $destination
     *   The relative destination path where to extract files.
     */
    public function prepareExtraction($archive, $destination = '')
    {
        // Determine paths.
        if (empty($destination)) {
            $extpath = $this->localdir;
        } else {
            $extpath = $destination;
            // Todo: move this to extraction function.
            if (!is_dir($extpath)) {
                mkdir($extpath);
            }
        }
        // Only local existing archives are allowed to be extracted.
        if (in_array($archive, $this->zipfiles)) {
            $this->extract($archive, $extpath);
        }
    }

    /**
     * Checks file extension and calls suitable extractor functions.
     *
     * @param string $archive
     *   The archive name including file extension. E.g. my_archive.zip.
     * @param string $destination
     *   The relative destination path where to extract files.
     */
    public function extract($archive, $destination)
    {
        $ext = pathinfo($archive, PATHINFO_EXTENSION);
        switch ($ext) {
            case 'zip':
                $this->extractZipArchive($archive, $destination);
                break;
            case 'gz':
                $this->extractGzipFile($archive, $destination);
                break;
            case 'rar':
                $this->extractRarArchive($archive, $destination);
                break;
        }

    }


    /**
     * Decompress/extract a zip archive using ZipArchive.
     *
     * @param $archive
     * @param $destination
     */
    public function extractZipArchive($archive, $destination)
    {
        // Check if webserver supports unzipping.
        if (!class_exists('ZipArchive')) {
            $this->status = array('error' => 'Error: Your PHP version does not support unzip functionality.');
            return;
        }

        $zip = new \ZipArchive;

        // Check if archive is readable.
        if ($zip->open($archive) === true) {
            // Check if destination is writable
            $zipname = $this->transcoding(basename($archive));
            $destination .= substr($zipname,0,strlen($zipname)-4);
            if(!is_dir($destination)) mkdir($destination,0775,true);
            if (is_writeable($destination . '/')) {
                $docnum = $zip->numFiles;
                for($i = 0; $i < $docnum; $i++) {
                    $statInfo = $zip->statIndex($i,\ZipArchive::FL_ENC_RAW);
                    $filename = $this->transcoding($statInfo['name']);
                    if($statInfo['crc'] == 0) {
                        //新建目录
                        if(!is_dir($destination.'/'.substr($filename, 0,-1))) mkdir($destination.'/'.substr($filename, 0,-1),0775,true);
                    } else {
                        //拷贝文件
                        copy('zip://'.$archive.'#'.$zip->getNameIndex($i), $destination.'/'.$filename);
                    }
                }
                $zip->close();
                $this->status = array('success' => 'Files unzipped successfully');
            } else {
                $this->status = array('error' => 'Error: Directory not writeable by webserver.');
            }
        } else {
            $this->status = array('error' => 'Error: Cannot read .zip archive.');
        }
    }

    function transcoding($filename, $iswin=null){
        $encodes = ['UTF-8','GBK','BIG5','CP936'];
        $encoding = mb_detect_encoding($filename,$encodes);
        if($encoding == 'UTF-8') return $filename;
        if(is_null($iswin)) $iswin = DIRECTORY_SEPARATOR == '/';  //linux
        $encoding = mb_detect_encoding($filename,['UTF-8','GBK','BIG5','CP936']);
        if ($iswin){    //linux
            $filename = iconv($encoding,'UTF-8',$filename);
        }else{  //win
            $filename = iconv($encoding,'GBK',$filename);
        }
        return $filename;
    }

    /**
     * Decompress a .gz File.
     *
     * @param string $archive
     *   The archive name including file extension. E.g. my_archive.zip.
     * @param string $destination
     *   The relative destination path where to extract files.
     */
    public  function extractGzipFile($archive, $destination)
    {
        // Check if zlib is enabled
        if (!function_exists('gzopen')) {
            $this->status = array('error' => 'Error: Your PHP has no zlib support enabled.');
            return;
        }

        $filename = pathinfo($archive, PATHINFO_FILENAME);
        $gzipped = gzopen($archive, "rb");
        $file = fopen($destination . '/' . $filename, "w");

        while ($string = gzread($gzipped, 4096)) {
            fwrite($file, $string, strlen($string));
        }
        gzclose($gzipped);
        fclose($file);

        // Check if file was extracted.
        if (file_exists($destination . '/' . $filename)) {
            $this->status = array('success' => 'File unzipped successfully.');

            // If we had a tar.gz file, let's extract that tar file.
            if (pathinfo($destination . '/' . $filename, PATHINFO_EXTENSION) == 'tar') {
                $phar = new PharData($destination . '/' . $filename);
                if ($phar->extractTo($destination)) {
                    $this->status = array('success' => 'Extracted tar.gz archive successfully.');
                    // Delete .tar.
                    unlink($destination . '/' . $filename);
                }
            }
        } else {
            $this->status = array('error' => 'Error unzipping file.');
        }

    }

    /**
     * Decompress/extract a Rar archive using RarArchive.
     *
     * @param string $archive
     *   The archive name including file extension. E.g. my_archive.zip.
     * @param string $destination
     *   The relative destination path where to extract files.
     */
    public  function extractRarArchive($archive, $destination)
    {
        // Check if webserver supports unzipping.
        if (!class_exists('RarArchive')) {
            $this->status = array('error' => 'Error: Your PHP version does not support .rar archive functionality. <a class="info" href="http://php.net/manual/en/rar.installation.php" target="_blank">How to install RarArchive</a>');
            return;
        }
        // Check if archive is readable.
        if ($rar = RarArchive::open($archive)) {
            // Check if destination is writable
            if (is_writeable($destination . '/')) {
                $entries = $rar->getEntries();
                foreach ($entries as $entry) {
                    $entry->extract($destination);
                }
                $rar->close();
                $this->status = array('success' => 'Files extracted successfully.');
            } else {
                $this->status = array('error' => 'Error: Directory not writeable by webserver.');
            }
        } else {
            $this->status = array('error' => 'Error: Cannot read .rar archive.');
        }
    }

/**
 * Class Zipper
 *
 * Copied and slightly modified from http://at2.php.net/manual/en/class.ziparchive.php#110719
 * @author umbalaconmeogia
 */

    /**
     * Add files and sub-directories in a folder to zip file.
     *
     * @param string $folder
     *   Path to folder that should be zipped.
     *
     * @param ZipArchive $zipFile
     *   Zipfile where files end up.
     *
     * @param int $exclusiveLength
     *   Number of text to be exclusived from the file path.
     */
    private  function folderToZip($folder, &$zipFile, $exclusiveLength)
    {
        $handle = opendir($folder);

        while (false !== $f = readdir($handle)) {
            // Check for local/parent path or zipping file itself and skip.
            if ($f != '.' && $f != '..' && $f != basename(__FILE__)) {
                $filePath = "$folder/$f";
                // Remove prefix from file path before add to zip.
                $localPath = substr($filePath, $exclusiveLength);

                if (is_file($filePath)) {
                    $zipFile->addFile($filePath, $localPath);
                } elseif (is_dir($filePath)) {
                    // Add sub-directory.
                    $zipFile->addEmptyDir($localPath);
                    $this->folderToZip($filePath, $zipFile, $exclusiveLength);
                }
            }
        }
        closedir($handle);
    }

    /**
     * Zip a folder (including itself).
     *
     * Usage:
     *   Zipper::zipDir('path/to/sourceDir', 'path/to/out.zip');
     *
     * @param string $sourcePath
     *   Relative path of directory to be zipped.
     *
     * @param string $outZipPath
     *   Relative path of the resulting output zip file.
     */
    public  function zipDir($sourcePath, $outZipPath)
    {
        $pathInfo = pathinfo($sourcePath);
        $parentPath = $pathInfo['dirname'];
        $dirName = $pathInfo['basename'];

        $z = new \ZipArchive();
        $z->open($outZipPath, \ZipArchive::CREATE);
        $z->addEmptyDir($dirName);
        if ($sourcePath == $dirName) {
            $this->folderToZip($sourcePath, $z, 0);
        } else {
            $this->folderToZip($sourcePath, $z, strlen("$parentPath/"));
        }
        $z->close();

        $this->status = array('success' => 'Successfully created archive ' . $outZipPath);
    }
}
