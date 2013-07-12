<?php
use OpenCloud\Rackspace;

require_once MODX_CORE_PATH . 'model/modx/sources/modmediasource.class.php';
/**
 * Implements an OpenStack Object Storage (Opencloud) media source, allowing basic manipulation, uploading and URL-retrieval of resources
 * in a specified Opencloud container.
 * 
 * @package modx
 * @subpackage sources
 */
class OpencloudMediaSource extends modMediaSource implements modMediaSourceInterface {
    /** @var $cloud */
    public $cloud;
    /** @var $container */
    public $container;
    /** @var $objectStore */
    public $objectStore;

    /**
     * Override the constructor to always force Opencloud sources to not be streams.
     *
     * {@inheritDoc}
     *
     * @param xPDO $xpdo
     */
    public function __construct(xPDO & $xpdo) {
        parent::__construct($xpdo);
        $this->set('is_stream',false);
    }

public function logger($str) {
    $fp = fopen('/tmp/logger.txt', 'a');
    fwrite($fp, $str."\n");
    fclose($fp);
}

    /**
     * Initializes Opencloud media class, connect and get container
     * @return boolean
     */
    public function initialize() {
        parent::initialize();
        $properties = $this->getPropertyList();

        // setup connect

        $username = $this->xpdo->getOption('username',$properties,'');
        $apiKey = $this->xpdo->getOption('api_key',$properties,'');
        $authentication_service = $this->xpdo->getOption('authentication_service',$properties,'');
        $container = $this->xpdo->getOption('container',$properties,'');
$this->logger("username: $username");
$this->logger("api_key: $apiKey");
$this->logger("authentication_service: $authentication_service");
$this->logger("container: $container");


        include_once dirname(dirname(__FILE__)).'/php-opencloud-1.5.8/lib/php-opencloud.php';
        $endpoint = 'https://lon.identity.api.rackspacecloud.com/v2.0/';
        $credentials = array(
            'username' => $username,
            'apiKey' => $apiKey,
            'tenantId' => 'MossoCloudFS_74e58090-dcaa-4458-a94f-cb5ff1cd1773'
        );
        try {
            $this->cloud = new Rackspace($endpoint, $credentials);
            $this->objectStore = $this->cloud->ObjectStore('cloudFiles', 'LON');
            $this->setContainer($container);

        } catch (Exception $e) {
            $this->xpdo->log(modX::LOG_LEVEL_ERROR,'[OpencloudMediaSource] Could not authenticate: '.$e->getMessage());
        }
        // and container
        return true;
    }

    /**
     * Get the name of this source type
     * @return string
     */
    public function getTypeName() {
        $this->xpdo->lexicon->load('opencloud:default');
        return $this->xpdo->lexicon('source_type.opencloud');
    }
    /**
     * Get the description of this source type
     * @return string
     */
    public function getTypeDescription() {
        $this->xpdo->lexicon->load('opencloud:default');
        return $this->xpdo->lexicon('source_type.opencloud_desc');
    }


    /**
     * Set the containder for the connection to Opencloud
     * @param string $container
     * @return void
     */
    public function setContainer($container) {

        $this->container = $this->objectStore->Container($container);
    }

    /**
     * Get a list of objects from within a bucket
     * @param string $dir
     * @return array
     */
    public function getOpencloudObjectList($dir) {
        $path = !empty($dir)?ltrim($dir,'/'):'';

        $objlist = $this->container->ObjectList(array("delimiter" => "/", "prefix" => $path));
        return $objects;
    }


    /**
     * Tells if a file is a binary file or not
     * @param CF_Object $obj
     * @return boolean
     */
    public function isBinary($obj) {
        return (isset($obj->content_type) && stripos($obj->content_type, 'text') !== 0);

    }

    /**
     * @param string $path
     * @return array
     */
    public function getContainerList($path) {
        $path = trim($path,"/");
        $this->logger( "start paths: $path");
        $properties = $this->getPropertyList();

         // get list of all files
        $objlist = $this->container->ObjectList(array("delimiter" => "/", "prefix" => ($path ? "$path/" : '')));

        $directories = $files = array();

        while($object = $objlist->Next()) {
            // $this->logger( print_r($object, true) );

            $isDir = isset($object->subdir);

            $currentPath = (!$isDir ? $object->name : $object->subdir);
            $fileName = basename($currentPath);
            $extension = pathinfo($fileName,PATHINFO_EXTENSION);

            if ($isDir) {

                $directories[$currentPath] = array(
                    'id' => $currentPath.'/',
                    'text' => $fileName,
                    'cls' => 'icon-'.$extension,
                    'type' => 'dir',
                    'leaf' => false,
                    'path' => $currentPath,
                    'pathRelative' => $currentPath,
                    'perms' => '',
                );
                $directories[$currentPath]['menu'] = array('items' => $this->getListContextMenu($currentPath,$isDir,$directories[$currentPath], false));
            } else {

                $files[$currentPath] = array(
                    'id' => $currentPath,
                    'text' => $fileName,
                    'cls' => 'icon-'.$extension,
                    'type' => 'file',
                    'leaf' => true,
                    'path' => $currentPath,
                    'pathRelative' => $currentPath,
                    'directory' => $currentPath,
                    'url' => rtrim($properties['url'],'/').'/'.$currentPath,
                    'file' => $currentPath,
                );
                $isBinary = $this->isBinary($object);
                $files[$currentPath]['menu'] = array('items' => $this->getListContextMenu($currentPath, $isDir,$files[$currentPath], $isBinary));
            }
        }

        $ls = array();
        /* now sort files/directories */
        ksort($directories);
        foreach ($directories as $dir) {
            $ls[] = $dir;
        }
        ksort($files);
        foreach ($files as $file) {
            $ls[] = $file;
        }

        return $ls;
    }

    /**
     * Get the context menu for when viewing the source as a tree
     * 
     * @param string $file
     * @param boolean $isDir
     * @param array $fileArray
     * @return array
     */
    public function getListContextMenu($file,$isDir,array $fileArray, $isBinary) {
        $menu = array();
        if (!$isDir) { /* files */
            if ($this->hasPermission('file_update')) {
                if (!$isBinary) {
                    $menu[] = array(
                        'text' => $this->xpdo->lexicon('file_edit'),
                        'handler' => 'this.editFile',
                    );
                    $menu[] = array(
                        'text' => $this->xpdo->lexicon('quick_update_file'),
                        'handler' => 'this.quickUpdateFile',
                    );
                }
                $menu[] = array(
                    'text' => $this->xpdo->lexicon('rename'),
                    'handler' => 'this.renameFile',
                );
            }
            if ($this->hasPermission('file_view')) {
                $menu[] = array(
                    'text' => $this->xpdo->lexicon('file_download'),
                    'handler' => 'this.downloadFile',
                );
            }
            if ($this->hasPermission('file_remove')) {
                if (!empty($menu)) $menu[] = '-';
                $menu[] = array(
                    'text' => $this->xpdo->lexicon('file_remove'),
                    'handler' => 'this.removeFile',
                );
            }
        } else { /* directories */
            if ($this->hasPermission('directory_create')) {
                $menu[] = array(
                    'text' => $this->xpdo->lexicon('file_folder_create_here'),
                    'handler' => 'this.createDirectory',
                );
            }
            /*if ($this->hasPermission('directory_update')) {
                $menu[] = array(
                    'text' => $this->xpdo->lexicon('rename'),
                    'handler' => 'this.renameDirectory',
                );
            }*/
            $menu[] = array(
                'text' => $this->xpdo->lexicon('directory_refresh'),
                'handler' => 'this.refreshActiveNode',
            );
            if ($this->hasPermission('file_upload')) {
                $menu[] = '-';
                $menu[] = array(
                    'text' => $this->xpdo->lexicon('upload_files'),
                    'handler' => 'this.uploadFiles',
                );
            }
            if ($this->hasPermission('file_create')) {
                $menu[] = array(
                    'text' => $this->xpdo->lexicon('file_create'),
                    'handler' => 'this.createFile',
                );
                $menu[] = array(
                    'text' => $this->xpdo->lexicon('quick_create_file'),
                    'handler' => 'this.quickCreateFile',
                );
            }
            if ($this->hasPermission('directory_remove')) {
                $menu[] = '-';
                $menu[] = array(
                    'text' => $this->xpdo->lexicon('file_folder_remove'),
                    'handler' => 'this.removeDirectory',
                );
            }
        }
        return $menu;
    }

    /**
     * Get all files in the directory and prepare thumbnail views
     * 
     * @param string $path
     * @return array
     */
    public function getObjectsInContainer($path) {
        $this->logger('getPath', $path);
        $list = $this->getOpencloudObjectList($path);
        $properties = $this->getPropertyList();

        return false;
    }

    /**
     * Create a Container
     *
     * @param string $name
     * @param string $parentContainer
     * @return boolean
     */
    public function createContainer($name,$parentContainer) {
        $parentContainer = trim($parentContainer,'/') . '/';
        if ($parentContainer == '/' || $parentContainer == '.') $parentContainer = '';

        $newPath = $parentContainer.trim($name,'/');

        $folder = $this->container->DataObject();
        $folder->name = $newPath;
        $folder->content_type = 'application/directory';

        $ok = $folder->Create();

        $this->xpdo->logManagerAction('directory_create','',$newPath);
        return true;
    }

    /**
     * Remove an empty folder
     *
     * @param $path
     * @return boolean
     */
    public function removeContainer($path) {
        try {
            $container = $this->container->DataObject($path);
            $container->Delete();
        }
        catch (NoSuchObjectException $e) {
            $this->addError('file',$this->xpdo->lexicon('file_folder_err_ns').': '.$path);
            return false;
        }

        /* log manager action */
        $this->xpdo->logManagerAction('directory_remove','',$path);
        return true;
    }

    /**
     * Rename a container
     *
     * @param string $oldPath
     * @param string $newName
     * @return boolean
     */
    public function renameContainer($oldPath,$newName) {
        return false; //TODO: Need manual move all files in container??
    }

    /**
     * Delete a file
     * 
     * @param string $objectPath
     * @return boolean
     */
    public function removeObject($objectPath) {
        $obj = false;
        try {
            $obj = $this->container->DataObject($objectPath);
        }
        catch (NoSuchObjectException $e) {
            $this->addError('file',$this->xpdo->lexicon('file_folder_err_ns').': '.$objectPath);
            return false;
        }

       /* remove object */
        $deleted = $obj->Delete();

        /* log manager action */
        $this->xpdo->logManagerAction('file_remove','',$objectPath);
        return $deleted;
    }

    /**
     * Rename/move a file
     * 
     * @param string $oldPath
     * @param string $newName
     * @return bool
     */
    public function renameObject($oldPath,$newName) {
        try {
            $obj = $this->container->DataObject($oldPath);
        }
        catch (NoSuchObjectException $e) {
            $this->addError('file',$this->xpdo->lexicon('file_folder_err_ns').': '.$objectPath);
            return false;
        }

        $dir = dirname($oldPath);
        $newPath = ($dir != '.' ? $dir.'/' : '').$newName;

        $mypicture = $this->container->DataObject();
        $mypicture->name = $newPath;
        $mypicture->Create();

        $moved = $obj->copy($mypicture);

        if (!$moved) {
            $this->addError('file',$this->xpdo->lexicon('file_folder_err_rename').': '.$oldPath);
            return false;
        }

        $obj->Delete();

        $this->xpdo->logManagerAction('file_rename','',$oldPath);
        return $moved;

    }

    /**
     * Move a file or folder to a specific location
     *
     * @param string $from The location to move from
     * @param string $to The location to move to
     * @param string $point
     * @return boolean
     */
    public function moveObject($from,$to,$point = 'append') {
        $this->xpdo->lexicon->load('source');
        $success = false;

        try {
            $obj_from = $this->container->DataObject($from);
        }
        catch (Exception $e) {
            $this->addError('file',$this->xpdo->lexicon('file_err_ns').': '.$from);
            return false;
        }

        if ($to != '/') {
            if(substr($to,-1,1) !== "/") {
                try {
                    $obj_to = $this->container->DataObject($to);
                }
                catch (NoSuchObjectException $e) {
                    $this->addError('file',$this->xpdo->lexicon('file_err_ns').': '.$to);
                    return false;
                }

                $toPath = dirname($obj_to->name).'/';
                if($toPath == "./") $toPath = "";
            } else {
                $toPath = trim($to,"/")."/";
            }
        } else {
            $toPath = basename($from);
        }
        $toPath .= basename($obj_from->name);


        try {
            $mypicture = $this->container->DataObject();
            $mypicture->name = $toPath;
            $mypicture->Create();

            $moved = $obj_from->copy($mypicture);
        } catch(Exception $e) {
            $this->addError('file',$this->xpdo->lexicon('file_folder_err_rename').': '.$oldPath);
            return false;
        }

        if (!$moved) {
            $this->addError('file',$this->xpdo->lexicon('file_folder_err_rename').': '.$oldPath);
            return false;
        }

        $obj_from->Delete();

        return $moved;
    }

    /**
     * Update the contents of a specific object
     *
     * @param string $objectPath
     * @param string $content
     * @return boolean|string
     */
    public function updateObject($objectPath,$content) {

        try {
            $obj = new CF_Object($this->container,ltrim($objectPath,'/'),true);
    
        }
        catch (NoSuchObjectException $e) {
            $this->addError('file',$this->xpdo->lexicon('file_err_ns').': '.$objectPath);
            return false;
        }

        /* update file */
        $obj->write($content);

        $this->xpdo->logManagerAction('file_create','',$filePath);

        return rawurlencode($objectPath);

    }

    /**
     * Create an object from a path
     *
     * @param string $objectPath
     * @param string $name
     * @param string $content
     * @return boolean|string
     */
    public function createObject($objectPath,$name,$content) {
        $objectPath = trim($objectPath,'/') . '/';
        if ($objectPath == '/' || $objectPath == '.') $objectPath = '';

        $filePath = $objectPath.trim($name,'/');

        $mypicture = $this->container->DataObject();

        $ok = $mypicture->Create(
            array('name'=>$name, 'content_type'=> $this->getContentType($ext)), $filePath);

        if (!$ok) {
            $this->addError('name',$this->xpdo->lexicon('file_err_nf').': '.$filePath);
            return false;
        }


        $this->xpdo->logManagerAction('file_create','',$filePath);

        return rawurlencode($filePath);
    }


    /**
     * Upload files to Opencloud
     * 
     * @param string $container
     * @param array $objects
     * @return bool
     */
    public function uploadObjectsToContainer($container,array $objects = array()) {
        $container = trim($container,'/') . '/';
        if ($container == '/' || $container == '.') $container = '';

        $allowedFileTypes = explode(',',$this->xpdo->getOption('upload_files',null,''));
        $allowedFileTypes = array_merge(explode(',',$this->xpdo->getOption('upload_images')),explode(',',$this->xpdo->getOption('upload_media')),explode(',',$this->xpdo->getOption('upload_flash')),$allowedFileTypes);
        $allowedFileTypes = array_unique($allowedFileTypes);
        $maxFileSize = $this->xpdo->getOption('upload_maxsize',null,1048576);

        /* loop through each file and upload */
        foreach ($objects as $file) {
            if ($file['error'] != 0) continue;
            if (empty($file['name'])) continue;
            $ext = @pathinfo($file['name'],PATHINFO_EXTENSION);
            $ext = strtolower($ext);

            if (empty($ext) || !in_array($ext,$allowedFileTypes)) {
                $this->addError('path',$this->xpdo->lexicon('file_err_ext_not_allowed',array(
                    'ext' => $ext,
                )));
                continue;
            }
            $size = @filesize($file['tmp_name']);

            if ($size > $maxFileSize) {
                $this->addError('path',$this->xpdo->lexicon('file_err_too_large',array(
                    'size' => $size,
                    'allowed' => $maxFileSize,
                )));
                continue;
            }

            $newPath = $container.$file['name'];


            $mypicture = $this->container->DataObject();
            $uploaded = $mypicture->Create(array('name'=>$newPath, 'content_type'=> $this->getContentType($ext)), $file['tmp_name']);

            if (!$uploaded) {
                $this->addError('path',$this->xpdo->lexicon('file_err_upload'));
            }
        }

        /* invoke event */
        $this->xpdo->invokeEvent('OnFileManagerUpload',array(
            'files' => &$objects,
            'directory' => $container,
            'source' => &$this,
        ));

        $this->xpdo->logManagerAction('file_upload','',$container);

        return true;
    }

    /**
     * Get the content type of the file based on extension
     * @param string $ext
     * @return string
     */
    protected function getContentType($ext) {
        $contentType = 'application/octet-stream';
        $mimeTypes = array(
            '323' => 'text/h323',
            'acx' => 'application/internet-property-stream',
            'ai' => 'application/postscript',
            'aif' => 'audio/x-aiff',
            'aifc' => 'audio/x-aiff',
            'aiff' => 'audio/x-aiff',
            'asf' => 'video/x-ms-asf',
            'asr' => 'video/x-ms-asf',
            'asx' => 'video/x-ms-asf',
            'au' => 'audio/basic',
            'avi' => 'video/x-msvideo',
            'axs' => 'application/olescript',
            'bas' => 'text/plain',
            'bcpio' => 'application/x-bcpio',
            'bin' => 'application/octet-stream',
            'bmp' => 'image/bmp',
            'c' => 'text/plain',
            'cat' => 'application/vnd.ms-pkiseccat',
            'cdf' => 'application/x-cdf',
            'cer' => 'application/x-x509-ca-cert',
            'class' => 'application/octet-stream',
            'clp' => 'application/x-msclip',
            'cmx' => 'image/x-cmx',
            'cod' => 'image/cis-cod',
            'cpio' => 'application/x-cpio',
            'crd' => 'application/x-mscardfile',
            'crl' => 'application/pkix-crl',
            'crt' => 'application/x-x509-ca-cert',
            'csh' => 'application/x-csh',
            'css' => 'text/css',
            'dcr' => 'application/x-director',
            'der' => 'application/x-x509-ca-cert',
            'dir' => 'application/x-director',
            'dll' => 'application/x-msdownload',
            'dms' => 'application/octet-stream',
            'doc' => 'application/msword',
            'dot' => 'application/msword',
            'dvi' => 'application/x-dvi',
            'dxr' => 'application/x-director',
            'eps' => 'application/postscript',
            'etx' => 'text/x-setext',
            'evy' => 'application/envoy',
            'exe' => 'application/octet-stream',
            'fif' => 'application/fractals',
            'flr' => 'x-world/x-vrml',
            'gif' => 'image/gif',
            'gtar' => 'application/x-gtar',
            'gz' => 'application/x-gzip',
            'h' => 'text/plain',
            'hdf' => 'application/x-hdf',
            'hlp' => 'application/winhlp',
            'hqx' => 'application/mac-binhex40',
            'hta' => 'application/hta',
            'htc' => 'text/x-component',
            'htm' => 'text/html',
            'html' => 'text/html',
            'htt' => 'text/webviewhtml',
            'ico' => 'image/x-icon',
            'ief' => 'image/ief',
            'iii' => 'application/x-iphone',
            'ins' => 'application/x-internet-signup',
            'isp' => 'application/x-internet-signup',
            'jfif' => 'image/pipeg',
            'jpe' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'js' => 'application/x-javascript',
            'latex' => 'application/x-latex',
            'lha' => 'application/octet-stream',
            'lsf' => 'video/x-la-asf',
            'lsx' => 'video/x-la-asf',
            'lzh' => 'application/octet-stream',
            'm13' => 'application/x-msmediaview',
            'm14' => 'application/x-msmediaview',
            'm3u' => 'audio/x-mpegurl',
            'man' => 'application/x-troff-man',
            'mdb' => 'application/x-msaccess',
            'me' => 'application/x-troff-me',
            'mht' => 'message/rfc822',
            'mhtml' => 'message/rfc822',
            'mid' => 'audio/mid',
            'mny' => 'application/x-msmoney',
            'mov' => 'video/quicktime',
            'movie' => 'video/x-sgi-movie',
            'mp2' => 'video/mpeg',
            'mp3' => 'audio/mpeg',
            'mpa' => 'video/mpeg',
            'mpe' => 'video/mpeg',
            'mpeg' => 'video/mpeg',
            'mpg' => 'video/mpeg',
            'mpp' => 'application/vnd.ms-project',
            'mpv2' => 'video/mpeg',
            'ms' => 'application/x-troff-ms',
            'mvb' => 'application/x-msmediaview',
            'nws' => 'message/rfc822',
            'oda' => 'application/oda',
            'p10' => 'application/pkcs10',
            'p12' => 'application/x-pkcs12',
            'p7b' => 'application/x-pkcs7-certificates',
            'p7c' => 'application/x-pkcs7-mime',
            'p7m' => 'application/x-pkcs7-mime',
            'p7r' => 'application/x-pkcs7-certreqresp',
            'p7s' => 'application/x-pkcs7-signature',
            'pbm' => 'image/x-portable-bitmap',
            'pdf' => 'application/pdf',
            'pfx' => 'application/x-pkcs12',
            'pgm' => 'image/x-portable-graymap',
            'pko' => 'application/ynd.ms-pkipko',
            'pma' => 'application/x-perfmon',
            'pmc' => 'application/x-perfmon',
            'pml' => 'application/x-perfmon',
            'pmr' => 'application/x-perfmon',
            'pmw' => 'application/x-perfmon',
            'pnm' => 'image/x-portable-anymap',
            'pot' => 'application/vnd.ms-powerpoint',
            'ppm' => 'image/x-portable-pixmap',
            'pps' => 'application/vnd.ms-powerpoint',
            'ppt' => 'application/vnd.ms-powerpoint',
            'prf' => 'application/pics-rules',
            'ps' => 'application/postscript',
            'pub' => 'application/x-mspublisher',
            'qt' => 'video/quicktime',
            'ra' => 'audio/x-pn-realaudio',
            'ram' => 'audio/x-pn-realaudio',
            'ras' => 'image/x-cmu-raster',
            'rgb' => 'image/x-rgb',
            'rmi' => 'audio/mid',
            'roff' => 'application/x-troff',
            'rtf' => 'application/rtf',
            'rtx' => 'text/richtext',
            'scd' => 'application/x-msschedule',
            'sct' => 'text/scriptlet',
            'setpay' => 'application/set-payment-initiation',
            'setreg' => 'application/set-registration-initiation',
            'sh' => 'application/x-sh',
            'shar' => 'application/x-shar',
            'sit' => 'application/x-stuffit',
            'snd' => 'audio/basic',
            'spc' => 'application/x-pkcs7-certificates',
            'spl' => 'application/futuresplash',
            'src' => 'application/x-wais-source',
            'sst' => 'application/vnd.ms-pkicertstore',
            'stl' => 'application/vnd.ms-pkistl',
            'stm' => 'text/html',
            'svg' => 'image/svg+xml',
            'sv4cpio' => 'application/x-sv4cpio',
            'sv4crc' => 'application/x-sv4crc',
            't' => 'application/x-troff',
            'tar' => 'application/x-tar',
            'tcl' => 'application/x-tcl',
            'tex' => 'application/x-tex',
            'texi' => 'application/x-texinfo',
            'texinfo' => 'application/x-texinfo',
            'tgz' => 'application/x-compressed',
            'tif' => 'image/tiff',
            'tiff' => 'image/tiff',
            'tr' => 'application/x-troff',
            'trm' => 'application/x-msterminal',
            'tsv' => 'text/tab-separated-values',
            'txt' => 'text/plain',
            'uls' => 'text/iuls',
            'ustar' => 'application/x-ustar',
            'vcf' => 'text/x-vcard',
            'vrml' => 'x-world/x-vrml',
            'wav' => 'audio/x-wav',
            'wcm' => 'application/vnd.ms-works',
            'wdb' => 'application/vnd.ms-works',
            'wks' => 'application/vnd.ms-works',
            'wmf' => 'application/x-msmetafile',
            'wps' => 'application/vnd.ms-works',
            'wri' => 'application/x-mswrite',
            'wrl' => 'x-world/x-vrml',
            'wrz' => 'x-world/x-vrml',
            'xaf' => 'x-world/x-vrml',
            'xbm' => 'image/x-xbitmap',
            'xla' => 'application/vnd.ms-excel',
            'xlc' => 'application/vnd.ms-excel',
            'xlm' => 'application/vnd.ms-excel',
            'xls' => 'application/vnd.ms-excel',
            'xlt' => 'application/vnd.ms-excel',
            'xlw' => 'application/vnd.ms-excel',
            'xof' => 'x-world/x-vrml',
            'xpm' => 'image/x-xpixmap',
            'xwd' => 'image/x-xwindowdump',
            'z' => 'application/x-compress',
            'zip' => 'application/zip'
        );
        if (isset($mimeTypes[$ext])) {
            $contentType = $mimeTypes[$ext];
        } else {
            $contentType = 'octet/application-stream';
        }
        return $contentType;
    }

    /**
     * @return array
     */
    public function getDefaultProperties() {
        return array(
            'url' => array(
                'name' => 'url',
                'desc' => 'prop_opencloud.url_desc',
                'type' => 'textfield',
                'options' => '',
                'value' => '',
                'lexicon' => 'core:source',
            ),
            'container' => array(
                'name' => 'container',
                'desc' => 'prop_opencloud.container_desc',
                'type' => 'textfield',
                'options' => '',
                'value' => '',
                'lexicon' => 'core:source',
            ),
            'authentication_service' => array(
                'name' => 'authentication_service',
                'desc' => 'prop_opencloud.authentication_service_desc',
                'type' => 'textfield',
                'options' => '',
                'value' => '',
                'lexicon' => 'core:source',
            ),
            'username' => array(
                'name' => 'username',
                'desc' => 'prop_opencloud.username_desc',
                'type' => 'password',
                'options' => '',
                'value' => '',
                'lexicon' => 'core:source',
            ),
            'api_key' => array(
                'name' => 'api_key',
                'desc' => 'prop_opencloud.api_key_desc',
                'type' => 'password',
                'options' => '',
                'value' => '',
                'lexicon' => 'core:source',
            ),
            'imageExtensions' => array(
                'name' => 'imageExtensions',
                'desc' => 'prop_opencloud.imageExtensions_desc',
                'type' => 'textfield',
                'value' => 'jpg,jpeg,png,gif',
                'lexicon' => 'core:source',
            ),
            'thumbnailType' => array(
                'name' => 'thumbnailType',
                'desc' => 'prop_opencloud.thumbnailType_desc',
                'type' => 'list',
                'options' => array(
                    array('name' => 'PNG','value' => 'png'),
                    array('name' => 'JPG','value' => 'jpg'),
                    array('name' => 'GIF','value' => 'gif'),
                ),
                'value' => 'png',
                'lexicon' => 'core:source',
            ),
            'thumbnailQuality' => array(
                'name' => 'thumbnailQuality',
                'desc' => 'prop_opencloud.thumbnailQuality_desc',
                'type' => 'textfield',
                'options' => '',
                'value' => 90,
                'lexicon' => 'core:source',
            ),
            'skipFiles' => array(
                'name' => 'skipFiles',
                'desc' => 'prop_opencloud.skipFiles_desc',
                'type' => 'textfield',
                'options' => '',
                'value' => '.svn,.git,_notes,nbproject,.idea,.DS_Store',
                'lexicon' => 'core:source',
            ),
        );
    }

    /**
     * Prepare a src parameter to be rendered with phpThumb
     * 
     * @param string $src
     * @return string
     */
    public function prepareSrcForThumb($src) {
        $properties = $this->getPropertyList();
        if (strpos($src,$properties['url']) === false) {
            $src = $properties['url'].ltrim($src,'/');
        }
        return $src;
    }

    /**
     * Get the base URL for this source. Only applicable to sources that are streams.
     *
     * @param string $object An optional object to find the base url of
     * @return string
     */
    public function getBaseUrl($object = '') {
        $properties = $this->getPropertyList();
        return $properties['url'];
    }

    /**
     * Get the absolute URL for a specified object. Only applicable to sources that are streams.
     *
     * @param string $object
     * @return string
     */
    public function getObjectUrl($object = '') {
        $properties = $this->getPropertyList();
        return $properties['url'].$object;
    }


    /**
     * Get the contents of a specified file
     *
     * @param string $objectPath
     * @return array
     */
    public function getObjectContents($objectPath) {
        $properties = $this->getPropertyList();
        try {
            $obj = new CF_Object($this->container,$objectPath, true);
            $contents = $obj->read();
            $last_modified = $obj->last_modified;
            $size = $obj->content_length;
        }
        catch (Exception $e) {
            $contents = '';
            $last_modified = '';
            $size = '';
        }
        
        $imageExtensions = $this->getOption('imageExtensions',$this->properties,'jpg,jpeg,png,gif');
        $imageExtensions = explode(',',$imageExtensions);
        $fileExtension = pathinfo($objectPath,PATHINFO_EXTENSION);
        
        return array(
            'name' => $objectPath,
            'basename' => basename($objectPath),
            'path' => $objectPath,
            'size' => $size,
            'last_accessed' => '',
            'last_modified' => $last_modified,
            'content' => $contents,
            'image' => in_array($fileExtension,$imageExtensions) ? true : false,
            'is_writable' => true,
            'is_readable' => true,
        );
    }
}