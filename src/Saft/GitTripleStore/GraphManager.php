<?php
namespace Saft\GitTripleStore;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use EasyRdf\Graph;
use Desarrolla2\Cache\Cache;
use Desarrolla2\Cache\Adapter\Memory;
use Filicious\Filesystem;
use Filicious\File;
use Filicious\Local\LocalAdapter;
use Doctrine\Instantiator\Exception\InvalidArgumentException;

final class GraphManager
{
    private $log;
    private $baseDir;
    private $fileSystem;
    private $graphUriFileMapping;
    private $cache;

    /**
     *
     * @param string $baseDir
     *            Path to the base directory where graph files gets
     *            relative loaded
     */
    public function __construct($baseDir)
    {
        $this->log = new Logger('GraphManager');
        $this->log->pushHandler(new StreamHandler('php://output'));
        
        $this->baseDir = $baseDir;
        $this->fileSystem = new Filesystem(new LocalAdapter($baseDir));
        $this->log->info('Using base dir: ' . $baseDir);
        
        $this->graphUriFileMapping = array();
        
        $adapter = new Memory();
        $adapter->setOption('limit', 100);
        $this->cache = new Cache($adapter);
    }

    /**
     * Removes all graphs
     */
    public function clearGraphs()
    {
        $this->graphUriFileMapping = array();
        $this->cache->dropCache();
    }

    /**
     * Add a graph with the given uri.
     * $relativePath get resolved against the base directory and
     * should point to a N-Triples file. If there was allready a
     * graph added with the same URI it will be overriden.
     * 
     * @param string $graphUri URI of the path
     * @param string $relativePath Relative path to N-Triples file 
     */
    public function addGraph($graphUri, $relativePath)
    {
        self::checkUri($graphUri);
        $this->graphUriFileMapping[$graphUri] =
            $this->fileSystem->getFile($relativePath);
    }

    /**
     * Removes the graph.
     * @param string $graphUri
     */
    public function removeGraph($graphUri)
    {
        self::checkUri($graphUri);
        unset($this->graphUriFileMapping[$graphUri]);
        $this->cache->delete($graphUri);
    }

    /**
     * Checks if the graph exists.
     * @param unknown $graphUri
     * @return boolean true if the graph exists, false elsewhere
     */
    public function containsGraph($graphUri)
    {
        self::checkUri($graphUri);
        return array_key_exists($graphUri, $this->graphUriFileMapping);
    }

    /**
     * Returns an array of all available graph uris.
     * @return string[] multitype:
     */
    public function getAvailableGraphs()
    {
        return array_keys($this->graphUriFileMapping);
    }

    /**
     * Returns the EasyRDF graph fpr the given graph uri.
     * If the graph uri was not registered an exception will thrown.
     *
     * @param string $graphUri
     *            URI of the graph
     * @throws Exception when graph does not exist
     * @return Graph Graph for the given uri
     */
    public function getGraph($graphUri)
    {
        self::checkUri($graphUri);
        if (!$this->containsGraph($graphUri)) {
            throw new Exception('Graph does not exists: ' . $graphUri);
        }
        
        $allreadyLoaded = $this->cache->has($graphUri);
        if ($allreadyLoaded) {
            return $this->cache->get($graphUri);
        } else {
            $graph = $this->loadGraph($graphUri);
            $this->cache->set($graphUri, $graph);
            return $graph;
        }
    }

    private function loadGraph($graphUri)
    {
        $file = $this->graphUriFileMapping[$graphUri];
        self::checkFileReadability($file);
        $absolutePath = $this->getAbsolutePath($file);
        $graph = new Graph($graphUri);
        try {
            $numTriplesRead = $graph->parseFile($absolutePath, 'ntriples');
            $message = sprintf('Graph successfully loaded: %s (%d triples)', $absolutePath, $numTriplesRead);
            $this->log->info($message);
        } catch (Exception $e) {
            $this->log->error('Unable to load graph: ' . $absolutePath, array(
                'exception' => $e
            ));
            throw $e;
        }
        return $graph;
    }

    private static function checkFileReadability(File $file)
    {
        if (!$file->isFile()) {
            throw new Exception('Path is not a file: ' . $file->getPathname());
        } else 
            if (!$file->isReadable()) {
                throw new Exception('File can\'t read: ' . $file->getPathname());
            }
    }

    private function getAbsolutePath(File $file)
    {
        return Util::getAbsolutePath($this->baseDir, $file->getPathname());
    }

    private static function checkUri($uri)
    {
        if (!Util::isValidUri($uri)) {
            throw new InvalidArgumentException('URI is not valid: ' . $uri);
        }
    }
    
    public function saveMapping(File $jsonFile) {
        if (is_null($jsonFile)) {
            throw new InvalidArgumentException('$jsonFile is null');
        } 
        $json = json_encode($this->graphUriFileMapping, JSON_PRETTY_PRINT | JSON_FORCE_OBJECT);
        $jsonFile->setContents($json);
    }
    
    public function loadMapping(File $jsonFile) {
        if (is_null($jsonFile)) {
            throw new InvalidArgumentException('$jsonFile is null');
        }
        $json = $jsonFile->getContents();
        $this->graphUriFileMapping = json_decode($json, true);
    }
} 