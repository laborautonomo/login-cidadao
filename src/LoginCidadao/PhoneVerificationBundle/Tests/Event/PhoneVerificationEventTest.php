<?php
/**
 * This file is part of the login-cidadao project or it's bundles.
 *
 * (c) Guilherme Donato <guilhermednt on github>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LoginCidadao\PhoneVerificationBundle\Tests\Event;

use LoginCidadao\PhoneVerificationBundle\Event\PhoneVerificationEvent;
use PHPUnit\Framework\TestCase;

class PhoneVerificationEventTest extends TestCase
{
    public function testPhoneVerificationEventTest()
    {
        $phoneVerification = $this->createMock('LoginCidadao\PhoneVerificationBundle\Model\PhoneVerificationInterface');
        $providedCode = '123456';

        $event = new PhoneVerificationEvent($phoneVerification, $providedCode);

        $this->assertEquals($phoneVerification, $event->getPhoneVerification());
        $this->assertEquals($providedCode, $event->getProvidedCode());
    }
}
