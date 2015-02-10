<?php
namespace Saft\GitTripleStore;

use Saft\StoreInterface\AbstractPatternFragmentTripleStore;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use \Exception;

final class GitTripleStore extends AbstractPatternFragmentTripleStore
{
    private $log;

    public function __construct()
    {
        $this->log = new Logger('GitTripleStore');
        $this->log->pushHandler(new StreamHandler('php://output'));
    }

    public function getStoreInformation()
    {
        throw new Exception('Method not implemented');
    }

    public function getAvailableGraphs()
    {
        throw new Exception('Method not implemented');
    }

    public function getDefaultGraph()
    {
        throw new Exception('Method not implemented');
    }

    public function addMultipleStatements(array $Statements, $graphUri = null, array $options = array())
    {
        throw new Exception('Method not implemented');
    }

    public function deleteMultipleStatements(array $Statements, $graphUri = null)
    {
        throw new Exception('Method not implemented');
    }

    public function getMatchingStatements(array $Statements, $graphUri = null, array $options = array())
    {
        throw new Exception('Method not implemented');
    }

    public function hasMatchingStatement($Statement, $graphUri = null)
    {
        throw new Exception('Method not implemented');
    }
}
