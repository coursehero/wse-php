<?php

use Wse\C14NGeneral;
use Wse\WSASoap;
use Wse\WSSESoap;
use Wse\WSSESoapServer;
use Wse\XMLSecEnc;
use Wse\XMLSecurityDSig;
use Wse\XMLSecurityKey;

class AutoloadTest extends \PHPUnit_Framework_TestCase
{
    public function testPsr0()
    {
        $this->assertNotNull(new Wse\C14NGeneral());
        $this->assertNotNull(new Wse\XMLSecEnc());
        $this->assertNotNull(new Wse\XMLSecurityDSig());
        $this->assertNotNull(new Wse\XMLSecurityKey(Wse\XMLSecurityKey::TRIPLEDES_CBC));
    }
}
