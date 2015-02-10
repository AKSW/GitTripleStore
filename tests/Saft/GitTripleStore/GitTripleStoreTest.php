<?php
namespace Saft\GitTripleStore;

class GitTripleStoreTest extends \PHPUnit_Framework_TestCase
{
    public function testSomething()
    {
        $store = new GitTripleStore();
        $this->assertTrue($store != null, "Nonsense test failed");
    }
}
